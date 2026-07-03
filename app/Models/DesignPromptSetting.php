<?php

namespace App\Models;

use App\Scopes\AccountScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignPromptSetting extends Model
{
    protected $fillable = ['account_id', 'key', 'label', 'prompt'];

    /** Vaste standaardprompt per onderdeel, gebruikt zolang er niets is opgeslagen. */
    public const DEFAULTS = [
        'background' => [
            'label'  => 'Achtergrond',
            'prompt' => "Ontwerp een verticale achtergrondafbeelding voor een fotostrip-mockup, in portretformaat (verhouding ongeveer 2:3). Speelse, sfeervolle stijl passend bij het thema hieronder. Houd het midden van de afbeelding rustig/leeg, want daar komt straks de fotostrip met foto's overheen. Geen tekst, geen logo's, geen mensen. Hoge resolutie, scherp, geschikt om af te drukken.\n\nThema: {beschrijving}",
        ],
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

    public static function currentPrompt(string $key): string
    {
        return static::where('key', $key)->value('prompt') ?? (self::DEFAULTS[$key]['prompt'] ?? '');
    }

    public static function label(string $key): string
    {
        return self::DEFAULTS[$key]['label'] ?? ucfirst($key);
    }
}
