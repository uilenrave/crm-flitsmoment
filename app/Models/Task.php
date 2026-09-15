<?php

namespace App\Models;

use App\Scopes\AccountScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    /** Soorten items — bepalen in welk menu-item van de takenapp ze verschijnen */
    public const TYPE_TAAK  = 'taak';
    public const TYPE_KOPEN = 'kopen';
    public const TYPE_LEREN = 'leren';

    /** Herkomst */
    public const SOURCE_HANDMATIG = 'handmatig';
    public const SOURCE_CRM       = 'crm';
    public const SOURCE_MAIL      = 'mail';

    protected $fillable = [
        'account_id', 'owner_id', 'task_list_id', 'parent_task_id',
        'title', 'notes', 'due_date', 'snoozed_until', 'completed_at', 'flagged', 'sort_order',
        'type', 'source', 'source_type', 'source_id', 'signal_key',
        'price_cents', 'vendor', 'url', 'kind',
    ];

    protected $casts = [
        'due_date'      => 'date',
        'snoozed_until' => 'date',
        'completed_at'  => 'datetime',
        'flagged'       => 'boolean',
        'price_cents'   => 'integer',
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

    public function scopeOfType(Builder $q, string $type): Builder
    {
        return $q->where('type', $type);
    }

    /** Niet weggelegd tot een datum in de toekomst */
    public function scopeNotSnoozed(Builder $q): Builder
    {
        return $q->where(function (Builder $q) {
            $q->whereNull('snoozed_until')->orWhere('snoozed_until', '<=', now()->toDateString());
        });
    }

    /** Alles wat vandaag aandacht vraagt: te laat, vandaag, of gevlagd */
    public function scopeDueToday(Builder $q): Builder
    {
        return $q->where(function (Builder $q) {
            $q->whereDate('due_date', '<=', now()->toDateString())
              ->orWhere('flagged', true);
        });
    }

    public function scopeUpcoming(Builder $q, int $days = 7): Builder
    {
        return $q->whereNotNull('due_date')
            ->whereDate('due_date', '>', now()->toDateString())
            ->whereDate('due_date', '<=', now()->addDays($days)->toDateString());
    }

    // ── Relaties ─────────────────────────────────────────────────────────────

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

    /** Prijs als "€ 89,00" — leeg als er geen bedrag staat */
    public function getPriceLabelAttribute(): ?string
    {
        if ($this->price_cents === null) return null;

        return '€ ' . number_format($this->price_cents / 100, 2, ',', '.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isFromCrm(): bool
    {
        return $this->source === self::SOURCE_CRM;
    }

    /**
     * Link naar de CRM-pagina waar dit item vandaan komt.
     *
     * Bewust op basis van config('app.url'): de takenapp draait op een eigen
     * domein, dus url() zou hier naar todo.flitsmoment.nl wijzen.
     */
    public function sourceUrl(): ?string
    {
        if (! $this->source_type || ! $this->source_id) return null;

        $base = rtrim(config('app.url'), '/');

        return match ($this->source_type) {
            'booking', 'shift' => $base . '/bookings/' . $this->source_id,
            'offer'            => $base . '/offers/' . $this->source_id,
            default            => null,
        };
    }

    public function sourceLabel(): ?string
    {
        return match ($this->source_type) {
            'booking' => 'boeking',
            'offer'   => 'offerte',
            'shift'   => 'shift',
            default   => $this->source === self::SOURCE_MAIL ? 'mail' : null,
        };
    }
}
