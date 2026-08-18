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

    @php
        $currentYear = $yearly['current_year'];
        $palette = ['#2563eb', '#64748b', '#16a34a', '#db2777', '#0891b2', '#9333ea', '#ca8a04', '#dc2626'];
        $yearColors = [];
        foreach ($yearly['years'] as $i => $yr) {
            $yearColors[$yr] = $yr === $currentYear ? '#f59e0b' : $palette[$i % count($palette)];
        }
    @endphp

    {{-- Jaartoggle: bepaalt welke jaren zichtbaar zijn in de grafieken én de openstaand-tabel --}}
    <style>
        .year-chip { display:inline-flex;align-items:center;gap:.35rem;font-size:.8rem;font-weight:600;padding:.25rem .7rem;border-radius:9999px;cursor:pointer;border:1px solid #e2e8f0;color:#94a3b8;background:#fff;user-select:none; }
        .year-chip .year-dot { width:.6rem;height:.6rem;border-radius:50%;background:#cbd5e1; }
        .year-chip:has(input:checked) { color:var(--yc);border-color:var(--yc);background:color-mix(in srgb, var(--yc) 10%, #fff); }
        .year-chip:has(input:checked) .year-dot { background:var(--yc); }
    </style>
    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:.5rem;margin-bottom:1rem;">
        <span style="font-size:.8rem;font-weight:600;color:#64748b;">Jaren vergelijken:</span>
        @foreach($yearly['years'] as $yr)
            <label class="year-chip" style="--yc:{{ $yearColors[$yr] }};">
                <input type="checkbox" class="year-toggle" value="{{ $yr }}" {{ in_array($yr, $yearly['default_years'], true) ? 'checked' : '' }} onchange="applyYearFilter()" style="display:none;">
                <span class="year-dot"></span>{{ $yr }}
            </label>
        @endforeach
    </div>

    {{-- Charts Row --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(400px, 1fr));gap:1.25rem;margin-bottom:2rem;">

        {{-- Bookings Trend Chart --}}
        <div class="card" style="padding:1.5rem;">
            <div style="display:flex;flex-wrap:wrap;align-items:baseline;gap:.75rem;margin-bottom:1rem;">
                <h3 style="font-size:1rem;font-weight:600;margin:0;color:#111827;">Boekingen per maand</h3>
                <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-left:auto;">
                    @foreach($yearly['years'] as $yr)
                    <span class="chart-year-chip" data-year="{{ $yr }}" style="font-size:.75rem;font-weight:600;padding:.15rem .55rem;border-radius:9999px;background:{{ $yearColors[$yr] }}1a;color:{{ $yearColors[$yr] }};{{ in_array($yr, $yearly['default_years'], true) ? '' : 'display:none;' }}">{{ $yr }}: {{ $yearly['total_bookings'][$yr] }}</span>
                    @endforeach
                </div>
            </div>
            <canvas id="bookingsTrendChart" style="max-height:300px;"></canvas>
        </div>

        {{-- Revenue Trend Chart --}}
        <div class="card" style="padding:1.5rem;">
            <div style="display:flex;flex-wrap:wrap;align-items:baseline;gap:.75rem;margin-bottom:1rem;">
                <h3 style="font-size:1rem;font-weight:600;margin:0;color:#111827;">Omzet per maand</h3>
                <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-left:auto;">
                    @foreach($yearly['years'] as $yr)
                    <span class="chart-year-chip" data-year="{{ $yr }}" style="font-size:.75rem;font-weight:600;padding:.15rem .55rem;border-radius:9999px;background:{{ $yearColors[$yr] }}1a;color:{{ $yearColors[$yr] }};{{ in_array($yr, $yearly['default_years'], true) ? '' : 'display:none;' }}">{{ $yr }}: €<span class="btw-amount" data-amount="{{ $yearly['total_revenue'][$yr] }}" data-decimals="0">{{ number_format($yearly['total_revenue'][$yr] / 1.21, 0, ',', '.') }}</span></span>
                    @endforeach
                </div>
            </div>
            <canvas id="revenueTrendChart" style="max-height:300px;"></canvas>
        </div>

    </div>

    {{-- Openstaande betalingen & facturatie --}}
    @php
        $oTot = $outstanding['total'];
        $oCur = $outstanding['by_year'][$currentYear] ?? ['outstanding_amount'=>0,'outstanding_count'=>0];
        $eur0 = fn($v) => number_format(((float)$v)/1.21, 0, ',', '.');
        $eur2 = fn($v) => number_format(((float)$v)/1.21, 2, ',', '.');
    @endphp
    <div class="card" style="padding:1.5rem;margin-bottom:2rem;">
        <h3 style="font-size:1rem;font-weight:600;margin:0 0 1rem;color:#111827;">💶 Openstaande betalingen &amp; facturatie</h3>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:1rem;margin-bottom:1.25rem;">
            <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:.6rem;padding:.9rem 1rem;">
                <div style="font-size:.72rem;color:#991b1b;font-weight:600;">Totaal openstaand</div>
                <div style="font-size:1.5rem;font-weight:800;color:#dc2626;line-height:1.2;">€<span class="btw-amount" data-amount="{{ $oTot['outstanding_amount'] }}" data-decimals="2">{{ $eur2($oTot['outstanding_amount']) }}</span></div>
                <div style="font-size:.72rem;color:#b91c1c;">{{ $oTot['outstanding_count'] }} boekingen</div>
            </div>
            <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:.6rem;padding:.9rem 1rem;">
                <div style="font-size:.72rem;color:#92400e;font-weight:600;">Openstaand {{ $currentYear }}</div>
                <div style="font-size:1.5rem;font-weight:800;color:#d97706;line-height:1.2;">€<span class="btw-amount" data-amount="{{ $oCur['outstanding_amount'] }}" data-decimals="2">{{ $eur2($oCur['outstanding_amount']) }}</span></div>
                <div style="font-size:.72rem;color:#b45309;">{{ $oCur['outstanding_count'] }} boekingen</div>
            </div>
            <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:.6rem;padding:.9rem 1rem;">
                <div style="font-size:.72rem;color:#991b1b;font-weight:600;">Nog te factureren</div>
                <div style="font-size:1.35rem;font-weight:800;color:#dc2626;line-height:1.2;">{{ $oTot['to_invoice_count'] }}×</div>
                <div style="font-size:.72rem;color:#b91c1c;">€<span class="btw-amount" data-amount="{{ $oTot['to_invoice_amount'] }}" data-decimals="0">{{ $eur0($oTot['to_invoice_amount']) }}</span></div>
            </div>
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:.6rem;padding:.9rem 1rem;">
                <div style="font-size:.72rem;color:#166534;font-weight:600;">Gefactureerd / niet nodig</div>
                <div style="font-size:1.35rem;font-weight:800;color:#16a34a;line-height:1.2;">{{ $oTot['invoiced_count'] }}×</div>
                <div style="font-size:.72rem;color:#15803d;">€<span class="btw-amount" data-amount="{{ $oTot['invoiced_amount'] }}" data-decimals="0">{{ $eur0($oTot['invoiced_amount']) }}</span></div>
            </div>
        </div>

        <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:.85rem;min-width:520px;">
            <thead><tr style="text-align:left;color:#64748b;font-size:.7rem;text-transform:uppercase;letter-spacing:.03em;">
                <th style="padding:.5rem;">Jaar</th>
                <th style="padding:.5rem;text-align:right;">Openstaand</th>
                <th style="padding:.5rem;text-align:right;"># boekingen</th>
                <th style="padding:.5rem;text-align:right;">Nog te factureren</th>
                <th style="padding:.5rem;text-align:right;">Gefactureerd / nvt</th>
            </tr></thead>
            <tbody>
            @foreach($yearly['years'] as $yr)
                @php $o = $outstanding['by_year'][$yr] ?? null; @endphp
                <tr class="outstanding-year-row" data-year="{{ $yr }}" style="border-top:1px solid #f1f5f9;{{ in_array($yr, $yearly['default_years'], true) ? '' : 'display:none;' }}">
                    <td style="padding:.5rem;font-weight:600;"><span style="display:inline-block;width:.55rem;height:.55rem;border-radius:50%;background:{{ $yearColors[$yr] }};margin-right:.4rem;"></span>{{ $yr }}</td>
                    <td style="padding:.5rem;text-align:right;font-weight:700;color:#dc2626;">€<span class="btw-amount" data-amount="{{ $o['outstanding_amount'] ?? 0 }}" data-decimals="2">{{ $eur2($o['outstanding_amount'] ?? 0) }}</span></td>
                    <td style="padding:.5rem;text-align:right;">{{ $o['outstanding_count'] ?? 0 }}</td>
                    <td style="padding:.5rem;text-align:right;">{{ $o['to_invoice_count'] ?? 0 }}× · €<span class="btw-amount" data-amount="{{ $o['to_invoice_amount'] ?? 0 }}" data-decimals="0">{{ $eur0($o['to_invoice_amount'] ?? 0) }}</span></td>
                    <td style="padding:.5rem;text-align:right;color:#16a34a;">{{ $o['invoiced_count'] ?? 0 }}× · €<span class="btw-amount" data-amount="{{ $o['invoiced_amount'] ?? 0 }}" data-decimals="0">{{ $eur0($o['invoiced_amount'] ?? 0) }}</span></td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
                <tr style="border-top:2px solid #e2e8f0;font-weight:700;">
                    <td style="padding:.5rem;">Totaal (alle jaren)</td>
                    <td style="padding:.5rem;text-align:right;color:#dc2626;">€<span class="btw-amount" data-amount="{{ $oTot['outstanding_amount'] }}" data-decimals="2">{{ $eur2($oTot['outstanding_amount']) }}</span></td>
                    <td style="padding:.5rem;text-align:right;">{{ $oTot['outstanding_count'] }}</td>
                    <td style="padding:.5rem;text-align:right;">{{ $oTot['to_invoice_count'] }}× · €<span class="btw-amount" data-amount="{{ $oTot['to_invoice_amount'] }}" data-decimals="0">{{ $eur0($oTot['to_invoice_amount']) }}</span></td>
                    <td style="padding:.5rem;text-align:right;color:#16a34a;">{{ $oTot['invoiced_count'] }}× · €<span class="btw-amount" data-amount="{{ $oTot['invoiced_amount'] }}" data-decimals="0">{{ $eur0($oTot['invoiced_amount']) }}</span></td>
                </tr>
            </tfoot>
        </table>
        </div>
        <p style="font-size:.72rem;color:#94a3b8;margin-top:.7rem;">Openstaand = restbedrag van niet-betaalde boekingen (excl. geannuleerd/terugbetaald). "Gefactureerd / nvt" = factuur aangemaakt óf gemarkeerd als niet nodig. Per jaar op basis van de eventdatum; rijen volgen de jaartoggle. Bedragen excl./incl. btw volgens de schakelaar bovenaan.</p>
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
    window.__bookingsChart = new Chart(bookingsChartCanvas, {
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
window.__revenueChart = revenueChart;

// ── Jaartoggle: toon/verberg jaren in beide grafieken, de chart-chips en de openstaand-tabel ──
function applyYearFilter() {
    const checked = new Set([...document.querySelectorAll('.year-toggle:checked')].map(c => c.value));
    [window.__bookingsChart, window.__revenueChart].forEach(ch => {
        if (!ch) return;
        ch.data.datasets.forEach(ds => { ds.hidden = !checked.has(String(ds.label)); });
        ch.update();
    });
    document.querySelectorAll('.chart-year-chip').forEach(el => {
        el.style.display = checked.has(el.dataset.year) ? '' : 'none';
    });
    document.querySelectorAll('.outstanding-year-row').forEach(el => {
        el.style.display = checked.has(el.dataset.year) ? '' : 'none';
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
