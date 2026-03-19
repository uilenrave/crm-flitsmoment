@extends('layouts.app')
@section('title', 'Account instellingen')

@section('content')

@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:1rem;">{{ session('success') }}</div>
@endif

@foreach($accounts as $account)
<div class="card" style="margin-bottom:1.5rem;">
    <h2 style="font-size:1rem;font-weight:700;margin-bottom:1.5rem;padding-bottom:.75rem;border-bottom:1px solid #e2e8f0;">
        {{ $account->name }}
    </h2>

    <form method="POST" action="{{ route('admin.settings.update', $account) }}">
        @csrf @method('PUT')

        {{-- Mollie ─────────────────────────────────────── --}}
        <h3 style="font-size:.75rem;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.04em;margin-bottom:1rem;">Betaling (Mollie)</h3>

        <div class="form-group" style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;">
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;margin:0;">
                <input type="hidden" name="mollie_enabled" value="0">
                <input type="checkbox" name="mollie_enabled" value="1" @checked($account->mollie_enabled)
                    style="width:1.1rem;height:1.1rem;cursor:pointer;">
                <span style="font-size:.875rem;font-weight:500;">iDEAL / Mollie betalingen inschakelen</span>
            </label>
        </div>

        <div class="form-group">
            <label>Mollie API key <span style="font-weight:400;color:#94a3b8;">(laat leeg voor globale key)</span></label>
            <input type="text" name="mollie_key"
                value="{{ old('mollie_key_' . $account->id, $account->mollie_key) }}"
                placeholder="live_... of test_..."
                style="font-family:monospace;">
            @if(!$account->mollie_key)
            <small style="color:#94a3b8;">Gebruikt nu: globale key ({{ Str::mask(config('services.mollie.key', env('MOLLIE_KEY','')), '*', 8) }})</small>
            @endif
        </div>

        {{-- e-Boekhouden ──────────────────────────────────── --}}
        <hr style="margin:1.25rem 0;border:none;border-top:1px solid #e2e8f0;">
        <h3 style="font-size:.75rem;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.04em;margin-bottom:1rem;">e-Boekhouden koppeling</h3>

        <div class="form-group" style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;">
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;margin:0;">
                <input type="hidden" name="eboekhouden_enabled" value="0">
                <input type="checkbox" name="eboekhouden_enabled" value="1" @checked($account->eboekhouden_enabled)
                    style="width:1.1rem;height:1.1rem;cursor:pointer;">
                <span style="font-size:.875rem;font-weight:500;">e-Boekhouden koppeling inschakelen</span>
            </label>
        </div>

        <div class="form-group">
            <label>e-Boekhouden API key <span style="font-weight:400;color:#94a3b8;">(laat leeg voor globale key)</span></label>
            <input type="text" name="eboekhouden_api_key"
                value="{{ old('eboekhouden_api_key_' . $account->id, $account->eboekhouden_api_key) }}"
                placeholder="Vul je e-Boekhouden API key in..."
                style="font-family:monospace;">
            @if(!$account->eboekhouden_api_key)
            <small style="color:#94a3b8;">Gebruikt nu: globale key uit .env</small>
            @endif
        </div>

        {{-- Reiskosten ──────────────────────────────────── --}}
        <hr style="margin:1.25rem 0;border:none;border-top:1px solid #e2e8f0;">
        <h3 style="font-size:.75rem;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.04em;margin-bottom:1rem;">Reiskosten</h3>

        <div class="form-group">
            <label>Vertrekadres <span style="font-weight:400;color:#94a3b8;">— adres van waaruit gereden wordt</span></label>
            <input type="text" name="origin_address"
                value="{{ old('origin_address_'.$account->id, $account->origin_address) }}"
                placeholder="bijv. Ravenswade 132, Nieuwegein">
            <small style="color:#94a3b8;">Dit adres wordt gebruikt om reiskosten automatisch te berekenen in boekingen en offertes.</small>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;max-width:400px;">
            <div class="form-group">
                <label>Gratis km</label>
                <input type="number" name="travel_free_km" min="0" step="1"
                    value="{{ old('travel_free_km_'.$account->id, $account->travel_free_km ?? 20) }}">
                <small style="color:#94a3b8;">Eerste X km gratis (standaard: 20)</small>
            </div>
            <div class="form-group">
                <label>Tarief per km (€)</label>
                <input type="number" name="travel_cost_per_km" min="0" step="0.10"
                    value="{{ old('travel_cost_per_km_'.$account->id, $account->travel_cost_per_km ?? '4.00') }}">
                <small style="color:#94a3b8;">Per km boven gratis (standaard: €4)</small>
            </div>
        </div>

        <div style="margin-top:1.5rem;">
            <button type="submit" class="btn btn-primary">Opslaan</button>
        </div>
    </form>
</div>
@endforeach

@endsection
