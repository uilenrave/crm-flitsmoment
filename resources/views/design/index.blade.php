@extends('layouts.app')

@section('title', 'Ontwerp-generator')

@section('content')

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem;">
    <h2 style="margin:0;font-size:1.25rem;font-weight:700;color:#1e293b;">✨ Ontwerp-generator — Achtergrond</h2>
    <span style="font-size:.8rem;color:#64748b;">AI-achtergrond genereren (Gemini)</span>
</div>
<p style="font-size:.72rem;color:#94a3b8;margin:-.5rem 0 1rem;">Onderdeel 1 van de fotostrip. Andere onderdelen (strip-kader, logo, …) volgen later. Los testen, zonder aan een boeking gekoppeld te zijn.</p>

<div class="card neu-card" style="padding:1rem 1.25rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
    <label style="font-size:.8rem;font-weight:700;color:#334155;white-space:nowrap;">Ontwerpen voor een boeking?</label>
    <select id="dg-booking-picker" style="flex:1;min-width:220px;padding:.5rem .7rem;border:1px solid #e2e8f0;border-radius:.5rem;font-size:.85rem;">
        <option value="">— kies een aankomende boeking —</option>
        @foreach($upcomingBookings as $b)
            <option value="{{ route('design.booking', $b) }}">{{ $b->customer_name }} — {{ $b->event_date?->format('d-m-Y') }} ({{ $b->booking_number }})</option>
        @endforeach
    </select>
    <button type="button" class="btn btn-primary" onclick="const v=document.getElementById('dg-booking-picker').value; if(v) window.location=v;">Ga naar ontwerp →</button>
</div>

@php
    $dgMode = 'admin';
    $urls = [
        'generate'         => route('design.generate'),
        'logoCutout'       => route('design.logo.cutout'),
        'promptUpdate'     => route('design.prompt.update'),
        'masksUpload'      => route('design.masks.upload'),
        'masksApply'       => route('design.masks.apply'),
        'masksDestroyBase' => url('ontwerp-generator/masks'),
        'saveState'        => null,
        'helpLink'         => null,
    ];
@endphp

@include('design._tool', compact('dgMode', 'urls', 'eventTypes', 'eventType', 'promptLabel', 'promptKey', 'promptDefault', 'promptsByType', 'logoEventTypes', 'masks', 'results', 'input'))

@push('scripts')
@include('design._tool_scripts', compact('dgMode', 'urls', 'eventTypes', 'eventType', 'promptLabel', 'promptKey', 'promptDefault', 'promptsByType', 'logoEventTypes', 'masks', 'results', 'input'))
@endpush
@endsection
