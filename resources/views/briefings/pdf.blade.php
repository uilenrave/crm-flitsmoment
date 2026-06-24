<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $briefing->effective_title }}</title>
    <style>
        @page { margin: 22mm 15mm 18mm 15mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; line-height: 1.45; color: #1e293b; }

        h1 { font-size: 18pt; margin: 0 0 4pt; color: #0f172a; }
        h2 { font-size: 13pt; margin: 16pt 0 6pt; padding-bottom: 3pt; border-bottom: 1.5pt solid #fcd34d; color: #0f172a; }
        h3 { font-size: 11pt; margin: 10pt 0 4pt; color: #1e293b; }

        .header-bar { padding-bottom: 8pt; border-bottom: 2pt solid #1e293b; margin-bottom: 12pt; }
        .header-meta { font-size: 9pt; color: #64748b; }
        .header-meta strong { color: #1e293b; }

        .section-intro { font-size: 9pt; color: #64748b; margin: 0 0 8pt; font-style: italic; }

        .day-block { margin-bottom: 10pt; page-break-inside: avoid; }
        .day-header { background: #fef3c7; padding: 5pt 8pt; border-radius: 3pt; font-weight: bold; font-size: 10pt; color: #78350f; }

        .rit-card {
            border: 0.7pt solid #e2e8f0;
            border-left: 3pt solid #2563eb;
            border-radius: 3pt;
            padding: 6pt 9pt;
            margin: 5pt 0;
            page-break-inside: avoid;
        }
        .rit-card.pickup { border-left-color: #16a34a; }
        .rit-card.handover { border-left-color: #d97706; }

        .rit-title { font-weight: bold; font-size: 10pt; margin-bottom: 3pt; }
        .rit-title .booking { color: #2563eb; font-size: 9pt; }
        .rit-title .badge { display: inline-block; background: #eff6ff; color: #1e40af; padding: 1pt 5pt; border-radius: 8pt; font-size: 8pt; font-weight: bold; margin-right: 3pt; }
        .rit-title .badge.pickup { background: #f0fdf4; color: #15803d; }
        .rit-title .badge.handover { background: #fef3c7; color: #92400e; }

        .rit-meta { font-size: 9pt; color: #475569; margin-top: 2pt; }
        .rit-meta .label { color: #94a3b8; font-weight: 600; display: inline-block; min-width: 60pt; }

        .rit-items { margin-top: 4pt; padding: 4pt 6pt; background: #f8fafc; border-radius: 2pt; font-size: 8.5pt; }
        .rit-items .label { font-weight: bold; color: #64748b; font-size: 7.5pt; text-transform: uppercase; letter-spacing: .04em; }
        .rit-items ul { margin: 2pt 0 0; padding-left: 14pt; }

        .rit-note { margin-top: 4pt; padding: 5pt 7pt; background: #fffbeb; border: 0.7pt solid #fcd34d; border-radius: 2pt; font-size: 9pt; }
        .rit-note .label { font-weight: bold; color: #92400e; font-size: 7.5pt; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 2pt; }

        .unit-block { margin-bottom: 12pt; page-break-inside: avoid; }
        .unit-header {
            background: #1e293b; color: #fff;
            padding: 5pt 9pt; border-radius: 3pt;
            font-weight: bold; font-size: 10pt;
        }
        .unit-header .unit-no { color: #fcd34d; }

        .timeline {
            border-left: 1.5pt solid #cbd5e1;
            margin-left: 8pt;
            padding: 4pt 0 0 12pt;
        }
        .timeline-row { margin-bottom: 5pt; position: relative; font-size: 9pt; }
        .timeline-row::before {
            content: ''; position: absolute;
            left: -16.5pt; top: 3pt;
            width: 7pt; height: 7pt;
            background: #2563eb; border-radius: 50%;
        }
        .timeline-row.pickup::before { background: #16a34a; }
        .timeline-row.handover::before { background: #d97706; }

        .empty-msg { padding: 14pt; text-align: center; color: #94a3b8; font-style: italic; border: 1pt dashed #cbd5e1; border-radius: 4pt; }

        .footer-note { margin-top: 18pt; padding-top: 6pt; border-top: 0.5pt solid #e2e8f0; font-size: 8pt; color: #94a3b8; text-align: center; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>

    {{-- ───── Header ───── --}}
    <div class="header-bar">
        <h1>{{ $briefing->effective_title }}</h1>
        <div class="header-meta">
            <strong>Voor:</strong> {{ $briefing->staff->name ?? '—' }}
            &nbsp;·&nbsp;
            <strong>Periode:</strong> {{ $briefing->date_from->translatedFormat('l j F') }} t/m {{ $briefing->date_to->translatedFormat('l j F Y') }}
            &nbsp;·&nbsp;
            <strong>Aangemaakt:</strong> {{ now()->format('d-m-Y H:i') }}
        </div>
    </div>

    @php
        $roleIcons = ['delivery' => '↓', 'pickup' => '↑', 'handover' => '⇄'];
        $roleLabels = ['delivery' => 'BEZORGING', 'pickup' => 'OPHALEN', 'handover' => 'AFGIFTE (To Go)'];
    @endphp

    {{-- ═══════════════ DEEL 1: PLANNING PER DAG ═══════════════ --}}
    <h2>📅 Planning per dag</h2>
    <p class="section-intro">Alle ritten gesorteerd op datum & tijd.</p>

    @if(empty($byDay))
        <div class="empty-msg">Geen ritten ingepland in deze periode.</div>
    @else
        @foreach($byDay as $day)
        <div class="day-block">
            <div class="day-header">{{ $day['date']->translatedFormat('l j F') }} — {{ count($day['ritten']) }} {{ count($day['ritten']) === 1 ? 'rit' : 'ritten' }}</div>
            @foreach($day['ritten'] as $r)
                @php
                    $b = $r['booking'];
                    $role = $r['role'];
                    $datum = $r['datum'];
                    $adres = collect([$b->event_address, $b->event_postcode, $b->event_city])->filter()->implode(', ');
                    $note = $briefing->noteFor($b->id, $role);
                    $photobooths = $b->items->filter(fn($i) => $i->asset && $i->asset->category === 'photobooth');
                    $extras = $b->items->filter(fn($i) => $i->asset && $i->asset->category !== 'photobooth');
                @endphp
                <div class="rit-card {{ $role }}">
                    <div class="rit-title">
                        <span class="badge {{ $role }}">{{ $roleIcons[$role] }} {{ $roleLabels[$role] }}</span>
                        <strong>{{ $datum->format('H:i') }}</strong>
                        &nbsp;·&nbsp;
                        <span class="booking">{{ $b->booking_number }}</span>
                        &nbsp;·&nbsp;
                        {{ $b->customer_name }}
                    </div>
                    <div class="rit-meta">
                        @if($adres || $b->event_location)
                            <div><span class="label">📍 Adres:</span> {{ $b->event_location ? $b->event_location.' — ' : '' }}{{ $adres ?: '—' }}</div>
                        @endif
                        @if($b->customer_phone)
                            <div><span class="label">📞 Telefoon:</span> {{ $b->customer_phone }}</div>
                        @endif
                        @if($role === 'pickup' && $b->pickup_contact_person)
                            <div><span class="label">👤 Contact:</span> {{ $b->pickup_contact_person }}{{ $b->pickup_contact_time ? ' ('.substr($b->pickup_contact_time, 0, 5).')' : '' }}</div>
                        @endif
                        @if($b->event_start_time)
                            <div><span class="label">🎉 Event:</span> {{ substr($b->event_start_time, 0, 5) }}@if($b->event_end_time) – {{ substr($b->event_end_time, 0, 5) }}@endif</div>
                        @endif
                    </div>

                    @if($role !== 'pickup' && ($photobooths->isNotEmpty() || $extras->isNotEmpty()))
                    <div class="rit-items">
                        <span class="label">📦 Meenemen:</span>
                        <ul>
                            @foreach($photobooths as $it)
                                <li>📷 {{ $it->asset->name }}@if($it->unit_number) — unit {{ $it->unit_number }}@endif</li>
                            @endforeach
                            @foreach($extras as $it)
                                <li>
                                    @if($it->asset->category === 'background') 🖼
                                    @elseif($it->asset->category === 'prop_box') 🎩
                                    @else ✨ @endif
                                    {{ $it->asset->name }}@if($it->quantity > 1) ×{{ $it->quantity }}@endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    @if($note)
                    <div class="rit-note">
                        <div class="label">✍️ Extra instructies</div>
                        {!! nl2br(e($note)) !!}
                    </div>
                    @endif
                </div>
            @endforeach
        </div>
        @endforeach
    @endif

    {{-- ═══════════════ DEEL 2: PER PHOTOBOOTH-UNIT ═══════════════ --}}
    <div class="page-break"></div>

    <h2>📷 Per photobooth-unit</h2>
    <p class="section-intro">Welke unit gaat waarheen — handig om vooraf even langs te lopen.</p>

    @if(empty($byUnit))
        <div class="empty-msg">Geen photobooth-units in deze periode.</div>
    @else
        @foreach($byUnit as $unit)
        <div class="unit-block">
            <div class="unit-header">
                📷 {{ $unit['asset_name'] }}@if($unit['unit_number']) <span class="unit-no">— unit {{ $unit['unit_number'] }}</span>@endif
                &nbsp;·&nbsp; {{ $unit['movements']->count() }} {{ $unit['movements']->count() === 1 ? 'rit' : 'ritten' }}
            </div>
            <div class="timeline">
                @foreach($unit['movements'] as $r)
                    @php
                        $b = $r['booking'];
                        $role = $r['role'];
                        $datum = $r['datum'];
                        $adres = collect([$b->event_address, $b->event_postcode, $b->event_city])->filter()->implode(', ');
                        $note = $briefing->noteFor($b->id, $role);
                    @endphp
                    <div class="timeline-row {{ $role }}">
                        <strong>{{ $datum->translatedFormat('D d M, H:i') }}</strong>
                        &nbsp;—&nbsp;
                        <strong>{{ $roleLabels[$role] }}</strong>
                        &nbsp;·&nbsp;
                        {{ $b->booking_number }} · {{ $b->customer_name }}
                        @if($adres)<br><span style="color:#64748b;">📍 {{ $adres }}</span>@endif
                        @if($note)<br><span style="color:#92400e;">✍ {{ $note }}</span>@endif
                    </div>
                @endforeach
            </div>
        </div>
        @endforeach
    @endif

    <div class="footer-note">
        Briefing voor {{ $briefing->staff->name ?? '—' }} · gegenereerd op {{ now()->format('d-m-Y H:i') }} · Flitsmoment CRM
    </div>

</body>
</html>
