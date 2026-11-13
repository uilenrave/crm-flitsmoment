<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\BoothToken;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BoothTokenCreate extends Command
{
    protected $signature = 'booth:token {name : Naam van de machine, bijv. "Booth 3"} {--account= : Account-id (verplicht als er meerdere accounts zijn)} {--unit= : Optioneel unit-nummer}';

    protected $description = 'Maakt een booth-API-token aan en toont de platte waarde één keer.';

    public function handle(): int
    {
        // Account bepalen
        $accountId = $this->option('account');
        if (! $accountId) {
            $accounts = Account::query()->pluck('name', 'id');
            if ($accounts->count() === 1) {
                $accountId = $accounts->keys()->first();
            } else {
                $this->error('Geef --account op (meerdere accounts aanwezig): ' . $accounts->map(fn ($n, $id) => "$id=$n")->implode(', '));
                return self::FAILURE;
            }
        }
        if (! Account::whereKey($accountId)->exists()) {
            $this->error("Account {$accountId} bestaat niet.");
            return self::FAILURE;
        }

        $unit = $this->option('unit');
        $unit = ($unit === null || $unit === '') ? null : (int) $unit;

        // Plat token → alleen de sha256-hash opslaan
        $plain = Str::random(64);

        $token = BoothToken::create([
            'account_id'  => $accountId,
            'name'        => $this->argument('name'),
            'unit_number' => $unit,
            'token'       => BoothToken::hashToken($plain),
        ]);

        $this->info("Booth-token aangemaakt (id {$token->id}) voor account {$accountId}, naam \"{$token->name}\"" . ($unit !== null ? ", unit {$unit}" : '') . '.');
        $this->newLine();
        $this->warn('Bewaar dit token nu — het wordt maar één keer getoond:');
        $this->line($plain);

        return self::SUCCESS;
    }
}
