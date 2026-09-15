<?php

namespace App\Models;

use App\Scopes\AccountScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskList extends Model
{
    protected $fillable = ['account_id', 'user_id', 'title', 'color', 'sort_order'];

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Alleen top-level taken (geen subtaken), gesorteerd */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)
            ->whereNull('parent_task_id')
            ->orderBy('sort_order')
            ->orderBy('created_at');
    }

    /** Alle taken inclusief subtaken */
    public function allTasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * De lijst waar losse items in landen als er geen lijst is gekozen
     * (snelle invoer, en alles wat de CRM-sync aanmaakt).
     */
    public static function defaultFor(int $accountId): self
    {
        $list = static::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($list) return $list;

        return static::withoutGlobalScopes()->create([
            'account_id' => $accountId,
            'title'      => 'Flitsmoment',
            'color'      => '#f59e0b',
            'sort_order' => 0,
        ]);
    }

    /** Aantal openstaande taken (voor badge in sidebar) */
    public function getPendingCountAttribute(): int
    {
        return $this->hasMany(Task::class)->whereNull('completed_at')->count();
    }
}
