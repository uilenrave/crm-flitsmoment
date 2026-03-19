@extends('layouts.app')
@section('title', 'Offerte: ' . $offer->offer_number)

@section('actions')
    <a href="{{ route('leads.show', $offer->lead_id) }}" class="btn btn-secondary">← Terug naar lead</a>
@endsection

@section('content')
@php
    $statusColors = [
        'draft'    => ['bg' => '#f3f4f6', 'color' => '#6b7280'],
        'sent'     => ['bg' => '#dbeafe', 'color' => '#2563eb'],
        'accepted' => ['bg' => '#dcfce7', 'color' => '#16a34a'],
        'expired'  => ['bg' => '#fef3c7', 'color' => '#d97706'],
        'declined' => ['bg' => '#fee2e2', 'color' => '#dc2626'],
    ];
    $sc = $statusColors[$offer->status] ?? $statusColors['draft'];
@endphp

<div style="display:grid;grid-template-columns:1fr 340px;gap:1.25rem;align-items:start;">

    {{-- Hoofdkolom --}}
    <div>
        {{-- Header --}}
        <div class="card" style="margin-bottom:1.25rem;">
            <div class="card-header">
                <div>
                    <div class="card-title">{{ $offer->title }}</div>
                    <div style="font-size:.8rem;color:#64748b;">{{ $offer->offer_number }}</div>
                </div>
                <span style="background:{{ $sc['bg'] }};color:{{ $sc['color'] }};font-size:.8rem;padding:.3rem .75rem;border-radius:9999px;font-weight:600;">
                    {{ $offer->status_label }}
                </span>
            </div>

            <div class="form-grid">
                <div>
                    <label>Klant</label>
                    <p style="font-size:.875rem;">{{ $offer->lead?->name ?? '—' }}</p>
                </div>
                <div>
                    <label>E-mail</label>
                    <p style="font-size:.875rem;">{{ $offer->lead?->email ?? '—' }}</p>
                </div>
                <div>
                    <label>Eventdatum</label>
                    <p style="font-size:.875rem;">{{ $offer->lead?->event_date?->format('d M Y') ?? '—' }}</p>
                </div>
                <div>
                    <label>Locatie</label>
                    <p style="font-size:.875rem;">{{ $offer->lead?->event_location ?? '—' }}</p>
                </div>
                @if($offer->sent_at)
                <div>
                    <label>Verstuurd op</label>
                    <p style="font-size:.875rem;">{{ $offer->sent_at->format('d-m-Y H:i') }}</p>
                </div>
                @endif
                @if($offer->accepted_at)
                <div>
                    <label>Geaccepteerd op</label>
                    <p style="font-size:.875rem;color:#16a34a;font-weight:600;">{{ $offer->accepted_at->format('d-m-Y H:i') }}</p>
                </div>
                @endif
                @if($offer->expires_at)
                <div>
                    <label>Geldig tot</label>
                    <p style="font-size:.875rem;{{ $offer->expires_at->isPast() ? 'color:#dc2626;' : '' }}">
                        {{ $offer->expires_at->format('d-m-Y') }}
                        @if($offer->expires_at->isPast()) (verlopen) @endif
                    </p>
                </div>
                @endif
            </div>
        </div>

        {{-- Line items --}}
        <div class="card" style="margin-bottom:1.25rem;">
            <div class="card-header"><span class="card-title">Producten & diensten</span></div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Omschrijving</th>
                            <th style="text-align:center;">Aantal</th>
                            <th style="text-align:right;">Stukprijs</th>
                            <th style="text-align:right;">Totaal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($offer->line_items ?? [] as $item)
                        <tr>
                            <td>{{ $item['description'] ?? '—' }}</td>
                            <td style="text-align:center;">{{ $item['quantity'] ?? 1 }}</td>
                            <td style="text-align:right;">€ {{ number_format($item['unit_price'] ?? 0, 2, ',', '.') }}</td>
                            <td style="text-align:right;font-weight:600;">€ {{ number_format($item['total'] ?? 0, 2, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="padding:.75rem 1rem;border-top:1px solid #e2e8f0;">
                <div style="display:flex;justify-content:space-between;margin-bottom:.25rem;">
                    <span style="font-size:.875rem;color:#64748b;">Subtotaal</span>
                    <span style="font-size:.875rem;">€ {{ number_format((float)$offer->subtotal, 2, ',', '.') }}</span>
                </div>
                @if((float)$offer->discount > 0)
                <div style="display:flex;justify-content:space-between;margin-bottom:.25rem;">
                    <span style="font-size:.875rem;color:#16a34a;">Korting</span>
                    <span style="font-size:.875rem;color:#16a34a;">−€ {{ number_format((float)$offer->discount, 2, ',', '.') }}</span>
                </div>
                @endif
                <div style="display:flex;justify-content:space-between;padding-top:.5rem;border-top:2px solid #1e293b;">
                    <span style="font-weight:700;">Totaal</span>
                    <span style="font-weight:700;font-size:1.125rem;">€ {{ number_format((float)$offer->total, 2, ',', '.') }}</span>
                </div>
            </div>
        </div>

        {{-- Voorwaarden --}}
        @if($offer->terms_text)
        <div class="card">
            <div class="card-header"><span class="card-title">Voorwaarden</span></div>
            <div style="padding:1rem;font-size:.8rem;color:#475569;white-space:pre-line;line-height:1.6;">{{ $offer->terms_text }}</div>
        </div>
        @endif

        {{-- Gekoppelde boeking --}}
        @if($offer->booking)
        <div class="card" style="margin-top:1.25rem;">
            <div class="card-header"><span class="card-title">Gekoppelde boeking</span></div>
            <div style="padding:1rem;display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <div style="font-weight:600;">{{ $offer->booking->booking_number }}</div>
                    <div style="font-size:.8rem;color:#64748b;">{{ $offer->booking->customer_name }} — {{ $offer->booking->event_date?->format('d M Y') }}</div>
                </div>
                <a href="{{ route('bookings.show', $offer->booking) }}" class="btn btn-primary btn-sm">Bekijken</a>
            </div>
        </div>
        @endif
    </div>

    {{-- Zijbalk --}}
    <div>
        {{-- Acties --}}
        <div class="card" style="margin-bottom:1rem;">
            @if(in_array($offer->status, ['sent']))
                <form method="POST" action="{{ route('offers.send', $offer) }}" style="margin-bottom:.75rem;">
                    @csrf
                    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;"
                        onclick="return confirm('Offerte per email versturen naar {{ $offer->lead?->email }}?')">
                        ✉ Verstuur per email
                    </button>
                </form>

                <a href="{{ route('offers.edit', $offer) }}" class="btn btn-secondary" style="width:100%;justify-content:center;margin-bottom:.75rem;">
                    Bewerken
                </a>
            @endif

            <a href="{{ route('offer.show', $offer->public_token) }}" target="_blank" class="btn btn-secondary" style="width:100%;justify-content:center;margin-bottom:.75rem;">
                Bekijk offertepagina ↗
            </a>

            {{-- Kopieer link --}}
            <div style="margin-bottom:.75rem;">
                <label style="font-size:.75rem;color:#64748b;display:block;margin-bottom:.25rem;">Publieke link</label>
                <div style="display:flex;gap:.375rem;">
                    <input type="text" id="offer-link" value="{{ route('offer.show', $offer->public_token) }}" readonly
                        style="flex:1;padding:.375rem .5rem;border:1px solid #e2e8f0;border-radius:.375rem;font-size:.7rem;background:#f8fafc;">
                    <button type="button" onclick="copyLink()" class="btn btn-sm btn-secondary" style="white-space:nowrap;">Kopieer</button>
                </div>
            </div>

            @if($offer->status === 'draft')
                <form method="POST" action="{{ route('offers.destroy', $offer) }}"
                    onsubmit="return confirm('Weet u zeker dat u deze offerte wilt verwijderen?')" style="margin-top:.75rem;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger" style="width:100%;justify-content:center;opacity:.6;font-size:.8rem;">Verwijderen</button>
                </form>
            @endif
        </div>

        {{-- Lead link --}}
        <div class="card">
            <div class="card-header"><span class="card-title">Lead</span></div>
            <div style="padding:.5rem 0;">
                <a href="{{ route('leads.show', $offer->lead_id) }}" style="font-size:.875rem;color:#2563eb;text-decoration:none;">
                    {{ $offer->lead?->name ?? 'Onbekend' }} ({{ $offer->lead?->lead_number }})
                </a>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function copyLink() {
        const input = document.getElementById('offer-link');
        input.select();
        navigator.clipboard.writeText(input.value);
        const btn = input.nextElementSibling;
        btn.textContent = '✓ Gekopieerd';
        setTimeout(() => btn.textContent = 'Kopieer', 2000);
    }
</script>
@endpush
@endsection
