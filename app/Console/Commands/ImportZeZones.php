<?php

namespace App\Console\Commands;

use App\Models\ZeroEmissionZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Importeert zero-emissiezone-polygonen (WGS84 GeoJSON) naar de database. Standaardbron is de
 * landelijke opwegnaarzes-dataset (config ze_zones.source_url); alleen de gemeenten uit
 * config ze_zones.relevant_cities worden ingeladen. GeometryCollection wordt platgeslagen tot
 * MultiPolygon. Idempotent: upsert op gemeente. Ook --url / --file mogelijk.
 */
class ImportZeZones extends Command
{
    protected $signature = 'ze-zones:import {--url=} {--file= : lokaal GeoJSON-bestand i.p.v. de bron} {--all-cities : negeer de relevant_cities-filter} {--dump : toon namen+types uit de bron}';
    protected $description = 'Importeer zero-emissiezone-polygonen (opwegnaarzes-bron) naar de database.';

    public function handle(): int
    {
        $features = $this->loadFeatures();
        if (! is_array($features) || empty($features)) {
            $this->error('Geen features gevonden in de bron.');
            return self::FAILURE;
        }

        if ($this->option('dump')) {
            foreach ($features as $f) {
                $p = $f['properties'] ?? [];
                $this->line(($p['type'] ?? '?') . ' | ' . ($p['name'] ?? '?'));
            }
            return self::SUCCESS;
        }

        $cities  = $this->option('all-cities') ? [] : array_map('mb_strtolower', config('ze_zones.relevant_cities', []));
        $count   = 0;

        foreach ($features as $f) {
            $props = $f['properties'] ?? [];
            $type  = $props['type'] ?? '';
            $name  = trim((string) ($props['name'] ?? ''));

            // Alleen actieve ZE-zones (niet "onderzoeksfase"), en alleen de relevante gemeenten.
            if ($type !== 'ZE-zone' || $name === '') continue;
            if (! empty($cities) && ! in_array(mb_strtolower($name), $cities, true)) continue;

            $multi = $this->toMultiPolygon($f['geometry'] ?? []);
            if (! $multi) continue;

            [$minLng, $minLat, $maxLng, $maxLat] = $this->bbox($multi);

            ZeroEmissionZone::updateOrCreate(
                ['external_id' => 'oz-' . mb_strtolower($name), 'source' => 'opwegnaarzes'],
                [
                    'name'     => $name,
                    'gemeente' => $name,
                    'geometry' => json_encode($multi),
                    'min_lng'  => $minLng, 'min_lat' => $minLat, 'max_lng' => $maxLng, 'max_lat' => $maxLat,
                ]
            );
            $count++;
            $this->line("  ✓ {$name}");
        }

        $this->info("Klaar. {$count} zones geïmporteerd/bijgewerkt (totaal in DB: " . ZeroEmissionZone::count() . ').');
        return self::SUCCESS;
    }

    private function loadFeatures(): ?array
    {
        if ($file = $this->option('file')) {
            if (! is_file($file)) { $this->error('Bestand niet gevonden: ' . $file); return null; }
            $this->info('Inlezen bestand: ' . $file);
            return json_decode((string) file_get_contents($file), true)['features'] ?? null;
        }

        $url = $this->option('url') ?: config('ze_zones.source_url');
        $this->info('Ophalen: ' . $url);
        try {
            $resp = Http::connectTimeout(30)->timeout(60)->get($url);
        } catch (\Throwable $e) {
            $this->error('Ophalen mislukt: ' . $e->getMessage());
            return null;
        }
        if (! $resp->ok()) { $this->error('HTTP ' . $resp->status()); return null; }
        return $resp->json('features');
    }

    /** Sla een (Geometry)Collection plat tot één MultiPolygon. */
    private function toMultiPolygon(array $geometry): ?array
    {
        $polys = [];
        $collect = function ($g) use (&$collect, &$polys) {
            $t = $g['type'] ?? null;
            if ($t === 'Polygon' && isset($g['coordinates'])) {
                $polys[] = $g['coordinates'];
            } elseif ($t === 'MultiPolygon' && isset($g['coordinates'])) {
                foreach ($g['coordinates'] as $p) $polys[] = $p;
            } elseif ($t === 'GeometryCollection') {
                foreach ($g['geometries'] ?? [] as $sub) $collect($sub);
            }
        };
        $collect($geometry);

        return $polys ? ['type' => 'MultiPolygon', 'coordinates' => $polys] : null;
    }

    /** @return array [minLng,minLat,maxLng,maxLat] */
    private function bbox(array $geometry): array
    {
        $min = [INF, INF]; $max = [-INF, -INF];
        $walk = function ($node) use (&$walk, &$min, &$max) {
            if (isset($node[0], $node[1]) && is_numeric($node[0]) && ! is_array($node[0]) && is_numeric($node[1])) {
                $min[0] = min($min[0], $node[0]); $max[0] = max($max[0], $node[0]);
                $min[1] = min($min[1], $node[1]); $max[1] = max($max[1], $node[1]);
                return;
            }
            foreach ($node as $child) if (is_array($child)) $walk($child);
        };
        $walk($geometry['coordinates'] ?? []);

        return [
            is_finite($min[0]) ? $min[0] : null, is_finite($min[1]) ? $min[1] : null,
            is_finite($max[0]) ? $max[0] : null, is_finite($max[1]) ? $max[1] : null,
        ];
    }
}
