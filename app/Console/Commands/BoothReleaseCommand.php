<?php

namespace App\Console\Commands;

use App\Models\BoothRelease;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BoothReleaseCommand extends Command
{
    protected $signature = 'booth:release {version : Versienummer, bijv. 0.2.0} {filename : Installer in storage/app/private/booth-updates/} {--channel=beta : beta of stable} {--notes= : Korte omschrijving}';

    protected $description = 'Registreert een geüploade booth-installer als versie op een updatekanaal.';

    public function handle(): int
    {
        $version  = $this->argument('version');
        $filename = basename($this->argument('filename'));
        $channel  = $this->option('channel');

        if (! preg_match('/^\d+\.\d+\.\d+([\-+][0-9A-Za-z.\-]+)?$/', $version)) {
            $this->error("Ongeldig versienummer: {$version}");
            return self::FAILURE;
        }
        if (! array_key_exists($channel, config('booth.channels'))) {
            $this->error("Onbekend kanaal: {$channel}");
            return self::FAILURE;
        }

        $disk = Storage::disk('local');
        $path = BoothRelease::DIR . '/' . $filename;
        if (! $disk->exists($path)) {
            $this->error("Bestand niet gevonden: storage/app/private/{$path}");
            return self::FAILURE;
        }

        $release = BoothRelease::updateOrCreate(['version' => $version], [
            'filename' => $filename,
            'sha512'   => base64_encode(hash_file('sha512', $disk->path($path), true)),
            'size'     => $disk->size($path),
            'channel'  => $channel,
            'notes'    => $this->option('notes'),
        ]);

        $this->info("Versie {$release->version} staat op kanaal {$release->channel} (" . round($release->size / 1048576) . ' MB).');

        return self::SUCCESS;
    }
}
