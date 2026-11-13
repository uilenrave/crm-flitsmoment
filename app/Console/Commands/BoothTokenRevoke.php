<?php

namespace App\Console\Commands;

use App\Models\BoothToken;
use Illuminate\Console\Command;

class BoothTokenRevoke extends Command
{
    protected $signature = 'booth:token:revoke {id : Id van het booth-token}';

    protected $description = 'Trekt een booth-API-token in (revoked_at = nu).';

    public function handle(): int
    {
        $token = BoothToken::find($this->argument('id'));
        if (! $token) {
            $this->error("Booth-token {$this->argument('id')} niet gevonden.");
            return self::FAILURE;
        }
        if ($token->isRevoked()) {
            $this->warn("Token {$token->id} (\"{$token->name}\") was al ingetrokken op {$token->revoked_at}.");
            return self::SUCCESS;
        }

        $token->forceFill(['revoked_at' => now()])->save();
        $this->info("Booth-token {$token->id} (\"{$token->name}\") ingetrokken.");

        return self::SUCCESS;
    }
}
