<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Scopes\AccountScope;
use App\Services\MailService;
use Illuminate\View\View;

/**
 * Publieke 1-klik goedkeuring van een door de klant aangevraagde bezorg/ophaal-wijziging.
 * Werkt via een opaque token (patroon OfferPublicController), zonder login — bruikbaar direct
 * vanuit de admin-mail op de telefoon.
 */
class BookingChangeController extends Controller
{
    public function approve(string $token): View
    {
        $booking = Booking::withoutGlobalScope(AccountScope::class)
            ->where('pending_change_token', $token)
            ->with('account')
            ->first();

        // Token al gebruikt of ongeldig → idempotente melding.
        if (! $booking || empty($booking->pending_change)) {
            return view('booking-change.approved', ['booking' => $booking, 'alreadyDone' => true]);
        }

        $change = $booking->pending_change;
        $update = ['pending_change' => null, 'pending_change_token' => null];
        foreach (['delivery_at', 'pickup_at', 'customer_pickup_at', 'customer_return_at'] as $col) {
            if (! empty($change[$col])) {
                $update[$col] = $change[$col];
            }
        }
        $booking->update($update);

        // Bevestig de klant met de juiste "aangepast"-template (verse booking = bijgewerkte tijden).
        $booking = $booking->fresh();
        if ($booking->customer_email) {
            $key = $booking->booking_type === 'to_go'
                ? 'customer_times_updated_to_go'
                : 'customer_times_updated_full_service';
            app(MailService::class)->send($key, $booking, $booking->customer_email);
        }

        return view('booking-change.approved', ['booking' => $booking, 'alreadyDone' => false]);
    }
}
