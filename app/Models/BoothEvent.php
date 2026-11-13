<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gebeurtenis gemeld door een booth. Bewust GEEN AccountScope (wordt machine-to-machine
 * aangemaakt, zonder ingelogde user) — filter in queries altijd zelf op account_id.
 */
class BoothEvent extends Model
{
    protected $fillable = [
        'account_id', 'booth_token_id', 'type', 'app_version', 'message', 'data', 'mailed_at',
    ];

    protected $casts = [
        'data'      => 'array',
        'mailed_at' => 'datetime',
    ];

    public function booth(): BelongsTo
    {
        return $this->belongsTo(BoothToken::class, 'booth_token_id');
    }

    public function label(): string
    {
        return config("booth.events.{$this->type}.label", $this->type);
    }

    public function isOk(): bool
    {
        return (bool) config("booth.events.{$this->type}.ok", true);
    }
}
