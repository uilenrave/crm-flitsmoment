<?php

namespace App\Console\Commands;

use App\Services\ImageGeneration\ImageGenerationManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TestImageGeneration extends Command
{
    protected $signature = 'design:test-generation
        {--prompt= : De tekstprompt voor het ontwerp}
        {--image=* : Pad naar één of meer referentieafbeeldingen (flyer, huisstijl, logo)}
        {--mask= : Optioneel pad naar een PNG-mask (transparant = bewerkbaar gebied)}
        {--provider=both : gemini, openai of both}';

    protected $description = 'Genereer een test-ontwerp via Gemini en/of OpenAI om providers naast elkaar te vergelijken';

    public function handle(ImageGenerationManager $manager): int
    {
        $prompt = $this->option('prompt');
        $images = $this->option('image');
        $mask = $this->option('mask');
        $providerOption = $this->option('provider');

        if (! $prompt) {
            $this->error('Geef een --prompt="..." mee.');

            return self::FAILURE;
        }

        foreach (array_merge($images, $mask ? [$mask] : []) as $path) {
            if (! is_file($path)) {
                $this->error("Bestand niet gevonden: {$path}");

                return self::FAILURE;
            }
        }

        $providers = match ($providerOption) {
            'both' => ['gemini', 'openai'],
            default => [$providerOption],
        };

        $outputDir = storage_path('app/design-tests');
        File::ensureDirectoryExists($outputDir);

        foreach ($providers as $providerName) {
            $this->info("Genereren via {$providerName}...");

            try {
                $result = $manager->driver($providerName)->generate($prompt, $images, $mask);

                $filename = sprintf('%s-%s.%s', $providerName, now()->format('Y-m-d_His'), $result->extension());
                $path = $outputDir . DIRECTORY_SEPARATOR . $filename;

                File::put($path, $result->binary);

                $this->info("  ✓ Opgeslagen: {$path}");
            } catch (\Throwable $e) {
                $this->error("  ✗ {$providerName} mislukt: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
