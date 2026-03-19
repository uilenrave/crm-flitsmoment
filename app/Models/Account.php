<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = [
        'name', 'code', 'type', 'email', 'phone',
        'address', 'timezone', 'currency', 'status',
        'mollie_key', 'mollie_enabled', 'eboekhouden_enabled', 'eboekhouden_api_key',
        'origin_address', 'travel_free_km', 'travel_cost_per_km',
    ];

    protected $casts = [
        'mollie_enabled'       => 'boolean',
        'eboekhouden_enabled'  => 'boolean',
        'travel_free_km'       => 'integer',
        'travel_cost_per_km'   => 'decimal:2',
    ];

    /** Berekent reiskosten op basis van afstand in km */
    public function calculateTravelCost(float $distanceKm): float
    {
        $freeKm = $this->travel_free_km ?? 20;
        $ratePerKm = (float) ($this->travel_cost_per_km ?? 4.00);

        if ($distanceKm <= $freeKm) {
            return 0.0;
        }

        return round(($distanceKm - $freeKm) * $ratePerKm, 2);
    }

    /** Geeft de effectieve Mollie key terug: account-specifiek of globale config */
    public function getMollieKey(): string
    {
        return $this->mollie_key ?: config('services.mollie.key', env('MOLLIE_KEY', ''));
    }

    /** Geeft de effectieve e-Boekhouden API key terug: account-specifiek of globale config */
    public function getEBoekhoudenApiKey(): string
    {
        return $this->eboekhouden_api_key ?: config('services.eboekhouden.api_key', env('EBOEKHOUDEN_API_KEY', ''));
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function leadStatuses(): HasMany
    {
        return $this->hasMany(LeadStatus::class)->orderBy('sort_order');
    }
}
