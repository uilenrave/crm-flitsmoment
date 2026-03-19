<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class EBoekhoudenPaymentSyncService
{
    private EBoekhoudenService $ebService;

    public function __construct(EBoekhoudenService $ebService)
    {
        $this->ebService = $ebService;
    }

    /**
     * Sync payment statuses from e-boekhouden for unpaid bookings
     * Returns array with sync statistics
     */
    public function syncPaymentStatuses($accountId = null): array
    {
        $synced = 0;
        $errors = 0;
        $skipped = 0;

        try {
            // Get all unpaid bookings with e-boekhouden invoice IDs
            $query = Booking::where('payment_status', '!=', 'paid');

            if ($accountId) {
                $query->where('account_id', $accountId);
            }

            $unpaidBookings = $query
                ->whereNotNull('eboekhouden_invoice_id')
                ->where('status', '!=', 'cancelled')
                ->get();

            Log::channel('eboekhouden-sync')->info("Starting payment sync for " . $unpaidBookings->count() . " bookings");

            foreach ($unpaidBookings as $booking) {
                try {
                    // Check if invoice was already synced recently (within 5 minutes)
                    if ($booking->eboekhouden_synced_at && now()->diffInMinutes($booking->eboekhouden_synced_at) < 5) {
                        $skipped++;
                        continue;
                    }

                    // Fetch invoice status from e-boekhouden
                    $invoiceData = $this->ebService->getInvoiceStatus($booking->eboekhouden_invoice_id);

                    if (!$invoiceData) {
                        Log::channel('eboekhouden-sync')->warning("Could not fetch invoice {$booking->eboekhouden_invoice_id} for booking {$booking->id}");
                        $errors++;
                        continue;
                    }

                    // Check if invoice is marked as "betaald" (paid)
                    $isPaidInEb = isset($invoiceData['status']) && $invoiceData['status'] === 'betaald';

                    if ($isPaidInEb && $booking->payment_status !== 'paid') {
                        // Create payment record for this sync
                        Payment::create([
                            'account_id' => $booking->account_id,
                            'booking_id' => $booking->id,
                            'provider' => 'eboekhouden',
                            'provider_payment_id' => $booking->eboekhouden_invoice_id,
                            'amount' => $booking->total_price,
                            'currency' => 'EUR',
                            'description' => "E-boekhouden invoice {$booking->eboekhouden_invoice_id}",
                            'status' => 'paid',
                            'paid_at' => now(),
                            'eboekhouden_synced_at' => now(),
                        ]);

                        // Update booking payment status
                        $booking->update([
                            'payment_status' => 'paid',
                            'eboekhouden_synced_at' => now(),
                        ]);

                        // Send notification email to admin
                        $this->sendPaymentReceivedNotification($booking);

                        Log::channel('eboekhouden-sync')->info("Synced booking {$booking->id}: unpaid → paid");
                        $synced++;
                    } else {
                        // Update sync timestamp even if not paid yet
                        $booking->update(['eboekhouden_synced_at' => now()]);
                    }

                } catch (\Exception $e) {
                    Log::channel('eboekhouden-sync')->error("Error syncing booking {$booking->id}: " . $e->getMessage());
                    $errors++;
                    continue;
                }
            }

            Log::channel('eboekhouden-sync')->info("Sync completed: $synced synced, $errors errors, $skipped skipped");

        } catch (\Exception $e) {
            Log::channel('eboekhouden-sync')->error("Critical error in syncPaymentStatuses: " . $e->getMessage());
            $errors++;
        }

        return [
            'synced' => $synced,
            'errors' => $errors,
            'skipped' => $skipped,
        ];
    }

    /**
     * Send email notification to admin when payment is synced from e-boekhouden
     */
    private function sendPaymentReceivedNotification(Booking $booking): void
    {
        try {
            $booking->load('account');
            $mail = app(MailService::class);

            if ($booking->account->email) {
                $mail->send('admin_payment_synced_from_eboekhouden', $booking, $booking->account->email);
            }
        } catch (\Exception $e) {
            Log::channel('eboekhouden-sync')->error("Error sending notification for booking {$booking->id}: " . $e->getMessage());
        }
    }
}
