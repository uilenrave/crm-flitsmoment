<?php

namespace App\Console\Commands;

use App\Models\DesignTemplate;
use App\Models\DesignTemplateCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Eenmalige (idempotente) import van de bestaande template-previews op schijf naar de
 * design_templates-tabel. De bestanden blijven staan waar ze staan (geen move — veilig op
 * shared hosting); we slaan alleen het bestaande pad op. Meermaals draaien is veilig: bestaande
 * rijen (op image_path) worden overgeslagen.
 */
class ImportDiskTemplates extends Command
{
    protected $signature = 'design-templates:import-disk';

    protected $description = 'Importeer de bestaande template-previews van schijf als goedgekeurde templates in de database';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $dirs = $disk->directories('template-previews');

        if (empty($dirs)) {
            $this->warn('Geen template-previews-mappen gevonden. Niets te importeren.');
            return self::SUCCESS;
        }

        $imported = 0;
        $skipped = 0;
        $sortCat = 0;

        foreach ($dirs as $dir) {
            $slug  = basename($dir);
            $label = Str::of($slug)->replace('-', ' ')->ucfirst();

            $category = DesignTemplateCategory::firstOrCreate(
                ['slug' => $slug],
                ['label' => $label, 'sort_order' => $sortCat++]
            );

            $files = $disk->files($dir);
            natsort($files);
            $sort = 0;

            foreach ($files as $file) {
                if (DesignTemplate::where('image_path', $file)->exists()) {
                    $skipped++;
                    continue;
                }

                $name      = pathinfo($file, PATHINFO_FILENAME);
                $themeSlug = preg_replace('/^\d+-/', '', $name);

                DesignTemplate::create([
                    'category_id' => $category->id,
                    'status'      => 'approved',
                    'image_path'  => $file,
                    'label'       => Str::of($themeSlug)->replace('-', ' ')->ucfirst(),
                    'source'      => 'disk_import',
                    'sort_order'  => $sort++,
                    'approved_at' => now(),
                ]);
                $imported++;
            }

            $this->info("  {$slug}: {$sort} geïmporteerd");
        }

        $this->info("Klaar. Geïmporteerd: {$imported}, overgeslagen (al aanwezig): {$skipped}.");

        return self::SUCCESS;
    }
}
