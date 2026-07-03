<?php

namespace App\Services\ImageGeneration\Providers;

use App\Services\ImageGeneration\Contracts\ImageGenerationProvider;
use App\Services\ImageGeneration\DTO\GeneratedImage;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiImageProvider implements ImageGenerationProvider
{
    public function name(): string
    {
        return 'gemini';
    }

    public function generate(string $prompt, array $referenceImagePaths = [], ?string $maskPath = null): GeneratedImage
    {
        $apiKey = config('services.gemini.api_key');
        $model = config('services.gemini.image_model');

        if (! $apiKey) {
            throw new RuntimeException('GEMINI_API_KEY is niet ingesteld.');
        }

        $parts = [['text' => $prompt]];

        foreach ($referenceImagePaths as $path) {
            $parts[] = $this->inlineImagePart($path);
        }

        if ($maskPath) {
            $parts[] = $this->inlineImagePart($maskPath);
        }

        $response = Http::timeout(120)
            ->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                [
                    'contents' => [
                        ['parts' => $parts],
                    ],
                ]
            );

        if (! $response->successful()) {
            throw new RuntimeException("Gemini API fout ({$response->status()}): {$response->body()}");
        }

        $responseParts = $response->json('candidates.0.content.parts', []);

        foreach ($responseParts as $part) {
            if (isset($part['inlineData']['data'])) {
                return new GeneratedImage(
                    binary: base64_decode($part['inlineData']['data']),
                    mimeType: $part['inlineData']['mimeType'] ?? 'image/png',
                    provider: $this->name(),
                );
            }
        }

        throw new RuntimeException('Gemini gaf geen afbeelding terug: ' . $response->body());
    }

    private function inlineImagePart(string $path): array
    {
        return [
            'inlineData' => [
                'mimeType' => mime_content_type($path) ?: 'image/png',
                'data' => base64_encode(file_get_contents($path)),
            ],
        ];
    }
}
