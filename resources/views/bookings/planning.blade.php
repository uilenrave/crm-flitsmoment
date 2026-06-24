@extends('layouts.app')

@section('title', 'Boekingen - Planning')

@section('content')
<style>
    .gantt-wrap {
        overflow-x: auto;
        border: 1px solid #e2e8f0;
        border-radius: .75rem;
        background: #fff;
        box-shadow: 0 1px 3px rgba(0,0,0,.06);
    }
    .gantt-table {
        border-collapse: collapse;
        min-width: 100%;
        font-size: .8rem;
    }
    .gantt-table th, .gantt-table td {
        border: 1px solid #e2e8f0;
        padding: 0;
        white-space: nowrap;
    }
    /* Header: maand labels */
    .gantt-month-cell {
        background: #f8fafc;
        font-weight: 700;
        font-size: .7rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #475569;
        padding: .4rem .5rem;
        border-bottom: 2px solid #cbd5e1;
        text-align: center;
    }
    /* Header: dag labels */
    .gantt-day-cell {
        background: #f8fafc;
        text-align: center;
        padding: .35rem .2rem;
        min-width: 38px;
        width: 38px;
        font-size: .72rem;
        color: #64748b;
        border-bottom: 2px solid #cbd5e1;
    }
    .gantt-day-cell.today {
        background: #eff6ff;
        color: #1d4ed8;
        font-weight: 700;
    }
    .gantt-day-cell.weekend {
        background: #fafafa;
        color: #94a3b8;
    }
    /* Rij: photobooth label */
    .gantt-label {
        background: #f8fafc;
        font-weight: 600;
        font-size: .8rem;
        color: #1e293b;
        padding: .5rem .875rem;
        min-width: 160px;
        max-width: 160px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        border-right: 2px solid #cbd5e1;
        vertical-align: middle;
    }
    /* Vrije dag cel */
    .gantt-free {
        height: 44px;
        background: #fff;
    }
    .gantt-free.weekend-col {
        background: #fafafa;
    }
    .gantt-free.today-col {
        background: #eff6ff;
    }
    /* Boeking blok cel — absolute positioning toelaten */
    .gantt-booking-cell {
        height: 44px;
        padding: 0;
        vertical-align: middle;
        position: relative;
        cursor: pointer;
    }
    .gantt-booking-block {
        position: absolute;
        top: 4px;
        bottom: 4px;
        padding: .15rem .4rem;
        display: flex;
        flex-direction: column;
        justify-content: center;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        border-left: 3px solid;
        transition: filter .15s;
        text-decoration: none;
    }
    .gantt-booking-block:hover {
        filter: brightness(.93);
    }
    .gantt-booking-block .b-num {
        font-size: .7rem;
        font-weight: 700;
        opacity: .9;
        line-height: 1.2;
    }
    .gantt-booking-block .b-name {
        font-size: .72rem;
        line-height: 1.2;
        opacity: .85;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    /* Conflict blok */
    .gantt-conflict-cell { background: #fff1f2; position: relative; }
    .gantt-conflict-block {
        position: absolute;
        top: 4px;
        bottom: 4px;
        padding: .2rem .4rem;
        display: flex;
        flex-direction: column;
        justify-content: center;
        overflow: hidden;
        white-space: nowrap;
        border-left: 3px solid #ef4444;
        background: #fee2e2;
        text-decoration: none;
    }
    .gantt-conflict-block::after {
        content: '⚠️';
        position: absolute;
        top: 2px;
        right: 4px;
        font-size: .65rem;
    }
    .gantt-conflict-block .b-num { font-size: .68rem; font-weight: 700; color: #dc2626; line-height: 1.2; }
    .gantt-conflict-block .b-name { font-size: .68rem; color: #991b1b; line-height: 1.2; overflow: hidden; text-overflow: ellipsis; }
    .conflict-extra { font-size: .62rem; color: #dc2626; background: #fecaca; border-radius: 3px; padding: .05rem .3rem; margin-top: .1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    /* Hover tooltip */
    .gantt-booking-cell { position: relative; }
    .booking-tooltip {
        display: none;
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        z-index: 9999;
        background: #1e293b;
        color: #f1f5f9;
        border-radius: .6rem;
        padding: .75rem 1rem;
        min-width: 220px;
        max-width: 300px;
        font-size: .78rem;
        line-height: 1.5;
        box-shadow: 0 8px 24px rgba(0,0,0,.22);
        pointer-events: none;
        white-space: normal;
    }
    .booking-tooltip::before {
        content: '';
        position: absolute;
        top: -5px;
        left: 14px;
        border: 5px solid transparent;
        border-bottom-color: #1e293b;
        border-top: none;
    }
    /* tooltip content hidden in DOM, shown via JS overlay */
    #gantt-tooltip-overlay {
        background: #1e293b;
        color: #f1f5f9;
        border-radius: .6rem;
        padding: .75rem 1rem;
        min-width: 220px;
        max-width: 300px;
        font-size: .78rem;
        line-height: 1.5;
        box-shadow: 0 8px 24px rgba(0,0,0,.3);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }
    .tooltip-row { display: flex; gap: .4rem; margin-bottom: .2rem; align-items: flex-start; }
    .tooltip-label { color: #94a3b8; flex-shrink: 0; min-width: 60px; }
    .tooltip-val { color: #f1f5f9; font-weight: 500; }
    .tooltip-divider { border: none; border-top: 1px solid #334155; margin: .4rem 0; }
    .tooltip-extras { color: #fcd34d; font-size: .72rem; margin-top: .25rem; }

    /* Status kleuren */
    .status-confirmed  { background: #dbeafe; color: #1e40af; border-color: #3b82f6; }
    .status-completed  { background: #dcfce7; color: #166534; border-color: #22c55e; }
    .status-cancelled  { background: #fee2e2; color: #991b1b; border-color: #ef4444; }
    .status-no_show    { background: #fef9c3; color: #713f12; border-color: #eab308; }
    /* Voorraad indicator */
    .stock-badge {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        font-size: .7rem;
        font-weight: 600;
        padding: .15rem .45rem;
        border-radius: 999px;
        margin-left: .5rem;
    }
    .stock-ok    { background: #dcfce7; color: #166534; }
    .stock-warn  { background: #fef9c3; color: #92400e; }
    .stock-full  { background: #fee2e2; color: #991b1b; }
</style>

<div style="padding:0 1.5rem;">
    {{-- Tabs --}}
    @include('bookings.tabs')

    {{-- Header + Navigatie --}}
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;gap:1rem;flex-wrap:wrap;">
        <div>
            <h2 style="font-size:1.5rem;font-weight:700;margin:0;color:#0f172a;">
                🗓️ Planning — Photobooths
            </h2>
            <p style="margin:.25rem 0 0;font-size:.875rem;color:#64748b;">
                {{ $startDate->isoFormat('D MMM') }} – {{ $endDate->isoFormat('D MMM YYYY') }}
                &nbsp;·&nbsp; {{ $weeks }} weken
            </p>
        </div>

        <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
            {{-- Periode keuze --}}
            <div style="display:flex;gap:.25rem;">
                @foreach([1,2,4,8,12] as $w)
                    <a href="{{ route('bookings.planning', ['weeks' => $w, 'offset' => 0]) }}"
                       style="padding:.4rem .75rem;border-radius:.375rem;font-size:.8rem;font-weight:600;text-decoration:none;border:1px solid;{{ $weeks == $w ? 'background:#7c3aed;color:#fff;border-color:#7c3aed;' : 'background:#fff;color:#374151;border-color:#d1d5db;' }}">
                        {{ $w }}w
                    </a>
                @endforeach
            </div>

            {{-- Navigatie --}}
            <a href="{{ route('bookings.planning', ['weeks' => $weeks, 'offset' => $prevOffset]) }}"
               style="display:inline-flex;align-items:center;padding:.4rem .75rem;border:1px solid #d1d5db;border-radius:.375rem;background:#fff;color:#374151;font-size:.875rem;font-weight:500;text-decoration:none;">
                ← Vorige
            </a>
            <a href="{{ route('bookings.planning', ['weeks' => $weeks]) }}"
               style="display:inline-flex;align-items:center;padding:.4rem .875rem;border:1px solid #d1d5db;border-radius:.375rem;background:#fff;color:#374151;font-size:.875rem;font-weight:500;text-decoration:none;">
                Vandaag
            </a>
            <a href="{{ route('bookings.planning', ['weeks' => $weeks, 'offset' => $nextOffset]) }}"
               style="display:inline-flex;align-items:center;padding:.4rem .75rem;border:1px solid #d1d5db;border-radius:.375rem;background:#fff;color:#374151;font-size:.875rem;font-weight:500;text-decoration:none;">
                Volgende →
            </a>
        </div>
    </div>

    @php $maandGroepen = $days->groupBy(fn($d) => $d->format('Y-m')); @endphp

    @if($photobooths->isEmpty())
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:.75rem;padding:3rem;text-align:center;color:#64748b;">
            <div style="font-size:2.5rem;margin-bottom:.75rem;">📦</div>
            <p style="font-size:1rem;font-weight:600;margin:0 0 .5rem;">Geen photobooth assets gevonden</p>
            <p style="font-size:.875rem;margin:0 0 1rem;">Voeg eerst photobooths toe in Voorraad &gt; Nieuw product (categorie: Photobooth).</p>
            <a href="{{ route('assets.create') }}" style="display:inline-flex;padding:.5rem 1.25rem;background:#7c3aed;color:#fff;border-radius:.5rem;font-size:.875rem;font-weight:600;text-decoration:none;">
                + Photobooth toevoegen
            </a>
        </div>
    @else

        {{-- ⚠️ Conflict waarschuwing --}}
        @if(! empty($conflicts))
        <div style="background:#fff1f2;border:1.5px solid #fca5a5;border-radius:.75rem;padding:1rem 1.25rem;margin-bottom:1.25rem;">
            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.6rem;">
                <span style="font-size:1.1rem;">⚠️</span>
                <strong style="color:#dc2626;font-size:.9rem;">{{ count($conflicts) === 1 ? '1 dubbele boeking' : count($conflicts) . ' dubbele boekingen' }} gedetecteerd</strong>
                <span style="font-size:.8rem;color:#ef4444;">— dezelfde unit is meerdere keren geboekt op overlappende data.</span>
            </div>
            @foreach($conflicts as $c)
            <div style="font-size:.8rem;color:#991b1b;padding:.35rem .5rem;background:#fee2e2;border-radius:.4rem;margin-bottom:.35rem;display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
                <strong>{{ $c['label'] }}:</strong>
                <a href="{{ route('bookings.show', $c['a']) }}" style="color:#dc2626;font-weight:600;">{{ $c['a']->booking_number }} ({{ $c['a']->customer_name }})</a>
                <span>en</span>
                <a href="{{ route('bookings.show', $c['b']) }}" style="color:#dc2626;font-weight:600;">{{ $c['b']->booking_number }} ({{ $c['b']->customer_name }})</a>
                <span style="color:#b91c1c;">— open één van de boekingen en wijs een andere unit toe.</span>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Gantt Tabel --}}
        <div class="gantt-wrap">
            <table class="gantt-table">

                {{-- Maand header --}}
                <thead>
                    <tr>
                        <th class="gantt-label" style="border-right:2px solid #cbd5e1;background:#f8fafc;"></th>
                        @foreach($maandGroepen as $ym => $groepDagen)
                            <th class="gantt-month-cell" colspan="{{ $groepDagen->count() }}">
                                {{ $groepDagen->first()->isoFormat('MMMM YYYY') }}
                            </th>
                        @endforeach
                    </tr>
                    <tr>
                        {{-- Lege hoek --}}
                        <th class="gantt-label" style="border-right:2px solid #cbd5e1;background:#f8fafc;font-size:.7rem;color:#94a3b8;font-weight:500;padding:.5rem .875rem;">Photobooth</th>
                        {{-- Dag kolommen --}}
                        @foreach($days as $day)
                            @php
                                $isToday   = $day->isToday();
                                $isWeekend = $day->isWeekend();
                                $dagNaam   = $day->isoFormat('dd');
                                $dagNum    = $day->format('j');
                            @endphp
                            <th class="gantt-day-cell {{ $isToday ? 'today' : ($isWeekend ? 'weekend' : '') }}">
                                <div>{{ $dagNaam }}</div>
                                <div style="font-weight:700;font-size:.78rem;">{{ $dagNum }}</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>

                {{-- Rijen per photobooth --}}
                <tbody>
                    @foreach($rows as $row)
                        @php $pb = $row['photobooth']; @endphp
                        <tr>
                            {{-- Label kolom --}}
                            <td class="gantt-label" style="vertical-align:middle;">
                                <div style="font-weight:600;font-size:.8rem;color:#1e293b;">{{ $row['label'] }}</div>
                                @if($row['total_slots'] > 1)
                                <div style="font-size:.68rem;color:#94a3b8;margin-top:.1rem;">
                                    Unit {{ $row['slot'] }} / {{ $row['total_slots'] }}
                                </div>
                                @endif
                            </td>

                            {{-- Dag cellen (per dag, kunnen meerdere boekingen tonen) --}}
                            @foreach($row['segments'] as $segIdx => $seg)
                                @php
                                    $day       = $days[$segIdx] ?? null;
                                    $isToday   = $day && $day->isToday();
                                    $isWeekend = $day && $day->isWeekend();
                                @endphp

                                @if(empty($seg['bookings']))
                                    <td class="gantt-free {{ $isToday ? 'today-col' : ($isWeekend ? 'weekend-col' : '') }}"></td>
                                @else
                                    <td class="gantt-booking-cell {{ $seg['has_conflict'] ? 'gantt-conflict-cell' : '' }}"
                                        @if($seg['has_conflict']) title="⚠️ Tijdconflict — twee boekingen overlappen elkaar in tijd" @endif>
                                        @foreach($seg['bookings'] as $b)
                                            @php
                                                $booking      = $b['booking'];
                                                $startPct     = $b['start_pct'];
                                                $endPct       = $b['end_pct'];
                                                $statusClass  = 'status-' . $booking->status;
                                                $blockClass   = $seg['has_conflict'] ? 'gantt-conflict-block' : 'gantt-booking-block ' . $statusClass;

                                                // Bar overlapt celranden voor naadloze multi-day weergave
                                                $leftPos    = $startPct > 0 ? $startPct . '%' : '-1px';
                                                $rightPos   = $endPct   < 100 ? (100 - $endPct) . '%' : '-1px';
                                                $radL       = $startPct > 0 ? '6px' : '0';
                                                $radR       = $endPct   < 100 ? '6px' : '0';
                                                $radius     = "border-top-left-radius:{$radL};border-bottom-left-radius:{$radL};border-top-right-radius:{$radR};border-bottom-right-radius:{$radR};";
                                            @endphp
                                            <a href="{{ route('bookings.show', $booking) }}"
                                               class="{{ $blockClass }}"
                                               style="left:{{ $leftPos }};right:{{ $rightPos }};{{ $radius }}"
                                               title="{{ $booking->booking_number }} — {{ $booking->customer_name }}">
                                                @if($b['is_first_day'])
                                                    <span class="b-num">{{ $seg['has_conflict'] ? '⚠️ ' : '' }}{{ $booking->booking_number }}@if($booking->booking_type === 'to_go') <span style="font-size:.65rem;">🚗</span>@endif</span>
                                                    <span class="b-name">{{ $booking->customer_name }}</span>
                                                @endif
                                            </a>
                                            @if($b['is_first_day'])
                                                @include('bookings._planning_tooltip', ['booking' => $booking])
                                            @endif
                                        @endforeach
                                    </td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Legende --}}
        <div style="display:flex;gap:1rem;align-items:center;margin-top:1rem;flex-wrap:wrap;">
            <span style="font-size:.75rem;font-weight:600;color:#64748b;">Legende:</span>
            <span class="status-confirmed" style="display:inline-block;padding:.2rem .6rem;border-left:3px solid #3b82f6;border-radius:6px;font-size:.72rem;font-weight:600;">Bevestigd</span>
            <span class="status-completed" style="display:inline-block;padding:.2rem .6rem;border-left:3px solid #22c55e;border-radius:6px;font-size:.72rem;font-weight:600;">Afgerond</span>
            <span style="font-size:.75rem;color:#94a3b8;">· Klik op een blok om de boeking te openen</span>
        </div>
    @endif

    {{-- ── Gantt: Achtergronden & Extra's ── --}}
    @if($extraAssets->isNotEmpty())
    @php
        $catLabels  = ['background' => '🖼️ Achtergronden', 'prop_box' => '🎩 Attributenkisten', 'extra' => '✨ Extra\'s'];
        $catColors  = ['background' => '#7c3aed', 'prop_box' => '#0891b2', 'extra' => '#d97706'];
        $rowsBycat  = collect($extraRows)->groupBy('category');
    @endphp

    @foreach($rowsBycat as $cat => $catRows)
    <div style="margin-top:2rem;">
        <h3 style="font-size:.95rem;font-weight:700;color:#0f172a;margin:0 0 .75rem;">
            {{ $catLabels[$cat] ?? $cat }}
        </h3>
        <div class="gantt-wrap">
            <table class="gantt-table">
                {{-- Maand header --}}
                <thead>
                    <tr>
                        <th class="gantt-label" style="border-right:2px solid #cbd5e1;background:#f8fafc;"></th>
                        @foreach($maandGroepen as $ym => $groepDagen)
                            <th class="gantt-month-cell" colspan="{{ $groepDagen->count() }}">
                                {{ $groepDagen->first()->isoFormat('MMMM YYYY') }}
                            </th>
                        @endforeach
                    </tr>
                    <tr>
                        <th class="gantt-label" style="border-right:2px solid #cbd5e1;background:#f8fafc;font-size:.7rem;color:#94a3b8;font-weight:500;padding:.5rem .875rem;">
                            {{ $catLabels[$cat] ?? $cat }}
                        </th>
                        @foreach($days as $day)
                            @php $isToday = $day->isToday(); $isWeekend = $day->isWeekend(); @endphp
                            <th class="gantt-day-cell {{ $isToday ? 'today' : ($isWeekend ? 'weekend' : '') }}">
                                <div>{{ $day->isoFormat('dd') }}</div>
                                <div style="font-weight:700;font-size:.78rem;">{{ $day->format('j') }}</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($catRows as $row)
                        @php $color = $catColors[$cat] ?? '#475569'; @endphp
                        <tr>
                            <td class="gantt-label" style="vertical-align:middle;">
                                <div style="font-weight:600;font-size:.8rem;color:#1e293b;">{{ $row['label'] }}</div>
                                @if($row['total_slots'] > 1)
                                <div style="font-size:.68rem;color:#94a3b8;margin-top:.1rem;">
                                    Unit {{ $row['slot'] }} / {{ $row['total_slots'] }}
                                </div>
                                @endif
                            </td>
                            @foreach($row['segments'] as $segIdx => $seg)
                                @php
                                    $day       = $days[$segIdx] ?? null;
                                    $isToday   = $day && $day->isToday();
                                    $isWeekend = $day && $day->isWeekend();
                                @endphp
                                @if(empty($seg['bookings']))
                                    <td class="gantt-free {{ $isToday ? 'today-col' : ($isWeekend ? 'weekend-col' : '') }}"></td>
                                @else
                                    <td class="gantt-booking-cell {{ $seg['has_conflict'] ? 'gantt-conflict-cell' : '' }}"
                                        @if($seg['has_conflict']) title="⚠️ Tijdconflict — twee boekingen overlappen elkaar in tijd" @endif>
                                        @foreach($seg['bookings'] as $b)
                                            @php
                                                $booking    = $b['booking'];
                                                $startPct   = $b['start_pct'];
                                                $endPct     = $b['end_pct'];
                                                $blockClass = $seg['has_conflict'] ? 'gantt-conflict-block' : 'gantt-booking-block status-' . $booking->status;

                                                $leftPos  = $startPct > 0 ? $startPct . '%' : '-1px';
                                                $rightPos = $endPct   < 100 ? (100 - $endPct) . '%' : '-1px';
                                                $radL     = $startPct > 0 ? '6px' : '0';
                                                $radR     = $endPct   < 100 ? '6px' : '0';
                                                $radius   = "border-top-left-radius:{$radL};border-bottom-left-radius:{$radL};border-top-right-radius:{$radR};border-bottom-right-radius:{$radR};";
                                            @endphp
                                            <a href="{{ route('bookings.show', $booking) }}"
                                               class="{{ $blockClass }}"
                                               style="left:{{ $leftPos }};right:{{ $rightPos }};{{ $radius }}{{ $seg['has_conflict'] ? '' : 'border-color:'.$color.';' }}"
                                               title="{{ $booking->booking_number }} — {{ $booking->customer_name }}">
                                                @if($b['is_first_day'])
                                                    <span class="b-num">{{ $seg['has_conflict'] ? '⚠️ ' : '' }}{{ $booking->booking_number }}@if($booking->booking_type === 'to_go') <span style="font-size:.65rem;">🚗</span>@endif</span>
                                                    <span class="b-name">{{ $booking->customer_name }}</span>
                                                @endif
                                            </a>
                                            @if($b['is_first_day'])
                                                @include('bookings._planning_tooltip', ['booking' => $booking])
                                            @endif
                                        @endforeach
                                    </td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endforeach
    @endif

    {{-- ── Gantt: Team ── --}}
    @if(!empty($teamRows))
    <div style="margin-top:2rem;">
        <h3 style="font-size:.875rem;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.75rem;">👥 Team</h3>
        <div class="gantt-wrap">
            <table class="gantt-table">
                {{-- Maand-header --}}
                <thead>
                    <tr>
                        <th class="gantt-label" style="background:#f8fafc;border-bottom:2px solid #cbd5e1;padding:.4rem .75rem;font-size:.7rem;font-weight:700;text-transform:uppercase;color:#475569;min-width:150px;">Medewerker</th>
                        @php
                            $maandGroepen = $days->groupBy(fn($d) => $d->format('Y-m'));
                        @endphp
                        @foreach($maandGroepen as $ym => $groepDagen)
                            <th class="gantt-month-cell" colspan="{{ $groepDagen->count() }}">
                                {{ \Carbon\Carbon::createFromFormat('Y-m', $ym)->translatedFormat('F Y') }}
                            </th>
                        @endforeach
                    </tr>
                    <tr>
                        <th class="gantt-label" style="background:#f8fafc;border-bottom:2px solid #cbd5e1;"></th>
                        @foreach($days as $day)
                            <th class="gantt-day-cell {{ $day->isToday() ? 'today' : ($day->isWeekend() ? 'weekend' : '') }}">
                                <div>{{ $day->translatedFormat('D') }}</div>
                                <div style="font-weight:700;">{{ $day->format('j') }}</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($teamRows as $row)
                    <tr>
                        <td class="gantt-label" style="vertical-align:middle;">
                            <div style="font-weight:600;font-size:.8rem;color:#1e293b;">{{ $row['label'] }}</div>
                        </td>
                        @php $dayIdx = 0; @endphp
                        @foreach($row['segments'] as $seg)
                            @php
                                $day      = $days[$dayIdx] ?? null;
                                $isToday  = $day && $day->isToday();
                                $isWeekend= $day && $day->isWeekend();
                                $booking  = $seg['booking'];
                                $roleLabel = $seg['role_label'] ?? null;
                                $dayIdx++;
                            @endphp
                            @if($booking)
                                <td class="gantt-booking-cell">
                                    <a href="{{ route('bookings.show', $booking) }}"
                                       class="gantt-booking-block"
                                       style="left:2px;right:2px;border-radius:6px;background:{{ $roleLabel === 'Ophaler' ? '#f0fdf4' : '#eff6ff' }};border-left-color:{{ $roleLabel === 'Ophaler' ? '#16a34a' : '#2563eb' }};">
                                        <span class="b-num" style="color:{{ $roleLabel === 'Ophaler' ? '#15803d' : '#1d4ed8' }};">
                                            {{ $roleLabel === 'Ophaler' ? '⬆' : ($booking->booking_type === 'to_go' ? '🤝' : '⬇') }} {{ $booking->booking_number }}
                                        </span>
                                        <span class="b-name">{{ $booking->customer_name }}</span>
                                    </a>
                                </td>
                            @else
                                <td class="gantt-free {{ $isToday ? 'today-col' : ($isWeekend ? 'weekend-col' : '') }}"></td>
                            @endif
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="display:flex;gap:1rem;align-items:center;margin-top:.75rem;flex-wrap:wrap;font-size:.72rem;color:#64748b;">
            <span style="display:inline-flex;align-items:center;gap:.3rem;"><span style="background:#eff6ff;border-left:3px solid #2563eb;padding:.1rem .4rem;border-radius:3px;font-weight:600;color:#1d4ed8;">⬇</span> Bezorger</span>
            <span style="display:inline-flex;align-items:center;gap:.3rem;"><span style="background:#f0fdf4;border-left:3px solid #16a34a;padding:.1rem .4rem;border-radius:3px;font-weight:600;color:#15803d;">⬆</span> Ophaler</span>
            <span style="display:inline-flex;align-items:center;gap:.3rem;"><span style="background:#eff6ff;border-left:3px solid #2563eb;padding:.1rem .4rem;border-radius:3px;font-weight:600;color:#1d4ed8;">🤝</span> To Go afgever</span>
        </div>
    </div>
    @endif

    {{-- Vandaag scroll anchor + hover tooltip --}}
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // Scroll naar vandaag
        const todayEl = document.querySelector('.gantt-day-cell.today');
        if (todayEl) {
            const wrap = document.querySelector('.gantt-wrap');
            const labelWidth = 160;
            const offset = todayEl.offsetLeft - labelWidth - 40;
            if (wrap && offset > 0) wrap.scrollLeft = offset;
        }

        // Floating tooltip via fixed overlay (voorkomt overflow-clipping)
        const overlay = document.createElement('div');
        overlay.id = 'gantt-tooltip-overlay';
        overlay.style.cssText = 'position:fixed;z-index:99999;pointer-events:none;display:none;';
        document.body.appendChild(overlay);

        let hideTimer = null;

        document.querySelectorAll('.gantt-booking-cell').forEach(cell => {
            const tooltipEl = cell.querySelector('.booking-tooltip');
            if (!tooltipEl) return;

            cell.addEventListener('mouseenter', function (e) {
                clearTimeout(hideTimer);
                overlay.innerHTML = tooltipEl.innerHTML;
                overlay.style.display = 'block';
                positionTooltip(e);
            });

            cell.addEventListener('mousemove', positionTooltip);

            cell.addEventListener('mouseleave', function () {
                hideTimer = setTimeout(() => { overlay.style.display = 'none'; }, 80);
            });
        });

        function positionTooltip(e) {
            const gap = 12;
            const tw  = overlay.offsetWidth  || 260;
            const th  = overlay.offsetHeight || 120;
            const vw  = window.innerWidth;
            const vh  = window.innerHeight;

            let x = e.clientX + gap;
            let y = e.clientY + gap;

            if (x + tw > vw - 8) x = e.clientX - tw - gap;
            if (y + th > vh - 8) y = e.clientY - th - gap;

            overlay.style.left = x + 'px';
            overlay.style.top  = y + 'px';
        }
    });
    </script>
</div>
@endsection
