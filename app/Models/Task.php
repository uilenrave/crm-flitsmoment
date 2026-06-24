<?php

namespace App\Models;

use App\Scopes\AccountScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $fillable = [
        'account_id', 'task_list_id', 'parent_task_id',
        'title', 'notes', 'due_date', 'completed_at', 'flagged', 'sort_order',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'completed_at' => 'datetime',
        'flagged'      => 'boolean',
    ];

    protected $appends = ['is_completed', 'due_status'];

    protected static function booted(): void
    {
        static::addGlobalScope(new AccountScope());

        static::creating(function (self $model) {
            if (! $model->account_id) {
                $model->account_id = auth()->user()?->account_id;
            }
        });
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeCompleted(Builder $q): Builder
    {
        return $q->whereNotNull('completed_at');
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->whereNull('completed_at');
    }

    public function scopeTopLevel(Builder $q): Builder
    {
        return $q->whereNull('parent_task_id');
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function taskList(): BelongsTo
    {
        return $this->belongsTo(TaskList::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id')
            ->orderBy('sort_order')
            ->orderBy('created_at');
    }

    // ── Accessors ────────────────────────────────────────────────────────────

    public function getIsCompletedAttribute(): bool
    {
        return $this->completed_at !== null;
    }

    public function getDueStatusAttribute(): ?string
    {
        if (! $this->due_date) return null;

        $today = now()->startOfDay();

        if ($this->due_date->lt($today))  return 'overdue';
        if ($this->due_date->eq($today))  return 'today';
        return 'future';
    }
}
