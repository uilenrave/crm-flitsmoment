<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eén zero-emissiezone-polygon (uit officiële open data). Global model — géén AccountScope:
 * de zones zijn landelijk en voor alle accounts gelijk.
 */
class ZeroEmissionZone extends Model
{
    protected $fillable = [
        'external_id', 'name', 'gemeente', 'source', 'geometry',
        'min_lng', 'min_lat', 'max_lng', 'max_lat',
    ];

    /** Gedecodeerde GeoJSON-geometry (Polygon of MultiPolygon). */
    public function geometryArray(): array
    {
        return json_decode($this->geometry, true) ?: [];
    }
}
