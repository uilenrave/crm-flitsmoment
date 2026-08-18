<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DesignMask;
use App\Models\DesignSession;
use App\Scopes\AccountScope;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use ImagickPixel;
use RuntimeException;

/**
 * Bakt een DesignSession (achtergrond + masker + rand + logo) tot één plat PNG.
 * Werkt zonder auth()-context (nodig voor het klantportaal), scoped expliciet
 * op session->account_id i.p.v. via de ambient AccountScope.
 */
class DesignRenderService
{
    /**
     * @param  bool  $includePreviewPhotos  Plak de aan het masker gekoppelde voorbeeldfoto's achter
     *   de transparante foto-vensters, ongeacht de "Preview modus"-stand in de editor. Gebruikt voor
     *   de presentatie naar de klant (bookingSendToCustomer), NIET voor het productiebestand
     *   (bookingSetProduction) — daar moeten de vensters écht transparant blijven.
     */
    public function render(DesignSession $session, bool $includePreviewPhotos = false): string
    {
        $state = $session->state ?? [];

        if (empty($state['backgroundPath']) || ! Storage::disk('public')->exists($state['backgroundPath'])) {
            throw new RuntimeException('Nog geen achtergrond gegenereerd voor deze sessie.');
        }

        $image = $this->loadAsTrueColorCanvas($state['backgroundPath']);

        if (! empty($state['maskId'])) {
            $mask = DesignMask::withoutGlobalScope(AccountScope::class)
                ->where('account_id', $session->account_id)
                ->find($state['maskId']);

            if ($mask) {
                $this->applyMask($image, $mask);

                if ($includePreviewPhotos && $mask->preview_photos_path && Storage::disk('public')->exists($mask->preview_photos_path)) {
                    $this->compositePreviewPhotos($image, $mask);
                }
            }
        }

        // state['logo'] (enkelvoud) = oude vorm, voor lopende sessies van vóór de meerdere-logo's-update
        $logos = $state['logos'] ?? (isset($state['logo']) ? [$state['logo']] : []);
        foreach ($logos as $logo) {
            if (! empty($logo['path']) && Storage::disk('public')->exists($logo['path'])) {
                $this->compositeLogo($image, $logo);
            }
        }

        foreach (($state['texts'] ?? []) as $text) {
            if (! empty($text['content'])) {
                $this->compositeText($image, $text);
            }
        }

        return $image->getImageBlob();
    }

    /**
     * Bak de sessie tot het definitieve productie-PNG (échte transparante foto-vensters, dus GEEN
     * voorbeeldfoto's) en zet het klaar als productiebestand voor de photobooth-download. Zet
     * strip_status op 'ready'. Gooit door bij een render-fout (handmatige flow toont die aan de admin).
     */
    public function storeProductionFile(DesignSession $session, Booking $booking): string
    {
        $binary = $this->render($session);

        $filename = 'production/' . $booking->booking_number . '_' . Str::random(8) . '.png';
        Storage::disk('public')->put($filename, $binary);

        $booking->update([
            'production_file_path' => $filename,
            'production_file_at'   => now(),
            'strip_status'         => 'ready',
        ]);

        return $filename;
    }

    /**
     * Zet een boeking automatisch naar productie ALS het ontwerp in de AI-generator is gemaakt,
     * d.w.z. er is een renderbare DesignSession (achtergrond aanwezig). Best-effort: bij geen sessie,
     * geen achtergrond of een render-fout gebeurt er niets en blijft de status ongewijzigd (de admin
     * kan dan alsnog handmatig klaarzetten). Geeft true terug als er daadwerkelijk geproduceerd is.
     */
    public function publishBookingToProduction(Booking $booking): bool
    {
        $session = DesignSession::withoutGlobalScope(AccountScope::class)
            ->where('booking_id', $booking->id)
            ->first();

        if (! $session || empty($session->state['backgroundPath'] ?? null)) {
            return false;
        }

        try {
            $this->storeProductionFile($session, $booking);

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /** Genereer een preview van een masker op een losse achtergrond, zonder logo. */
    public function renderMaskPreview(string $backgroundPath, DesignMask $mask): string
    {
        $image = $this->loadAsTrueColorCanvas($backgroundPath);

        $this->applyMask($image, $mask);

        return $image->getImageBlob();
    }

    /**
     * Effen (bijv. door de klant gekozen grijstinten) achtergronden worden door Imagick bij het
     * inlezen soms als grayscale/palette gedetecteerd — composites van gekleurde logo's/randen
     * landen dan stilletjes niet. Fix: eerst overzetten op een gegarandeerd truecolor canvas.
     */
    private function loadAsTrueColorCanvas(string $path): Imagick
    {
        $loaded = new Imagick();
        $loaded->readImageBlob(Storage::disk('public')->get($path));

        $image = new Imagick();
        $image->newImage($loaded->getImageWidth(), $loaded->getImageHeight(), new ImagickPixel('white'));
        $image->setImageFormat('png');
        $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_OPAQUE); // canvas heeft anders geen alfakanaal-structuur, waardoor een latere maskercompositie (COMPOSITE_DSTIN) geen transparantie kan aanbrengen
        $image->compositeImage($loaded, Imagick::COMPOSITE_OVER, 0, 0);

        return $image;
    }

    public function applyMask(Imagick $image, DesignMask $mask): void
    {
        $maskImage = new Imagick();
        $maskImage->readImageBlob(Storage::disk('public')->get($mask->path));
        $maskImage->resizeImage($image->getImageWidth(), $image->getImageHeight(), Imagick::FILTER_LANCZOS, 1);
        // GEEN setImageAlphaChannel(ALPHACHANNEL_COPY) hier: het maskerbestand heeft al een correct
        // alfakanaal (ondoorzichtig = behouden, transparant = foto-venster). ALPHACHANNEL_COPY overschreef
        // dat met een nieuw alfakanaal afgeleid van de grijswaarde van de RGB-kleur, waardoor niet-zwarte
        // "behouden"-delen van het masker (bijv. een gekleurd kader) alsnog gedeeltelijk transparant werden.
        $image->compositeImage($maskImage, Imagick::COMPOSITE_DSTIN, 0, 0);
    }

    /**
     * Plakt de voorbeeldfoto's-afbeelding van het masker ACHTER de huidige inhoud: overal waar het
     * beeld tot nu toe transparant is (de foto-vensters) komt de voorbeeldfoto zichtbaar te liggen,
     * de rest (kader/rand) blijft gewoon zoals het was.
     */
    private function compositePreviewPhotos(Imagick $image, DesignMask $mask): void
    {
        $previewImage = new Imagick();
        $previewImage->readImageBlob(Storage::disk('public')->get($mask->preview_photos_path));
        $previewImage->resizeImage($image->getImageWidth(), $image->getImageHeight(), Imagick::FILTER_LANCZOS, 1);

        $image->compositeImage($previewImage, Imagick::COMPOSITE_DSTOVER, 0, 0);
    }

    /**
     * Logo-state is percentage-gebaseerd (xPct/yPct/widthPct t.o.v. het canvas, rotateDeg)
     * zodat de server exact dezelfde plaatsing kan reproduceren als de browser-preview.
     */
    private function compositeLogo(Imagick $image, array $logo): void
    {
        $logoImage = new Imagick();
        $logoImage->readImageBlob(Storage::disk('public')->get($logo['path']));

        $canvasWidth  = $image->getImageWidth();
        $canvasHeight = $image->getImageHeight();

        $widthPct    = (float) ($logo['widthPct'] ?? 35);
        $targetWidth = max(1, (int) round($canvasWidth * $widthPct / 100));
        $aspect      = $logoImage->getImageHeight() / $logoImage->getImageWidth();

        $logoImage->resizeImage($targetWidth, max(1, (int) round($targetWidth * $aspect)), Imagick::FILTER_LANCZOS, 1);

        $rotateDeg = (float) ($logo['rotateDeg'] ?? 0);
        if ($rotateDeg != 0) {
            $logoImage->rotateImage(new ImagickPixel('transparent'), $rotateDeg);
        }

        $xPct = (float) ($logo['xPct'] ?? 50);
        $yPct = (float) ($logo['yPct'] ?? 50);

        $x = (int) round(($canvasWidth * $xPct / 100) - ($logoImage->getImageWidth() / 2));
        $y = (int) round(($canvasHeight * $yPct / 100) - ($logoImage->getImageHeight() / 2));

        $image->compositeImage($logoImage, Imagick::COMPOSITE_OVER, $x, $y);
    }

    /**
     * Tekst-state is percentage-gebaseerd (xPct/yPct/fontSizePct t.o.v. het canvas), net als logo's,
     * zodat de server exact dezelfde plaatsing/grootte reproduceert als de browser-preview.
     * xPct/yPct is het MIDDEN van de tekst (matcht de gecentreerde drag-positionering in de preview).
     *
     * De tekst wordt op een eigen transparante laag getekend en dáárna rond het midden geroteerd
     * (rotateDeg), net als de CSS `transform: rotate(...)` in de preview — zo blijven preview en
     * definitieve PNG identiek, ook bij een gedraaide tekst.
     */
    private function compositeText(Imagick $image, array $text): void
    {
        $canvasWidth  = $image->getImageWidth();
        $canvasHeight = $image->getImageHeight();

        $fontSizePct = (float) ($text['fontSizePct'] ?? 4);
        $fontSizePx  = max(6, (int) round($canvasWidth * $fontSizePct / 100));

        $color = $text['color'] ?? '#000000';

        // Echt lettergewicht: kies de gedownloade gewicht-variant (light 300 / regular 400 / bold 700).
        // Bestaat het gevraagde gewicht niet voor dit font, dan valt ttfPath terug op regular.
        $fontWeight = (int) ($text['fontWeight'] ?? 400);
        $fontSlug   = $text['fontSlug'] ?? GoogleFontRegistry::DEFAULT_SLUG;

        $draw = new \ImagickDraw();
        $draw->setFont(GoogleFontRegistry::ttfPath($fontSlug, $fontWeight));
        $draw->setFontSize($fontSizePx);
        $draw->setFillColor(new ImagickPixel($color));
        $draw->setTextAlignment(Imagick::ALIGN_CENTER);
        $draw->setGravity(Imagick::GRAVITY_NORTHWEST);

        // Letterafstand: em-waarde × fontgrootte = kerning in px tussen de glyphs, matcht CSS letter-spacing.
        $letterSpacingEm = (float) ($text['letterSpacingEm'] ?? 0);
        $kerningPx = $letterSpacingEm * $fontSizePx;
        if ($kerningPx != 0.0) {
            $draw->setTextKerning($kerningPx);
        }

        $content = (string) $text['content'];
        $metrics = $image->queryFontMetrics($draw, $content);

        // Teken de tekst op een strak transparant laagje (met wat marge tegen clipping). Bij positieve
        // letterafstand kan queryFontMetrics de kerning niet altijd meerekenen → laagbreedte ophogen.
        $pad = 6;
        $extraKerning = max(0.0, $kerningPx) * max(0, mb_strlen($content) - 1);
        $layerW = max(1, (int) ceil($metrics['textWidth'] + $extraKerning) + $pad * 2);
        $layerH = max(1, (int) ceil($metrics['textHeight']) + $pad * 2);

        $textLayer = new Imagick();
        $textLayer->newImage($layerW, $layerH, new ImagickPixel('transparent'));
        $textLayer->setImageFormat('png');

        $baseline = $pad + $metrics['ascender'];
        $textLayer->annotateImage($draw, $layerW / 2, $baseline, 0, $content);

        $rotateDeg = (float) ($text['rotateDeg'] ?? 0);
        if ($rotateDeg != 0) {
            $textLayer->rotateImage(new ImagickPixel('transparent'), $rotateDeg);
        }

        $xPct = (float) ($text['xPct'] ?? 50);
        $yPct = (float) ($text['yPct'] ?? 50);

        $x = (int) round(($canvasWidth * $xPct / 100) - ($textLayer->getImageWidth() / 2));
        $y = (int) round(($canvasHeight * $yPct / 100) - ($textLayer->getImageHeight() / 2));

        $image->compositeImage($textLayer, Imagick::COMPOSITE_OVER, $x, $y);
        $textLayer->clear();
    }
}
