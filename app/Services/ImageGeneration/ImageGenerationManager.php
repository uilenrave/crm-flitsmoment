<?php

namespace App\Services\ImageGeneration;

use App\Services\ImageGeneration\Providers\GeminiImageProvider;
use App\Services\ImageGeneration\Providers\OpenAiImageProvider;
use Illuminate\Support\Manager;

/**
 * Resolves the active image generation provider (Gemini or OpenAI) so calling
 * code doesn't need to know which one is configured. Switch providers via
 * IMAGE_GENERATION_PROVIDER in .env, or call ->driver('openai') explicitly.
 */
class ImageGenerationManager extends Manager
{
    public function getDefaultDriver()
    {
        return $this->config->get('services.image_generation.default', 'gemini');
    }

    protected function createGeminiDriver(): GeminiImageProvider
    {
        return new GeminiImageProvider;
    }

    protected function createOpenaiDriver(): OpenAiImageProvider
    {
        return new OpenAiImageProvider;
    }
}
