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
        $trendData        = Cache::remember(
            "dashboard:trends:{$accountId}",
            now()->addMinutes(5),
            fn() => $this->analyticsService->getTrendData($accountId, 12)
        );
        $conversionFunnel = $this->analyticsService->getConversionFunnel($accountId);
        $upcomingEvents   = $this->analyticsService->getUpcomingEvents($accountId, 7);
        $recentLeads      = $this->analyticsService->getRecentLeads($accountId, 5);
        $staffHoursStats  = $this->analyticsService->getStaffHoursStats($accountId);

        $y0 = $trendData['current_year'];
        $y1 = $trendData['prev_year'];
        $y2 = $trendData['prev2_year'];

        // Prepare data for charts
        $bookingsTrendChartData = json_encode([
            'labels' => $trendData['labels'],
            'datasets' => [
                [
                    'label' => (string) $y0,
                    'data' => $trendData['bookings'],
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(252, 211, 77, 0.08)',
                    'tension' => 0.4,
                    'fill' => true,
                    'borderWidth' => 2,
                ],
                [
                    'label' => (string) $y1,
                    'data' => $trendData['bookings_prev_year'],
                    'borderColor' => '#64748b',
                    'backgroundColor' => 'transparent',
                    'borderDash' => [5, 4],
                    'tension' => 0.4,
                    'fill' => false,
                    'pointRadius' => 3,
                    'borderWidth' => 1.5,
                ],
                [
                    'label' => (string) $y2,
                    'data' => $trendData['bookings_prev2_year'],
                    'borderColor' => '#cbd5e1',
                    'backgroundColor' => 'transparent',
                    'borderDash' => [3, 3],
                    'tension' => 0.4,
                    'fill' => false,
                    'pointRadius' => 2,
                    'borderWidth' => 1.5,
                ],
            ]
        ]);

        $revenueTrendChartData = json_encode([
            'labels' => $trendData['labels'],
            'datasets' => [
                [
                    'label' => (string) $y0,
                    'data' => $trendData['revenue'],
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(252, 211, 77, 0.08)',
                    'tension' => 0.4,
                    'fill' => true,
                    'borderWidth' => 2,
                ],
                [
                    'label' => (string) $y1,
                    'data' => $trendData['revenue_prev_year'],
                    'borderColor' => '#64748b',
                    'backgroundColor' => 'transparent',
                    'borderDash' => [5, 4],
                    'tension' => 0.4,
                    'fill' => false,
                    'pointRadius' => 3,
                    'borderWidth' => 1.5,
                ],
                [
                    'label' => (string) $y2,
                    'data' => $trendData['revenue_prev2_year'],
                    'borderColor' => '#cbd5e1',
                    'backgroundColor' => 'transparent',
                    'borderDash' => [3, 3],
                    'tension' => 0.4,
                    'fill' => false,
                    'pointRadius' => 2,
                    'borderWidth' => 1.5,
                ],
            ]
        ]);

        return view('dashboard.index', compact(
            'leadsMetrics',
            'bookingsMetrics',
            'revenueMetrics',
            'trendData',
            'conversionFunnel',
            'upcomingEvents',
            'recentLeads',
            'dateRange',
            'bookingsTrendChartData',
            'revenueTrendChartData',
            'staffHoursStats'
        ));
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
