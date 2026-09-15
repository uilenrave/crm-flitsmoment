<?php

namespace App\Console\Commands;

use App\Services\TodoSignalService;
use Illuminate\Console\Command;

class SyncCrmTasks extends Command
{
    protected $signature   = 'taken:sync';
    protected $description = 'Zet CRM-signalen om in taken en vinkt opgeloste taken af';

    public function handle(TodoSignalService $signals): int
    {
        $result = $signals->syncAll();

        $this->info("Taken aangemaakt: {$result['aangemaakt']}");
        $this->info("Automatisch afgerond: {$result['afgerond']}");

        return self::SUCCESS;
    }
}
