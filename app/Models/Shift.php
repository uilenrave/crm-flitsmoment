<?php

namespace App\Models;

use App\Scopes\AccountScope;
use App\Services\ShiftRouteService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    /** Standaard startlocatie (depot). Los van Account.origin_address (dat is voor reiskosten). */
    public const DEPOT_ADDRESS = 'Ravenswade 132, 3439 LD Nieuwegein';

    protected $fillable = [
        'account_id', 'staff_id', 'staff_id_2', 'shift_date', 'start_at', 'start_manual',
        'start_address', 'end_address', 'notes',
        'route_cache', 'total_minutes', 'end_at',
    ];

    protected $casts = [
        'shift_date'    => 'date',
        'start_at'      => 'datetime',
        'end_at'        => 'datetime',
        'start_manual'  => 'boolean',
        'route_cache'   => 'array',
        'total_minutes' => 'integer',
    ];

    protected static function booted(): void
    {
        // Multi-tenant: alles op het eigen account
        static::addGlobalScope('account', function (Builder $q) {
            if (auth()->check() && auth()->user()->account_id) {
                $q->where('shifts.account_id', auth()->user()->account_id);
            }
        });

        static::creating(function (Shift $shift) {
            if (! $shift->account_id && auth()->check()) {
                $shift->account_id = auth()->user()->account_id;
            }
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class)->withoutGlobalScope(AccountScope::class);
    }

    public function staff2(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id_2')->withoutGlobalScope(AccountScope::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(ShiftJob::class)->orderBy('sort_order');
    }

    /** Startlocatie: eigen adres of anders het standaard depot (Ravenswade 132). */
    public function startLocation(): ?string
    {
        return $this->start_address ?: self::DEPOT_ADDRESS;
    }

    /** Zet de shift-medewerker op de oude staff-velden van alle gekoppelde boekingen (bridge). */
    public function syncLegacyStaffAssignments(): void
    {
        foreach ($this->jobs as $job) {
            $job->setRelation('shift', $this);
            $job->syncLegacyStaff();
        }
    }

    /** (Her)bereken de route + tijdlijn en cache het resultaat. */
    public function recompute(?ShiftRouteService $service = null): array
    {
        $service ??= app(ShiftRouteService::class);
        $result = $service->compute($this);

        $fill = [
            'route_cache'   => $result,
            'total_minutes' => $result['total_minutes'] ?? null,
            'end_at'        => $result['end_at'] ?? null,
        ];

        // Automatische starttijd (terugberekend vanaf de eerste klus) persisteren zolang de
        // gebruiker de starttijd niet handmatig heeft vastgezet.
        if (! $this->start_manual && ! empty($result['start_at'])) {
            $fill['start_at'] = \Illuminate\Support\Carbon::parse($result['start_at']);
        }

        $this->forceFill($fill)->save();

        return $result;
    }
}
