<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Scopes\AccountScope;
use App\Services\MailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IntakeController extends Controller
{
    public function show(string $token): View|\Illuminate\Http\RedirectResponse
    {
        $booking = $this->findBooking($token);

        if ($booking->intake_completed) {
            return redirect()->route('portal.show', $token);
        }

        return view('portal.intake', compact('booking'));
    }

    public function saveStep(Request $request, string $token, int $step): JsonResponse
    {
        $booking = $this->findBooking($token);

        // Stap 1 (fotostrip) is verwijderd — alleen 2 (bezorging) en 3 (ophalen/to go) zijn nog geldig
        if ($step < 2 || $step > 3) {
            return response()->json(['error' => 'Ongeldig stapnummer'], 422);
        }

        $validated = $this->validateStep($request, $step, $booking);

        // Merge into intake_data JSON
        $intakeData = $booking->intake_data ?? [];
        $intakeData["step_{$step}"] = array_merge(
            $validated,
            ['answered_at' => now()->toISOString()]
        );

        $updateData = [
            'intake_data'         => $intakeData,
            'intake_current_step' => max($booking->intake_current_step ?? 0, $step),
        ];

        // Sync denormalized columns per stap
        if ($step === 3) {
            if ($booking->booking_type !== 'to_go') {
                // Full service: ophaalvoorkeur opslaan
                $updateData['pickup_preference']     = $validated['pickup_preference'] ?? null;
                $updateData['pickup_contact_person'] = $validated['pickup_contact_person'] ?? null;
                $updateData['pickup_contact_time']   = $validated['pickup_time'] ?? null;
            } else {
                // To Go: als beide tijden 10:00 zijn → direct goedkeuren
                $pickupTime = $validated['pickup_time'] ?? '10:00';
                $returnTime = $validated['return_time'] ?? '10:00';
                $pickupDate = $validated['pickup_date'] ?? null;
                $returnDate = $validated['return_date'] ?? null;

                if ($pickupTime === '10:00' && $returnTime === '10:00' && $pickupDate && $returnDate) {
                    $updateData['customer_pickup_at']       = $pickupDate . ' 10:00';
                    $updateData['customer_return_at']       = $returnDate . ' 10:00';
                    $updateData['customer_pickup_approved'] = true;
                }
            }
        }

        // Intake afronden
        $isComplete = $step === 3;
        if ($isComplete) {
            $updateData['intake_completed']    = true;
            $updateData['intake_completed_at'] = now();
        }

        $booking->update($updateData);

        if ($isComplete) {
            $this->notifyIntakeCompleted($booking);
        }

        return response()->json([
            'success'   => true,
            'next_step' => $isComplete ? null : $step + 1,
            'completed' => $isComplete,
        ]);
    }

    /** Approve pickup + return voor To Go boeking */
    public function approvePickup(Request $request, string $token): JsonResponse
    {
        $booking = $this->findBooking($token);

        $request->validate([
            'customer_pickup_at' => ['required', 'date'],
            'customer_return_at' => ['required', 'date'],
        ]);

        $booking->update([
            'customer_pickup_at'       => $request->customer_pickup_at,
            'customer_return_at'       => $request->customer_return_at,
            'customer_pickup_approved' => true,
        ]);

        // Mail naar klant
        if ($booking->customer_email) {
            $pickup = \Carbon\Carbon::parse($request->customer_pickup_at);
            $retour = \Carbon\Carbon::parse($request->customer_return_at);
            $portalUrl = route('portal.show', $booking->public_token);
            $subject = "✅ Ophaal- en retourtijden bevestigd — {$booking->booking_number}";
            $body = "
                <p>Beste {$booking->customer_name},</p>
                <p>Je ophaal- en retourtijden voor je photobooth zijn bevestigd:</p>
                <table style='border-collapse:collapse;margin:1rem 0;font-size:15px;'>
                    <tr>
                        <td style='padding:.4rem 1rem .4rem 0;color:#6b7280;'>📦 Ophalen bij ons</td>
                        <td style='padding:.4rem 0;font-weight:700;'>{$pickup->translatedFormat('l j F Y')} om {$pickup->format('H:i')}</td>
                    </tr>
                    <tr>
                        <td style='padding:.4rem 1rem .4rem 0;color:#6b7280;'>🔄 Terugbrengen</td>
                        <td style='padding:.4rem 0;font-weight:700;'>{$retour->translatedFormat('l j F Y')} om {$retour->format('H:i')}</td>
                    </tr>
                    <tr>
                        <td style='padding:.4rem 1rem .4rem 0;color:#6b7280;'>📍 Adres</td>
                        <td style='padding:.4rem 0;'>Ravenswade 132, 3439 LD Nieuwegein</td>
                    </tr>
                </table>
                <p><a href='{$portalUrl}' style='display:inline-block;background:#ea580c;color:#fff;padding:.6rem 1.25rem;border-radius:.4rem;text-decoration:none;font-weight:700;'>Bekijk je boeking →</a></p>
            ";
            app(MailService::class)->sendRaw($booking->customer_email, $subject, $body);
        }

        return response()->json(['success' => true]);
    }

    private function findBooking(string $token): Booking
    {
        return Booking::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)
            ->with('account')
            ->firstOrFail();
    }

    private function validateStep(Request $request, int $step, Booking $booking): array
    {
        if ($step === 3 && $booking->booking_type === 'to_go') {
            $slots = [];
            for ($h = 8; $h <= 15; $h++) {
                $slots[] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
                if ($h < 15) $slots[] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':30';
            }
            $slotsRule = 'in:' . implode(',', $slots);

            return $request->validate([
                'pickup_option' => ['required', 'in:suggested'],
                'pickup_date'   => ['required', 'date'],
                'pickup_time'   => ['required', 'string', $slotsRule],
                'return_date'   => ['required', 'date'],
                'return_time'   => ['required', 'string', $slotsRule],
            ]);
        }

        return match ($step) {
            2 => $request->validate([
                'delivery_time_preference' => ['nullable', 'string', 'max:500'],
                'delivery_notes'           => ['nullable', 'string', 'max:1000'],
            ]),
            3 => $request->validate([
                'pickup_preference'    => ['required', 'in:same_evening,next_morning'],
                'pickup_time'          => ['nullable', 'string', 'max:10'],
                'pickup_contact_person'=> ['nullable', 'string', 'max:150'],
            ]),
        };
    }

    private function notifyToGoTimeApprovalNeeded(Booking $booking, string $pickupTime, string $returnTime): void
    {
        $booking->loadMissing('account');
        if (! $booking->account->email) return;

        $s3          = $booking->intake_data['step_3'] ?? [];
        $pickupDate  = $s3['pickup_date'] ?? '?';
        $returnDate  = $s3['return_date'] ?? '?';
        $crmUrl      = route('bookings.show', $booking->id);
        $subject     = "⏰ Tijden goedkeuren — {$booking->customer_name} ({$booking->booking_number})";
        $body        = "
            <p><strong>{$booking->customer_name}</strong> heeft het intake formulier ingevuld voor boeking <strong>{$booking->booking_number}</strong> en heeft afwijkende tijden gekozen:</p>
            <table style='border-collapse:collapse;margin:1rem 0;font-size:15px;'>
                <tr>
                    <td style='padding:.4rem 1rem .4rem 0;color:#6b7280;'>📦 Ophalen</td>
                    <td style='padding:.4rem 0;font-weight:700;'>{$pickupDate} om <span style='color:#ea580c;font-size:18px;'>{$pickupTime}</span></td>
                </tr>
                <tr>
                    <td style='padding:.4rem 1rem .4rem 0;color:#6b7280;'>🔄 Terugbrengen</td>
                    <td style='padding:.4rem 0;font-weight:700;'>{$returnDate} om <span style='color:#ea580c;font-size:18px;'>{$returnTime}</span></td>
                </tr>
            </table>
            <p><a href='{$crmUrl}' style='display:inline-block;background:#ea580c;color:#fff;padding:.6rem 1.25rem;border-radius:.4rem;text-decoration:none;font-weight:700;'>Goedkeuren in CRM →</a></p>
        ";

        app(MailService::class)->sendRaw($booking->account->email, $subject, $body);
    }

    private function notifyToGoCounterProposal(Booking $booking, string $proposal): void
    {
        $booking->loadMissing('account');
        if (! $booking->account->email) return;

        $crmUrl  = url('/bookings/' . $booking->id);
        $subject = "⚠️ Tegenvoorstel ophaalmoment — {$booking->customer_name} ({$booking->booking_number})";
        $body    = "
            <p>Klant <strong>{$booking->customer_name}</strong> heeft een eigen voorstel ingediend voor het ophaal- en retourmoment van boeking <strong>{$booking->booking_number}</strong>.</p>
            <blockquote style='background:#f9fafb;border-left:4px solid #f59e0b;padding:.75rem 1rem;margin:1rem 0;'>
                " . nl2br(htmlspecialchars($proposal)) . "
            </blockquote>
            <p><a href='{$crmUrl}' style='background:#f59e0b;color:#fff;padding:.5rem 1rem;border-radius:.4rem;text-decoration:none;font-weight:600;'>Openen in CRM en goedkeuren →</a></p>
        ";

        app(MailService::class)->sendRaw($booking->account->email, $subject, $body);
    }

    private function notifyIntakeCompleted(Booking $booking): void
    {
        $booking->loadMissing('account');
        $mail = app(MailService::class);

        if ($booking->account->email) {
            $mail->send('admin_intake_completed', $booking, $booking->account->email);
        }
        if ($booking->customer_email) {
            $mail->send('customer_intake_confirmed', $booking, $booking->customer_email);
        }
    }
}
