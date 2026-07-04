<?php

namespace App\Services;

use Imagick;
use ImagickPixel;
use RuntimeException;

class StripMockupService
{
    /**
     * Stel een mockup samen: achtergrond+logo onder, de strip met schaduw,
     * en het plakbandje transparant erover. Geeft de binaire afbeeldingsdata terug.
     *
     * @param  string  $stripPath  Absoluut pad naar de kale strip-afbeelding.
     */
    public function render(string $stripPath): string
    {
        $cfg = config('mockup');
        $bgPath = resource_path('mockups/' . $cfg['background_file']);

        if (! is_file($bgPath)) {
            throw new RuntimeException("Mockup-achtergrond niet gevonden: {$bgPath}");
        }

        // Effen/weinig-verzadigde achtergronden (zoals een lichte marmertextuur) worden door Imagick
        // bij het inlezen soms als grayscale/palette gedetecteerd, waardoor een latere compositie van
        // een gekleurde strip er zonder kleur op landt. Fix: eerst overzetten op een truecolor canvas.
        $loaded = new Imagick($bgPath);
        $cw = $loaded->getImageWidth();
        $ch = $loaded->getImageHeight();

        $canvas = new Imagick();
        $canvas->newImage($cw, $ch, new ImagickPixel('white'));
        $canvas->setImageFormat('png');
        $canvas->setImageAlphaChannel(Imagick::ALPHACHANNEL_OPAQUE);
        $canvas->compositeImage($loaded, Imagick::COMPOSITE_OVER, 0, 0);
        $loaded->clear();

        // ── Strip laden + schalen binnen het doelvak (aspect behouden) ──
        $strip = new Imagick($stripPath);
        $strip->setImageColorspace(Imagick::COLORSPACE_SRGB);
        if ($strip->getImageWidth() === 0) {
            throw new RuntimeException('Kon de strip-afbeelding niet lezen.');
        }
        $boxW = (int) round($cw * $cfg['strip']['width']);
        $boxH = (int) round($ch * $cfg['strip']['max_height']);
        $strip->scaleImage($boxW, $boxH, true); // bestfit binnen vak
        $sw = $strip->getImageWidth();
        $sh = $strip->getImageHeight();

        $stripX = (int) round($cw * $cfg['strip']['center_x'] - $sw / 2);
        $stripY = (int) round($ch * $cfg['strip']['top']);

        // ── Slagschaduw ──
        $s = $cfg['shadow'];
        $shadow = clone $strip;
        $shadow->setImageBackgroundColor(new ImagickPixel('black'));
        $shadow->shadowImage((int) $s['opacity'], (float) $s['sigma'], 0, 0);
        // shadowImage vergroot de afbeelding; centreer 'm achter de strip + offset
        $shX = (int) round($stripX - ($shadow->getImageWidth() - $sw) / 2 + $s['offset_x']);
        $shY = (int) round($stripY - ($shadow->getImageHeight() - $sh) / 2 + $s['offset_y']);
        $canvas->compositeImage($shadow, Imagick::COMPOSITE_OVER, $shX, $shY);

        // ── Strip ──
        $canvas->compositeImage($strip, Imagick::COMPOSITE_OVER, $stripX, $stripY);

        // ── Plakbandje (optioneel) ──
        $tapePath = resource_path('mockups/' . $cfg['tape_file']);
        if (is_file($tapePath)) {
            $tape = new Imagick($tapePath);
            $tapeW = (int) round($cw * $cfg['tape']['width']);
            $tape->scaleImage($tapeW, 0); // schaal op breedte, hoogte automatisch
            $tx = (int) round($cw * $cfg['tape']['center_x'] - $tape->getImageWidth() / 2);
            $ty = (int) round($ch * $cfg['tape']['center_y'] - $tape->getImageHeight() / 2);
            $canvas->compositeImage($tape, Imagick::COMPOSITE_OVER, $tx, $ty);
            $tape->clear();
        }

        // ── Uitvoer ──
        $canvas->setImageFormat($cfg['format']);
        if ($cfg['format'] === 'jpeg') {
            $canvas->setImageBackgroundColor(new ImagickPixel('white'));
            $canvas->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
            $canvas->setImageCompressionQuality((int) $cfg['quality']);
        }

        $blob = $canvas->getImageBlob();

        $strip->clear();
        $shadow->clear();
        $canvas->clear();

        return $blob;
    }
}
