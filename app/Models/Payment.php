<?php

namespace App\Models;

use App\Scopes\AccountScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'account_id', 'booking_id', 'provider', 'provider_payment_id',
        'amount', 'currency', 'description', 'status', 'paid_at', 'raw_payload', 'eboekhouden_synced_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'eboekhouden_synced_at' => 'datetime',
        'raw_payload' => 'array',
        'amount' => 'decimal:2',
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

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class)->withoutGlobalScope(AccountScope::class);
    }
}
