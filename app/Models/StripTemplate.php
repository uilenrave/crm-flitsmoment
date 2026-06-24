<?php

namespace App\Models;

use App\Scopes\AccountScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class StripTemplate extends Model
{
    protected $fillable = [
        'account_id', 'number', 'name', 'theme', 'format', 'image_path', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'number'     => 'integer',
        'sort_order' => 'integer',
    ];

    /** Klantvriendelijke labels per thema */
    public const THEME_LABELS = [
        'bruiloft'      => 'Bruiloft',
        'bedrijfsfeest' => 'Bedrijfsfeest',
        'verjaardag'    => 'Verjaardag',
        'kerst'         => 'Kerst',
    ];

    /** Klantvriendelijke labels per formaat */
    public const FORMAT_LABELS = [
        'strips_5x15' => '2× fotostrip 5×15 cm',
        'photo_10x15' => '1 foto 10×15 cm',
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

    /** Volledige URL naar de template-afbeelding. */
    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) return null;
        return Storage::disk('public')->url($this->image_path);
    }

    public function getThemeLabelAttribute(): string
    {
        return self::THEME_LABELS[$this->theme] ?? $this->theme;
    }

    public function getFormatLabelAttribute(): string
    {
        return self::FORMAT_LABELS[$this->format] ?? $this->format;
    }
}
