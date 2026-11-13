<?php

namespace App\Models;

use App\Scopes\AccountScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Token per photobooth-machine voor de booth-API, én het "apparaat" zelf: koppeling aan
 * asset+unit, updatekanaal, functieschakelaars en laatst gemelde status.
 * Bewust GEEN AccountScope: de middleware moet dit token kunnen vinden zonder ingelogde user.
 * Filter in beheer-queries dus altijd zelf op account_id.
 * Het platte token wordt nooit opgeslagen — alleen de sha256-hash.
 */
class BoothToken extends Model
{
    protected $fillable = [
        'account_id', 'asset_id', 'name', 'unit_number', 'token', 'last_used_at', 'revoked_at',
        'update_channel', 'pinned_version', 'features',
        'app_version', 'last_heartbeat_at', 'selftest_ok', 'last_status',
        'prints_total', 'prints_total_at_digest',
    ];

    protected $hidden = ['token'];

    protected $casts = [
        'asset_id'               => 'integer',
        'unit_number'            => 'integer',
        'last_used_at'           => 'datetime',
        'revoked_at'             => 'datetime',
        'features'               => 'array',
        'last_heartbeat_at'      => 'datetime',
        'selftest_ok'            => 'boolean',
        'last_status'            => 'array',
        'prints_total'           => 'integer',
        'prints_total_at_digest' => 'integer',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function asset(): BelongsTo
    {
        // Zonder AccountScope: ook bruikbaar vanuit de API en de scheduler (geen ingelogde user).
        return $this->belongsTo(Asset::class)->withoutGlobalScope(AccountScope::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(BoothEvent::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /** Hash waarmee het platte token in de kolom `token` wordt opgeslagen en opgezocht. */
    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /** Weergavenaam: booth-naam van de asset-unit indien ingesteld, anders de token-naam. */
    public function displayName(): string
    {
        return $this->asset?->boothName($this->unit_number) ?? $this->name;
    }

    public function timezone(): string
    {
        return $this->account?->timezone ?: 'Europe/Amsterdam';
    }

    public function channel(): string
    {
        return array_key_exists($this->update_channel, config('booth.channels'))
            ? $this->update_channel
            : 'stable';
    }

    /**
     * Effectieve functieschakelaars: defaults uit config/booth.php, overschreven per booth.
     * Onbekende sleutels in de opgeslagen overrides worden genegeerd.
     *
     * @return array<string, bool>
     */
    public function effectiveFeatures(): array
    {
        $overrides = is_array($this->features) ? $this->features : [];

        $result = [];
        foreach (config('booth.features', []) as $key => $meta) {
            $result[$key] = array_key_exists($key, $overrides)
                ? (bool) $overrides[$key]
                : (bool) ($meta['default'] ?? false);
        }

        return $result;
    }

    /**
     * Eerstvolgende (of lopende) bevestigde boeking voor deze booth.
     * Met unit: boekingen met een item op deze unit (en deze asset, indien gekoppeld).
     * Zonder unit weten we niet welke booth dit is → elke bevestigde boeking telt (veilige kant).
     */
    public function nextBooking(?Carbon $now = null): ?Booking
    {
        $today = ($now ?? now())->copy()->setTimezone($this->timezone())->toDateString();

        $query = Booking::withoutGlobalScopes()
            ->where('account_id', $this->account_id)
            ->where('status', 'confirmed')
            ->where(function ($q) use ($today) {
                $q->whereDate('event_date', '>=', $today)
                  ->orWhereDate('event_end_date', '>=', $today);
            })
            ->orderBy('event_date');

        if ($this->unit_number !== null) {
            $query->whereHas('items', function ($q) {
                $q->where('unit_number', $this->unit_number);
                if ($this->asset_id) {
                    $q->where('asset_id', $this->asset_id);
                }
            });
        }

        return $query->first();
    }

    /**
     * Mag deze booth nu een app-update installeren? Niet als er binnen de vriesperiode
     * (config booth.update_freeze_hours) een boeking begint of er nu één loopt.
     *
     * @return array{allowed: bool, reason: ?string, next_booking_date: ?string}
     */
    public function updatePolicy(?Carbon $now = null): array
    {
        $now  = $now ?? now();
        $next = $this->nextBooking($now);

        if (! $next) {
            return ['allowed' => true, 'reason' => null, 'next_booking_date' => null];
        }

        // Eventtijden zijn lokale tijd van het account; de app zelf draait in UTC.
        $start = Carbon::parse(
            $next->event_date->toDateString() . ' ' . ($next->event_start_time ?: '00:00:00'),
            $this->timezone()
        );
        $freezeFrom = $start->copy()->subHours((int) config('booth.update_freeze_hours', 24));

        $blocked = $now->greaterThanOrEqualTo($freezeFrom);

        return [
            'allowed'           => ! $blocked,
            'reason'            => $blocked ? "Boeking {$next->booking_number} op {$next->event_date->format('d-m-Y')}" : null,
            'next_booking_date' => $next->event_date->toDateString(),
        ];
    }

    /** Configuratie die de booth-app bij elke sync/heartbeat terugkrijgt. */
    public function deviceConfig(): array
    {
        return [
            'booth' => [
                'id'          => $this->id,
                'name'        => $this->displayName(),
                'asset_id'    => $this->asset_id,
                'unit_number' => $this->unit_number,
            ],
            'update' => array_merge([
                'channel'        => $this->channel(),
                'pinned_version' => $this->pinned_version,
            ], $this->updatePolicy()),
            'features'    => $this->effectiveFeatures(),
            'server_time' => now()->toIso8601String(),
        ];
    }
}
