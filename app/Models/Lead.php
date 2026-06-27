<?php

namespace App\Models;

use App\Scopes\AccountScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    protected $fillable = [
        'account_id', 'lead_number', 'name', 'email', 'phone',
        'event_date', 'event_start_time', 'event_end_time',
        'event_location', 'event_address', 'event_postcode', 'event_city', 'notes',
        'status_id', 'source_id', 'event_type_id', 'booking_type', 'assigned_to',
        'archived_at', 'archive_reason',
        'total_price', 'follow_up_at', 'conversion_chance',
    ];

    protected $casts = [
        'event_date'        => 'date',
        'follow_up_at'      => 'date',
        'archived_at'       => 'datetime',
        'total_price'       => 'decimal:2',
        'conversion_chance' => 'integer',
    ];

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function archiveer(string $reden): void
    {
        $this->archived_at    = now();
        $this->archive_reason = $reden;
        $this->save(); // sla alle dirty attributes op (inclusief status_id)
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new AccountScope());

        static::creating(function (self $model) {
            if (! $model->account_id) {
                $model->account_id = auth()->user()?->account_id;
            }
            if (! $model->lead_number) {
                // Retry bij race condition (gelijktijdige requests)
                $attempts = 0;
                do {
                    $model->lead_number = static::generateLeadNumber($model->account_id);
                    $attempts++;
                    $exists = static::withoutGlobalScope(AccountScope::class)
                        ->where('account_id', $model->account_id)
                        ->where('lead_number', $model->lead_number)
                        ->exists();
                } while ($exists && $attempts < 10);
            }
        });
    }

    public static function generateLeadNumber(int $accountId): string
    {
        $account = Account::find($accountId);
        $prefix  = $account ? strtoupper($account->code) : 'CRM';
        $year    = date('Y');

        // Gebruik max() van het hoogste nummer ipv count() —
        // voorkomt duplicaten bij verwijderde leads of gelijktijdige requests.
        $last = static::withoutGlobalScope(AccountScope::class)
            ->where('account_id', $accountId)
            ->whereYear('created_at', $year)
            ->where('lead_number', 'like', "{$prefix}-{$year}-%")
            ->max('lead_number');

        if ($last) {
            $lastNum = (int) substr($last, strrpos($last, '-') + 1);
            $next    = $lastNum + 1;
        } else {
            $next = 1;
        }

        return "{$prefix}-{$year}-" . str_pad($next, 3, '0', STR_PAD_LEFT);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(LeadStatus::class, 'status_id')->withoutGlobalScope(AccountScope::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'source_id')->withoutGlobalScope(AccountScope::class);
    }

    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class, 'event_type_id')->withoutGlobalScope(AccountScope::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->withoutGlobalScope(AccountScope::class)->latest();
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class)->withoutGlobalScope(AccountScope::class);
    }

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'lead_assets')
            ->withPivot('quantity', 'price')
            ->withTimestamps();
    }
}
