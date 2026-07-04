@extends('layouts.app')

@section('title', 'Ontwerp — ' . $booking->customer_name)

@section('content')

<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem;">
    <div>
        <h2 style="margin:0;font-size:1.25rem;font-weight:700;color:#1e293b;">✨ Ontwerp — {{ $booking->customer_name }}</h2>
        <p style="margin:.25rem 0 0;font-size:.8rem;color:#64748b;">
            {{ $booking->event_date?->format('d M Y') }} · {{ $booking->booking_number }}
            · <a href="{{ route('bookings.index') }}" style="color:#7c3aed;">← Terug naar boekingen</a>
            <span id="dg-save-indicator" style="margin-left:.5rem;color:#16a34a;font-weight:600;"></span>
        </p>
    </div>

    @if($results['ok'] ?? false)
    <div style="display:flex;gap:.6rem;flex-wrap:wrap;">
        <form method="POST" action="{{ route('design.booking.send', $booking) }}" onsubmit="return confirm('Ontwerp versturen naar {{ $booking->customer_name }}? De klant krijgt een mail met het ontwerp.');">
            @csrf
            <button type="submit" class="btn btn-primary">📤 Stuur naar de klant</button>
        </form>
        <form method="POST" action="{{ route('design.booking.production', $booking) }}" onsubmit="return confirm('PNG klaarzetten voor productie? De boekingstatus wordt op \'Ontwerp klaar\' gezet.');">
            @csrf
            <button type="submit" class="btn" style="background:#16a34a;color:#fff;border:none;border-radius:.5rem;padding:.5rem 1rem;font-weight:700;cursor:pointer;">✅ PNG klaarzetten voor productie</button>
        </form>
    </div>
    @endif
</div>

@php
    $dgMode = 'admin';
    $urls = [
        'generate'         => route('design.booking.generate', $booking),
        'logoCutout'       => route('design.booking.logo', $booking),
        'promptUpdate'     => route('design.prompt.update'),
        'masksUpload'      => route('design.masks.upload'),
        'masksApply'       => route('design.masks.apply'),
        'masksDestroyBase' => url('ontwerp-generator/masks'),
        'saveState'        => route('design.booking.state', $booking),
        'helpLink'         => null,
    ];
@endphp

@include('design._tool', compact('dgMode', 'urls', 'eventTypes', 'eventType', 'promptLabel', 'promptKey', 'promptDefault', 'promptsByType', 'logoEventTypes', 'masks', 'results', 'input'))

@push('scripts')
@include('design._tool_scripts', compact('dgMode', 'urls', 'eventTypes', 'eventType', 'promptLabel', 'promptKey', 'promptDefault', 'promptsByType', 'logoEventTypes', 'masks', 'results', 'input', 'initialState'))
@endpush
@endsection
