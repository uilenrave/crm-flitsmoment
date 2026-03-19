<?php

namespace App\Models;

use App\Scopes\AccountScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Offer extends Model
{
    protected $fillable = [
        'account_id', 'lead_id', 'public_token', 'offer_number', 'title',
        'line_items', 'subtotal', 'discount', 'total',
        'terms_text', 'status', 'sent_at', 'accepted_at', 'expires_at',
    ];

    protected $casts = [
        'line_items' => 'array',
        'sent_at' => 'datetime',
        'accepted_at' => 'datetime',
        'expires_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new AccountScope());

        static::creating(function (self $model) {
            if (! $model->account_id) {
                $model->account_id = auth()->user()?->account_id;
            }
            if (! $model->public_token) {
                $model->public_token = Str::random(64);
            }
            if (! $model->offer_number) {
                $model->offer_number = static::generateOfferNumber($model->account_id);
            }
        });
    }

    public static function generateOfferNumber(int $accountId): string
    {
        $account = Account::find($accountId);
        $prefix  = $account ? strtoupper($account->code) : 'CRM';
        $year    = date('Y');
        $count   = static::withoutGlobalScope(AccountScope::class)
            ->where('account_id', $accountId)
            ->whereYear('created_at', $year)
            ->count() + 1;

        return "{$prefix}-O-{$year}-" . str_pad($count, 3, '0', STR_PAD_LEFT);
    }

    /** Is de offerte accepteerbaar door de klant? */
    public function isAcceptable(): bool
    {
        return $this->status === 'sent'
            && (! $this->expires_at || $this->expires_at->isFuture());
    }

    /** Voornaam uit lead naam */
    public function getFirstNameAttribute(): string
    {
        $name = $this->lead?->name ?? '';
        return explode(' ', $name)[0];
    }

    /** Geformateerd totaalbedrag */
    public function getFormattedTotalAttribute(): string
    {
        return '€ ' . number_format((float) $this->total, 2, ',', '.');
    }

    /** Status label in het Nederlands */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft'    => 'Concept',
            'sent'     => 'Verzonden',
            'accepted' => 'Geaccepteerd',
            'expired'  => 'Verlopen',
            'declined' => 'Afgewezen',
            default    => $this->status,
        };
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class)->withoutGlobalScope(AccountScope::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function booking(): HasOne
    {
        return $this->hasOne(Booking::class)->withoutGlobalScope(AccountScope::class);
    }
}
