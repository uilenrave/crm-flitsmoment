<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ZeroEmissionZone;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Bepaalt of een (bezorg)adres in een zero-emissiezone ligt.
 *  - geocoderen via PDOK Locatieserver (gratis, NL) → lat/lng
 *  - point-in-polygon tegen de opgeslagen zone-polygonen (bbox-voorfilter + ray casting)
 * Resultaat wordt als vlag op de boeking opgeslagen (in_ze_zone / ze_zone_name).
 */
class ZeZoneService
{
    /** Geocodeer een NL-adres naar [lat, lng] via PDOK, of null. */
    public function geocode(string $address): ?array
    {
        $address = trim($address);
        if ($address === '') return null;

        try {
            $resp = Http::timeout(15)->get('https://api.pdok.nl/bzk/locatieserver/search/v3_1/free', [
                'q'    => $address,
                'fq'   => 'type:adres',
                'rows' => 1,
            ]);
            $doc = $resp->json('response.docs.0');
            $point = $doc['centroide_ll'] ?? null; // "POINT(lng lat)"
            if (! $point || ! preg_match('/POINT\(([-0-9.]+)\s+([-0-9.]+)\)/', $point, $m)) {
                return null;
            }
            return ['lat' => (float) $m[2], 'lng' => (float) $m[1]];
        } catch (\Throwable $e) {
            Log::warning('PDOK geocode mislukt: ' . $e->getMessage());
            return null;
        }
    }

    /** Vind de zero-emissiezone waarin (lat,lng) valt, of null. */
    public function zoneForPoint(float $lat, float $lng): ?ZeroEmissionZone
    {
        // Bbox-voorfilter: alleen zones waarvan de omhullende doos het punt bevat precies checken.
        $candidates = ZeroEmissionZone::query()
            ->where(function ($q) use ($lat, $lng) {
                $q->whereNull('min_lng')
                  ->orWhere(function ($q2) use ($lat, $lng) {
                      $q2->where('min_lng', '<=', $lng)->where('max_lng', '>=', $lng)
                         ->where('min_lat', '<=', $lat)->where('max_lat', '>=', $lat);
                  });
            })
            ->get();

        foreach ($candidates as $zone) {
            if ($this->pointInGeometry($lng, $lat, $zone->geometryArray())) {
                return $zone;
            }
        }
        return null;
    }

    /**
     * Herbereken de zone-vlag voor een boeking. Geocodeert het bezorgadres (of hergebruikt
     * opgeslagen coördinaten) en zet in_ze_zone / ze_zone_name / ze_checked_at.
     */
    public function checkBooking(Booking $booking, bool $regeocode = false): void
    {
        $lat = $booking->latitude;
        $lng = $booking->longitude;

        if ($regeocode || $lat === null || $lng === null) {
            $address = $this->bookingAddress($booking);
            $geo = $address ? $this->geocode($address) : null;
            $lat = $geo['lat'] ?? null;
            $lng = $geo['lng'] ?? null;
        }

        $zone = ($lat !== null && $lng !== null) ? $this->zoneForPoint($lat, $lng) : null;

        $booking->forceFill([
            'latitude'      => $lat,
            'longitude'     => $lng,
            'in_ze_zone'    => (bool) $zone,
            'ze_zone_name'  => $zone?->name ?? $zone?->gemeente,
            'ze_checked_at' => now(),
        ])->saveQuietly();
    }

    /** Stel het geocodeerbare adres samen uit de boekingvelden. */
    private function bookingAddress(Booking $booking): string
    {
        return trim(implode(' ', array_filter([
            $booking->event_address,
            $booking->event_postcode,
            $booking->event_city,
        ])));
    }

    // ── Point-in-polygon (GeoJSON Polygon / MultiPolygon), ray casting ──

    private function pointInGeometry(float $x, float $y, array $geometry): bool
    {
        $type   = $geometry['type'] ?? null;
        $coords = $geometry['coordinates'] ?? null;
        if (! $coords) return false;

        if ($type === 'Polygon') {
            return $this->pointInPolygon($x, $y, $coords);
        }
        if ($type === 'MultiPolygon') {
            foreach ($coords as $polygon) {
                if ($this->pointInPolygon($x, $y, $polygon)) return true;
            }
        }
        return false;
    }

    /** $polygon = [outerRing, hole1, hole2, ...], elke ring = [[lng,lat], ...]. */
    private function pointInPolygon(float $x, float $y, array $polygon): bool
    {
        if (empty($polygon) || ! $this->pointInRing($x, $y, $polygon[0])) {
            return false; // buiten de buitenring
        }
        for ($i = 1; $i < count($polygon); $i++) {
            if ($this->pointInRing($x, $y, $polygon[$i])) {
                return false; // in een gat
            }
        }
        return true;
    }

    private function pointInRing(float $x, float $y, array $ring): bool
    {
        $inside = false;
        $n = count($ring);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = $ring[$i][0]; $yi = $ring[$i][1];
            $xj = $ring[$j][0]; $yj = $ring[$j][1];
            $intersect = (($yi > $y) !== ($yj > $y))
                && ($x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-12) + $xi);
            if ($intersect) $inside = ! $inside;
        }
        return $inside;
    }
}
