@extends('layouts.app')

@section('title', 'Boekingen - Kalender')

@push('styles')
<style>
@media (max-width: 640px) {
  .cal-cell { min-height: 60px !important; padding: 0.375rem !important; }
  .cal-cell .cal-day-num { font-size: .8rem !important; margin-bottom: .2rem !important; }
  .cal-event { font-size: .65rem !important; padding: .2rem .3rem !important; }
  .cal-header-day { padding: .5rem .25rem !important; font-size: .7rem !important; }
  .cal-month-title { font-size: 1.25rem !important; }
  .cal-legend { flex-wrap: wrap; gap: .5rem !important; }
}
</style>
@endpush

@section('content')
<div style="padding:0 1.5rem;">
    {{-- Tabs --}}
    @include('bookings.tabs')

    {{-- Kalender Header met Navigatie --}}
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;gap:1rem;flex-wrap:wrap;">
        <h2 class="cal-month-title" style="font-size:1.875rem;font-weight:bold;margin:0;">
            {{ \App\Helpers\CalendarHelper::monthName($month) }} {{ $year }}
        </h2>

        <div style="display:flex;gap:0.5rem;">
            <a href="{{ route('bookings.calendar', array_merge(request()->query(), $previousMonth)) }}"
               style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border:1px solid #d1d5db;border-radius:0.375rem;background:#fff;cursor:pointer;transition:all 0.2s;font-size:0.875rem;font-weight:500;color:#374151;text-decoration:none;"
               onmouseover="this.style.backgroundColor='#f3f4f6';this.style.borderColor='#9ca3af';"
               onmouseout="this.style.backgroundColor='#fff';this.style.borderColor='#d1d5db';">
                ← Vorige
            </a>

            <a href="{{ route('bookings.calendar') }}"
               style="display:inline-flex;align-items:center;justify-content:center;padding:0.5rem 1rem;border:1px solid #d1d5db;border-radius:0.375rem;background:#fff;cursor:pointer;transition:all 0.2s;font-size:0.875rem;font-weight:500;color:#374151;text-decoration:none;white-space:nowrap;"
               onmouseover="this.style.backgroundColor='#f3f4f6';this.style.borderColor='#9ca3af';"
               onmouseout="this.style.backgroundColor='#fff';this.style.borderColor='#d1d5db';">
                Vandaag
            </a>

            <a href="{{ route('bookings.calendar', array_merge(request()->query(), $nextMonth)) }}"
               style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border:1px solid #d1d5db;border-radius:0.375rem;background:#fff;cursor:pointer;transition:all 0.2s;font-size:0.875rem;font-weight:500;color:#374151;text-decoration:none;"
               onmouseover="this.style.backgroundColor='#f3f4f6';this.style.borderColor='#9ca3af';"
               onmouseout="this.style.backgroundColor='#fff';this.style.borderColor='#d1d5db';">
                Volgende →
            </a>
        </div>
    </div>

    {{-- Kleur Legende --}}
    <div style="display:flex;gap:2rem;margin-bottom:1.5rem;padding:1rem;background:#f9fafb;border-radius:0.5rem;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:0.75rem;">
            <div style="width:16px;height:16px;background:#3b82f6;border-radius:0.25rem;"></div>
            <span style="font-size:0.875rem;color:#6b7280;">Full Service Event</span>
        </div>
        <div style="display:flex;align-items:center;gap:0.75rem;">
            <div style="width:16px;height:16px;background:#f97316;border-radius:0.25rem;"></div>
            <span style="font-size:0.875rem;color:#6b7280;">To Go Event</span>
        </div>
        <div style="display:flex;align-items:center;gap:0.75rem;">
            <div style="width:16px;height:16px;background:#fcd34d;border-radius:0.25rem;border:1px solid #d4a000;"></div>
            <span style="font-size:0.875rem;color:#6b7280;">Meerdaagse boeking</span>
        </div>
        <div style="display:flex;align-items:center;gap:0.75rem;">
            <div style="width:16px;height:16px;background:#9ca3af;border-radius:0.25rem;"></div>
            <span style="font-size:0.875rem;color:#6b7280;">Bezorging</span>
        </div>
        <div style="display:flex;align-items:center;gap:0.75rem;">
            <div style="width:16px;height:16px;background:#ef4444;border-radius:0.25rem;"></div>
            <span style="font-size:0.875rem;color:#6b7280;">Ophaling/Retour</span>
        </div>
    </div>

    {{-- Kalender Grid --}}
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:0.5rem;overflow:hidden;">
        {{-- Dag Headers (Ma t/m Zo) --}}
        <div style="display:grid;grid-template-columns:repeat(7, 1fr);border-bottom:2px solid #e5e7eb;">
            @for($dow = 0; $dow < 7; $dow++)
                <div class="cal-header-day" style="padding:1rem;text-align:center;font-weight:bold;background:#f3f4f6;border-right:1px solid #e5e7eb;@if($dow === 6) border-right:none; @endif">
                    {{ \App\Helpers\CalendarHelper::dayName($dow) }}
                </div>
            @endfor
        </div>

        {{-- Dag Cellen --}}
        <div style="display:grid;grid-template-columns:repeat(7, 1fr);">
            @php
                $firstDay = \App\Helpers\CalendarHelper::firstDayOfMonth($year, $month);
                $daysInMonth = \App\Helpers\CalendarHelper::daysInMonth($year, $month);

                // Vorige maand aanvullen
                $prevMonthData = \App\Helpers\CalendarHelper::previousMonth($year, $month);
                $daysInPrevMonth = \App\Helpers\CalendarHelper::daysInMonth($prevMonthData['year'], $prevMonthData['month']);
                $startDay = $daysInPrevMonth - $firstDay + 1;
            @endphp

            {{-- Grijs cellen vorige maand --}}
            @for($day = $startDay; $day <= $daysInPrevMonth; $day++)
                <div class="cal-cell" style="min-height:120px;padding:0.75rem;border-right:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb;background:#f9fafb;color:#9ca3af;">
                    <div class="cal-day-num" style="font-weight:500;margin-bottom:0.5rem;">{{ $day }}</div>
                </div>
            @endfor

            {{-- Dagen van huidige maand --}}
            @for($day = 1; $day <= $daysInMonth; $day++)
                @php
                    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $events = $eventsByDay[$dateStr] ?? collect();
                    $isToday = $dateStr === now()->format('Y-m-d');
                @endphp
                <div class="cal-cell" style="min-height:120px;padding:0.75rem;border-right:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb;background:@if($isToday) #f0f9ff; @else #fff; @endif overflow-y:auto;">
                    {{-- Dag nummer --}}
                    <div class="cal-day-num" style="font-weight:600;margin-bottom:0.5rem;font-size:0.95rem;@if($isToday) color:#2563eb; @endif">
                        {{ $day }}
                    </div>

                    {{-- Events voor deze dag --}}
                    <div style="display:flex;flex-direction:column;gap:0.375rem;">
                        @foreach($events as $event)
                            @php
                                $isMultiDay = isset($event['is_multi_day']) && $event['is_multi_day'];
                            @endphp
                            <a href="{{ $event['url'] }}"
                               class="cal-event" style="display:block;padding:0.375rem 0.5rem;background:{{ $event['color'] }};color:{{ $isMultiDay ? '#000' : '#fff' }};font-size:0.75rem;border-radius:0.25rem;text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;transition:all 0.2s;border:{{ $isMultiDay ? '2px solid #d4a000' : 'none' }};font-weight:{{ $isMultiDay ? '600' : 'normal' }};"
                               title="{{ $event['title'] }}"
                               class="hover:opacity-80">
                                {{ $isMultiDay ? '📅 ' : '' }}{{ substr($event['title'], 0, 25) }}...
                            </a>
                        @endforeach
                    </div>
                </div>
            @endfor

            {{-- Grijs cellen volgende maand --}}
            @php
                $totalCells = ceil(($firstDay + $daysInMonth) / 7) * 7;
                $remainingCells = $totalCells - ($firstDay + $daysInMonth);
            @endphp
            @for($day = 1; $day <= $remainingCells; $day++)
                <div class="cal-cell" style="min-height:120px;padding:0.75rem;border-right:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb;background:#f9fafb;color:#9ca3af;">
                    <div class="cal-day-num" style="font-weight:500;margin-bottom:0.5rem;">{{ $day }}</div>
                </div>
            @endfor
        </div>
    </div>

    {{-- Events samenvatting --}}
    @if($allEvents->count() > 0)
        <div style="margin-top:2rem;padding:1.5rem;background:#f9fafb;border-radius:0.5rem;border:1px solid #e5e7eb;">
            <h3 style="font-weight:bold;margin-bottom:1rem;">📊 Overzicht ({{ $allEvents->count() }} events)</h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(250px, 1fr));gap:1rem;">
                @foreach($bookings as $booking)
                    @php
                        $bookingEvents = \App\Helpers\CalendarHelper::eventsFromBooking($booking);
                    @endphp
                    <a href="{{ route('bookings.show', $booking) }}"
                       style="display:block;padding:1rem;background:#fff;border:1px solid #e5e7eb;border-radius:0.5rem;text-decoration:none;transition:all 0.2s;"
                       class="hover:shadow-md hover:border-blue-300">
                        <div style="font-weight:600;color:#111827;margin-bottom:0.5rem;">
                            {{ $booking->booking_number }}
                        </div>
                        <div style="font-size:0.875rem;color:#6b7280;margin-bottom:0.5rem;">
                            {{ $booking->customer_name }}
                        </div>
                        <div style="font-size:0.875rem;color:#6b7280;margin-bottom:0.5rem;">
                            📅 {{ $booking->event_date->format('d M Y') }}
                            @if($booking->is_multi_day && $booking->event_end_date)
                                — {{ $booking->event_end_date->format('d M Y') }}
                                <span style="font-size:0.75rem;background:#fcd34d;color:#000;padding:0.25rem 0.5rem;border-radius:0.25rem;display:inline-block;margin-top:0.25rem;">
                                    {{ $booking->duration_formatted }}
                                </span>
                            @endif
                        </div>
                        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                            @if($booking->delivery_at && $booking->booking_type === 'full_service')
                                <span style="font-size:0.75rem;background:#9ca3af;color:#fff;padding:0.25rem 0.5rem;border-radius:0.25rem;">
                                    🚚 Bezorging
                                </span>
                            @endif
                            <span style="font-size:0.75rem;background:@if($booking->booking_type === 'full_service') #3b82f6; @else #f97316; @endif color:#fff;padding:0.25rem 0.5rem;border-radius:0.25rem;">
                                @if($booking->booking_type === 'full_service') 🎉 Event @else 🎪 To Go @endif
                            </span>
                            @if($booking->pickup_at || $booking->customer_return_at)
                                <span style="font-size:0.75rem;background:#ef4444;color:#fff;padding:0.25rem 0.5rem;border-radius:0.25rem;">
                                    ↩️ Ophaling
                                </span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @else
        <div style="margin-top:2rem;padding:2rem;text-align:center;background:#f9fafb;border-radius:0.5rem;color:#6b7280;">
            <p>Geen boekingen in {{ \App\Helpers\CalendarHelper::monthName($month) }} {{ $year }}</p>
        </div>
    @endif
</div>
@endsection
