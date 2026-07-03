<?php

namespace App\Models;

use App\Scopes\AccountScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignPromptSetting extends Model
{
    protected $fillable = ['account_id', 'key', 'event_type', 'label', 'prompt'];

    /** Type event dat je selecteert in de generator — bepaalt welke prompt-variant wordt gebruikt. */
    public const EVENT_TYPES = [
        'bruiloft'      => 'Bruiloft',
        'verjaardag'    => 'Verjaardag',
        'bedrijfsfeest' => 'Bedrijfsfeest',
    ];

    /** Event-types waarbij het optionele logo-uploadveld getoond wordt. */
    public const LOGO_EVENT_TYPES = ['bruiloft', 'bedrijfsfeest'];

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

    /**
     * Prompt voor een onderdeel + event-type. Valt terug op de algemene (event_type=null)
     * prompt van dit onderdeel, en daarna op de ingebouwde standaardtekst.
     */
    public static function currentPrompt(string $key, ?string $eventType = null): string
    {
        if ($eventType) {
            $specific = static::where('key', $key)->where('event_type', $eventType)->value('prompt');
            if ($specific !== null) {
                return $specific;
            }
        }

        $general = static::where('key', $key)->whereNull('event_type')->value('prompt');

        return $general ?? (self::DEFAULTS[$key]['prompt'] ?? '');
    }

    public static function label(string $key): string
    {
        return self::DEFAULTS[$key]['label'] ?? ucfirst($key);
    }
}
