<?php

namespace App\Observers;

use App\Models\Booking;
use App\Services\ZeZoneService;

class BookingObserver
{
    /**
     * Herbereken de zero-emissiezone-vlag zodra een boeking nieuw is of het adres wijzigt.
     * Draait synchroon (één PDOK-geocode). checkBooking() slaat via saveQuietly() op, dus
     * dit triggert de observer niet opnieuw (geen loop).
     */
    public function saved(Booking $booking): void
    {
        $addressChanged = $booking->wasChanged(['event_address', 'event_postcode', 'event_city']);
        if (! $booking->wasRecentlyCreated && ! $addressChanged) {
            return;
        }
        app(ZeZoneService::class)->checkBooking($booking, regeocode: $addressChanged);
    }
}
