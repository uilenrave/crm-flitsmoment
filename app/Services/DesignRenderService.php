<?php

namespace App\Services;

use App\Models\DesignMask;
use App\Models\DesignSession;
use App\Scopes\AccountScope;
use Illuminate\Support\Facades\Storage;
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
    public function render(DesignSession $session): string
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
                $this->applyMask($image, $mask, $state['borderColor'] ?? null);
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

    /** Genereer een preview van een masker (+ optionele gekleurde rand) op een losse achtergrond, zonder logo. */
    public function renderMaskPreview(string $backgroundPath, DesignMask $mask, ?string $borderColor): string
    {
        $image = $this->loadAsTrueColorCanvas($backgroundPath);

        $this->applyMask($image, $mask, $borderColor);

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
        $image->compositeImage($loaded, Imagick::COMPOSITE_OVER, 0, 0);

        return $image;
    }

    public function applyMask(Imagick $image, DesignMask $mask, ?string $borderColor): void
    {
        $maskImage = new Imagick();
        $maskImage->readImageBlob(Storage::disk('public')->get($mask->path));
        $maskImage->resizeImage($image->getImageWidth(), $image->getImageHeight(), Imagick::FILTER_LANCZOS, 1);
        $maskImage->setImageAlphaChannel(Imagick::ALPHACHANNEL_COPY);

        $image->compositeImage($maskImage, Imagick::COMPOSITE_DSTIN, 0, 0);

        if ($mask->svg_path && $borderColor) {
            $svg = $this->recolorSvg(Storage::disk('public')->get($mask->svg_path), $borderColor);

            $svgImage = new Imagick();
            $svgImage->setBackgroundColor(new ImagickPixel('transparent'));
            $svgImage->readImageBlob($svg);
            $svgImage->resizeImage($image->getImageWidth(), $image->getImageHeight(), Imagick::FILTER_LANCZOS, 1);

            $image->compositeImage($svgImage, Imagick::COMPOSITE_OVER, 0, 0);
        }
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
     */
    private function compositeText(Imagick $image, array $text): void
    {
        $canvasWidth  = $image->getImageWidth();
        $canvasHeight = $image->getImageHeight();

        $fontSizePct = (float) ($text['fontSizePct'] ?? 4);
        $fontSizePx  = max(6, (int) round($canvasWidth * $fontSizePct / 100));

        $draw = new \ImagickDraw();
        $draw->setFont(GoogleFontRegistry::ttfPath($text['fontSlug'] ?? GoogleFontRegistry::DEFAULT_SLUG));
        $draw->setFontSize($fontSizePx);
        $draw->setFillColor(new ImagickPixel($text['color'] ?? '#000000'));
        $draw->setTextAlignment(Imagick::ALIGN_CENTER);
        $draw->setGravity(Imagick::GRAVITY_NORTHWEST);

        $content = (string) $text['content'];
        $metrics = $image->queryFontMetrics($draw, $content);

        $xPct = (float) ($text['xPct'] ?? 50);
        $yPct = (float) ($text['yPct'] ?? 50);

        $x = (int) round($canvasWidth * $xPct / 100);
        $topY = ($canvasHeight * $yPct / 100) - ($metrics['textHeight'] / 2);
        $y = (int) round($topY + $metrics['ascender']);

        $image->annotateImage($draw, $x, $y, 0, $content);
    }

    /**
     * Vervang de randkleur in een svg door één gekozen kleur. Afspraak: de kleurbare rand
     * gebruikt css-klasse "cls-2" (bijv. Illustrator-export met <style>.cls-2{fill:...}</style>).
     * Daarnaast een fallback voor svg's met inline fill/stroke-attributen (behalve "none").
     */
    private function recolorSvg(string $svg, string $color): string
    {
        $svg = preg_replace('/\.cls-2\s*\{[^}]*\}/i', '.cls-2{fill:' . $color . ';}', $svg);
        $svg = preg_replace('/fill="(?!none)[^"]*"/i', 'fill="' . $color . '"', $svg);
        $svg = preg_replace('/stroke="(?!none)[^"]*"/i', 'stroke="' . $color . '"', $svg);

        return $svg;
    }
}
