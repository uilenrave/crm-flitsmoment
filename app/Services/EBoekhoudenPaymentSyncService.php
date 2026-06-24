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
            // Get all unpaid bookings that have either an invoice ID or invoice number
            $query = Booking::where('payment_status', '!=', 'paid');

            if ($accountId) {
                $query->where('account_id', $accountId);
            }

            $unpaidBookings = $query
                ->where(function ($q) {
                    $q->whereNotNull('eboekhouden_invoice_id')
                      ->orWhereNotNull('eboekhouden_invoice_number');
                })
                ->where('status', '!=', 'cancelled')
                ->get();

            Log::channel('eboekhouden-sync')->info("Starting payment sync for " . $unpaidBookings->count() . " bookings");

            foreach ($unpaidBookings as $booking) {
                try {
                    $result = $this->syncSingleBooking($booking);

                    if ($result['status'] === 'paid') {
                        $synced++;
                        Log::channel('eboekhouden-sync')->info("Synced booking {$booking->id}: unpaid → paid");
                    } elseif ($result['status'] === 'error') {
                        $errors++;
                        Log::channel('eboekhouden-sync')->warning("Error syncing booking {$booking->id}: {$result['message']}");
                    }
                } catch (\Exception $e) {
                    Log::channel('eboekhouden-sync')->error("Error syncing booking {$booking->id}: " . $e->getMessage());
                    $errors++;
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
     * Sync de betaalstatus van één boeking
     * Zoekt het factuur-ID op via factuurnummer als dat nog ontbreekt
     * Geeft een array terug met 'status' (paid/unpaid/not_found/error) en 'message'
     */
    public function syncSingleBooking(Booking $booking): array
    {
        try {
            $booking->loadMissing('account');

            // Zorg dat we een API key hebben
            $apiKey = $booking->account->getEBoekhoudenApiKey();
            if (!$apiKey) {
                return ['status' => 'error', 'message' => 'Geen e-boekhouden API key geconfigureerd.'];
            }

            $this->ebService->withApiKey($apiKey);

            $invoiceId = $booking->eboekhouden_invoice_id;

            // Als er geen ID is maar wel een handmatig ingevuld factuurnummer, zoek het ID op
            if (!$invoiceId && $booking->eboekhouden_invoice_number) {
                $invoice = $this->ebService->findInvoiceByNumber($booking->eboekhouden_invoice_number);
                if (!$invoice) {
                    return [
                        'status'  => 'not_found',
                        'message' => "Factuur '{$booking->eboekhouden_invoice_number}' niet gevonden in e-boekhouden.",
                    ];
                }
                $invoiceId = $invoice['id'] ?? null;
                if ($invoiceId) {
                    // Sla het ID op zodat we het de volgende keer niet opnieuw hoeven te zoeken
                    $booking->update(['eboekhouden_invoice_id' => $invoiceId]);
                }
            }

            if (!$invoiceId) {
                return ['status' => 'error', 'message' => 'Geen factuur-ID beschikbaar.'];
            }

            $invoiceData = $this->ebService->getInvoiceStatus($invoiceId);
            if (!$invoiceData) {
                return ['status' => 'error', 'message' => 'Kon factuurstatus niet ophalen van e-boekhouden.'];
            }

            // Update sync timestamp
            $booking->update(['eboekhouden_synced_at' => now()]);

            // e-boekhouden kan verschillende veldnamen gebruiken voor betaalstatus
            $isPaid = $this->isBetaald($invoiceData);

            if ($isPaid && $booking->payment_status !== 'paid') {
                // Betalingsrecord aanmaken
                Payment::create([
                    'account_id'            => $booking->account_id,
                    'booking_id'            => $booking->id,
                    'provider'              => 'eboekhouden',
                    'provider_payment_id'   => $invoiceId,
                    'amount'                => $booking->total_price,
                    'currency'              => 'EUR',
                    'description'           => "e-boekhouden factuur {$booking->eboekhouden_invoice_number}",
                    'status'                => 'paid',
                    'paid_at'               => now(),
                    'eboekhouden_synced_at' => now(),
                ]);

                $booking->update(['payment_status' => 'paid']);

                Log::info("e-boekhouden sync: boeking {$booking->id} gemarkeerd als betaald");
                return ['status' => 'paid', 'message' => 'Betaling bevestigd — boeking bijgewerkt naar "betaald".'];
            }

            if ($isPaid) {
                return ['status' => 'paid', 'message' => 'Factuur is betaald (was al bijgewerkt).'];
            }

            return ['status' => 'unpaid', 'message' => 'Factuur nog niet betaald in e-boekhouden.'];

        } catch (\Exception $e) {
            Log::error("e-boekhouden syncSingleBooking exception: " . $e->getMessage(), ['booking_id' => $booking->id]);
            return ['status' => 'error', 'message' => 'Fout: ' . $e->getMessage()];
        }
    }

    /**
     * Bepaal of een factuur betaald is.
     * EBoekhoudenService::getInvoiceStatus() voegt een 'isPaid' veld toe
     * door de mutaties te controleren (type 3 = ontvangst = betaald).
     */
    private function isBetaald(array $invoiceData): bool
    {
        return (bool) ($invoiceData['isPaid'] ?? false);
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
