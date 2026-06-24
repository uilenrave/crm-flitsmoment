@extends('layouts.app')
@section('title', 'Lead: ' . $lead->name)

@section('actions')
    @if(!$lead->isArchived())
        <a href="{{ route('leads.edit', $lead) }}" class="btn btn-primary">Bewerken</a>
    @else
        <a href="{{ route('leads.index', ['tab' => 'archief']) }}" class="btn btn-secondary">← Terug naar archief</a>
    @endif
@endsection

@section('content')
<div style="display:grid;grid-template-columns:1fr 340px;gap:1.25rem;align-items:start;" class="leads-show-grid">

    {{-- Hoofdkaart --}}
    <div>
        <div class="card" style="margin-bottom:1.25rem;">
            <div class="card-header">
                <div>
                    <div class="card-title">{{ $lead->name }}</div>
                    <div style="font-size:.8rem;color:#64748b;">{{ $lead->lead_number }}</div>
                </div>
                @if($lead->status)
                    <span class="badge" style="background:{{ $lead->status->color }}20;color:{{ $lead->status->color }};font-size:.8rem;padding:.3rem .75rem;">
                        {{ $lead->status->display_name }}
                    </span>
                @endif
            </div>

            <div class="form-grid">
                <div>
                    <label>E-mail</label>
                    <p style="font-size:.875rem;">{{ $lead->email ?? '—' }}</p>
                </div>
                <div>
                    <label>Telefoon</label>
                    <p style="font-size:.875rem;">{{ $lead->phone ?? '—' }}</p>
                </div>
                <div>
                    <label>Eventdatum</label>
                    <p style="font-size:.875rem;">{{ $lead->event_date?->format('d M Y') ?? '—' }}</p>
                </div>
                <div>
                    <label>Tijd</label>
                    <p style="font-size:.875rem;">
                        @if($lead->event_start_time || $lead->event_end_time)
                            {{ $lead->event_start_time ?? '?' }} – {{ $lead->event_end_time ?? '?' }}
                        @else
                            —
                        @endif
                    </p>
                </div>
                <div>
                    <label>Type event</label>
                    <p style="font-size:.875rem;">{{ $lead->eventType?->name ?? '—' }}</p>
                </div>
                <div>
                    <label>Type boeking</label>
                    <p style="font-size:.875rem;">
                        @if($lead->booking_type === 'full_service') 🎯 Full Service
                        @elseif($lead->booking_type === 'to_go') 🧳 To Go
                        @else —
                        @endif
                    </p>
                </div>
                <div style="grid-column:span 2;">
                    <label>Locatie</label>
                    @if($lead->event_location || $lead->event_address || $lead->event_city)
                        @if($lead->event_location)
                            <p style="font-size:.875rem;margin-bottom:.125rem;">{{ $lead->event_location }}</p>
                        @endif
                        @if($lead->event_address || $lead->event_city)
                            <p style="font-size:.8rem;color:#64748b;">
                                {{ implode(', ', array_filter([$lead->event_address, $lead->event_postcode, $lead->event_city])) }}
                            </p>
                        @endif
                    @else
                        <p style="font-size:.875rem;">—</p>
                    @endif
                </div>
                @if($lead->notes)
                <div style="grid-column:span 2;">
                    <label>Notities</label>
                    <p style="font-size:.875rem;white-space:pre-line;">{{ $lead->notes }}</p>
                </div>
                @endif
            </div>
        </div>

        {{-- Producten (indicatief) --}}
        @if($lead->assets->isNotEmpty())
        <div class="card" style="margin-bottom:1.25rem;">
            <div class="card-header">
                <span class="card-title">Geïnteresseerd in</span>
                <span style="font-size:.75rem;color:#64748b;">indicatief — nog niet gereserveerd</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Categorie</th>
                            <th style="text-align:right;">Prijs</th>
                            <th style="text-align:center;">Aantal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lead->assets as $asset)
                        <tr>
                            <td><strong>{{ $asset->name }}</strong></td>
                            <td style="color:#64748b;font-size:.875rem;">{{ $asset->category_label }}</td>
                            <td style="text-align:right;">€ {{ number_format($asset->pivot->price ?? $asset->price, 2, ',', '.') }}</td>
                            <td style="text-align:center;">{{ $asset->pivot->quantity }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Offertes --}}
        @if($lead->offers->isNotEmpty())
        <div class="card" style="margin-bottom:1.25rem;">
            <div class="card-header">
                <span class="card-title">Offertes</span>
                <span style="font-size:.75rem;color:#64748b;">{{ $lead->offers->count() }} offerte(s)</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Nummer</th>
                            <th>Titel</th>
                            <th>Status</th>
                            <th style="text-align:right;">Totaal</th>
                            <th>Datum</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $offerStatusColors = [
                                'draft'    => ['bg' => '#f3f4f6', 'color' => '#6b7280'],
                                'sent'     => ['bg' => '#dbeafe', 'color' => '#2563eb'],
                                'accepted' => ['bg' => '#dcfce7', 'color' => '#16a34a'],
                                'expired'  => ['bg' => '#fef3c7', 'color' => '#d97706'],
                                'declined' => ['bg' => '#fee2e2', 'color' => '#dc2626'],
                            ];
                        @endphp
                        @foreach($lead->offers->sortByDesc('created_at') as $offer)
                        @php $osc = $offerStatusColors[$offer->status] ?? $offerStatusColors['draft']; @endphp
                        <tr style="cursor:pointer;" onclick="window.location='{{ route('offers.show', $offer) }}'">
                            <td style="font-family:monospace;font-size:.8rem;">{{ $offer->offer_number }}</td>
                            <td>{{ $offer->title }}</td>
                            <td>
                                <span style="background:{{ $osc['bg'] }};color:{{ $osc['color'] }};font-size:.75rem;padding:.2rem .5rem;border-radius:9999px;font-weight:600;">
                                    {{ $offer->status_label }}
                                </span>
                            </td>
                            <td style="text-align:right;font-weight:600;">{{ $offer->formatted_total }}</td>
                            <td style="font-size:.8rem;color:#64748b;">{{ $offer->created_at->format('d-m-Y') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Activiteiten --}}
        <div class="card">
            <div class="card-header">
                <span class="card-title">Activiteiten</span>
            </div>

            {{-- Notitie toevoegen --}}
            <form method="POST" action="{{ route('leads.add-note', $lead) }}" style="margin-bottom:1.25rem;">
                @csrf
                <textarea name="note" rows="3" placeholder="Voeg een notitie toe..." style="width:100%;padding:.5rem .75rem;border:1px solid #e2e8f0;border-radius:.375rem;font-size:.875rem;resize:vertical;margin-bottom:.5rem;"></textarea>
                <button type="submit" class="btn btn-primary btn-sm">Notitie opslaan</button>
            </form>

            @forelse($lead->activities as $activiteit)
            <div style="padding:.75rem 0;border-bottom:1px solid #f1f5f9;">
                <div style="display:flex;justify-content:space-between;margin-bottom:.25rem;">
                    <span style="font-size:.8rem;font-weight:600;">{{ $activiteit->title }}</span>
                    <span style="font-size:.75rem;color:#64748b;">{{ $activiteit->created_at->format('d-m-Y H:i') }}</span>
                </div>
                @if($activiteit->description)
                    <p style="font-size:.8rem;color:#475569;white-space:pre-line;">{{ $activiteit->description }}</p>
                @endif
                <span style="font-size:.7rem;color:#94a3b8;">door {{ $activiteit->user?->full_name ?? 'Systeem' }}</span>
            </div>
            @empty
            <p style="color:#64748b;font-size:.875rem;">Nog geen activiteiten.</p>
            @endforelse
        </div>
    </div>

    {{-- Zijbalk --}}
    <div>
        <div class="card" style="margin-bottom:1rem;">
            <div class="card-header"><span class="card-title">Details</span></div>
            <div style="font-size:.8rem;">
                <div style="padding:.5rem 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;">
                    <span style="color:#64748b;">Bron</span>
                    <span>{{ $lead->source?->name ?? '—' }}</span>
                </div>
                <div style="padding:.5rem 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;">
                    <span style="color:#64748b;">Toegewezen aan</span>
                    <span>{{ $lead->assignedTo?->full_name ?? '—' }}</span>
                </div>
                <div style="padding:.5rem 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;">
                    <span style="color:#64748b;">Aangemaakt</span>
                    <span>{{ $lead->created_at->format('d-m-Y') }}</span>
                </div>
                <div style="padding:.5rem 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;">
                    <span style="color:#64748b;">Bijgewerkt</span>
                    <span>{{ $lead->updated_at->format('d-m-Y') }}</span>
                </div>
                <div style="padding:.5rem 0;display:flex;justify-content:space-between;align-items:center;">
                    <span style="color:#64748b;">📅 Opvolging</span>
                    @if($lead->follow_up_at)
                        @if($lead->follow_up_at->isPast() && !$lead->follow_up_at->isToday())
                            <span style="font-weight:600;color:#dc2626;">
                                {{ $lead->follow_up_at->format('d M Y') }}
                                <span style="font-size:.7rem;background:#fee2e2;padding:.1rem .35rem;border-radius:999px;">achterstallig</span>
                            </span>
                        @elseif($lead->follow_up_at->isToday())
                            <span style="font-weight:600;color:#d97706;">
                                Vandaag
                                <span style="font-size:.7rem;background:#fef3c7;padding:.1rem .35rem;border-radius:999px;">⏰</span>
                            </span>
                        @else
                            <span style="font-weight:600;color:#374151;">{{ $lead->follow_up_at->format('d M Y') }}</span>
                        @endif
                    @else
                        <a href="{{ route('leads.edit', $lead) }}" style="font-size:.75rem;color:#94a3b8;text-decoration:none;">Instellen →</a>
                    @endif
                </div>
            </div>
        </div>

        <div class="card">
            @if($lead->isArchived())
                <div style="padding:.75rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:.375rem;margin-bottom:.75rem;font-size:.8rem;color:#64748b;text-align:center;">
                    @if($lead->archive_reason === 'won')
                        <span style="color:#16a34a;font-weight:600;">✓ Gewonnen</span> — Omgezet naar boeking
                    @else
                        <span style="color:#dc2626;font-weight:600;">✕ Afgewezen</span> — Gearchiveerd op {{ $lead->archived_at->format('d-m-Y') }}
                    @endif
                </div>
            @else
                <a href="{{ route('bookings.create', ['lead_id' => $lead->id]) }}" class="btn btn-primary" style="width:100%;justify-content:center;">
                    Omzetten naar boeking
                </a>

                <a href="{{ route('offers.create', ['lead_id' => $lead->id]) }}" class="btn btn-secondary" style="width:100%;justify-content:center;margin-top:.5rem;">
                    Maak offerte
                </a>

                {{-- Afwijzen --}}
                <div style="margin-top:.75rem;">
                    <button type="button" class="btn btn-secondary" style="width:100%;justify-content:center;"
                        onclick="document.getElementById('afwijzen-form').style.display='block';this.style.display='none'">
                        Afwijzen
                    </button>
                    <div id="afwijzen-form" style="display:none;margin-top:.5rem;">
                        <form method="POST" action="{{ route('leads.afwijzen', $lead) }}">
                            @csrf
                            <textarea name="reden" rows="2" placeholder="Optioneel: reden van afwijzing..." style="width:100%;padding:.5rem;border:1px solid #e2e8f0;border-radius:.375rem;font-size:.8rem;margin-bottom:.5rem;resize:none;"></textarea>
                            <button type="submit" class="btn btn-danger" style="width:100%;justify-content:center;"
                                onclick="crmConfirm('Lead afwijzen en archiveren?', () => this.closest('form').submit()); return false;">
                                Bevestig afwijzing
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            <div style="margin-top:.75rem;">
                <form method="POST" action="{{ route('leads.destroy', $lead) }}" data-confirm="Weet u zeker dat u deze lead permanent wilt verwijderen?">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger" style="width:100%;justify-content:center;opacity:.6;font-size:.8rem;">Verwijderen</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
