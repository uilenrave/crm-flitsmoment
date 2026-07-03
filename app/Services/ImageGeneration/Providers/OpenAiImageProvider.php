<?php

namespace App\Services\ImageGeneration\Providers;

use App\Services\ImageGeneration\Contracts\ImageGenerationProvider;
use App\Services\ImageGeneration\DTO\GeneratedImage;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiImageProvider implements ImageGenerationProvider
{
    public function name(): string
    {
        return 'openai';
    }

    public function generate(string $prompt, array $referenceImagePaths = [], ?string $maskPath = null): GeneratedImage
    {
        $apiKey = config('services.openai_images.api_key');
        $model = config('services.openai_images.image_model');

        if (! $apiKey) {
            throw new RuntimeException('OPENAI_API_KEY is niet ingesteld.');
        }

        if (empty($referenceImagePaths)) {
            throw new RuntimeException('OpenAI images/edits vereist minimaal één referentieafbeelding.');
        }

        $request = Http::timeout(120)
            ->withToken($apiKey)
            ->asMultipart();

        foreach ($referenceImagePaths as $path) {
            $request = $request->attach('image[]', file_get_contents($path), basename($path));
        }

        if ($maskPath) {
            $request = $request->attach('mask', file_get_contents($maskPath), basename($maskPath));
        }

        $response = $request->post('https://api.openai.com/v1/images/edits', [
            'model' => $model,
            'prompt' => $prompt,
            'background' => 'transparent',
            'size' => $this->pickSize($referenceImagePaths[0] ?? null),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("OpenAI API fout ({$response->status()}): {$response->body()}");
        }

        $b64 = $response->json('data.0.b64_json');

        if (! $b64) {
            throw new RuntimeException('OpenAI gaf geen afbeelding terug: ' . $response->body());
        }

        return new GeneratedImage(
            binary: base64_decode($b64),
            mimeType: 'image/png',
            provider: $this->name(),
        );
    }

    /**
     * gpt-image-1 ondersteunt alleen "1024x1024", "1024x1536", "1536x1024" of "auto".
     * Zonder expliciete size valt "auto" vaak terug op vierkant, waardoor niet-vierkante
     * input (bijv. een brede/hoge flyer) wordt bijgesneden om te passen. Kies daarom de
     * ondersteunde afmeting die het dichtst bij de werkelijke verhouding van de bron ligt.
     */
    private function pickSize(?string $path): string
    {
        if (! $path) {
            return 'auto';
        }

        [$width, $height] = @getimagesize($path) ?: [0, 0];

        if (! $width || ! $height) {
            return 'auto';
        }

        $ratio = $width / $height;

        if ($ratio > 1.15) {
            return '1536x1024';
        }

        if ($ratio < 0.87) {
            return '1024x1536';
        }

        return '1024x1024';
    }
}
