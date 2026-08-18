<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eén geslaagde AI-generatie (achtergrond via Gemini of logo-vrijstelling via GPT/OpenAI),
 * gekoppeld aan het account dat 'm veroorzaakte. Bewust GEEN AccountScope: dit is een
 * cross-account verbruiksoverzicht voor de admin-instellingen.
 */
class AiUsageEvent extends Model
{
    public const PROVIDER_GEMINI = 'gemini';
    public const PROVIDER_OPENAI = 'openai';

    protected $fillable = ['account_id', 'provider', 'operation'];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /** Leg een generatie vast. Faalt stil: verbruiksregistratie mag nooit een generatie breken. */
    public static function record(?int $accountId, string $provider, string $operation): void
    {
        try {
            static::create([
                'account_id' => $accountId,
                'provider'   => $provider,
                'operation'  => $operation,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
