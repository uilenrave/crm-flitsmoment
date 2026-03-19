<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Scopes\AccountScope;
use App\Services\EBoekhoudenService;
use App\Services\MailService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Mollie\Api\MollieApiClient;

class PortalController extends Controller
{
    /** Publieke portalpagina voor klant */
    public function show(string $token): View
    {
        $booking = Booking::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)
            ->with(['items.asset', 'payments', 'account'])
            ->firstOrFail();

        return view('portal.booking', compact('booking'));
    }

    /** Klant downloadt factuur PDF via e-boekhouden */
    public function factuurDownload(string $token): RedirectResponse
    {
        $booking = Booking::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)
            ->with('account')
            ->firstOrFail();

        if (!$booking->eboekhouden_invoice_id) {
            abort(404, 'Geen factuur beschikbaar.');
        }

        $service = app(EBoekhoudenService::class)
            ->withApiKey($booking->account->getEBoekhoudenApiKey());

        $pdfUrl = $service->getInvoicePdfUrl($booking->eboekhouden_invoice_id);

        if (!$pdfUrl) {
            abort(503, 'Factuur PDF tijdelijk niet beschikbaar. Probeer het later opnieuw.');
        }

        return redirect()->away($pdfUrl);
    }

    /** Display the strip design to customer in portal */
    public function stripDesignView(string $token): View
    {
        $booking = Booking::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)
            ->with('account')
            ->firstOrFail();

        return view('portal.strip-design', compact('booking'));
    }

    /** Klant levert input aan voor het fotostrip ontwerp */
    public function submitStripInput(Request $request, string $token): RedirectResponse
    {
        $booking = Booking::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)
            ->with('account')
            ->firstOrFail();

        $request->validate([
            'strip_input_text'   => ['nullable', 'string', 'max:3000'],
            'strip_input_files'  => ['nullable', 'array', 'max:5'],
            'strip_input_files.*' => ['file', 'mimes:jpg,jpeg,png,gif,webp,pdf', 'max:10240'],
        ], [
            'strip_input_text.required' => 'Vul minimaal een omschrijving in.',
        ]);

        if (! $request->filled('strip_input_text') && ! $request->hasFile('strip_input_files')) {
            return back()->with('error', 'Vul een omschrijving in of voeg een bestand toe.');
        }

        // Upload bestanden
        $uploadedFiles = [];
        if ($request->hasFile('strip_input_files')) {
            foreach ($request->file('strip_input_files') as $file) {
                $path = $file->store('strip-intake', 'public');
                $uploadedFiles[] = [
                    'url'      => Storage::disk('public')->url($path),
                    'filename' => $file->getClientOriginalName(),
                ];
            }
        }

        $intakeData = [
            'text'         => $request->strip_input_text ?? '',
            'files'        => $uploadedFiles,
            'submitted_at' => now()->toIso8601String(),
        ];

        $booking->update([
            'strip_intake_data' => $intakeData,
            'strip_status'      => 'designing',
        ]);

        // Mail naar admin
        $mail = app(MailService::class);
        if ($booking->account->email) {
            $mail->send('admin_strip_input_received', $booking, $booking->account->email);
        }

        return redirect()->route('portal.show', $token)
            ->with('success', '✅ Bedankt! We gaan aan de slag met jouw fotostrip ontwerp.');
    }

    /** Customer submits feedback on strip design */
    public function submitStripFeedback(Request $request, string $token): RedirectResponse
    {
        $booking = Booking::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)
            ->with('account')
            ->firstOrFail();

        $request->validate([
            'feedback' => ['required', 'string', 'max:2000']
        ]);

        // Auto-increment strip_version
        $newVersion = ($booking->strip_version ?? 1) + 1;
        $now = now();

        // Voeg toe aan commentaarhistorie
        $comments = $booking->strip_comments ?? [];
        $comments[] = [
            'text'       => $request->feedback,
            'design_url' => $booking->strip_design_url,
            'created_at' => $now->toIso8601String(),
        ];

        $booking->update([
            'strip_notes'       => $request->feedback,
            'strip_feedback'    => $request->feedback,
            'strip_feedback_at' => $now,
            'strip_version'     => $newVersion,
            'strip_comments'    => $comments,
            'strip_status'      => 'designing',
        ]);

        // Stuur direct de admin mail
        $mail = app(MailService::class);
        if ($booking->account->email) {
            $mail->send('admin_strip_comment', $booking, $booking->account->email);
        }

        return redirect()->route('portal.strip-design', $token)
            ->with('success', '✅ Bedankt voor je feedback! We passen het ontwerp aan.');
    }

    /** Klant beoordeelt het fotostrip ontwerp (accepteren of feedback) */
    public function stripReview(Request $request, string $token): RedirectResponse
    {
        $booking = Booking::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)
            ->with('account')
            ->firstOrFail();

        $action = $request->input('action');
        $mail   = app(MailService::class);

        if ($action === 'accept') {
            $booking->update(['strip_status' => 'accepted']);

            if ($booking->customer_email) {
                $mail->send('customer_strip_accepted', $booking, $booking->customer_email);
            }
            if ($booking->account->email) {
                $mail->send('admin_strip_accepted', $booking, $booking->account->email);
            }

            return redirect()->route('portal.show', $token)
                ->with('success', '✅ Bedankt! U heeft het ontwerp goedgekeurd. We gaan ermee aan de slag!');
        }

        if ($action === 'comment') {
            $request->validate(['strip_notes' => ['required', 'string', 'max:2000']]);

            $now = now();
            $comments = $booking->strip_comments ?? [];
            $comments[] = [
                'text'       => $request->strip_notes,
                'design_url' => $booking->strip_design_url,
                'created_at' => $now->toIso8601String(),
            ];

            $booking->update([
                'strip_notes'       => $request->strip_notes,
                'strip_feedback'    => $request->strip_notes,
                'strip_feedback_at' => $now,
                'strip_comments'    => $comments,
                'strip_status'      => 'designing',
            ]);

            if ($booking->account->email) {
                $mail->send('admin_strip_comment', $booking, $booking->account->email);
            }

            return redirect()->route('portal.show', $token)
                ->with('success', '💬 Bedankt voor je feedback! We passen het ontwerp aan en sturen het opnieuw.');
        }

        return redirect()->route('portal.show', $token);
    }

    /** Start een Mollie betaling */
    public function startPayment(Request $request, string $token): RedirectResponse
    {
        $booking = Booking::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)
            ->with('account')
            ->firstOrFail();

        if (! $booking->account->mollie_enabled) {
            return redirect()->route('portal.show', $token)
                ->with('info', 'Online betalen is niet beschikbaar voor deze boeking.');
        }

        $bedrag = $booking->amount_due;

        if ($bedrag <= 0) {
            return redirect()->route('portal.show', $token)
                ->with('info', 'Er is geen openstaand bedrag.');
        }

        try {
            $mollie = new MollieApiClient();
            $mollie->setApiKey($booking->account->getMollieKey());

            $paymentData = [
                'amount'      => [
                    'currency' => 'EUR',
                    'value'    => number_format($bedrag, 2, '.', ''),
                ],
                'description' => "Boeking {$booking->booking_number} — {$booking->customer_name}",
                'redirectUrl' => route('portal.payment-return', $token),
                'metadata'    => ['booking_id' => $booking->id],
            ];

            if (! app()->environment('local')) {
                $paymentData['webhookUrl'] = route('portal.payment-webhook', $token);
            }

            $payment = $mollie->payments->create($paymentData);

            Payment::create([
                'account_id'          => $booking->account_id,
                'booking_id'          => $booking->id,
                'provider'            => 'mollie',
                'provider_payment_id' => $payment->id,
                'amount'              => $bedrag,
                'currency'            => 'EUR',
                'description'         => "Boeking {$booking->booking_number}",
                'status'              => 'pending',
            ]);

            return redirect($payment->getCheckoutUrl());

        } catch (\Exception $e) {
            return back()->with('error', 'Betaling kon niet worden gestart: ' . $e->getMessage());
        }
    }

    /** Klant keert terug na Mollie betaling — sync status direct */
    public function paymentReturn(string $token): RedirectResponse
    {
        $booking = Booking::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)
            ->with('account')
            ->firstOrFail();

        $payment = Payment::withoutGlobalScope(AccountScope::class)
            ->where('booking_id', $booking->id)
            ->whereIn('status', ['pending', 'created'])
            ->latest()
            ->first();

        if ($payment) {
            try {
                $mollie = new MollieApiClient();
                $mollie->setApiKey($booking->account->getMollieKey());
                $molliePayment = $mollie->payments->get($payment->provider_payment_id);

                $payment->update([
                    'status'      => $molliePayment->status,
                    'paid_at'     => $molliePayment->status === 'paid' ? now() : null,
                    'raw_payload' => (array) $molliePayment,
                ]);

                if ($molliePayment->status === 'paid') {
                    $totaalBetaald = Payment::withoutGlobalScope(AccountScope::class)
                        ->where('booking_id', $booking->id)
                        ->where('status', 'paid')
                        ->sum('amount');

                    $nieuwStatus = $totaalBetaald >= $booking->total_price ? 'paid' : 'partial';
                    $booking->update(['payment_status' => $nieuwStatus]);

                    // Admin mail: nieuwe betaling
                    if ($booking->account->email) {
                        app(MailService::class)->send('admin_new_payment', $booking, $booking->account->email);
                    }

                    return redirect()->route('portal.show', $token)
                        ->with('success', 'Betaling ontvangen! Bedankt.');
                }
            } catch (\Exception $e) {
                \Log::error('Mollie status sync fout: ' . $e->getMessage());
            }
        }

        return redirect()->route('portal.show', $token)
            ->with('success', 'Bedankt! Uw betaling wordt verwerkt.');
    }

    /** Mollie webhook — betaalstatus updaten */
    public function paymentWebhook(Request $request, string $token): \Illuminate\Http\Response
    {
        $booking = Booking::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)
            ->with('account')
            ->firstOrFail();

        $mollieId = $request->input('id');
        if (! $mollieId) return response('ok');

        try {
            $mollie = new MollieApiClient();
            $mollie->setApiKey($booking->account->getMollieKey());
            $molliePayment = $mollie->payments->get($mollieId);

            $payment = Payment::withoutGlobalScope(AccountScope::class)
                ->where('provider_payment_id', $mollieId)->first();
            if (! $payment) return response('ok');

            $payment->update([
                'status'      => $molliePayment->status,
                'paid_at'     => $molliePayment->status === 'paid' ? now() : null,
                'raw_payload' => (array) $molliePayment,
            ]);

            if ($molliePayment->status === 'paid') {
                $totaalBetaald = Payment::withoutGlobalScope(AccountScope::class)
                    ->where('booking_id', $booking->id)
                    ->where('status', 'paid')
                    ->sum('amount');

                $nieuwStatus = $totaalBetaald >= $booking->total_price ? 'paid' : 'partial';
                $booking->update(['payment_status' => $nieuwStatus]);

                // Admin mail: nieuwe betaling
                if ($booking->account->email) {
                    app(MailService::class)->send('admin_new_payment', $booking, $booking->account->email);
                }
            }

        } catch (\Exception $e) {
            \Log::error('Mollie webhook fout: ' . $e->getMessage());
        }

        return response('ok');
    }
}
