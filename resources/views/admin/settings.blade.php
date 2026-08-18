@extends('layouts.app')
@section('title', 'Account instellingen')

@section('content')

@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:1rem;">{{ session('success') }}</div>
@endif

@foreach($accounts as $account)
@php
    $icalToken    = hash_hmac('sha256', (string) $account->id, config('app.key'));
    $icalUrl      = url('/ical/' . $icalToken . '.ics');
    $webhookUrl   = url('/webhook/lead/' . $icalToken);
@endphp
<div class="card" style="margin-bottom:1.5rem;">
    <h2 style="font-size:1rem;font-weight:700;margin-bottom:1.5rem;padding-bottom:.75rem;border-bottom:1px solid #e2e8f0;">
        {{ $account->name }}
    </h2>

    {{-- AI-verbruik (credits) ─────────────────────────────── --}}
    @php($usage = $aiUsage[$account->id] ?? ['gemini' => ['total'=>0,'month'=>0], 'openai' => ['total'=>0,'month'=>0]])
    <div style="margin-bottom:1.5rem;padding:1.25rem;background:#faf5ff;border:1px solid #e9d5ff;border-radius:.5rem;">
        <h3 style="font-size:.75rem;font-weight:600;color:#7c3aed;text-transform:uppercase;letter-spacing:.04em;margin:0 0 .75rem;">🤖 AI-verbruik ontwerp-generator</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.75rem;">
            <div style="background:#fff;border:1px solid #e9d5ff;border-radius:.5rem;padding:.85rem 1rem;">
                <div style="font-size:.8rem;font-weight:600;color:#334155;">🎨 Achtergronden <span style="color:#94a3b8;font-weight:400;">— Gemini</span></div>
                <div style="font-size:1.5rem;font-weight:800;color:#7c3aed;line-height:1.2;margin-top:.2rem;">{{ $usage['gemini']['total'] }}</div>
                <div style="font-size:.75rem;color:#64748b;">totaal · <strong>{{ $usage['gemini']['month'] }}</strong> deze maand</div>
            </div>
            <div style="background:#fff;border:1px solid #e9d5ff;border-radius:.5rem;padding:.85rem 1rem;">
                <div style="font-size:.8rem;font-weight:600;color:#334155;">✂️ Logo's vrijstellen <span style="color:#94a3b8;font-weight:400;">— GPT</span></div>
                <div style="font-size:1.5rem;font-weight:800;color:#7c3aed;line-height:1.2;margin-top:.2rem;">{{ $usage['openai']['total'] }}</div>
                <div style="font-size:.75rem;color:#64748b;">totaal · <strong>{{ $usage['openai']['month'] }}</strong> deze maand</div>
            </div>
        </div>
        <p style="font-size:.72rem;color:#94a3b8;margin:.7rem 0 0;">Elke telling = één AI-generatie (≈ 1 credit). Achtergronden via Gemini, logo-vrijstellingen via GPT/OpenAI.</p>
    </div>

    {{-- iCal agenda koppeling ────────────────────────────── --}}
    <div style="margin-bottom:1.5rem;padding:1.25rem;background:#f0f9ff;border:1px solid #bae6fd;border-radius:.5rem;">
        <h3 style="font-size:.75rem;font-weight:600;color:#0369a1;text-transform:uppercase;letter-spacing:.04em;margin:0 0 .75rem;">📅 Agenda-koppeling (iCal / ICS)</h3>
        <p style="font-size:.875rem;color:#374151;margin:0 0 .75rem;">
            Voeg deze link toe aan Google Agenda, Apple Agenda of Outlook als <strong>URL-agenda</strong> om je boekingen, bezorgmomenten en ophaalmomenten automatisch te zien.
        </p>
        <div style="display:flex;gap:.5rem;align-items:center;">
            <input type="text" id="ical-url-{{ $account->id }}" value="{{ $icalUrl }}" readonly
                style="flex:1;font-family:monospace;font-size:.78rem;background:#fff;border:1px solid #cbd5e1;border-radius:.375rem;padding:.5rem .75rem;color:#1e293b;">
            <button type="button" onclick="
                navigator.clipboard.writeText('{{ $icalUrl }}');
                this.textContent='✓ Gekopieerd';
                this.style.background='#16a34a';
                setTimeout(() => { this.textContent='Kopieer'; this.style.background=''; }, 2000);
            " style="padding:.5rem 1rem;background:#0ea5e9;color:#fff;border:none;border-radius:.375rem;font-size:.875rem;font-weight:600;cursor:pointer;white-space:nowrap;">
                Kopieer
            </button>
        </div>
        <p style="font-size:.78rem;color:#64748b;margin:.5rem 0 0;">
            🔒 De link is beveiligd met een unieke token. Deel hem niet openbaar.
            &nbsp;·&nbsp;
            <strong>Google Agenda:</strong> Andere agenda's → Via URL → plak de link
        </p>
    </div>

    {{-- Webhook voor leads ──────────────────────────────── --}}
    <div style="margin-bottom:1.5rem;padding:1.25rem;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:.5rem;">
        <h3 style="font-size:.75rem;font-weight:600;color:#15803d;text-transform:uppercase;letter-spacing:.04em;margin:0 0 .75rem;">🔗 Webhook — Leads vanuit website</h3>
        <p style="font-size:.875rem;color:#374151;margin:0 0 .75rem;">
            Koppel je Elementor-formulier aan het CRM. Nieuwe inzendingen komen automatisch als lead binnen met bron <strong>Website</strong>.
        </p>
        <div style="display:flex;gap:.5rem;align-items:center;">
            <input type="text" id="webhook-url-{{ $account->id }}" value="{{ $webhookUrl }}" readonly
                style="flex:1;font-family:monospace;font-size:.78rem;background:#fff;border:1px solid #cbd5e1;border-radius:.375rem;padding:.5rem .75rem;color:#1e293b;">
            <button type="button" onclick="
                navigator.clipboard.writeText('{{ $webhookUrl }}');
                this.textContent='✓ Gekopieerd';
                this.style.background='#16a34a';
                setTimeout(() => { this.textContent='Kopieer'; this.style.background=''; }, 2000);
            " style="padding:.5rem 1rem;background:#22c55e;color:#fff;border:none;border-radius:.375rem;font-size:.875rem;font-weight:600;cursor:pointer;white-space:nowrap;">
                Kopieer
            </button>
        </div>
        <p style="font-size:.78rem;color:#64748b;margin:.5rem 0 0;">
            🔒 Beveiligd met dezelfde token als de agenda-koppeling. Deel niet openbaar.
            &nbsp;·&nbsp;
            <strong>Elementor:</strong> Formulier → Acties na verzenden → Webhook → plak de URL
        </p>
    </div>

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
