<?php

namespace App\Models;

use App\Scopes\AccountScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignSession extends Model
{
    protected $fillable = [
        'account_id', 'booking_id', 'event_type', 'input', 'state',
        'background_generations', 'logo_cutouts', 'finished_at',
    ];

    protected $casts = [
        'state'                   => 'array',
        'background_generations'  => 'integer',
        'logo_cutouts'            => 'integer',
        'finished_at'             => 'datetime',
    ];

    /** Aantal keer dat een onderdeel gegenereerd mag worden vóór de "neem contact op"-melding. */
    public const MAX_BACKGROUND_GENERATIONS = 20;
    public const MAX_LOGO_CUTOUTS = 20;

    protected static function booted(): void
    {
        static::addGlobalScope(new AccountScope());

        static::creating(function (self $model) {
            if (! $model->account_id) {
                $model->account_id = auth()->user()?->account_id;
            }
            if (! $model->state) {
                $model->state = [];
            }
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class)->withoutGlobalScope(AccountScope::class);
    }

    public function backgroundLimitReached(): bool
    {
        return $this->background_generations >= self::MAX_BACKGROUND_GENERATIONS;
    }

    public function logoLimitReached(): bool
    {
        return $this->logo_cutouts >= self::MAX_LOGO_CUTOUTS;
    }
}
