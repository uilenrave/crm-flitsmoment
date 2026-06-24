@php
    $isToGo = $booking->booking_type === 'to_go';

    // Bezorg / ophaal
    if ($isToGo && $booking->customer_pickup_at) {
        $bezorgLabel  = 'Ophalen';
        $bezorgVal    = \Carbon\Carbon::parse($booking->customer_pickup_at)->translatedFormat('D j M H:i');
        $ophaalLabel  = 'Retour';
        $ophaalVal    = $booking->customer_return_at
            ? \Carbon\Carbon::parse($booking->customer_return_at)->translatedFormat('D j M H:i')
            : '—';
    } elseif ($booking->delivery_at) {
        $bezorgLabel  = 'Bezorging';
        $bezorgVal    = \Carbon\Carbon::parse($booking->delivery_at)->translatedFormat('D j M H:i');
        $ophaalLabel  = 'Ophalen';
        $ophaalVal    = $booking->pickup_at
            ? \Carbon\Carbon::parse($booking->pickup_at)->translatedFormat('D j M H:i')
            : '—';
    } else {
        $bezorgLabel = null;
        $bezorgVal   = null;
        $ophaalLabel = null;
        $ophaalVal   = null;
    }

    // Adres
    $adresDelen = array_filter([
        $booking->event_address,
        trim(($booking->event_postcode ?? '') . ' ' . ($booking->event_city ?? '')),
    ]);
    $adres = implode(', ', $adresDelen) ?: null;

    // Extra items (niet-photobooth)
    $extras = $booking->items
        ->filter(fn($i) => $i->asset && $i->asset->category !== 'photobooth')
        ->map(fn($i) => $i->asset->name . ($i->quantity > 1 ? ' ×' . $i->quantity : ''))
        ->values();
@endphp

<div class="booking-tooltip">
    {{-- Naam + nummer --}}
    <div style="font-weight:700;font-size:.82rem;color:#fff;margin-bottom:.3rem;">
        {{ $booking->customer_name }}
        @if($isToGo)<span style="font-size:.7rem;opacity:.7;"> 🚗 To Go</span>@endif
    </div>

    {{-- Adres --}}
    @if($adres)
    <div class="tooltip-row">
        <span class="tooltip-label">📍 Adres</span>
        <span class="tooltip-val">{{ $adres }}</span>
    </div>
    @endif

    {{-- Bezorging / ophaalmoment --}}
    @if($bezorgLabel)
    <hr class="tooltip-divider">
    <div class="tooltip-row">
        <span class="tooltip-label">🚚 {{ $bezorgLabel }}</span>
        <span class="tooltip-val">{{ $bezorgVal }}</span>
    </div>
    <div class="tooltip-row">
        <span class="tooltip-label">📦 {{ $ophaalLabel }}</span>
        <span class="tooltip-val">{{ $ophaalVal }}</span>
    </div>
    @endif

    {{-- Extra's --}}
    @if($extras->isNotEmpty())
    <hr class="tooltip-divider">
    <div class="tooltip-extras">
        + {{ $extras->implode(' · ') }}
    </div>
    @endif
</div>
