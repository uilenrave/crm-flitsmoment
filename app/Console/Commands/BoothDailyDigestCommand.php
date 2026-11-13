<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\BoothToken;
use App\Services\BoothFleetService;
use Illuminate\Console\Command;

class BoothDailyDigestCommand extends Command
{
    protected $signature = 'booth:daily-digest {--account= : Alleen dit account-id}';

    protected $description = 'Mailt de dagelijkse samenvatting van alle booths naar elk account met gekoppelde booths.';

    public function handle(BoothFleetService $fleet): int
    {
        $accountIds = BoothToken::whereNull('revoked_at')
            ->when($this->option('account'), fn ($q, $id) => $q->where('account_id', $id))
            ->distinct()
            ->pluck('account_id');

        foreach (Account::whereIn('id', $accountIds)->get() as $account) {
            try {
                $sent = $fleet->sendDailyDigest($account);
                $this->line(($sent ? 'Verzonden' : 'Overgeslagen') . ": {$account->name}");
            } catch (\Throwable $e) {
                $this->error("Mislukt voor {$account->name}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
