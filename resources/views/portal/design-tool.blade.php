@php
    $hideHeader = true;
@endphp
@extends('layouts.portal')

@section('title', 'Ontwerp je fotostrip')

@section('content')
<div style="max-width:1100px;margin:0 auto;padding:1.5rem 1.25rem 3rem;">
    @section('dg-header')
    <div style="margin-bottom:1.25rem;">
        <a href="{{ route('portal.show', $booking->public_token) }}" style="font-size:.85rem;color:#7c3aed;text-decoration:none;">← Terug naar je boeking</a>
        <h1 style="font-size:1.4rem;font-weight:700;margin:.5rem 0 0;color:#1e293b;">✨ Ontwerp je fotostrip</h1>
        <p style="margin:.35rem 0 0;color:#6b7280;font-size:.85rem;">
            {{ $booking->booking_number }} · {{ $booking->customer_name }} · {{ $booking->event_date?->translatedFormat('j F Y') }}
        </p>
        <p style="margin:.35rem 0 0;color:#6b7280;font-size:.9rem;">Werk stap voor stap door de onderdelen heen — je voortgang wordt automatisch opgeslagen.</p>
    </div>

    @if(session('error'))
        <div style="padding:.875rem 1.125rem;border-radius:.75rem;margin-bottom:1.25rem;font-size:.9rem;background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;">{{ session('error') }}</div>
    @endif
    @endsection

    @include('design._tool', compact('dgMode', 'urls', 'eventTypes', 'eventType', 'promptLabel', 'promptKey', 'promptDefault', 'promptsByType', 'logoEventTypes', 'masks', 'templateCategories', 'elementCategories', 'results', 'input', 'googleFonts'))
</div>

@push('scripts')
@include('design._tool_scripts', compact('dgMode', 'urls', 'eventTypes', 'eventType', 'promptLabel', 'promptKey', 'promptDefault', 'promptsByType', 'logoEventTypes', 'masks', 'templateCategories', 'elementCategories', 'results', 'input', 'initialState', 'limits', 'justGenerated', 'googleFonts'))
@endpush
@endsection
