<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private AnalyticsService $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    public function index(Request $request): View
    {
        $accountId = auth()->user()->account_id;
        $dateRange = $request->input('range', 'month');

        // Get all metrics — trend data is cached omdat het historische maandcijfers zijn (zelden verandert)
        $leadsMetrics     = $this->analyticsService->getLeadsMetrics($accountId, $dateRange);
        $bookingsMetrics  = $this->analyticsService->getBookingsMetrics($accountId, $dateRange);
        $revenueMetrics   = $this->analyticsService->getRevenueMetrics($accountId, $dateRange);
        $yearly           = Cache::remember(
            "dashboard:yearly:{$accountId}",
            now()->addMinutes(5),
            fn() => $this->analyticsService->getYearlyTrends($accountId)
        );
        $outstanding      = Cache::remember(
            "dashboard:outstanding:{$accountId}",
            now()->addMinutes(5),
            fn() => $this->analyticsService->getOutstandingInvoiceStats($accountId)
        );
        $conversionFunnel = $this->analyticsService->getConversionFunnel($accountId);
        $upcomingEvents   = $this->analyticsService->getUpcomingEvents($accountId, 7);
        $recentLeads      = $this->analyticsService->getRecentLeads($accountId, 5);
        $staffHoursStats  = $this->analyticsService->getStaffHoursStats($accountId);

        // Bouw één dataset per kalenderjaar; niet-standaard jaren starten verborgen (jaartoggle in de view).
        $bookingsTrendChartData = json_encode([
            'labels'   => $yearly['labels'],
            'datasets' => $this->yearDatasets($yearly, 'bookings'),
        ]);
        $revenueTrendChartData = json_encode([
            'labels'   => $yearly['labels'],
            'datasets' => $this->yearDatasets($yearly, 'revenue'),
        ]);

        return view('dashboard.index', compact(
            'leadsMetrics',
            'bookingsMetrics',
            'revenueMetrics',
            'yearly',
            'outstanding',
            'conversionFunnel',
            'upcomingEvents',
            'recentLeads',
            'dateRange',
            'bookingsTrendChartData',
            'revenueTrendChartData',
            'staffHoursStats'
        ));
    }

    /** Kleur per jaar: huidig jaar amber, overige jaren uit een vast palet (stabiel per positie). */
    private function yearColor(int $year, int $index, int $currentYear): string
    {
        if ($year === $currentYear) return '#f59e0b';
        $palette = ['#2563eb', '#64748b', '#16a34a', '#db2777', '#0891b2', '#9333ea', '#ca8a04', '#dc2626'];
        return $palette[$index % count($palette)];
    }

    /** Chart.js-datasets: één lijn per jaar, standaard-jaren zichtbaar, rest verborgen. */
    private function yearDatasets(array $yearly, string $seriesKey): array
    {
        $datasets = [];
        foreach ($yearly['years'] as $i => $year) {
            $isCurrent = $year === $yearly['current_year'];
            $color = $this->yearColor($year, $i, $yearly['current_year']);
            $datasets[] = [
                'label'           => (string) $year,
                'data'            => array_values($yearly[$seriesKey][$year]),
                'borderColor'     => $color,
                'backgroundColor' => $isCurrent ? 'rgba(245,158,11,0.08)' : 'transparent',
                'fill'            => $isCurrent,
                'tension'         => 0.4,
                'borderWidth'     => $isCurrent ? 2.5 : 1.8,
                'pointRadius'     => 2,
                'hidden'          => ! in_array($year, $yearly['default_years'], true),
            ];
        }
        return $datasets;
    }

    public function trends(Request $request)
    {
        $accountId = auth()->user()->account_id;
        $dateRange = $request->input('range', 'month');

        $trendData = $this->analyticsService->getTrendData($accountId, 12);

        return response()->json([
            'labels' => $trendData['labels'],
            'bookings' => $trendData['bookings'],
            'revenue' => $trendData['revenue'],
        ]);
    }
}
