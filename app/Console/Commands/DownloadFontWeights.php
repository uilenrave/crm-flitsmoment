<?php

namespace App\Console\Commands;

use App\Services\GoogleFontRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Downloadt per gebundeld font de echte gewicht-varianten (light 300 / regular 400 / bold 700)
 * als losse TTF's, i.p.v. "vet" na te bootsen met een stroke. Bestaat een gewicht niet voor een
 * font, dan wordt het simpelweg overgeslagen.
 *
 * Truc: Google's CSS v1 API (fonts.googleapis.com/css?...) levert TTF (i.p.v. woff2) mét een
 * font-weight-descriptor wanneer je een oude/simpele User-Agent stuurt, en geeft ALLEEN de
 * gewichten terug die daadwerkelijk bestaan — zo is de beschikbaarheid gratis te detecteren.
 *
 * Bestandsnaamgeving (matcht GoogleFontRegistry::ttfPath):
 *   - 400 → resources/fonts/google/{slug}.ttf           (blijft de bestaande "regular")
 *   - 300 → resources/fonts/google/{slug}-300.ttf
 *   - 700 → resources/fonts/google/{slug}-700.ttf
 * Elk bestand wordt ook naar public/fonts/google/ gekopieerd voor de browser-preview.
 */
class DownloadFontWeights extends Command
{
    protected $signature = 'fonts:download-weights {--weights=300,400,700 : Komma-gescheiden gewichten om op te halen}';

    protected $description = 'Download light/regular/bold TTF-varianten van de gebundelde Google Fonts (bestaande gewichten alleen).';

    private const UA = 'Wget/1.13';

    public function handle(): int
    {
        $weights = collect(explode(',', (string) $this->option('weights')))
            ->map(fn ($w) => (int) trim($w))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $resourceDir = resource_path('fonts/google');
        $publicDir   = public_path('fonts/google');

        foreach ([$resourceDir, $publicDir] as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        $totalDownloaded = 0;
        $totalSkipped    = 0;

        foreach (GoogleFontRegistry::FONTS as $slug => $font) {
            $family = explode(':', $font['cssFamily'])[0]; // strip evt. ':wght@400'
            $url    = 'https://fonts.googleapis.com/css?family=' . $family . ':' . implode(',', $weights);

            try {
                $response = Http::withHeaders(['User-Agent' => self::UA])->timeout(30)->get($url);
            } catch (\Throwable $e) {
                $this->error("  ✗ {$slug}: request mislukt ({$e->getMessage()})");
                continue;
            }

            if (! $response->ok()) {
                $this->warn("  ✗ {$slug}: HTTP {$response->status()} — overgeslagen");
                continue;
            }

            $found = $this->parseWeightUrls($response->body());

            if (empty($found)) {
                $this->warn("  ✗ {$slug}: geen TTF-bronnen gevonden");
                continue;
            }

            $got = [];
            foreach ($weights as $weight) {
                if (! isset($found[$weight])) {
                    $totalSkipped++;
                    continue; // gewicht bestaat niet voor dit font → overslaan
                }

                $filename = $weight === 400 ? "{$slug}.ttf" : "{$slug}-{$weight}.ttf";

                try {
                    $ttf = Http::withHeaders(['User-Agent' => self::UA])->timeout(30)->get($found[$weight]);
                } catch (\Throwable $e) {
                    $this->error("      TTF-download {$weight} mislukt: {$e->getMessage()}");
                    continue;
                }

                if (! $ttf->ok() || strlen($ttf->body()) < 1000) {
                    $this->error("      TTF-download {$weight} ongeldig (HTTP {$ttf->status()})");
                    continue;
                }

                file_put_contents("{$resourceDir}/{$filename}", $ttf->body());
                file_put_contents("{$publicDir}/{$filename}", $ttf->body());
                $got[] = $weight;
                $totalDownloaded++;
            }

            $this->line("  ✓ {$slug}: " . (empty($got) ? 'niets' : implode(', ', $got)));
        }

        $this->newLine();
        $this->info("Klaar. {$totalDownloaded} bestanden gedownload, {$totalSkipped} gewichten overgeslagen (bestonden niet).");

        return self::SUCCESS;
    }

    /**
     * Parseert Google's CSS v1 respons naar [gewicht => ttf-url]. Elk @font-face-blok bevat een
     * `font-weight: N;` en een `src: url(...ttf) ...`.
     *
     * @return array<int,string>
     */
    private function parseWeightUrls(string $css): array
    {
        $result = [];

        if (! preg_match_all('/@font-face\s*\{(.*?)\}/s', $css, $blocks)) {
            return $result;
        }

        foreach ($blocks[1] as $block) {
            if (! preg_match('/font-weight:\s*(\d+)/', $block, $wm)) {
                continue;
            }
            if (! preg_match('/src:\s*url\(([^)]+\.ttf)\)/', $block, $um)) {
                continue;
            }
            $weight = (int) $wm[1];
            // eerste voorkomen wint (normal style staat vóór italic in de respons)
            if (! isset($result[$weight])) {
                $result[$weight] = trim($um[1], "'\" ");
            }
        }

        return $result;
    }
}
