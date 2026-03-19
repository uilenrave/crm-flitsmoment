<?php

namespace App\Console\Commands;

use App\Services\EBoekhoudenService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ShowEBoekhoudenLedgerAccounts extends Command
{
    protected $signature = 'eboekhouden:ledger-accounts';
    protected $description = 'Show all available ledger accounts from e-boekhouden';

    public function handle()
    {
        $this->info('Fetching ledger accounts from e-boekhouden...');
        $this->line('');

        try {
            $service = app(EBoekhoudenService::class);
            $ledgers = $service->getAllLedgerAccounts();

            if (empty($ledgers)) {
                $this->error('No ledger accounts found!');
                return;
            }

            $this->info('Available Ledger Accounts:');
            $this->line('');

            // Create a table with the ledger account information
            $headers = ['ID', 'Code', 'Name', 'Description'];
            $rows = [];

            foreach ($ledgers as $ledger) {
                $rows[] = [
                    $ledger['id'] ?? 'N/A',
                    $ledger['code'] ?? 'N/A',
                    $ledger['name'] ?? 'N/A',
                    substr($ledger['description'] ?? 'N/A', 0, 50),
                ];
            }

            $this->table($headers, $rows);

            $this->line('');
            $this->info("Total accounts: " . count($ledgers));
            $this->line('');
            $this->comment('Current setup uses ledgerId 43301467 (Photobooth verhuur) for all items.');
            $this->comment('To use a different account for extras/upsell items, provide the ID from the list above.');

        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            Log::error('ShowEBoekhoudenLedgerAccounts error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
