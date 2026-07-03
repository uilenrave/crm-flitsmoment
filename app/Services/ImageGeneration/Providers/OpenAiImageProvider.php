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
}
