<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Eén vrijgesteld element (transparante PNG). Gedeelde bibliotheek (geen AccountScope). Elke
 * AI-vrijstelling komt als 'pending' binnen; na goedkeuring + categorie verschijnt hij in de picker.
 * Parallel aan DesignTemplate, maar volledig gescheiden zodat de achtergronden-bibliotheek ongemoeid blijft.
 */
class DesignElement extends Model
{
    protected $fillable = [
        'category_id', 'status', 'image_path', 'label', 'source',
        'source_account_id', 'source_booking_id', 'usage_count', 'sort_order', 'approved_at',
    ];

    protected $casts = [
        'usage_count' => 'integer',
        'sort_order'  => 'integer',
        'approved_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(DesignElementCategory::class, 'category_id');
    }

    public function sourceBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'source_booking_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->image_path);
    }
}
