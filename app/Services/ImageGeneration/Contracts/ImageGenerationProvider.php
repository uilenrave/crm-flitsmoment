<?php

namespace App\Services\ImageGeneration\Contracts;

use App\Services\ImageGeneration\DTO\GeneratedImage;

interface ImageGenerationProvider
{
    /**
     * Generate/edit an image from a text prompt and optional reference images.
     *
     * @param  string  $prompt  The instruction describing the desired design.
     * @param  array<string>  $referenceImagePaths  Absolute paths to reference images (flyer, huisstijl, logo, ...).
     * @param  string|null  $maskPath  Absolute path to a PNG mask (transparent = editable area). Only used by providers that support it.
     */
    public function generate(string $prompt, array $referenceImagePaths = [], ?string $maskPath = null): GeneratedImage;

    public function name(): string;
}
