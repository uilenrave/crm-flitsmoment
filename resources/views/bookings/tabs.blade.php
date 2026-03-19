{{-- Tabs voor Boekingen sectie: List / Calendar / Follow Up --}}
@php
    $followUpCount = \App\Models\Booking::where('event_date', '<', now()->toDateString())
        ->whereIn('status', ['confirmed', 'completed'])
        ->where(function($q) { $q->whereNull('gallery_url')->orWhere('gallery_url', ''); })
        ->count();
@endphp
<div style="display:flex;border-bottom:2px solid #e5e7eb;margin-bottom:1.5rem;gap:0;">
    {{-- List Tab --}}
    <a href="{{ route('bookings.index') }}"
       style="padding:0.75rem 1.5rem;font-weight:500;border-bottom:3px solid transparent;transition:all 0.2s;@if(request()->routeIs('bookings.index') || request()->routeIs('bookings.create')) border-bottom-color:#2563eb;color:#2563eb; @else color:#6b7280;@endif"
       class="hover:text-blue-600">
        📋 Lijstweergave
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
</div>
