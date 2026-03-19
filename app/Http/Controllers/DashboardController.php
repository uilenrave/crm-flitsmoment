<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use Illuminate\Http\Request;
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

        // Get all metrics
        $leadsMetrics = $this->analyticsService->getLeadsMetrics($accountId, $dateRange);
        $bookingsMetrics = $this->analyticsService->getBookingsMetrics($accountId, $dateRange);
        $revenueMetrics = $this->analyticsService->getRevenueMetrics($accountId, $dateRange);
        $trendData = $this->analyticsService->getTrendData($accountId, 12);
        $conversionFunnel = $this->analyticsService->getConversionFunnel($accountId);
        $upcomingEvents = $this->analyticsService->getUpcomingEvents($accountId, 7);
        $recentLeads = $this->analyticsService->getRecentLeads($accountId, 5);

        // Prepare data for charts
        $bookingsTrendChartData = json_encode([
            'labels' => $trendData['labels'],
            'datasets' => [
                [
                    'label' => 'Boekingen',
                    'data' => $trendData['bookings'],
                    'borderColor' => '#fcd34d',
                    'backgroundColor' => 'rgba(252, 211, 77, 0.1)',
                    'tension' => 0.4,
                    'fill' => true,
                ]
            ]
        ]);

        $revenueTrendChartData = json_encode([
            'labels' => $trendData['labels'],
            'datasets' => [
                [
                    'label' => 'Omzet (€)',
                    'data' => $trendData['revenue'],
                    'borderColor' => '#fcd34d',
                    'backgroundColor' => 'rgba(252, 211, 77, 0.1)',
                    'tension' => 0.4,
                    'fill' => true,
                ]
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
            'revenueTrendChartData'
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
