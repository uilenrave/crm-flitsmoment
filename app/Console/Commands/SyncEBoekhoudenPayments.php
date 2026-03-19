<?php

namespace App\Console\Commands;

use App\Services\EBoekhoudenPaymentSyncService;
use Illuminate\Console\Command;

class SyncEBoekhoudenPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'eboekhouden:sync-payments {--account= : Account ID to sync (optional, all if empty)}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Sync payment statuses from e-boekhouden for unpaid bookings';

    /**
     * Execute the console command.
     */
    public function handle(EBoekhoudenPaymentSyncService $syncService): int
    {
        $accountId = $this->option('account');
        $this->info('Starting e-boekhouden payment sync...');

        try {
            $result = $syncService->syncPaymentStatuses($accountId);

            $this->info("✓ Payment sync completed!");
            $this->info("  Synced: {$result['synced']}");
            $this->info("  Errors: {$result['errors']}");
            $this->info("  Skipped: {$result['skipped']}");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("✗ Error during sync: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
