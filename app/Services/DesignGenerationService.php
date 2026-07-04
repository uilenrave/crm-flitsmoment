<?php

namespace App\Services;

use App\Models\DesignPromptSetting;
use App\Services\ImageGeneration\ImageGenerationManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;

/**
 * Kernlogica voor het genereren van achtergronden en het vrijstellen van logo's —
 * gedeeld tussen de admin-generator (DesignGeneratorController) en de klant-wizard
 * in het portaal (PortalController), zodat beide dezelfde AI-aanroepen/opslag gebruiken.
 */
class DesignGenerationService
{
    private const CANVAS_WIDTH = 600;
    private const CANVAS_HEIGHT = 1800;

    /** Genereer + cover-crop een achtergrondafbeelding via Gemini. Retourneert het standaard results-array. */
    public function generateBackground(string $eventType, string $input, array $refPaths): array
    {
        $template = DesignPromptSetting::currentPrompt('background', $eventType);
        $prompt = str_contains($template, '{beschrijving}')
            ? str_replace('{beschrijving}', $input, $template)
            : $template . "\n\n" . $input;

        $started = microtime(true);
        try {
            $manager = app(ImageGenerationManager::class);
            $image   = $manager->driver('gemini')->generate($prompt, $refPaths, null);
            $binary  = $this->coverCropToCanvas($image->binary, self::CANVAS_WIDTH, self::CANVAS_HEIGHT);

            $filename = 'design-generator/out/' . Str::random(24) . '.jpg';
            Storage::disk('public')->put($filename, $binary);

            return [
                'ok'      => true,
                'url'     => Storage::disk('public')->url($filename),
                'path'    => $filename,
                'seconds' => round(microtime(true) - $started, 1),
            ];
        } catch (\Throwable $e) {
            Log::error('Achtergrond-generatie mislukt: ' . $e->getMessage());

            return [
                'ok'    => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /** Stel een logo vrij als transparante PNG (via GPT/OpenAI — Gemini kan geen echte transparantie). */
    public function cutoutLogo(string $localPath): array
    {
        $prompt = 'Cut out only the logo from this image. Remove the background completely so it becomes '
            . 'fully transparent (alpha channel = 0), keep the logo itself unchanged — do not redraw, '
            . 'recolor, restyle, or add effects. No shadow, no border, no added background color. '
            . 'The output must have a genuinely transparent background.';

        try {
            $manager = app(ImageGenerationManager::class);
            $image   = $manager->driver('openai')->generate($prompt, [$localPath], null);

            $filename = 'design-generator/logos/' . Str::random(24) . '.' . $image->extension();
            Storage::disk('public')->put($filename, $image->binary);

            return [
                'ok'   => true,
                'url'  => Storage::disk('public')->url($filename),
                'path' => $filename,
            ];
        } catch (\Throwable $e) {
            Log::error('Logo vrijstellen mislukt: ' . $e->getMessage());

            return [
                'ok'    => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Forceer de gegenereerde achtergrond op het vaste canvas (zoals CSS background-size: cover):
     * schalen tot beide dimensies gedekt zijn, daarna vanuit het midden bijsnijden op maat.
     * Gemini's aspect ratio klopt niet altijd exact — dit garandeert altijd 600×1800px.
     */
    private function coverCropToCanvas(string $binary, int $targetWidth, int $targetHeight): string
    {
        $image = new Imagick();
        $image->readImageBlob($binary);
        $image = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);

        $srcWidth  = $image->getImageWidth();
        $srcHeight = $image->getImageHeight();
        $scale     = max($targetWidth / $srcWidth, $targetHeight / $srcHeight);

        $image->resizeImage(
            (int) ceil($srcWidth * $scale),
            (int) ceil($srcHeight * $scale),
            Imagick::FILTER_LANCZOS,
            1
        );

        $cropX = (int) round(($image->getImageWidth() - $targetWidth) / 2);
        $cropY = (int) round(($image->getImageHeight() - $targetHeight) / 2);
        $image->cropImage($targetWidth, $targetHeight, max(0, $cropX), max(0, $cropY));
        $image->setImagePage(0, 0, 0, 0);

        $image->setImageFormat('jpg');
        $image->setImageCompressionQuality(92);

        return $image->getImageBlob();
    }
}
