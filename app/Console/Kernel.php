<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Sync payment statuses from e-boekhouden every 15 minutes
        $schedule->command('eboekhouden:sync-payments')
            ->everyFifteenMinutes()
            ->runInBackground()
            ->name('eboekhouden-payment-sync')
            ->onFailure(function () {
                \Log::error('E-boekhouden payment sync failed');
            });
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
