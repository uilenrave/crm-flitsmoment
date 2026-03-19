<?php

namespace App\Console\Commands;

use App\Services\EBoekhoudenService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestEBoekhoudenInvoiceNumber extends Command
{
    protected $signature = 'eboekhouden:test-invoice-number';
    protected $description = 'Test the e-boekhouden invoice number generation';

    public function handle()
    {
        $this->info('Testing e-boekhouden invoice number generation...');

        try {
            // Create service instance
            $service = app(EBoekhoudenService::class);

            // Get next invoice number using reflection to call private method
            $reflection = new \ReflectionClass($service);
            $method = $reflection->getMethod('getNextInvoiceNumber');
            $method->setAccessible(true);

            $nextNumber = $method->invoke($service);

            $this->info("Next invoice number: {$nextNumber}");
            $this->line("Check your logs at storage/logs/laravel.log for detailed debugging info.");

        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            Log::error('TestEBoekhoudenInvoiceNumber error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
