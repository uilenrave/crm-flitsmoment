@extends('layouts.app')
@section('title', 'Analytics Dashboard')

@section('content')
<div style="padding:0 1.5rem;">
    {{-- Dashboard Header --}}
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;">
        <h1 style="font-size:2rem;font-weight:bold;margin:0;">📊 Analytics Dashboard</h1>
        <div style="display:flex;gap:0.5rem;align-items:center;">
            {{-- BTW toggle --}}
            <div style="display:flex;align-items:center;gap:.5rem;padding:.35rem .75rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:.5rem;font-size:.8rem;">
                <span style="font-weight:600;color:#64748b;">BTW</span>
                <button id="btw-toggle"
                        onclick="toggleBtw()"
                        style="position:relative;width:40px;height:22px;border-radius:999px;border:none;cursor:pointer;transition:background .2s;background:#e2e8f0;flex-shrink:0;">
                    <span id="btw-knob" style="position:absolute;top:3px;left:3px;width:16px;height:16px;border-radius:50%;background:#fff;transition:transform .2s;box-shadow:0 1px 3px rgba(0,0,0,.2);"></span>
                </button>
                <span id="btw-label" style="font-weight:600;color:#dc2626;min-width:50px;">excl.</span>
            </div>
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
                €<span class="btw-amount" data-amount="{{ $revenueMetrics['total_revenue'] }}" data-decimals="2">{{ number_format($revenueMetrics['total_revenue'] / 1.21, 2, ',', '.') }}</span>
            </div>
            <div style="font-size:0.875rem;color:#64748b;margin-bottom:.3rem;">
                Gemiddeld: €<span class="btw-amount" data-amount="{{ $revenueMetrics['average_booking_value'] }}" data-decimals="2">{{ number_format($revenueMetrics['average_booking_value'] / 1.21, 2, ',', '.') }}</span> per boeking
            </div>
            @if($revenueMetrics['open_revenue'] > 0)
            <div style="font-size:0.875rem;color:#d97706;font-weight:500;">
                ⚠ €<span class="btw-amount" data-amount="{{ $revenueMetrics['open_revenue'] }}" data-decimals="2">{{ number_format($revenueMetrics['open_revenue'] / 1.21, 2, ',', '.') }}</span> openstaand
            </div>
            @endif
        </div>

    </div>

    {{-- Charts Row --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(400px, 1fr));gap:1.25rem;margin-bottom:2rem;">

        {{-- Bookings Trend Chart --}}
        <div class="card" style="padding:1.5rem;">
            <div style="display:flex;flex-wrap:wrap;align-items:baseline;gap:.75rem;margin-bottom:1rem;">
                <h3 style="font-size:1rem;font-weight:600;margin:0;color:#111827;">Boekingen per maand</h3>
                <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-left:auto;">
                    <span style="font-size:.75rem;font-weight:600;padding:.15rem .55rem;border-radius:9999px;background:#fef3c7;color:#b45309;">{{ now()->year }}: {{ $trendData['total_bookings_y0'] }}</span>
                    <span style="font-size:.75rem;font-weight:600;padding:.15rem .55rem;border-radius:9999px;background:#f1f5f9;color:#475569;">{{ now()->year - 1 }}: {{ $trendData['total_bookings_y1'] }}</span>
                    <span style="font-size:.75rem;font-weight:600;padding:.15rem .55rem;border-radius:9999px;background:#f8fafc;color:#94a3b8;">{{ now()->year - 2 }}: {{ $trendData['total_bookings_y2'] }}</span>
                </div>
            </div>
            <canvas id="bookingsTrendChart" style="max-height:300px;"></canvas>
        </div>

        {{-- Revenue Trend Chart --}}
        <div class="card" style="padding:1.5rem;">
            <div style="display:flex;flex-wrap:wrap;align-items:baseline;gap:.75rem;margin-bottom:1rem;">
                <h3 style="font-size:1rem;font-weight:600;margin:0;color:#111827;">Omzet per maand</h3>
                <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-left:auto;">
                    <span style="font-size:.75rem;font-weight:600;padding:.15rem .55rem;border-radius:9999px;background:#fef3c7;color:#b45309;">{{ now()->year }}: €<span class="btw-amount" data-amount="{{ $trendData['total_revenue_y0'] }}" data-decimals="0">{{ number_format($trendData['total_revenue_y0'] / 1.21, 0, ',', '.') }}</span></span>
                    <span style="font-size:.75rem;font-weight:600;padding:.15rem .55rem;border-radius:9999px;background:#f1f5f9;color:#475569;">{{ now()->year - 1 }}: €<span class="btw-amount" data-amount="{{ $trendData['total_revenue_y1'] }}" data-decimals="0">{{ number_format($trendData['total_revenue_y1'] / 1.21, 0, ',', '.') }}</span></span>
                    <span style="font-size:.75rem;font-weight:600;padding:.15rem .55rem;border-radius:9999px;background:#f8fafc;color:#94a3b8;">{{ now()->year - 2 }}: €<span class="btw-amount" data-amount="{{ $trendData['total_revenue_y2'] }}" data-decimals="0">{{ number_format($trendData['total_revenue_y2'] / 1.21, 0, ',', '.') }}</span></span>
                </div>
            </div>
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

    {{-- Medewerker Uren --}}
    @if(!empty($staffHoursStats['per_staff']) || $staffHoursStats['total_pending_entries'] > 0)
    <div class="card" style="padding:1.5rem;margin-bottom:2rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
            <h3 style="font-size:1rem;font-weight:600;margin:0;color:#111827;">⏱ Medewerker uren</h3>
            <a href="{{ route('staff-hours.index') }}" style="font-size:.8rem;color:#7c3aed;font-weight:600;text-decoration:none;">Alle uren beheren →</a>
        </div>

        @if($staffHoursStats['total_pending_entries'] > 0)
        <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:.75rem;padding:.65rem 1rem;margin-bottom:1rem;display:flex;align-items:center;gap:.65rem;">
            <span>⏳</span>
            <span style="font-size:.875rem;font-weight:600;color:#92400e;">
                {{ $staffHoursStats['total_pending_entries'] }} {{ $staffHoursStats['total_pending_entries'] === 1 ? 'inzending wacht' : 'inzendingen wachten' }} op goedkeuring
            </span>
            <a href="{{ route('staff-hours.index', ['status' => 'pending']) }}" style="margin-left:auto;font-size:.8rem;color:#92400e;font-weight:700;text-decoration:none;white-space:nowrap;">Beoordelen →</a>
        </div>
        @endif

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(120px, 1fr));gap:.75rem;margin-bottom:1rem;">
            <div style="padding:.875rem 1rem;background:#fef3c7;border:1px solid #fcd34d;border-radius:.75rem;text-align:center;">
                <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:#92400e;letter-spacing:.04em;margin-bottom:.25rem;">Openstaand</div>
                <div style="font-size:1.5rem;font-weight:700;color:#92400e;">{{ number_format($staffHoursStats['pending_hours'], 1, ',', '') }} u</div>
            </div>
            <div style="padding:.875rem 1rem;background:#dcfce7;border:1px solid #86efac;border-radius:.75rem;text-align:center;">
                <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:#166534;letter-spacing:.04em;margin-bottom:.25rem;">Goedgekeurd</div>
                <div style="font-size:1.5rem;font-weight:700;color:#166534;">{{ number_format($staffHoursStats['approved_hours'], 1, ',', '') }} u</div>
            </div>
            <div style="padding:.875rem 1rem;background:#ede9fe;border:1px solid #c4b5fd;border-radius:.75rem;text-align:center;">
                <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:#5b21b6;letter-spacing:.04em;margin-bottom:.25rem;">Uitbetaald</div>
                <div style="font-size:1.5rem;font-weight:700;color:#5b21b6;">{{ number_format($staffHoursStats['paid_hours'], 1, ',', '') }} u</div>
            </div>
            @if(($staffHoursStats['total_km_allowance'] ?? 0) > 0)
            <div style="padding:.875rem 1rem;background:#f0f9ff;border:1px solid #bae6fd;border-radius:.75rem;text-align:center;">
                <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:#0369a1;letter-spacing:.04em;margin-bottom:.25rem;">Km-vergoeding</div>
                <div style="font-size:1.5rem;font-weight:700;color:#0369a1;">€ {{ number_format($staffHoursStats['total_km_allowance'], 2, ',', '') }}</div>
            </div>
            @endif
        </div>

        @if(!empty($staffHoursStats['per_staff']))
        <div style="border-top:1px solid #f1f5f9;padding-top:.875rem;">
            <div style="display:flex;flex-direction:column;gap:.4rem;">
                @foreach($staffHoursStats['per_staff'] as $person)
                <div style="display:flex;align-items:center;gap:.75rem;padding:.5rem .75rem;background:#f8fafc;border-radius:.65rem;font-size:.85rem;">
                    <span style="font-weight:600;color:#1e293b;flex:1;">{{ $person['name'] }}</span>
                    @if($person['pending_hours'] > 0)
                    <span style="background:#fef3c7;color:#92400e;padding:.15rem .5rem;border-radius:999px;font-size:.72rem;font-weight:700;">⏳ {{ number_format($person['pending_hours'], 1, ',', '') }}u</span>
                    @endif
                    @if($person['approved_hours'] > 0)
                    <span style="background:#dcfce7;color:#166534;padding:.15rem .5rem;border-radius:999px;font-size:.72rem;font-weight:700;">✅ {{ number_format($person['approved_hours'], 1, ',', '') }}u</span>
                    @endif
                    @if($person['paid_hours'] > 0)
                    <span style="background:#ede9fe;color:#5b21b6;padding:.15rem .5rem;border-radius:999px;font-size:.72rem;font-weight:700;">💸 {{ number_format($person['paid_hours'], 1, ',', '') }}u</span>
                    @endif
                    @if(($person['km_allowance'] ?? 0) > 0)
                    <span style="background:#f0f9ff;color:#0369a1;padding:.15rem .5rem;border-radius:999px;font-size:.72rem;font-weight:700;">🚗 €{{ number_format($person['km_allowance'], 2, ',', '') }}</span>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
    @endif

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
let revenueChart = null;
const revenueChartDataRaw = {!! $revenueTrendChartData !!};

if (revenueChartCanvas) {
    revenueChart = new Chart(revenueChartCanvas, {
        type: 'line',
        data: JSON.parse(JSON.stringify(revenueChartDataRaw)), // deep clone
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { display: true, position: 'top' } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: v => '€' + v.toLocaleString('nl-NL') }
                }
            }
        }
    });
}

// ── BTW toggle ────────────────────────────────────────
const BTW = 1.21;
let btwIncl = false; // standaard excl BTW

// Pas de chart direct aan op laden (zet naar excl)
if (revenueChart) {
    revenueChart.data.datasets.forEach(ds => {
        ds.data = ds.data.map(v => v !== null ? +(v / BTW).toFixed(2) : null);
    });
    revenueChart.update();
}

function toggleBtw() {
    btwIncl = !btwIncl;
    const toggle = document.getElementById('btw-toggle');
    const knob   = document.getElementById('btw-knob');
    const label  = document.getElementById('btw-label');

    toggle.style.background = btwIncl ? '#16a34a' : '#e2e8f0';
    knob.style.transform    = btwIncl ? 'translateX(18px)' : 'translateX(0)';
    label.textContent       = btwIncl ? 'incl.' : 'excl.';
    label.style.color       = btwIncl ? '#16a34a' : '#dc2626';

    // Bedragen in kaarten
    document.querySelectorAll('.btw-amount').forEach(el => {
        const base  = parseFloat(el.dataset.amount);
        const value = btwIncl ? base : base / BTW;
        const dec   = parseInt(el.dataset.decimals ?? '2');
        el.textContent = value.toLocaleString('nl-NL', {
            minimumFractionDigits: dec,
            maximumFractionDigits: dec,
        });
    });

    // Chart datasets: reset naar origineel en dan factor toepassen
    if (revenueChart) {
        revenueChart.data.datasets.forEach((ds, i) => {
            const orig = revenueChartDataRaw.datasets[i].data;
            ds.data = orig.map(v => v !== null ? +(btwIncl ? v : v / BTW).toFixed(2) : null);
        });
        revenueChart.update();
    }
}
</script>
@endpush
