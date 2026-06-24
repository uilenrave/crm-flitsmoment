<?php

namespace App\Models;

use App\Scopes\AccountScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RideSignup extends Model
{
    protected $table = 'ride_signups';

    protected $fillable = [
        'account_id', 'booking_id', 'staff_id', 'role', 'response',
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

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class)->withoutGlobalScopes();
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class)->withoutGlobalScopes();
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
}
