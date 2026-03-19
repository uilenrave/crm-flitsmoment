<?php

namespace App\Models;

use App\Scopes\AccountScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Booking extends Model
{
    protected $fillable = [
        'account_id', 'lead_id', 'offer_id',
        'booking_number', 'booking_type',
        'customer_name', 'customer_email', 'customer_phone', 'customer_type', 'company_name', 'payment_method',
        'event_date', 'event_end_date', 'is_multi_day', 'event_start_time', 'event_end_time',
        'delivery_at', 'pickup_at',
        'customer_pickup_at', 'customer_return_at',
        'event_location', 'event_address', 'event_postcode', 'event_city',
        'event_notes',
        'strip_status', 'strip_design_url', 'strip_notes', 'strip_feedback', 'strip_feedback_at', 'strip_version', 'strip_comments', 'strip_designs', 'strip_intake_data', 'gallery_url',
        'base_price', 'total_price', 'amount_total',
        'status', 'payment_status', 'public_token', 'portal_enabled',
        'confirmed_at', 'cancelled_at', 'completed_at',
        'eboekhouden_invoice_id', 'eboekhouden_invoice_number', 'eboekhouden_status', 'eboekhouden_synced_at', 'eboekhouden_relation_id',
        'intake_data', 'intake_current_step', 'intake_completed', 'intake_completed_at',
        'strip_format', 'pickup_preference', 'pickup_contact_person', 'pickup_contact_time',
        'review_mail_sent_at',
    ];

    protected $casts = [
        'event_date'          => 'date',
        'event_end_date'      => 'date',
        'is_multi_day'        => 'boolean',
        'delivery_at'         => 'datetime',
        'pickup_at'           => 'datetime',
        'customer_pickup_at'  => 'datetime',
        'customer_return_at'  => 'datetime',
        'portal_enabled'      => 'boolean',
        'confirmed_at'        => 'datetime',
        'cancelled_at'        => 'datetime',
        'completed_at'        => 'datetime',
        'strip_feedback_at'   => 'datetime',
        'eboekhouden_synced_at' => 'datetime',
        'intake_data'           => 'array',
        'strip_comments'        => 'array',
        'strip_designs'         => 'array',
        'strip_intake_data'     => 'array',
        'intake_completed'      => 'boolean',
        'intake_completed_at'   => 'datetime',
        'review_mail_sent_at'   => 'datetime',
        'base_price'          => 'decimal:2',
        'total_price'         => 'decimal:2',
        'amount_total'        => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new AccountScope());

        static::creating(function (self $model) {
            if (! $model->account_id) {
                $model->account_id = session('account_id');
            }
            if (! $model->booking_number) {
                $model->booking_number = static::generateBookingNumber($model->account_id);
            }
            if (! $model->public_token) {
                $model->public_token = Str::random(48);
            }
        });

        // Auto-set is_multi_day based on event_end_date
        static::saving(function (self $model) {
            if ($model->event_end_date && $model->event_date) {
                $model->is_multi_day = $model->event_date->diffInDays($model->event_end_date) > 0;
            } else {
                $model->is_multi_day = false;
            }
        });
    }

    public static function generateBookingNumber(int $accountId): string
    {
        $account = Account::find($accountId);
        $prefix  = $account ? strtoupper($account->code) : 'CRM';
        $year    = date('Y');
        $count   = static::withoutGlobalScope(AccountScope::class)
            ->where('account_id', $accountId)
            ->whereYear('created_at', $year)
            ->count() + 1;

        return "{$prefix}-B-{$year}-" . str_pad($count, 3, '0', STR_PAD_LEFT);
    }

    /** Zet telefoonnummer om naar WhatsApp-formaat (+31...) */
    public function getWhatsappNumberAttribute(): ?string
    {
        if (! $this->customer_phone) return null;

        $nr = preg_replace('/\D/', '', $this->customer_phone);

        if (str_starts_with($nr, '00')) {
            $nr = '+' . substr($nr, 2);
        } elseif (str_starts_with($nr, '0')) {
            $nr = '+31' . substr($nr, 1);
        } elseif (! str_starts_with($nr, '+')) {
            $nr = '+' . $nr;
        }

        return $nr;
    }

    /** Calculate duration in days for multi-day bookings */
    public function getEventDurationDaysAttribute(): int
    {
        if (! $this->event_end_date || ! $this->event_date) {
            return 1;
        }

        return $this->event_date->diffInDays($this->event_end_date) + 1;
    }

    /** Get human-readable duration format */
    public function getDurationFormattedAttribute(): string
    {
        $days = $this->event_duration_days;

        if ($days === 1) {
            return '1 dag';
        }

        return "{$days} dagen";
    }

    /** Bereken openstaand bedrag (total_price - betaald) */
    public function getAmountDueAttribute(): float
    {
        $betaald = $this->payments()
            ->where('status', 'paid')
            ->sum('amount');

        return max(0, (float) $this->total_price - (float) $betaald);
    }

    /** Should the intake form be shown in the portal? */
    public function getShowIntakeAttribute(): bool
    {
        return $this->status === 'confirmed'
            && !$this->intake_completed
            && $this->event_date
            && $this->event_date->isFuture();
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class)->withoutGlobalScope(AccountScope::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class)->withoutGlobalScope(AccountScope::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->withoutGlobalScope(AccountScope::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }
}
