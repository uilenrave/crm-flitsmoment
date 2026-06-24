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
        // Dagelijkse lead follow-up herinneringen om 08:00
        $schedule->command('leads:send-followup-reminders')
            ->dailyAt('08:00')
            ->runInBackground()
            ->name('lead-followup-reminders');

        // Sync payment statuses from e-boekhouden once a day at 07:00
        $schedule->command('eboekhouden:sync-payments')
            ->dailyAt('07:00')
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
