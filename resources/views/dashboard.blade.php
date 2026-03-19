@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Totaal leads</div>
        <div class="stat-value">{{ $stats['leads_totaal'] }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Nieuwe leads</div>
        <div class="stat-value" style="color:#2563eb">{{ $stats['leads_nieuw'] }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Totaal boekingen</div>
        <div class="stat-value">{{ $stats['boekingen_totaal'] }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Aankomende events</div>
        <div class="stat-value" style="color:#16a34a">{{ $stats['boekingen_aankomend'] }}</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
    <div class="card">
        <div class="card-header">
            <span class="card-title">Aankomende boekingen</span>
            <a href="{{ route('bookings.index') }}" class="btn btn-sm btn-secondary">Alle boekingen</a>
        </div>
        @forelse($aankomende_boekingen as $boeking)
        <div style="padding:.625rem 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;">
            <div>
                <div style="font-weight:500;font-size:.875rem;">{{ $boeking->customer_name }}</div>
                <div style="font-size:.75rem;color:#64748b;">{{ $boeking->event_date->format('d M Y') }} — {{ $boeking->event_location }}</div>
            </div>
            <a href="{{ route('bookings.show', $boeking) }}" class="btn btn-sm btn-secondary">Bekijk</a>
        </div>
        @empty
        <p style="color:#64748b;font-size:.875rem;padding:.5rem 0;">Geen aankomende boekingen.</p>
        @endforelse
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">Recente leads</span>
            <a href="{{ route('leads.index') }}" class="btn btn-sm btn-secondary">Alle leads</a>
        </div>
        @forelse($recente_leads as $lead)
        <div style="padding:.625rem 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;">
            <div>
                <div style="font-weight:500;font-size:.875rem;">{{ $lead->name }}</div>
                <div style="font-size:.75rem;color:#64748b;">
                    {{ $lead->lead_number }} •
                    @if($lead->status)
                        <span class="badge" style="background:{{ $lead->status->color }}20;color:{{ $lead->status->color }};">{{ $lead->status->display_name }}</span>
                    @endif
                </div>
            </div>
            <a href="{{ route('leads.show', $lead) }}" class="btn btn-sm btn-secondary">Bekijk</a>
        </div>
        @empty
        <p style="color:#64748b;font-size:.875rem;padding:.5rem 0;">Geen leads gevonden.</p>
        @endforelse
    </div>
</div>
@endsection
