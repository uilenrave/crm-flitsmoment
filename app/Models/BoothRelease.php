<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Een versie van de booth-app. Niet per account: de software is voor alle booths dezelfde.
 * Kanaal 'beta' = alleen booths op het beta-kanaal krijgen hem; 'stable' = iedereen.
 */
class BoothRelease extends Model
{
    public const DIR = 'booth-updates';

    protected $fillable = ['version', 'filename', 'sha512', 'size', 'channel', 'notes'];

    protected $casts = ['size' => 'integer'];

    public function storagePath(): string
    {
        return self::DIR . '/' . $this->filename;
    }

    /** Nieuwste versie die een booth op dit kanaal mag draaien (beta-booths krijgen ook stable-versies). */
    public static function latestFor(string $channel, ?Collection $releases = null): ?self
    {
        $allowed = $channel === 'beta' ? ['beta', 'stable'] : ['stable'];

        return ($releases ?? static::all())
            ->whereIn('channel', $allowed)
            ->reduce(fn (?self $best, self $r) => ! $best || version_compare($r->version, $best->version, '>') ? $r : $best);
    }

    /** Inhoud van latest.yml zoals electron-updater die verwacht. */
    public function toUpdateYml(): string
    {
        // Absolute URL, zodat de download altijd naar hetzelfde adres gaat, welke feed-URL de booth ook gebruikt.
        $url = \Illuminate\Support\Facades\URL::to("/api/booth/update/files/{$this->filename}");

        return implode("\n", [
            "version: {$this->version}",
            'files:',
            "  - url: {$url}",
            "    sha512: {$this->sha512}",
            "    size: {$this->size}",
            "path: {$url}",
            "sha512: {$this->sha512}",
            "releaseDate: '" . $this->created_at->toIso8601ZuluString('millisecond') . "'",
            '',
        ]);
    }
}
