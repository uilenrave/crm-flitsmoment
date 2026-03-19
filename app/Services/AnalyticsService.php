<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Lead;
use App\Models\Payment;
use Carbon\Carbon;

class AnalyticsService
{
    /**
     * Get leads metrics for a given date range
     */
    public function getLeadsMetrics($accountId, $dateRange = 'month'): array
    {
        $query = Lead::where('account_id', $accountId);
        $dates = $this->getDateRange($dateRange);

        // Total leads (all time)
        $totalLeads = Lead::where('account_id', $accountId)->count();

        // New leads in period
        $newLeads = $query
            ->whereBetween('created_at', [$dates['start'], $dates['end']])
            ->count();

        // Leads converted to bookings
        $leadsConverted = Lead::where('account_id', $accountId)
            ->where('archived_at', '!=', null)
            ->where('archive_reason', 'won')
            ->count();

        // Conversion rate
        $conversionRate = $totalLeads > 0 ? round(($leadsConverted / $totalLeads) * 100, 2) : 0;

        // Lost leads
        $leadsLost = Lead::where('account_id', $accountId)
            ->where('archive_reason', 'lost')
            ->count();

        return [
            'total_leads' => $totalLeads,
            'new_leads_this_period' => $newLeads,
            'leads_converted' => $leadsConverted,
            'conversion_rate' => $conversionRate,
            'leads_lost' => $leadsLost,
        ];
    }

    /**
     * Get bookings metrics for a given date range
     */
    public function getBookingsMetrics($accountId, $dateRange = 'month'): array
    {
        $dates = $this->getDateRange($dateRange);

        // Confirmed bookings
        $confirmedBookings = Booking::where('account_id', $accountId)
            ->where('status', 'confirmed')
            ->count();

        // Completed bookings
        $completedBookings = Booking::where('account_id', $accountId)
            ->where('status', 'completed')
            ->whereBetween('event_date', [$dates['start'], $dates['end']])
            ->count();

        // Upcoming events (next 7 days)
        $upcomingEvents = Booking::where('account_id', $accountId)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('event_date', [now()->startOfDay(), now()->addDays(7)->endOfDay()])
            ->count();

        return [
            'confirmed_bookings' => $confirmedBookings,
            'completed_bookings' => $completedBookings,
            'upcoming_events_7days' => $upcomingEvents,
        ];
    }

    /**
     * Get revenue metrics for a given date range
     */
    public function getRevenueMetrics($accountId, $dateRange = 'month'): array
    {
        $dates = $this->getDateRange($dateRange);

        // Total revenue from paid bookings
        $totalRevenue = Booking::where('account_id', $accountId)
            ->where('payment_status', 'paid')
            ->whereBetween('event_date', [$dates['start'], $dates['end']])
            ->sum('total_price');

        // Average booking value
        $bookingCount = Booking::where('account_id', $accountId)
            ->where('payment_status', 'paid')
            ->whereBetween('event_date', [$dates['start'], $dates['end']])
            ->count();

        $averageValue = $bookingCount > 0 ? round($totalRevenue / $bookingCount, 2) : 0;

        // Overdue payments count
        $overduePayments = Booking::where('account_id', $accountId)
            ->where('payment_status', '!=', 'paid')
            ->where('status', 'completed')
            ->count();

        return [
            'total_revenue' => round($totalRevenue, 2),
            'average_booking_value' => $averageValue,
            'overdue_payments' => $overduePayments,
        ];
    }

    /**
     * Get upcoming events for the next N days
     */
    public function getUpcomingEvents($accountId, $days = 7): array
    {
        $events = Booking::where('account_id', $accountId)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('event_date', [now()->startOfDay(), now()->addDays($days)->endOfDay()])
            ->orderBy('event_date')
            ->get(['id', 'booking_number', 'customer_name', 'event_date', 'payment_status', 'status'])
            ->toArray();

        return $events;
    }

    /**
     * Get trend data (monthly bookings and revenue for last 12 months)
     */
    public function getTrendData($accountId, $months = 12): array
    {
        $bookingsByMonth = [];
        $revenueByMonth = [];
        $labels = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthKey = $date->format('Y-m');
            $monthLabel = $date->format('M Y');

            $labels[] = $monthLabel;

            // Count bookings for this month
            $bookings = Booking::where('account_id', $accountId)
                ->where('status', '!=', 'cancelled')
                ->whereYear('event_date', $date->year)
                ->whereMonth('event_date', $date->month)
                ->count();

            $bookingsByMonth[] = $bookings;

            // Sum revenue for this month
            $revenue = Booking::where('account_id', $accountId)
                ->where('payment_status', 'paid')
                ->whereYear('event_date', $date->year)
                ->whereMonth('event_date', $date->month)
                ->sum('total_price');

            $revenueByMonth[] = round($revenue, 2);
        }

        return [
            'labels' => $labels,
            'bookings' => $bookingsByMonth,
            'revenue' => $revenueByMonth,
        ];
    }

    /**
     * Get conversion funnel data
     */
    public function getConversionFunnel($accountId): array
    {
        $totalLeads = Lead::where('account_id', $accountId)->count();
        $leadsWon = Lead::where('account_id', $accountId)
            ->where('archive_reason', 'won')
            ->count();
        $leadsLost = Lead::where('account_id', $accountId)
            ->where('archive_reason', 'lost')
            ->count();

        $conversionRate = $totalLeads > 0 ? round(($leadsWon / $totalLeads) * 100, 2) : 0;

        return [
            'total_leads' => $totalLeads,
            'leads_won' => $leadsWon,
            'leads_lost' => $leadsLost,
            'conversion_rate' => $conversionRate,
            'leads_pending' => $totalLeads - $leadsWon - $leadsLost,
        ];
    }

    /**
     * Get recent leads with their status
     */
    public function getRecentLeads($accountId, $limit = 5): array
    {
        return Lead::where('account_id', $accountId)
            ->where('archived_at', null)
            ->latest('created_at')
            ->limit($limit)
            ->get(['id', 'lead_number', 'name', 'status_id', 'event_date', 'created_at'])
            ->toArray();
    }

    /**
     * Calculate date range based on period
     */
    private function getDateRange($dateRange = 'month'): array
    {
        return match($dateRange) {
            'week' => [
                'start' => now()->startOfWeek(),
                'end' => now()->endOfWeek(),
            ],
            'year' => [
                'start' => now()->startOfYear(),
                'end' => now()->endOfYear(),
            ],
            'month' => [
                'start' => now()->startOfMonth(),
                'end' => now()->endOfMonth(),
            ],
            default => [
                'start' => now()->startOfMonth(),
                'end' => now()->endOfMonth(),
            ],
        };
    }
}
