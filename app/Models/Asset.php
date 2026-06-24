<?php

namespace App\Models;

use App\Scopes\AccountScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    protected $fillable = [
        'account_id', 'name', 'category', 'description', 'price', 'stock', 'ignore_stock', 'is_active',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'ignore_stock' => 'boolean',
        'price'     => 'decimal:2',
    ];

    // Labels voor categorieën
    public static array $categoryLabels = [
        'photobooth'  => 'Photobooth',
        'background'  => 'Achtergrond',
        'prop_box'    => 'Kist met attributen',
        'extra'       => 'Extra',
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

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function bookingItems(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    public function leads(): BelongsToMany
    {
        return $this->belongsToMany(Lead::class, 'lead_assets')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::$categoryLabels[$this->category] ?? $this->category;
    }
}
