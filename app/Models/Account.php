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
        'booking_sequence', 'booking_sequence_year',
        'production_token', 'shift_durations', 'todo_settings',
    ];

    protected $casts = [
        'mollie_enabled'       => 'boolean',
        'eboekhouden_enabled'  => 'boolean',
        'travel_free_km'       => 'integer',
        'travel_cost_per_km'   => 'decimal:2',
        'shift_durations'      => 'array',
        'todo_settings'        => 'array',
    ];

    /**
     * Drempel voor een CRM-signaal in de takenapp, met fallback naar config/todo.php.
     * Bijv. todoSetting('offer_followup_days') → 5.
     */
    public function todoSetting(string $key): int
    {
        $stored = is_array($this->todo_settings) ? $this->todo_settings : [];

        return (int) ($stored[$key] ?? config("todo.signals.{$key}"));
    }

    /**
     * Basis-werktijden per klus-actie (minuten), account-instelling met fallback naar de defaults
     * uit ShiftJob::ACTIONS. Vorm: ['opbouwen' => 45, 'afbouwen' => 15, 'afgeven' => 30, 'aannemen' => 15].
     */
    public function shiftDurations(): array
    {
        $defaults = collect(ShiftJob::ACTIONS)->map(fn ($m) => $m['default_minutes'])->all();
        $stored   = is_array($this->shift_durations) ? $this->shift_durations : [];

        $result = [];
        foreach ($defaults as $action => $default) {
            $result[$action] = (int) ($stored[$action] ?? $default);
        }

        return $result;
    }

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
