<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Scopes\AccountScope;

/**
 * Beschikbaarheid van assets op de datum(range) van een boeking. Hergebruikt de overlap-logica
 * uit de boeking-editor: een andere boeking bezet een asset als zijn bezorg/ophaal- (of event-)
 * datumrange die van deze boeking overlapt.
 */
class AvailabilityService
{
    /** Datumrange [start,end] (Y-m-d) van een boeking: bezorg/ophaal indien gezet, anders event. */
    public function dateRange(Booking $booking): array
    {
        if ($booking->booking_type === 'to_go' && $booking->customer_pickup_at) {
            $start = $booking->customer_pickup_at->toDateString();
            $end   = ($booking->customer_return_at ?? $booking->customer_pickup_at)->toDateString();
        } elseif ($booking->delivery_at) {
            $start = $booking->delivery_at->toDateString();
            $end   = ($booking->pickup_at ?? $booking->delivery_at)->toDateString();
        } else {
            $start = $booking->event_date->toDateString();
            $end   = ($booking->event_end_date ?? $booking->event_date)->toDateString();
        }
        return [$start, $end];
    }

    /** Aantal van een asset dat door ándere (bevestigde/voltooide) boekingen bezet is in [start,end]. */
    public function bookedByOthers(int $assetId, string $start, string $end, int $excludeBookingId): int
    {
        return (int) BookingItem::where('asset_id', $assetId)
            ->whereHas('booking', function ($q) use ($start, $end, $excludeBookingId) {
                $q->withoutGlobalScope(AccountScope::class)
                  ->whereIn('status', ['confirmed', 'completed'])
                  ->where('id', '!=', $excludeBookingId)
                  ->whereRaw("
                    (CASE
                        WHEN booking_type = 'to_go' AND customer_pickup_at IS NOT NULL THEN DATE(customer_pickup_at)
                        WHEN delivery_at IS NOT NULL THEN DATE(delivery_at)
                        ELSE event_date
                    END) <= ?
                    AND
                    (CASE
                        WHEN booking_type = 'to_go' AND customer_pickup_at IS NOT NULL THEN DATE(COALESCE(customer_return_at, customer_pickup_at))
                        WHEN delivery_at IS NOT NULL THEN DATE(COALESCE(pickup_at, delivery_at))
                        ELSE COALESCE(event_end_date, event_date)
                    END) >= ?
                  ", [$end, $start]);
            })
            ->sum('quantity');
    }

    /**
     * Hoeveel stuks van $asset deze boeking nog kan toevoegen op zijn datum.
     * ignore_stock (verbruik/kosten) = onbeperkt. Anders: stock − door anderen bezet − al op deze boeking.
     */
    public function availableToAdd(Asset $asset, Booking $booking): int
    {
        if ($asset->ignore_stock) {
            return PHP_INT_MAX;
        }
        [$start, $end] = $this->dateRange($booking);

        $others  = $this->bookedByOthers($asset->id, $start, $end, $booking->id);
        $onThis  = (int) BookingItem::where('asset_id', $asset->id)
            ->where('booking_id', $booking->id)->sum('quantity');

        return max(0, (int) $asset->stock - $others - $onThis);
    }
}
