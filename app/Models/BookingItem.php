<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingItem extends Model
{
    protected $fillable = [
        'booking_id', 'asset_id',
        'name_snapshot', 'price_snapshot', 'quantity', 'unit_number',
    ];

    protected $casts = [
        'price_snapshot' => 'decimal:2',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function getLinetotalAttribute(): float
    {
        return (float) $this->price_snapshot * $this->quantity;
    }
}
