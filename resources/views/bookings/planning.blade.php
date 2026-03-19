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
    /* Boeking blok cel */
    .gantt-booking-cell {
        height: 44px;
        padding: 4px 6px;
        vertical-align: middle;
        position: relative;
        cursor: pointer;
    }
    .gantt-booking-block {
        border-radius: 6px;
        padding: .25rem .5rem;
        height: 36px;
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
                @foreach([4,8,12] as $w)
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
        {{-- Gantt Tabel --}}
        <div class="gantt-wrap">
            <table class="gantt-table">

                {{-- Maand header --}}
                <thead>
                    <tr>
                        <th class="gantt-label" style="border-right:2px solid #cbd5e1;background:#f8fafc;"></th>
                        @php
                            $maandGroepen = $days->groupBy(fn($d) => $d->format('Y-m'));
                        @endphp
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

                            {{-- Dag cellen --}}
                            @php $dayIdx = 0; @endphp
                            @foreach($row['segments'] as $seg)
                                @php
                                    $day      = $days[$dayIdx] ?? null;
                                    $isToday  = $day && $day->isToday();
                                    $isWeekend= $day && $day->isWeekend();
                                    $booking  = $seg['booking'];
                                    $colspan  = $seg['colspan'];
                                    $dayIdx  += $colspan;

                                    $statusClass = 'status-' . ($booking?->status ?? 'confirmed');
                                @endphp

                                @if($booking)
                                    <td class="gantt-booking-cell" colspan="{{ $colspan }}">
                                        <a href="{{ route('bookings.show', $booking) }}"
                                           class="gantt-booking-block {{ $statusClass }}"
                                           title="{{ $booking->booking_number }} — {{ $booking->customer_name }} ({{ $booking->event_date->format('d M') }}{{ $booking->event_end_date ? ' – '.$booking->event_end_date->format('d M') : '' }})">
                                            <span class="b-num">{{ $booking->booking_number }}</span>
                                            <span class="b-name">{{ $booking->customer_name }}</span>
                                        </a>
                                    </td>
                                @else
                                    <td class="gantt-free {{ $isToday ? 'today-col' : ($isWeekend ? 'weekend-col' : '') }}" colspan="{{ $colspan }}"></td>
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
            <span class="gantt-booking-block status-confirmed" style="padding:.2rem .6rem;height:auto;border-left-width:3px;font-size:.72rem;font-weight:600;">Bevestigd</span>
            <span class="gantt-booking-block status-completed" style="padding:.2rem .6rem;height:auto;border-left-width:3px;font-size:.72rem;font-weight:600;">Afgerond</span>
            <span style="font-size:.75rem;color:#94a3b8;">· Klik op een blok om de boeking te openen</span>
        </div>

        {{-- Vandaag scroll anchor --}}
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Scroll de vandaag-kolom in beeld
            const todayEl = document.querySelector('.gantt-day-cell.today');
            if (todayEl) {
                const wrap = document.querySelector('.gantt-wrap');
                const labelWidth = 160;
                const offset = todayEl.offsetLeft - labelWidth - 40;
                if (wrap && offset > 0) wrap.scrollLeft = offset;
            }
        });
        </script>
    @endif
</div>
@endsection
