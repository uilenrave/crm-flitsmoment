<?php

namespace App\Models;

use App\Scopes\AccountScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailLog extends Model
{
    protected $fillable = [
        'account_id', 'booking_id', 'offer_id', 'template_key',
        'recipient_email', 'subject', 'status', 'error_message',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new AccountScope());
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
