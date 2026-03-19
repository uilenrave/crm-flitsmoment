<?php

namespace App\Helpers;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CalendarHelper
{
    /**
     * Genereer agenda events van een boeking (delivery, event, pickup)
     */
    public static function eventsFromBooking(Booking $booking, array $colors = []): Collection
    {
        // Standaard kleuren
        $colors = array_merge([
            'full_service' => '#3b82f6',  // Blauw
            'to_go' => '#f97316',         // Oranje
            'delivery' => '#9ca3af',      // Grijs
            'pickup' => '#ef4444',        // Rood
        ], $colors);

        $events = collect();

        // === BEZORGING (alleen Full Service) ===
        if ($booking->booking_type === 'full_service' && $booking->delivery_at) {
            $deliveryEnd = (clone $booking->delivery_at)->addMinutes(30);

            $events->push([
                'id' => "booking-{$booking->id}-delivery",
                'start' => $booking->delivery_at->format('Y-m-d H:i'),
                'end' => $deliveryEnd->format('Y-m-d H:i'),
                'title' => '🚚 Bezorging: ' . $booking->customer_name,
                'type' => 'delivery',
                'booking_id' => $booking->id,
                'color' => $colors['delivery'],
                'url' => route('bookings.show', $booking),
            ]);
        }

        // === EVENT ===
        // Voor multi-day bookings: gebruik event_end_date als einddatum
        $eventEndDate = $booking->is_multi_day && $booking->event_end_date
            ? $booking->event_end_date
            : $booking->event_date;

        $eventStartStr = $booking->event_date->format('Y-m-d') . ' ' . ($booking->event_start_time ?? '00:00');
        $eventEndStr = $eventEndDate->format('Y-m-d') . ' ' . ($booking->event_end_time ?? '23:59');

        try {
            $eventStart = Carbon::createFromFormat('Y-m-d H:i', $eventStartStr);
            $eventEnd = Carbon::createFromFormat('Y-m-d H:i', $eventEndStr);

            $events->push([
                'id' => "booking-{$booking->id}-event",
                'start' => $eventStart->format('Y-m-d H:i'),
                'end' => $eventEnd->format('Y-m-d H:i'),
                'title' => '🎉 Event: ' . $booking->customer_name,
                'type' => 'event',
                'booking_id' => $booking->id,
                'color' => $booking->is_multi_day ? '#fcd34d' : ($colors[$booking->booking_type] ?? $colors['full_service']),
                'is_multi_day' => $booking->is_multi_day,
                'url' => route('bookings.show', $booking),
            ]);
        } catch (\Exception $e) {
            // Fallback: toon minstens de event datum als ganzen dag
            $events->push([
                'id' => "booking-{$booking->id}-event",
                'start' => $booking->event_date->format('Y-m-d'),
                'end' => $eventEndDate->addDay()->format('Y-m-d'),
                'title' => '🎉 Event: ' . $booking->customer_name,
                'type' => 'event',
                'booking_id' => $booking->id,
                'color' => $booking->is_multi_day ? '#fcd34d' : ($colors[$booking->booking_type] ?? $colors['full_service']),
                'is_multi_day' => $booking->is_multi_day,
                'url' => route('bookings.show', $booking),
            ]);
        }

        // === OPHALING / RETOUR ===
        $pickupTime = $booking->booking_type === 'full_service'
            ? $booking->pickup_at
            : $booking->customer_return_at;

        if ($pickupTime) {
            $pickupEnd = (clone $pickupTime)->addMinutes(30);
            $pickupLabel = $booking->booking_type === 'full_service'
                ? '↩️ Ophaling: '
                : '↩️ Retour: ';

            $events->push([
                'id' => "booking-{$booking->id}-pickup",
                'start' => $pickupTime->format('Y-m-d H:i'),
                'end' => $pickupEnd->format('Y-m-d H:i'),
                'title' => $pickupLabel . $booking->customer_name,
                'type' => 'pickup',
                'booking_id' => $booking->id,
                'color' => $colors['pickup'],
                'url' => route('bookings.show', $booking),
            ]);
        }

        return $events;
    }

    /**
     * Genereer alle events voor een maand
     */
    public static function eventsForMonth(int $year, int $month, Collection $bookings): Collection
    {
        $allEvents = collect();

        foreach ($bookings as $booking) {
            $allEvents = $allEvents->merge(self::eventsFromBooking($booking));
        }

        return $allEvents;
    }

    /**
     * Aantal dagen in een maand
     */
    public static function daysInMonth(int $year, int $month): int
    {
        return cal_days_in_month(CAL_GREGORIAN, $month, $year);
    }

    /**
     * Eerste dag van de maand (0=zondag, 6=zaterdag)
     */
    public static function firstDayOfMonth(int $year, int $month): int
    {
        $date = Carbon::createFromDate($year, $month, 1);
        return $date->dayOfWeek; // 0=Sunday in Carbon
    }

    /**
     * Maand vorige/volgende
     */
    public static function previousMonth(int $year, int $month): array
    {
        if ($month === 1) {
            return ['year' => $year - 1, 'month' => 12];
        }
        return ['year' => $year, 'month' => $month - 1];
    }

    public static function nextMonth(int $year, int $month): array
    {
        if ($month === 12) {
            return ['year' => $year + 1, 'month' => 1];
        }
        return ['year' => $year, 'month' => $month + 1];
    }

    /**
     * Maand naam (Nederlands)
     */
    public static function monthName(int $month): string
    {
        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maart',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Augustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'December',
        ];
        return $months[$month] ?? '';
    }

    /**
     * Dag naam (Nederlands)
     */
    public static function dayName(int $dayOfWeek): string
    {
        $days = ['Zo', 'Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za'];
        return $days[$dayOfWeek] ?? '';
    }

    /**
     * Events voor een specifieke dag
     */
    public static function eventsForDay(string $date, Collection $allEvents): Collection
    {
        return $allEvents->filter(function ($event) use ($date) {
            return str_starts_with($event['start'], $date);
        })->sortBy('start');
    }
}
