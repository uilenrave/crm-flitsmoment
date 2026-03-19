<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DebugEBoekhoudenApi extends Command
{
    protected $signature = 'eboekhouden:debug-api';
    protected $description = 'Debug the e-boekhouden API responses';

    public function handle()
    {
        $this->info('Debugging e-boekhouden API...');
        $this->line('');

        // Step 1: Get session token
        $this->info('Step 1: Getting session token...');
        try {
            $response = Http::post(
                config('services.eboekhouden.api_url') . '/v1/session',
                [
                    'accessToken' => config('services.eboekhouden.api_key'),
                    'source' => config('services.eboekhouden.app_code'),
                ]
            );

            if ($response->successful()) {
                $data = $response->json();
                $token = $data['token'] ?? null;
                $this->info("✓ Session token obtained: " . substr($token, 0, 20) . "...");
                $this->line('');

                // Step 2: Fetch invoices with pagination to get all
                $this->info('Step 2: Fetching ALL invoices from /v1/invoice (with pagination)...');

                // Fetch with pagination until all invoices are retrieved
                $allInvoices = [];
                $offset = 0;
                $pageSize = 100;
                $pageNum = 1;

                while (true) {
                    $this->line("  Fetching page {$pageNum} (offset {$offset})...");

                    $invoiceResponse = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $token,
                    ])->get(
                        config('services.eboekhouden.api_url') . '/v1/invoice',
                        [
                            'offset' => $offset,
                            'limit' => $pageSize,
                        ]
                    );

                    if (!$invoiceResponse->successful()) {
                        $this->error("Failed to fetch page {$pageNum}");
                        break;
                    }

                    $pageData = $invoiceResponse->json();
                    $pageInvoices = $pageData['items'] ?? $pageData['data'] ?? [];

                    if (empty($pageInvoices)) {
                        $this->line("  No more invoices found.");
                        break;
                    }

                    $allInvoices = array_merge($allInvoices, $pageInvoices);
                    $this->line("  Found " . count($pageInvoices) . " invoices on this page (total so far: " . count($allInvoices) . ")");

                    if (count($pageInvoices) < $pageSize) {
                        break;
                    }

                    $offset += $pageSize;
                    $pageNum++;
                }

                $this->line('');

                if (!empty($allInvoices)) {
                    $this->info("Total invoices retrieved: " . count($allInvoices));
                    $this->line('');

                    // Show first and last few invoices
                    $this->info('First 3 invoices (oldest):');
                    foreach (array_slice($allInvoices, 0, 3) as $i => $invoice) {
                        $this->line("  " . ($i + 1) . ". Invoice: " . json_encode([
                            'id' => $invoice['id'] ?? null,
                            'invoiceNumber' => $invoice['invoiceNumber'] ?? null,
                            'date' => $invoice['date'] ?? null,
                        ]));
                    }
                    $this->line('');

                    // Sort by invoice number descending to find the latest
                    usort($allInvoices, function($a, $b) {
                        $numA = intval(preg_replace('/[^0-9]/', '', $a['invoiceNumber'] ?? ''));
                        $numB = intval(preg_replace('/[^0-9]/', '', $b['invoiceNumber'] ?? ''));
                        return $numB - $numA;
                    });

                    $this->info('Last 3 invoices (newest):');
                    foreach (array_slice($allInvoices, 0, 3) as $i => $invoice) {
                        $this->line("  " . ($i + 1) . ". Invoice: " . json_encode([
                            'id' => $invoice['id'] ?? null,
                            'invoiceNumber' => $invoice['invoiceNumber'] ?? null,
                            'date' => $invoice['date'] ?? null,
                        ]));
                    }
                    $this->line('');

                    $lastInvoice = $allInvoices[0];
                    $lastNumber = $lastInvoice['invoiceNumber'] ?? 'N/A';
                    $this->info("Latest invoice number: {$lastNumber}");

                    // Extract and calculate next
                    if (preg_match('/^F(\d+)$/', $lastNumber, $matches)) {
                        $nextNumber = intval($matches[1]) + 1;
                        $paddingLength = strlen($matches[1]);
                        $formattedNumber = 'F' . str_pad($nextNumber, $paddingLength, '0', STR_PAD_LEFT);
                        $this->info("Next invoice number should be: {$formattedNumber}");
                    } else {
                        $this->warn("Could not parse invoice number format: {$lastNumber}");
                    }
                } else {
                    $this->error('No invoices found');
                }
            } else {
                $this->error("Failed to get session token: {$response->status()}");
                $this->line($response->body());
            }
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            Log::error('DebugEBoekhoudenApi error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
