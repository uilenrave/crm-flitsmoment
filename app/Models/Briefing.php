<?php

namespace App\Models;

use App\Scopes\AccountScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Briefing extends Model
{
    protected $fillable = [
        'account_id', 'staff_id', 'title', 'date_from', 'date_to', 'notes', 'generated_at',
    ];

    protected $casts = [
        'date_from'    => 'date',
        'date_to'      => 'date',
        'notes'        => 'array',
        'generated_at' => 'datetime',
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

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /** Effectieve titel — gebruikt fallback als geen titel gezet */
    public function getEffectiveTitleAttribute(): string
    {
        if ($this->title) return $this->title;
        $staff = $this->staff?->name ?? 'Medewerker';
        $range = $this->date_from?->format('d M') . ' – ' . $this->date_to?->format('d M Y');
        return "Briefing {$staff} · {$range}";
    }

    /** Haal de notitie voor een specifieke booking-role op */
    public function noteFor(int $bookingId, string $role): ?string
    {
        return $this->notes["{$bookingId}:{$role}"] ?? null;
    }
}
