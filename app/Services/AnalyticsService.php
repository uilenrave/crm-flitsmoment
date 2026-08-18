<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Staff;
use App\Models\StaffHours;
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

        // Total revenue from all non-cancelled bookings
        $totalRevenue = Booking::where('account_id', $accountId)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('event_date', [$dates['start'], $dates['end']])
            ->sum('total_price');

        // Open (unpaid) revenue
        $openRevenue = Booking::where('account_id', $accountId)
            ->where('status', '!=', 'cancelled')
            ->where('payment_status', '!=', 'paid')
            ->whereBetween('event_date', [$dates['start'], $dates['end']])
            ->sum('total_price');

        // Average booking value (all non-cancelled)
        $bookingCount = Booking::where('account_id', $accountId)
            ->where('status', '!=', 'cancelled')
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
            'open_revenue' => round($openRevenue, 2),
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
     * Get trend data for full calendar year (Jan–Dec), current vs previous year
     */
    public function getTrendData($accountId, $months = 12): array
    {
        $currentYear = now()->year;
        $prevYear    = $currentYear - 1;
        $prev2Year   = $currentYear - 2;
        $monthNames  = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Aug','Sep','Okt','Nov','Dec'];
        $labels      = $monthNames;

        // ── Eén query voor alle 3 jaren × 12 maanden, gegroepeerd ──
        // Resultaat: rows met (year, month, count, sum) — caching in PHP, geen 72 losse queries meer.
        $rows = Booking::where('account_id', $accountId)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('event_date', [
                now()->setYear($prev2Year)->startOfYear()->toDateString(),
                now()->setYear($currentYear)->endOfYear()->toDateString(),
            ])
            ->selectRaw('YEAR(event_date) AS yr, MONTH(event_date) AS mo, COUNT(*) AS cnt, COALESCE(SUM(total_price),0) AS sum_price')
            ->groupBy('yr', 'mo')
            ->get()
            ->keyBy(fn($r) => $r->yr . '-' . $r->mo);

        $emptyMonths = array_fill(0, 12, 0);
        $bookingsByMonth   = $emptyMonths;
        $bookingsPrevYear  = $emptyMonths;
        $bookingsPrev2Year = $emptyMonths;
        $revenueByMonth    = $emptyMonths;
        $revenuePrevYear   = $emptyMonths;
        $revenuePrev2Year  = $emptyMonths;

        foreach ([$currentYear => 'cur', $prevYear => 'prev', $prev2Year => 'prev2'] as $yr => $tag) {
            for ($m = 1; $m <= 12; $m++) {
                $r = $rows->get("$yr-$m");
                $cnt = $r ? (int) $r->cnt : 0;
                $sum = $r ? round((float) $r->sum_price, 2) : 0.0;
                if ($tag === 'cur')   { $bookingsByMonth[$m-1]   = $cnt; $revenueByMonth[$m-1]   = $sum; }
                if ($tag === 'prev')  { $bookingsPrevYear[$m-1]  = $cnt; $revenuePrevYear[$m-1]  = $sum; }
                if ($tag === 'prev2') { $bookingsPrev2Year[$m-1] = $cnt; $revenuePrev2Year[$m-1] = $sum; }
            }
        }

        return [
            'labels'               => $labels,
            'bookings'             => $bookingsByMonth,
            'bookings_prev_year'   => $bookingsPrevYear,
            'bookings_prev2_year'  => $bookingsPrev2Year,
            'revenue'              => $revenueByMonth,
            'revenue_prev_year'    => $revenuePrevYear,
            'revenue_prev2_year'   => $revenuePrev2Year,
            'current_year'         => $currentYear,
            'prev_year'            => $prevYear,
            'prev2_year'           => $prev2Year,
            // Year totals
            'total_bookings_y0'    => array_sum($bookingsByMonth),
            'total_bookings_y1'    => array_sum($bookingsPrevYear),
            'total_bookings_y2'    => array_sum($bookingsPrev2Year),
            'total_revenue_y0'     => round(array_sum($revenueByMonth), 2),
            'total_revenue_y1'     => round(array_sum($revenuePrevYear), 2),
            'total_revenue_y2'     => round(array_sum($revenuePrev2Year), 2),
        ];
    }

    /**
     * Boekingen + omzet per maand, per kalenderjaar — dynamisch over álle jaren met boekingen
     * (inclusief toekomstige jaren zoals 2027). Voedt de in/uit-schakelbare jaargrafieken.
     */
    public function getYearlyTrends($accountId): array
    {
        $monthNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Aug','Sep','Okt','Nov','Dec'];

        $rows = Booking::where('account_id', $accountId)
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('event_date')
            ->selectRaw('YEAR(event_date) AS yr, MONTH(event_date) AS mo, COUNT(*) AS cnt, COALESCE(SUM(total_price),0) AS sum_price')
            ->groupBy('yr', 'mo')
            ->get();

        $currentYear = now()->year;
        $years = $rows->pluck('yr')->map(fn ($y) => (int) $y);
        // Huidig + vorig jaar altijd tonen (ook zonder boekingen) zodat de standaardvergelijking klopt.
        $years = $years->push($currentYear)->push($currentYear - 1)->unique()->sort()->values();

        $bookings = []; $revenue = []; $totalBookings = []; $totalRevenue = [];
        foreach ($years as $yr) {
            $b = array_fill(0, 12, 0); $r = array_fill(0, 12, 0.0);
            foreach ($rows->where('yr', $yr) as $row) {
                $b[$row->mo - 1] = (int) $row->cnt;
                $r[$row->mo - 1] = round((float) $row->sum_price, 2);
            }
            $bookings[$yr] = $b; $revenue[$yr] = $r;
            $totalBookings[$yr] = array_sum($b); $totalRevenue[$yr] = round(array_sum($r), 2);
        }

        $defaultYears = array_values(array_filter([$currentYear - 1, $currentYear], fn ($y) => $years->contains($y)));
        if (empty($defaultYears)) $defaultYears = $years->all();

        return [
            'labels'         => $monthNames,
            'years'          => $years->all(),
            'bookings'       => $bookings,
            'revenue'        => $revenue,
            'total_bookings' => $totalBookings,
            'total_revenue'  => $totalRevenue,
            'current_year'   => $currentYear,
            'default_years'  => $defaultYears,
        ];
    }

    /**
     * Openstaande betalingen (niet-betaalde boekingen) + facturatiestatus, per event-jaar + totaal.
     * "Openstaand" = restbedrag (total_price minus betaalde payments), excl. betaald/geannuleerd/terugbetaald.
     * Facturatie: al gefactureerd óf "niet nodig" (skip) vs. nog te factureren. Bedrag + aantal.
     */
    public function getOutstandingInvoiceStats($accountId): array
    {
        $bookings = Booking::where('account_id', $accountId)
            ->whereNotIn('payment_status', ['paid', 'cancelled', 'refunded'])
            ->whereNotNull('event_date')
            ->get(['id', 'event_date', 'total_price', 'payment_status',
                   'eboekhouden_invoice_id', 'eboekhouden_invoice_number', 'eboekhouden_skip_invoice']);

        $paidById = Payment::where('status', 'paid')
            ->whereIn('booking_id', $bookings->pluck('id'))
            ->selectRaw('booking_id, COALESCE(SUM(amount),0) AS paid')
            ->groupBy('booking_id')
            ->pluck('paid', 'booking_id');

        $blank = fn () => [
            'outstanding_amount' => 0.0, 'outstanding_count' => 0,
            'invoiced_amount'    => 0.0, 'invoiced_count'    => 0,
            'to_invoice_amount'  => 0.0, 'to_invoice_count'  => 0,
        ];
        $byYear = [];
        $total  = $blank();

        foreach ($bookings as $b) {
            $due = max(0, (float) $b->total_price - (float) ($paidById[$b->id] ?? 0));
            if ($due <= 0) continue; // effectief al voldaan

            $yr = (int) Carbon::parse($b->event_date)->year;
            $byYear[$yr] ??= $blank();

            $invoiced = $b->eboekhouden_invoice_id || $b->eboekhouden_invoice_number || $b->eboekhouden_skip_invoice;
            $bucket   = $invoiced ? 'invoiced' : 'to_invoice';

            foreach ([&$byYear[$yr], &$total] as &$agg) {
                $agg['outstanding_amount'] += $due; $agg['outstanding_count']++;
                $agg["{$bucket}_amount"]   += $due; $agg["{$bucket}_count"]++;
            }
            unset($agg);
        }

        ksort($byYear);
        $round = function (array $a): array {
            foreach (['outstanding_amount', 'invoiced_amount', 'to_invoice_amount'] as $k) $a[$k] = round($a[$k], 2);
            return $a;
        };
        $byYear = array_map($round, $byYear);

        return ['by_year' => $byYear, 'total' => $round($total), 'current_year' => now()->year];
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
     * Get staff hours stats voor dashboard
     */
    public function getStaffHoursStats(int $accountId): array
    {
        $allEntries = StaffHours::where('account_id', $accountId)->get();

        $pendingEntries = $allEntries->where('status', 'pending');
        $approvedEntries = $allEntries->where('status', 'approved');
        $paidEntries = $allEntries->where('status', 'paid');

        // Per medewerker samenvatten
        $staffIds = $allEntries->pluck('staff_id')->unique();
        $staffMap = Staff::withoutGlobalScopes()
            ->whereIn('id', $staffIds)
            ->pluck('name', 'id');

        $perStaff = $staffIds->map(function ($staffId) use ($allEntries, $staffMap) {
            $staffEntries = $allEntries->where('staff_id', $staffId);
            return [
                'name'           => $staffMap[$staffId] ?? 'Onbekend',
                'pending_hours'  => $staffEntries->where('status', 'pending')->sum('hours'),
                'approved_hours' => $staffEntries->where('status', 'approved')->sum(fn($e) => $e->effective_hours),
                'paid_hours'     => $staffEntries->where('status', 'paid')->sum(fn($e) => $e->effective_hours),
                'km_allowance'   => $staffEntries->sum(fn($e) => $e->km_allowance),
            ];
        })->values()->toArray();

        return [
            'total_pending_entries' => $pendingEntries->count(),
            'pending_hours'         => $pendingEntries->sum('hours'),
            'approved_hours'        => $approvedEntries->sum(fn($e) => $e->effective_hours),
            'paid_hours'            => $paidEntries->sum(fn($e) => $e->effective_hours),
            'total_km_allowance'    => $allEntries->sum(fn($e) => $e->km_allowance),
            'per_staff'             => $perStaff,
        ];
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
