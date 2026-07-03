<?php

namespace App\Models;

use App\Scopes\AccountScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DesignMask extends Model
{
    protected $fillable = ['account_id', 'label', 'path'];

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

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
