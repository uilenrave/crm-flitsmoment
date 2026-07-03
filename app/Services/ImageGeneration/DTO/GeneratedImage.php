<?php

namespace App\Services\ImageGeneration\DTO;

class GeneratedImage
{
    public function __construct(
        public readonly string $binary,
        public readonly string $mimeType,
        public readonly string $provider,
    ) {
    }

    public function extension(): string
    {
        return match ($this->mimeType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
