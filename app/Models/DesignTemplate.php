<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Eén fotostrip-achtergrond-template. Gedeelde bibliotheek (geen AccountScope). Elke gegenereerde
 * achtergrond komt als 'pending' binnen; na goedkeuring + categorie verschijnt hij in de picker.
 */
class DesignTemplate extends Model
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
        return $this->belongsTo(DesignTemplateCategory::class, 'category_id');
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
