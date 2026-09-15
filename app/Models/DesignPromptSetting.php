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
        'anders'        => 'Anders',
    ];

    /** Event-types waarbij het optionele logo-uploadveld getoond wordt. */
    public const LOGO_EVENT_TYPES = ['bruiloft', 'bedrijfsfeest'];

    /** Vaste standaardprompt per onderdeel, gebruikt zolang er niets is opgeslagen. */
    public const DEFAULTS = [
        'background' => [
            'label'  => 'Achtergrond',
            'prompt' => "Ontwerp een sfeervolle, naadloze decoratieve achtergrond in staand portretformaat (verhouding ongeveer 2:3), als een geschilderde achtergronddoek of behang passend bij het thema hieronder. Het is één doorlopend, zacht beeld dat van rand tot rand doorloopt. Zet de versiering vooral langs de boven- en onderrand en laat die subtiel naar binnen uitvloeien; houd het midden zacht en open met rustige, lichte kleuren, zodat er later foto's overheen geplaatst kunnen worden. Het is puur achtergrond: geen omlijsting, geen uitgesneden vakken of rechthoeken, geen lege kaders, geen strepen of scheidingslijnen — alsof je naar een egale geschilderde muur kijkt. Geen tekst, geen logo's, geen mensen. Hoge resolutie, scherp, geschikt om af te drukken.\n\nThema: {beschrijving}",
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
     *
     * $accountId is verplicht in contexten zonder ingelogde gebruiker (bijv. het klantportaal
     * op basis van een token) — de gewone AccountScope filtert dan namelijk niets, omdat die
     * op auth()->user() leunt. Bij een auth-context (admin) mag dit weg (ambient scope volstaat).
     */
    public static function currentPrompt(string $key, ?string $eventType = null, ?int $accountId = null): string
    {
        $query = $accountId
            ? static::withoutGlobalScope(AccountScope::class)->where('account_id', $accountId)
            : static::query();

        if ($eventType) {
            $specific = (clone $query)->where('key', $key)->where('event_type', $eventType)->value('prompt');
            if ($specific !== null) {
                return $specific;
            }
        }

        $general = (clone $query)->where('key', $key)->whereNull('event_type')->value('prompt');

        return $general ?? (self::DEFAULTS[$key]['prompt'] ?? '');
    }

    public static function label(string $key): string
    {
        return self::DEFAULTS[$key]['label'] ?? ucfirst($key);
    }
}
