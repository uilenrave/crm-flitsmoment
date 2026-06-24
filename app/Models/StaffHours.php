<?php

namespace App\Models;

use App\Scopes\AccountScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffHours extends Model
{
    protected $table = 'staff_hours';

    protected $fillable = [
        'account_id', 'staff_id', 'booking_id', 'combined_with_booking_id', 'combined_with_role', 'role',
        'hours', 'hours_approved', 'km', 'status',
        'staff_note', 'admin_note',
        'submitted_at', 'approved_at', 'paid_at',
    ];

    protected $casts = [
        'hours'          => 'decimal:2',
        'hours_approved' => 'decimal:2',
        'km'             => 'decimal:1',
        'submitted_at'   => 'datetime',
        'approved_at'    => 'datetime',
        'paid_at'        => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new AccountScope());

        static::creating(function (self $model) {
            if (! $model->account_id) {
                $model->account_id = auth()->user()?->account_id;
            }
        });
    }

    /** Effective uren: goedgekeurd of origineel ingediend */
    public function getEffectiveHoursAttribute(): float
    {
        return (float) ($this->hours_approved ?? $this->hours);
    }

    /** Km-vergoeding: €0,25 per km */
    public function getKmAllowanceAttribute(): float
    {
        return round((float) ($this->km ?? 0) * 0.25, 2);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class)->withoutGlobalScopes();
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class)->withoutGlobalScopes();
    }

    public function combinedWithBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'combined_with_booking_id')->withoutGlobalScopes();
    }

    public function rolLabel(): string
    {
        return match($this->role) {
            'delivery' => 'Bezorging',
            'pickup'   => 'Ophaling',
            'handover' => 'Afgifte (To Go)',
            default    => $this->role,
        };
    }

    /** Label voor de rol van de rit waarmee deze gecombineerd is */
    public function combinedWithRoleLabel(): string
    {
        return match($this->combined_with_role) {
            'delivery' => '🚚 Bezorging',
            'pickup'   => '↩ Ophalen',
            'handover' => '🤝 Afgifte (To Go)',
            default    => '',
        };
    }
}
