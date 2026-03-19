@extends('layouts.app')
@section('title', 'Boeking: ' . $booking->booking_number)

@section('actions')
    <a href="{{ route('bookings.edit', $booking) }}" class="btn btn-primary">Bewerken</a>
@endsection

@section('content')
@php
    // Color palette from design system (PHP variables for inline styles)
    $colors = [
        'primary-600' => '#ea580c',
        'success-600' => '#16a34a',
        'danger-600'  => '#dc2626',
        'warning-600' => '#d97706',
        'gray-500'    => '#737373',
        'gray-600'    => '#525252',
        'success-50'  => '#dcfce7',
        'primary-50'  => '#fff7ed',
        'primary-100' => '#ffedd5',
        'gray-50'     => '#fafafa',
        'gray-100'    => '#f5f5f5',
        'gray-300'    => '#d4d4d4',
        'gray-700'    => '#404040',
    ];

    $statusLabels   = ['confirmed'=>'Bevestigd','completed'=>'Voltooid','cancelled'=>'Geannuleerd','no_show'=>'No-show'];
    $statusKleuren  = ['confirmed'=>$colors['primary-600'],'completed'=>$colors['success-600'],'cancelled'=>$colors['danger-600'],'no_show'=>$colors['warning-600']];
    $betalingLabels = ['unpaid'=>'Niet betaald','partial'=>'Gedeeltelijk betaald','paid'=>'Betaald','cancelled'=>'Geannuleerd','refunded'=>'Terugbetaald'];
    $betalingKleuren= ['unpaid'=>$colors['danger-600'],'partial'=>$colors['warning-600'],'paid'=>$colors['success-600'],'cancelled'=>$colors['gray-500'],'refunded'=>$colors['primary-600']];
    $stripLabels    = ['waiting'=>'Wachten op input','in_progress'=>'Wordt gemaakt','review'=>'Ter beoordeling','accepted'=>'Geaccepteerd ✓','done'=>'Ingezet bij event'];
    $stripKleuren   = ['waiting'=>$colors['warning-600'],'in_progress'=>$colors['primary-600'],'review'=>$colors['primary-600'],'accepted'=>$colors['success-600'],'done'=>$colors['success-600']];
    $typeLabels     = ['full_service'=>'🚚 Full Service','to_go'=>'🏠 To Go'];
@endphp

<div style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem;align-items:start;">
    <div>
        {{-- Klant & event --}}
        <div class="card neu-card" style="margin-bottom:1.5rem;">
            <div class="card-header">
                <div>
                    <div class="card-title">{{ $booking->customer_name }}</div>
                    <div class="text-xs text-muted">{{ $booking->booking_number }}</div>
                </div>
                <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;justify-content:flex-end;">
                    @if($booking->booking_type)
                    <span class="badge" style="background:{{ $colors['gray-100'] }};color:{{ $colors['gray-600'] }};">{{ $typeLabels[$booking->booking_type] ?? $booking->booking_type }}</span>
                    @endif
                    <span class="badge" style="background:{{ $statusKleuren[$booking->status] ?? $colors['gray-500'] }}20;color:{{ $statusKleuren[$booking->status] ?? $colors['gray-500'] }};padding:.3rem .75rem;font-size:.8rem;">
                        {{ $statusLabels[$booking->status] ?? $booking->status }}
                    </span>
                </div>
            </div>

            <div class="form-grid">
                <div>
                    <label>E-mail</label>
                    <p class="text-sm">
                        @if($booking->customer_email)
                            <a href="mailto:{{ $booking->customer_email }}" style="color:{{ $colors['primary-600'] }};text-decoration:none;font-weight:500;">{{ $booking->customer_email }}</a>
                        @else <span class="text-muted">—</span>
                        @endif
                    </p>
                </div>
                <div>
                    <label>Telefoon</label>
                    <p class="text-sm">
                        @if($booking->customer_phone)
                            {{ $booking->customer_phone }}
                            @if($booking->whatsapp_number)
                            <a href="https://wa.me/{{ ltrim($booking->whatsapp_number, '+') }}" target="_blank"
                               style="margin-left:0.5rem;font-size:0.75rem;color:{{ $colors['success-600'] }};font-weight:600;">WhatsApp</a>
                            @endif
                        @else <span class="text-muted">—</span>
                        @endif
                    </p>
                </div>
                <div>
                    <label>Eventdatum</label>
                    <p style="font-size:.875rem;font-weight:600;">{{ $booking->event_date->format('d M Y') }}</p>
                </div>
                <div>
                    <label>Tijdstip</label>
                    <p style="font-size:.875rem;">
                        @if($booking->event_start_time || $booking->event_end_time)
                            {{ $booking->event_start_time ?? '?' }} — {{ $booking->event_end_time ?? '?' }}
                        @else —
                        @endif
                    </p>
                </div>

                @if($booking->booking_type === 'full_service')
                <div>
                    <label>Bezorging</label>
                    <p style="font-size:.875rem;">{{ $booking->delivery_at?->format('d-m-Y H:i') ?? '—' }}</p>
                </div>
                <div>
                    <label>Ophalen door ons</label>
                    <p style="font-size:.875rem;">{{ $booking->pickup_at?->format('d-m-Y H:i') ?? '—' }}</p>
                </div>
                @elseif($booking->booking_type === 'to_go')
                <div>
                    <label>Ophalen bij ons</label>
                    <p style="font-size:.875rem;">{{ $booking->customer_pickup_at?->format('d-m-Y H:i') ?? '—' }}</p>
                </div>
                <div>
                    <label>Terugbrengen</label>
                    <p style="font-size:.875rem;">{{ $booking->customer_return_at?->format('d-m-Y H:i') ?? '—' }}</p>
                </div>
                @endif

                <div style="grid-column:span 2;">
                    <label>Locatie</label>
                    <p style="font-size:.875rem;">
                        @if($booking->event_location){{ $booking->event_location }}@endif
                        @if($booking->event_address)
                            <span style="color:#64748b;margin-left:.25rem;">· {{ $booking->event_address }}, {{ $booking->event_postcode }} {{ $booking->event_city }}</span>
                        @endif
                        @if(!$booking->event_location && !$booking->event_address) — @endif
                    </p>
                </div>

                <div>
                    <label>Fotostrip</label>
                    <span class="badge" style="background:{{ $stripKleuren[$booking->strip_status ?? 'waiting'] ?? $colors['gray-500'] }}20;color:{{ $stripKleuren[$booking->strip_status ?? 'waiting'] ?? $colors['gray-500'] }};">
                        {{ $stripLabels[$booking->strip_status ?? 'waiting'] ?? '—' }}
                    </span>
                </div>
                <div>
                    <label>Betalingsstatus</label>
                    <span class="badge" style="background:{{ $betalingKleuren[$booking->payment_status] ?? $colors['gray-500'] }}20;color:{{ $betalingKleuren[$booking->payment_status] ?? $colors['gray-500'] }};">
                        {{ $betalingLabels[$booking->payment_status] ?? $booking->payment_status }}
                    </span>
                </div>

                @if($booking->event_notes)
                <div style="grid-column:span 2;">
                    <label>Opmerkingen</label>
                    <p style="font-size:.875rem;white-space:pre-line;">{{ $booking->event_notes }}</p>
                </div>
                @endif
            </div>
        </div>

        {{-- Fotostrip intake input van klant --}}
        @if($booking->strip_intake_data)
        <div class="card neu-card" style="margin-bottom:1.5rem;">
            <div class="card-header">
                <span class="card-title">Fotostrip input van klant</span>
                <span style="font-size:.75rem;color:#64748b;">Ontvangen op {{ \Carbon\Carbon::parse($booking->strip_intake_data['submitted_at'])->format('d M Y \o\m H:i') }}</span>
            </div>
            @if($booking->strip_intake_data['text'])
            <div style="margin-bottom:1rem;">
                <label>Omschrijving</label>
                <p style="margin-top:.25rem;font-size:.875rem;white-space:pre-line;color:#374151;line-height:1.6;">{{ $booking->strip_intake_data['text'] }}</p>
            </div>
            @endif
            @if(! empty($booking->strip_intake_data['files']))
            <div>
                <label>Bestanden</label>
                <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.25rem;">
                    @foreach($booking->strip_intake_data['files'] as $file)
                    @php $isImg = preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file['filename'] ?? ''); @endphp
                    <a href="{{ $file['url'] }}" target="_blank" style="display:inline-flex;align-items:center;gap:.4rem;padding:.4rem .75rem;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;font-size:.8rem;color:#1d4ed8;text-decoration:none;">
                        {{ $isImg ? '🖼' : '📄' }} {{ $file['filename'] }}
                    </a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        @endif

        {{-- Fotostrip ontwerp --}}
        @if($booking->strip_design_url || $booking->gallery_url || $booking->strip_notes || $booking->strip_feedback)
        <div class="card neu-card" style="margin-bottom:1.5rem;">
            <div class="card-header"><span class="card-title">Fotostrip & galerij</span></div>

            @if($booking->strip_design_url)
            <div style="margin-bottom:{{ ($booking->strip_notes || $booking->strip_feedback) ? '0' : '1rem' }};">
                <label>Fotostrip ontwerp</label>
                @php
                    $isImage = preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', parse_url($booking->strip_design_url, PHP_URL_PATH));
                @endphp
                @if($isImage)
                    <div style="margin-top:.5rem;">
                        <a href="{{ $booking->strip_design_url }}" target="_blank">
                            <img src="{{ $booking->strip_design_url }}" alt="Fotostrip ontwerp"
                                 style="max-width:100%;border:1px solid #e2e8f0;border-radius:.5rem;display:block;">
                        </a>
                    </div>
                @else
                    <p style="font-size:.875rem;"><a href="{{ $booking->strip_design_url }}" target="_blank">📄 Ontwerp bekijken →</a></p>
                @endif
            </div>

            {{-- Opmerkingen: huidig ontwerp + oudere versies --}}
            @php
                $allComments = collect($booking->strip_comments ?? []);
                $huidigeComments = $allComments->filter(fn($c) => ($c['design_url'] ?? null) === $booking->strip_design_url)->reverse()->values();
                $oudeComments    = $allComments->filter(fn($c) => ($c['design_url'] ?? null) !== $booking->strip_design_url)->reverse()->values();
            @endphp

            @if($huidigeComments->count() > 0)
            <div style="margin-top:.75rem;margin-bottom:1rem;">
                <div style="font-size:.75rem;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.5rem;">
                    💬 Opmerkingen huidig ontwerp ({{ $huidigeComments->count() }})
                </div>
                @foreach($huidigeComments as $idx => $comment)
                @php $commentDate = \Carbon\Carbon::parse($comment['created_at']); @endphp
                <div style="padding:.875rem 1rem;background:#fef3c7;border:1px solid #fcd34d;border-radius:.5rem;margin-bottom:.5rem;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.4rem;">
                        <span style="font-size:.75rem;font-weight:600;color:#92400e;">Opmerking {{ $huidigeComments->count() - $idx }}</span>
                        <span style="font-size:.75rem;color:#92400e;">{{ $commentDate->format('d M Y \o\m H:i') }}</span>
                    </div>
                    <p style="font-size:.875rem;white-space:pre-line;margin:0;color:#1e293b;line-height:1.6;">{{ $comment['text'] }}</p>
                </div>
                @endforeach
            </div>
            @endif

            @if($oudeComments->count() > 0)
            <div style="margin-bottom:1rem;">
                <button type="button" onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display==='none'?'block':'none';this.textContent=this.textContent.includes('▶')?'▼ Oudere versies verbergen':'▶ Oudere versies tonen ('+{{ $oudeComments->count() }}+')';"
                    style="background:none;border:none;cursor:pointer;font-size:.75rem;color:#94a3b8;padding:0;">
                    ▶ Oudere versies tonen ({{ $oudeComments->count() }})
                </button>
                <div style="display:none;margin-top:.5rem;">
                    @foreach($oudeComments as $idx => $comment)
                    @php $commentDate = \Carbon\Carbon::parse($comment['created_at']); @endphp
                    <div style="padding:.75rem 1rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:.5rem;margin-bottom:.4rem;opacity:.7;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.35rem;">
                            <span style="font-size:.72rem;font-weight:600;color:#64748b;">Oudere versie</span>
                            <span style="font-size:.72rem;color:#94a3b8;">{{ $commentDate->format('d M Y \o\m H:i') }}</span>
                        </div>
                        <p style="font-size:.8rem;white-space:pre-line;margin:0;color:#475569;line-height:1.5;">{{ $comment['text'] }}</p>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
            @endif

            @if($booking->gallery_url)
            <div>
                <label>Fotogalerij</label>
                <p style="font-size:.875rem;margin-top:.25rem;">
                    <a href="{{ $booking->gallery_url }}" target="_blank" style="color:#16a34a;font-weight:600;">📸 Galerij bekijken →</a>
                </p>
            </div>
            @endif
        </div>
        @endif

        {{-- Intake antwoorden --}}
        @if($booking->intake_data)
        <div class="card neu-card" style="margin-bottom:1.5rem;">
            <div class="card-header">
                <span class="card-title">📋 Intake antwoorden</span>
                @if($booking->intake_completed)
                <span class="badge" style="background:{{ $colors['success-50'] }};color:{{ $colors['success-600'] }};margin-left:auto;">Compleet</span>
                @else
                <span class="badge" style="background:{{ $colors['primary-100'] }};color:{{ $colors['primary-600'] }};margin-left:auto;">Stap {{ $booking->intake_current_step }}/3</span>
                @endif
            </div>
            @php $intake = $booking->intake_data; @endphp
            <div style="padding:1.25rem;font-size:.875rem;">

                @if(isset($intake['step_1']))
                <div style="margin-bottom:1rem;padding-bottom:1rem;border-bottom:1px solid {{ $colors['gray-100'] }};">
                    <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:{{ $colors['gray-500'] }};margin-bottom:.35rem;">1. Fotostrip ontwerp</div>
                    <p style="font-weight:600;">{{ ($intake['step_1']['design_choice'] ?? '') === 'self_input' ? 'Levert zelf input aan' : 'Gebruikt template' }}</p>
                    @if(!empty($intake['step_1']['uploaded_files']))
                    <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.5rem;">
                        @foreach($intake['step_1']['uploaded_files'] as $file)
                        <a href="{{ $file }}" target="_blank" style="font-size:.75rem;color:{{ $colors['primary-600'] }};text-decoration:underline;">📎 Bestand {{ $loop->iteration }}</a>
                        @endforeach
                    </div>
                    @endif
                </div>
                @endif

                @if(isset($intake['step_2']))
                <div style="margin-bottom:1rem;padding-bottom:1rem;border-bottom:1px solid {{ $colors['gray-100'] }};">
                    <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:{{ $colors['gray-500'] }};margin-bottom:.35rem;">2. Bezorgtijd</div>
                    <p>{{ $intake['step_2']['delivery_time_preference'] ?? '—' }}</p>
                    @if(!empty($intake['step_2']['delivery_notes']))
                    <p style="color:{{ $colors['gray-600'] }};margin-top:.25rem;">{{ $intake['step_2']['delivery_notes'] }}</p>
                    @endif
                </div>
                @endif

                @if(isset($intake['step_3']))
                <div style="margin-bottom:.5rem;">
                    <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:{{ $colors['gray-500'] }};margin-bottom:.35rem;">3. Ophalen</div>
                    <p style="font-weight:600;">{{ ($intake['step_3']['pickup_preference'] ?? '') === 'same_evening' ? 'Dezelfde avond voor 22:00' : 'Volgende ochtend' }}</p>
                    @if(($intake['step_3']['pickup_preference'] ?? '') === 'next_morning')
                        @if(!empty($intake['step_3']['pickup_time']))
                        <p style="margin-top:.25rem;">Tijdstip: {{ $intake['step_3']['pickup_time'] }}</p>
                        @endif
                        @if(!empty($intake['step_3']['pickup_contact_person']))
                        <p>Contact: {{ $intake['step_3']['pickup_contact_person'] }}</p>
                        @endif
                    @endif
                </div>
                @endif

                @if($booking->intake_completed_at)
                <div style="font-size:.75rem;color:{{ $colors['gray-500'] }};margin-top:.75rem;padding-top:.75rem;border-top:1px solid {{ $colors['gray-100'] }};">
                    Ingevuld op {{ $booking->intake_completed_at->format('d M Y \o\m H:i') }}
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Producten --}}
        @if($booking->items->count())
        <div class="card neu-card" style="margin-bottom:1.5rem;">
            <div class="card-header"><span class="card-title">Producten</span></div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th style="text-align:right;">Prijs excl.</th>
                            <th style="text-align:right;">BTW 21%</th>
                            <th style="text-align:right;">Prijs incl.</th>
                            <th style="text-align:center;">Aantal</th>
                            <th style="text-align:right;">Totaal incl.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($booking->items as $item)
                        @php
                            $prijsExcl = $item->price_snapshot / 1.21;
                            $prijsBtw  = $item->price_snapshot - $prijsExcl;
                        @endphp
                        <tr>
                            <td>{{ $item->name_snapshot }}</td>
                            <td style="text-align:right;color:#64748b;">€ {{ number_format($prijsExcl, 2, ',', '.') }}</td>
                            <td style="text-align:right;color:#64748b;">€ {{ number_format($prijsBtw, 2, ',', '.') }}</td>
                            <td style="text-align:right;">€ {{ number_format($item->price_snapshot, 2, ',', '.') }}</td>
                            <td style="text-align:center;">{{ $item->quantity }}</td>
                            <td style="text-align:right;font-weight:600;">€ {{ number_format($item->linetotal, 2, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Betalingen --}}
        <div class="card neu-card">
            <div class="card-header">
                <span class="card-title">Betalingen</span>
            </div>
            @forelse($booking->payments as $betaling)
            <div style="padding:0.625rem 1.25rem;border-bottom:1px solid {{ $colors['gray-100'] }};display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <div class="text-sm" style="font-weight:500;">€ {{ number_format($betaling->amount, 2, ',', '.') }}</div>
                    <div class="text-xs text-muted">{{ $betaling->provider }} — {{ $betaling->created_at->format('d-m-Y') }}</div>
                </div>
                <span class="badge badge-success" style="font-size:0.7rem;">{{ $betaling->status }}</span>
            </div>
            @empty
            <p class="text-sm text-muted" style="padding:1.25rem;">Nog geen betalingen.</p>
            @endforelse
        </div>
    </div>

    <div>
        {{-- Prijsoverzicht --}}
        <div class="card neu-card" style="margin-bottom:1.25rem;background:{{ $colors['primary-50'] }};">
            <div class="card-header" style="border-bottom-color:{{ $colors['primary-100'] }};"><span class="card-title">Prijsoverzicht</span></div>
            @php
                $exBtw    = $booking->total_price / 1.21;
                $btwBedrag = $booking->total_price - $exBtw;
                $betaald   = $booking->payments->where('status','paid')->sum('amount');
                $openstaand = max(0, $booking->total_price - $betaald);
            @endphp
            <div style="font-size:0.875rem;padding:1.25rem;">
                <div style="display:flex;justify-content:space-between;padding:0.375rem 0;color:{{ $colors['gray-500'] }};">
                    <span>Subtotaal excl. BTW</span>
                    <span>€ {{ number_format($exBtw, 2, ',', '.') }}</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:0.375rem 0;color:{{ $colors['gray-500'] }};">
                    <span>BTW 21%</span>
                    <span>€ {{ number_format($btwBedrag, 2, ',', '.') }}</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:0.5rem 0;font-weight:700;border-top:1px solid {{ $colors['primary-100'] }};margin-top:0.25rem;">
                    <span>Totaal incl. BTW</span>
                    <span>€ {{ number_format($booking->total_price, 2, ',', '.') }}</span>
                </div>
                @if($betaald > 0)
                <div style="display:flex;justify-content:space-between;padding:0.375rem 0;color:{{ $colors['success-600'] }};">
                    <span>Betaald</span>
                    <span>€ {{ number_format($betaald, 2, ',', '.') }}</span>
                </div>
                @endif
                @if($openstaand > 0)
                <div style="display:flex;justify-content:space-between;padding:0.5rem 0;color:{{ $colors['danger-600'] }};font-weight:600;border-top:1px solid {{ $colors['primary-100'] }};margin-top:0.25rem;">
                    <span>Openstaand</span>
                    <span>€ {{ number_format($openstaand, 2, ',', '.') }}</span>
                </div>
                @endif
            </div>
        </div>

        {{-- Klantportaal --}}
        <div class="card neu-card" style="margin-bottom:1.25rem;">
            <div class="card-header"><span class="card-title" style="font-size:0.875rem;">Klantportaal</span></div>
            @php $portalUrl = route('portal.show', $booking->public_token); @endphp
            <div style="padding:1.25rem;">
                <p class="text-xs text-muted" style="margin-bottom:0.75rem;">Deel deze link met de klant om online te betalen.</p>
                <div style="display:flex;gap:0.5rem;margin-bottom:0.75rem;">
                    <input type="text" id="portal-link" value="{{ $portalUrl }}"
                        style="font-size:0.75rem;background:{{ $colors['gray-50'] }};cursor:pointer;border:1px solid {{ $colors['gray-300'] }};border-radius:0.5rem;padding:0.5rem;flex:1;" readonly onclick="this.select()">
                    <button type="button" onclick="copyPortalLink()" class="btn btn-sm btn-secondary" title="Kopieer">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                    </button>
                </div>
                <a href="{{ $portalUrl }}" target="_blank" class="btn btn-primary" style="width:100%;justify-content:center;font-size:0.875rem;">
                    Portaal bekijken →
                </a>
            </div>
        </div>

        {{-- ── Intake formulier ── --}}
        <div class="card neu-card" style="margin-bottom:1.25rem;">
            <div class="card-header">
                <span class="card-title" style="font-size:0.875rem;">📋 Intake
                    @if($booking->intake_completed)
                        <span style="margin-left:.5rem;font-size:.7rem;font-weight:600;background:#dcfce7;color:#16a34a;padding:.15rem .5rem;border-radius:9999px;">✅ Ingevuld</span>
                    @else
                        <span style="margin-left:.5rem;font-size:.7rem;font-weight:600;background:#fef3c7;color:#d97706;padding:.15rem .5rem;border-radius:9999px;">⏳ Nog niet ingevuld</span>
                    @endif
                </span>
            </div>
            <div style="padding:1.25rem;">
                @if($booking->intake_completed)
                    @php $d = $booking->intake_data ?? []; @endphp

                    {{-- Stap 1: Ontwerp --}}
                    @if(!empty($d['step_1']))
                    <div style="margin-bottom:1rem;">
                        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:#94a3b8;letter-spacing:.05em;margin-bottom:.4rem;">Fotostrip ontwerp</div>
                        @php $keuze = $d['step_1']['design_choice'] ?? null; @endphp
                        <div style="font-size:.875rem;">
                            @if($keuze === 'self_input') 🎨 Eigen input aanleveren
                            @elseif($keuze === 'template') 📄 Template kiezen
                            @else {{ $keuze }}
                            @endif
                        </div>
                        @if(!empty($d['step_1']['branding_files']))
                            <div style="margin-top:.4rem;font-size:.75rem;color:#64748b;">
                                📎 {{ count((array)$d['step_1']['branding_files']) }} bestand(en) geüpload
                            </div>
                        @endif
                        @if(!empty($d['step_1']['selected_template']))
                            <div style="margin-top:.4rem;font-size:.75rem;color:#64748b;">Template: {{ $d['step_1']['selected_template'] }}</div>
                        @endif
                    </div>
                    @endif

                    {{-- Stap 2: Bezorging --}}
                    @if(!empty($d['step_2']))
                    <div style="margin-bottom:1rem;">
                        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:#94a3b8;letter-spacing:.05em;margin-bottom:.4rem;">Bezorging</div>
                        @if(!empty($d['step_2']['delivery_time_preference']))
                            <div style="font-size:.875rem;">🕐 {{ $d['step_2']['delivery_time_preference'] }}</div>
                        @endif
                        @if(!empty($d['step_2']['delivery_notes']))
                            <div style="font-size:.8rem;color:#64748b;margin-top:.25rem;">{{ $d['step_2']['delivery_notes'] }}</div>
                        @endif
                    </div>
                    @endif

                    {{-- Stap 3: Ophalen --}}
                    @if(!empty($d['step_3']))
                    <div style="margin-bottom:.5rem;">
                        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:#94a3b8;letter-spacing:.05em;margin-bottom:.4rem;">Ophalen</div>
                        @php $pickup = $d['step_3']['pickup_preference'] ?? null; @endphp
                        <div style="font-size:.875rem;">
                            @if($pickup === 'same_evening') 🌙 Dezelfde avond voor 22:00
                            @elseif($pickup === 'next_morning') 🌅 Volgende ochtend
                            @else {{ $pickup }}
                            @endif
                        </div>
                        @if(!empty($d['step_3']['pickup_time']))
                            <div style="font-size:.75rem;color:#64748b;margin-top:.25rem;">Tijdstip: {{ $d['step_3']['pickup_time'] }}</div>
                        @endif
                        @if(!empty($d['step_3']['pickup_contact_person']))
                            <div style="font-size:.75rem;color:#64748b;">Contactpersoon: {{ $d['step_3']['pickup_contact_person'] }}</div>
                        @endif
                    </div>
                    @endif

                    @if($booking->intake_completed_at)
                        <div style="font-size:.7rem;color:#94a3b8;margin-top:.75rem;padding-top:.75rem;border-top:1px solid #f1f5f9;">
                            Ingevuld op {{ $booking->intake_completed_at->format('d-m-Y H:i') }}
                        </div>
                    @endif

                @else
                    <p class="text-xs text-muted" style="margin-bottom:.75rem;">De klant heeft het intake formulier nog niet ingevuld. Je kunt het ook zelf invullen.</p>
                    <a href="{{ route('portal.intake', $booking->public_token) }}" target="_blank"
                        class="btn btn-primary" style="width:100%;justify-content:center;font-size:.875rem;">
                        📋 Intake invullen →
                    </a>
                @endif
            </div>
        </div>

        @if($booking->account->eboekhouden_enabled)
        {{-- e-boekhouden Integratie --}}
        <div class="card neu-card" style="margin-bottom:1.25rem;">
            <div class="card-header"><span class="card-title" style="font-size:0.875rem;">📊 e-boekhouden</span></div>
            <div style="padding:1.25rem;">
                @if($booking->eboekhouden_invoice_id || $booking->eboekhouden_invoice_number)
                    <div style="padding:0.75rem;background:{{ $colors['success-50'] }};border:1px solid {{ $colors['success-600'] }};border-radius:0.5rem;margin-bottom:0.75rem;">
                        <div style="font-size:0.75rem;color:{{ $colors['success-600'] }};font-weight:600;margin-bottom:0.5rem;">✅ Factuur aangemaakt</div>
                        <div style="font-size:0.75rem;">
                            @if($booking->eboekhouden_invoice_number)
                            <div style="margin-bottom:0.5rem;">
                                <strong style="color:{{ $colors['success-600'] }};">Factuurnummer:</strong>
                                <span style="display:inline-block;background:{{ $colors['success-600'] }};color:white;padding:0.25rem 0.5rem;border-radius:0.25rem;font-weight:600;margin-left:0.25rem;">{{ $booking->eboekhouden_invoice_number }}</span>
                            </div>
                            @endif
                            <div style="color:{{ $colors['gray-700'] }};line-height:1.6;font-size:0.7rem;">
                                @if($booking->eboekhouden_invoice_id)
                                <div><strong>ID:</strong> {{ $booking->eboekhouden_invoice_id }}</div>
                                @endif
                                @if($booking->eboekhouden_status)
                                <div><strong>Status:</strong> {{ $booking->eboekhouden_status }}</div>
                                @endif
                                @if($booking->eboekhouden_synced_at)
                                <div><strong>Gesynchroniseerd:</strong> {{ $booking->eboekhouden_synced_at->format('d-m-Y H:i') }}</div>
                                @endif
                            </div>
                        </div>
                    </div>
                @else
                    <p class="text-xs text-muted" style="margin-bottom:0.75rem;">Nog geen factuur aangemaakt in e-boekhouden.</p>
                    <form method="POST" action="{{ route('bookings.create-invoice', $booking) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;font-size:0.875rem;">
                            📄 Maak factuur aan
                        </button>
                    </form>
                @endif
            </div>
        </div>
        @endif

        <div class="card neu-card">
            <div style="padding:1.25rem;display:flex;flex-direction:column;gap:0.75rem;">
                @if($booking->lead)
                <a href="{{ route('leads.show', $booking->lead) }}" class="btn btn-secondary" style="width:100%;justify-content:center;">
                    Bekijk lead
                </a>
                @endif
                <form method="POST" action="{{ route('bookings.destroy', $booking) }}" onsubmit="return confirm('Weet u zeker dat u deze boeking wilt verwijderen?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger" style="width:100%;justify-content:center;">Verwijderen</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function copyPortalLink() {
    const input = document.getElementById('portal-link');
    input.select();
    navigator.clipboard.writeText(input.value).then(() => {
        const btn = event.target.closest('button');
        const orig = btn.innerHTML;
        btn.textContent = 'Gekopieerd!';
        btn.style.background = '#dcfce7';
        btn.style.color = '#166534';
        setTimeout(() => { btn.innerHTML = orig; btn.style = ''; }, 2000);
    });
}
</script>
@endpush
