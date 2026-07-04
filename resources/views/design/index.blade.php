@extends('layouts.app')

@section('title', 'Ontwerp-generator')

@section('content')

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem;">
    <h2 style="margin:0;font-size:1.25rem;font-weight:700;color:#1e293b;">✨ Ontwerp-generator — Achtergrond</h2>
    <span style="font-size:.8rem;color:#64748b;">AI-achtergrond genereren (Gemini)</span>
</div>
<p style="font-size:.72rem;color:#94a3b8;margin:-.5rem 0 1rem;">Onderdeel 1 van de fotostrip. Andere onderdelen (strip-kader, logo, …) volgen later. Los testen, zonder aan een boeking gekoppeld te zijn.</p>

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
