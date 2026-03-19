@extends('layouts.app')
@section('title', 'Analytics Dashboard')

@section('content')
<div style="padding:0 1.5rem;">
    {{-- Dashboard Header --}}
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;">
        <h1 style="font-size:2rem;font-weight:bold;margin:0;">📊 Analytics Dashboard</h1>
        <div style="display:flex;gap:0.5rem;">
            <a href="{{ route('dashboard', ['range' => 'month']) }}"
               class="btn {{ $dateRange === 'month' ? 'btn-primary' : 'btn-secondary' }}">
                Deze maand
            </a>
            <a href="{{ route('dashboard', ['range' => 'year']) }}"
               class="btn {{ $dateRange === 'year' ? 'btn-primary' : 'btn-secondary' }}">
                Dit jaar
            </a>
        </div>
    </div>

    {{-- KPI Cards Row 1 --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(250px, 1fr));gap:1.25rem;margin-bottom:2rem;">

        {{-- Total Leads Card --}}
        <div class="card" style="padding:1.5rem;">
            <div style="font-size:0.875rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:0.5rem;">
                Totale Leads
            </div>
            <div style="font-size:2.5rem;font-weight:bold;color:#fcd34d;margin-bottom:0.5rem;">
                {{ $leadsMetrics['total_leads'] }}
            </div>
            <div style="font-size:0.875rem;color:#64748b;">
                @if($leadsMetrics['new_leads_this_period'] > 0)
                    <span style="color:#16a34a;">↑ +{{ $leadsMetrics['new_leads_this_period'] }} dit(se) periode</span>
                @else
                    <span>Geen nieuwe leads deze periode</span>
                @endif
            </div>
        </div>

        {{-- Conversion Rate Card --}}
        <div class="card" style="padding:1.5rem;">
            <div style="font-size:0.875rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:0.5rem;">
                Conversieratio
            </div>
            <div style="font-size:2.5rem;font-weight:bold;color:#fcd34d;margin-bottom:0.5rem;">
                {{ $leadsMetrics['conversion_rate'] }}%
            </div>
            <div style="font-size:0.875rem;color:#64748b;">
                {{ $leadsMetrics['leads_converted'] }} van {{ $leadsMetrics['total_leads'] }} leads
            </div>
        </div>

        {{-- Confirmed Bookings Card --}}
        <div class="card" style="padding:1.5rem;">
            <div style="font-size:0.875rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:0.5rem;">
                Bevestigde Boekingen
            </div>
            <div style="font-size:2.5rem;font-weight:bold;color:#fcd34d;margin-bottom:0.5rem;">
                {{ $bookingsMetrics['confirmed_bookings'] }}
            </div>
            <div style="font-size:0.875rem;color:#64748b;">
                {{ $bookingsMetrics['upcoming_events_7days'] }} komend(e) week
            </div>
        </div>

        {{-- Revenue Card --}}
        <div class="card" style="padding:1.5rem;">
            <div style="font-size:0.875rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:0.5rem;">
                Totale Omzet
            </div>
            <div style="font-size:2.5rem;font-weight:bold;color:#fcd34d;margin-bottom:0.5rem;">
                €{{ number_format($revenueMetrics['total_revenue'], 2, ',', '.') }}
            </div>
            <div style="font-size:0.875rem;color:#64748b;">
                Gemiddeld: €{{ number_format($revenueMetrics['average_booking_value'], 2, ',', '.') }} per boeking
            </div>
        </div>

    </div>

    {{-- Charts Row --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(400px, 1fr));gap:1.25rem;margin-bottom:2rem;">

        {{-- Bookings Trend Chart --}}
        <div class="card" style="padding:1.5rem;">
            <h3 style="font-size:1rem;font-weight:600;margin:0 0 1rem 0;color:#111827;">
                Boekingen Trend (12 maanden)
            </h3>
            <canvas id="bookingsTrendChart" style="max-height:300px;"></canvas>
        </div>

        {{-- Revenue Trend Chart --}}
        <div class="card" style="padding:1.5rem;">
            <h3 style="font-size:1rem;font-weight:600;margin:0 0 1rem 0;color:#111827;">
                Omzet Trend (12 maanden)
            </h3>
            <canvas id="revenueTrendChart" style="max-height:300px;"></canvas>
        </div>

    </div>

    {{-- Bottom Row: Upcoming Events & Recent Leads --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(400px, 1fr));gap:1.25rem;margin-bottom:2rem;">

        {{-- Upcoming Events --}}
        <div class="card" style="padding:1.5rem;">
            <h3 style="font-size:1rem;font-weight:600;margin:0 0 1rem 0;color:#111827;">
                📅 Komende Events (volgende 7 dagen)
            </h3>
            @if(count($upcomingEvents) > 0)
                <div style="display:flex;flex-direction:column;gap:0.75rem;">
                    @foreach($upcomingEvents as $event)
                        <a href="{{ route('bookings.show', $event['id']) }}"
                           style="display:block;padding:0.75rem;background:#f9fafb;border-left:3px solid #fcd34d;border-radius:0.375rem;text-decoration:none;transition:all 0.2s;color:#111827;"
                           onmouseover="this.style.backgroundColor='#f3f4f6';this.style.boxShadow='0 1px 3px rgba(0,0,0,0.1)';"
                           onmouseout="this.style.backgroundColor='#f9fafb';this.style.boxShadow='none';">
                            <div style="font-weight:600;margin-bottom:0.25rem;">
                                {{ $event['booking_number'] }} - {{ $event['customer_name'] }}
                            </div>
                            <div style="font-size:0.875rem;color:#64748b;">
                                {{ $event['event_date'] }}
                                <span style="display:inline-block;margin-left:0.5rem;padding:0.125rem 0.5rem;background:@if($event['payment_status'] === 'paid') #dcfce7 @else #fee2e2 @endif;color:@if($event['payment_status'] === 'paid') #16a34a @else #dc2626 @endif;border-radius:9999px;font-size:0.75rem;">
                                    @if($event['payment_status'] === 'paid') ✓ Betaald @else ⚠ Openstaand @endif
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <div style="padding:2rem;text-align:center;color:#9ca3af;background:#f9fafb;border-radius:0.375rem;">
                    <p>Geen events de komende 7 dagen</p>
                </div>
            @endif
        </div>

        {{-- Recent Leads --}}
        <div class="card" style="padding:1.5rem;">
            <h3 style="font-size:1rem;font-weight:600;margin:0 0 1rem 0;color:#111827;">
                👥 Recente Leads
            </h3>
            @if(count($recentLeads) > 0)
                <div style="display:flex;flex-direction:column;gap:0.75rem;">
                    @foreach($recentLeads as $lead)
                        <a href="{{ route('leads.show', $lead['id']) }}"
                           style="display:block;padding:0.75rem;background:#f9fafb;border-left:3px solid #fcd34d;border-radius:0.375rem;text-decoration:none;transition:all 0.2s;color:#111827;"
                           onmouseover="this.style.backgroundColor='#f3f4f6';this.style.boxShadow='0 1px 3px rgba(0,0,0,0.1)';"
                           onmouseout="this.style.backgroundColor='#f9fafb';this.style.boxShadow='none';">
                            <div style="font-weight:600;margin-bottom:0.25rem;">
                                {{ $lead['lead_number'] }} - {{ $lead['name'] }}
                            </div>
                            <div style="font-size:0.875rem;color:#64748b;">
                                @if($lead['event_date'])
                                    📅 {{ $lead['event_date'] }}
                                @else
                                    Geen datum ingesteld
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <div style="padding:2rem;text-align:center;color:#9ca3af;background:#f9fafb;border-radius:0.375rem;">
                    <p>Geen recente leads</p>
                </div>
            @endif
        </div>

    </div>

    {{-- Conversion Funnel --}}
    <div class="card" style="padding:1.5rem;margin-bottom:2rem;">
        <h3 style="font-size:1rem;font-weight:600;margin:0 0 1.5rem 0;color:#111827;">
            🔄 Conversie Funnel
        </h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));gap:1rem;">

            <div style="padding:1rem;background:#f9fafb;border-radius:0.5rem;text-align:center;border-left:4px solid #fcd34d;">
                <div style="font-size:0.875rem;color:#64748b;margin-bottom:0.5rem;">Totaal Leads</div>
                <div style="font-size:2rem;font-weight:bold;color:#111827;">{{ $conversionFunnel['total_leads'] }}</div>
            </div>

            <div style="display:flex;align-items:center;justify-content:center;">
                <div style="font-size:2rem;color:#d1d5db;">→</div>
            </div>

            <div style="padding:1rem;background:#f9fafb;border-radius:0.5rem;text-align:center;border-left:4px solid #16a34a;">
                <div style="font-size:0.875rem;color:#64748b;margin-bottom:0.5rem;">Gewonnen</div>
                <div style="font-size:2rem;font-weight:bold;color:#16a34a;">{{ $conversionFunnel['leads_won'] }}</div>
            </div>

            <div style="display:flex;align-items:center;justify-content:center;">
                <div style="font-size:2rem;color:#d1d5db;">→</div>
            </div>

            <div style="padding:1rem;background:#f9fafb;border-radius:0.5rem;text-align:center;border-left:4px solid #fcd34d;">
                <div style="font-size:0.875rem;color:#64748b;margin-bottom:0.5rem;">Conversieratio</div>
                <div style="font-size:2rem;font-weight:bold;color:#fcd34d;">{{ $conversionFunnel['conversion_rate'] }}%</div>
            </div>

            <div style="padding:1rem;background:#f9fafb;border-radius:0.5rem;text-align:center;border-left:4px solid #dc2626;">
                <div style="font-size:0.875rem;color:#64748b;margin-bottom:0.5rem;">Afgewezen</div>
                <div style="font-size:2rem;font-weight:bold;color:#dc2626;">{{ $conversionFunnel['leads_lost'] }}</div>
            </div>

        </div>
    </div>

</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// ── Bookings Trend Chart ──────────────────────────────
const bookingsChartCanvas = document.getElementById('bookingsTrendChart');
if (bookingsChartCanvas) {
    const bookingsChartData = {!! $bookingsTrendChartData !!};
    new Chart(bookingsChartCanvas, {
        type: 'line',
        data: bookingsChartData,
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

// ── Revenue Trend Chart ──────────────────────────────
const revenueChartCanvas = document.getElementById('revenueTrendChart');
if (revenueChartCanvas) {
    const revenueChartData = {!! $revenueTrendChartData !!};
    new Chart(revenueChartCanvas, {
        type: 'line',
        data: revenueChartData,
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '€' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}
</script>
@endpush
