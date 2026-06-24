{{-- Tabs voor Boekingen sectie: List / Calendar / Follow Up --}}
@php
    $followUpCount = \App\Models\Booking::where('event_date', '<', now()->toDateString())
        ->whereIn('status', ['confirmed', 'completed'])
        ->where(function($q) { $q->whereNull('gallery_url')->orWhere('gallery_url', ''); })
        ->where('gallery_skipped', false)
        ->count();
    $boekingCount  = \App\Models\Booking::whereDate('event_date', '>=', today())->count();
    $archiefCount  = \App\Models\Booking::whereDate('event_date', '<', today())->count();
@endphp
<div class="page-tabs" style="display:flex;border-bottom:2px solid #e5e7eb;margin-bottom:1.5rem;gap:0;overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none;">
    {{-- List Tab --}}
    <a href="{{ route('bookings.index') }}"
       style="padding:0.75rem 1.5rem;font-weight:500;border-bottom:3px solid transparent;transition:all 0.2s;display:flex;align-items:center;gap:.45rem;@if(request()->routeIs('bookings.index') || request()->routeIs('bookings.create')) border-bottom-color:#2563eb;color:#2563eb; @else color:#6b7280;@endif"
       class="hover:text-blue-600">
        📋 Boekingen
        <span style="background:#e2e8f0;color:#475569;border-radius:9999px;padding:.1rem .5rem;font-size:.72rem;font-weight:700;line-height:1.4;">{{ $boekingCount }}</span>
    </a>

    {{-- Calendar Tab --}}
    <a href="{{ route('bookings.calendar') }}"
       style="padding:0.75rem 1.5rem;font-weight:500;border-bottom:3px solid transparent;transition:all 0.2s;@if(request()->routeIs('bookings.calendar')) border-bottom-color:#2563eb;color:#2563eb; @else color:#6b7280;@endif"
       class="hover:text-blue-600">
        📅 Kalender
    </a>

    {{-- Follow Up Tab --}}
    <a href="{{ route('bookings.follow-up') }}"
       style="padding:0.75rem 1.5rem;font-weight:500;border-bottom:3px solid transparent;transition:all 0.2s;display:flex;align-items:center;gap:.5rem;@if(request()->routeIs('bookings.follow-up')) border-bottom-color:#d97706;color:#d97706; @else color:#6b7280;@endif"
       class="hover:text-yellow-600">
        📸 Follow Up
        @if($followUpCount > 0)
        <span data-follow-badge style="background:#fef9c3;color:#854d0e;font-size:.65rem;font-weight:700;padding:.1rem .45rem;border-radius:1rem;border:1px solid #fde68a;line-height:1.4;">
            {{ $followUpCount }}
        </span>
        @endif
    </a>

    {{-- Planning Tab --}}
    <a href="{{ route('bookings.planning') }}"
       style="padding:0.75rem 1.5rem;font-weight:500;border-bottom:3px solid transparent;transition:all 0.2s;@if(request()->routeIs('bookings.planning')) border-bottom-color:#7c3aed;color:#7c3aed; @else color:#6b7280;@endif"
       class="hover:text-purple-600">
        🗓️ Planning
    </a>

    {{-- Archief Tab --}}
    <a href="{{ route('bookings.archive') }}"
       style="padding:0.75rem 1.5rem;font-weight:500;border-bottom:3px solid transparent;transition:all 0.2s;display:flex;align-items:center;gap:.45rem;@if(request()->routeIs('bookings.archive')) border-bottom-color:#374151;color:#374151; @else color:#6b7280;@endif"
       class="hover:text-gray-700">
        🗃️ Archief
        <span style="background:#e2e8f0;color:#475569;border-radius:9999px;padding:.1rem .5rem;font-size:.72rem;font-weight:700;line-height:1.4;">{{ $archiefCount }}</span>
    </a>
</div>
