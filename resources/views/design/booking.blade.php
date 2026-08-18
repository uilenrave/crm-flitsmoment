@extends('layouts.app')

@section('title', 'Ontwerp — ' . $booking->customer_name)

@section('content')

@section('dg-header')
<div style="margin-bottom:1rem;">
    <h2 style="margin:0;font-size:1.25rem;font-weight:700;color:#1e293b;">✨ Ontwerp — {{ $booking->customer_name }}</h2>
    <p style="margin:.25rem 0 0;font-size:.8rem;color:#64748b;">
        {{ $booking->event_date?->format('d M Y') }} · {{ $booking->booking_number }}
        · <a href="{{ route('bookings.index') }}" style="color:#7c3aed;">← Terug naar boekingen</a>
    </p>

    @if($results['ok'] ?? false)
    <div style="display:flex;flex-direction:column;gap:.5rem;margin-top:.85rem;">
        <form method="POST" action="{{ route('design.booking.send', $booking) }}" onsubmit="return confirm('Ontwerp versturen naar {{ $booking->customer_name }}? De klant krijgt een mail met het ontwerp.');">
            @csrf
            <button type="submit" class="dg-btn-primary" style="width:100%;">📤 Stuur naar de klant</button>
        </form>
        <form method="POST" action="{{ route('design.booking.production', $booking) }}" onsubmit="return confirm('PNG klaarzetten voor productie? De boekingstatus wordt op \'Ontwerp klaar\' gezet.');">
            @csrf
            <button type="submit" style="width:100%;background:#16a34a;color:#fff;border:none;border-radius:.75rem;padding:.6rem 1.125rem;font-weight:700;cursor:pointer;">✅ PNG klaarzetten voor productie</button>
        </form>
    </div>
    @endif
</div>
@endsection

@php
    $dgMode = 'admin';
    $urls = [
        'generate'         => route('design.booking.generate', $booking),
        'logoCutout'       => route('design.booking.logo', $booking),
        'logoUpload'       => route('design.booking.logo.upload', $booking),
        'elementUse'       => route('design.booking.element', $booking),
        'promptUpdate'     => route('design.prompt.update'),
        'masksUpload'      => route('design.masks.upload'),
        'masksApply'       => route('design.masks.apply'),
        'masksDestroyBase' => url('ontwerp-generator/masks'),
        'saveState'        => route('design.booking.state', $booking),
        'helpLink'         => null,
    ];
@endphp

@include('design._tool', compact('dgMode', 'urls', 'eventTypes', 'eventType', 'promptLabel', 'promptKey', 'promptDefault', 'promptsByType', 'logoEventTypes', 'masks', 'templateCategories', 'elementCategories', 'results', 'input', 'googleFonts'))

@push('scripts')
@include('design._tool_scripts', compact('dgMode', 'urls', 'eventTypes', 'eventType', 'promptLabel', 'promptKey', 'promptDefault', 'promptsByType', 'logoEventTypes', 'masks', 'templateCategories', 'elementCategories', 'results', 'input', 'initialState', 'googleFonts'))
@endpush
@endsection
