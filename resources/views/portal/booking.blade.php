<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Uw boeking — {{ $booking->account->name }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary: #2563eb;
            --primary-light: #eff6ff;
            --green: #16a34a;
            --green-light: #dcfce7;
            --orange: #d97706;
            --orange-light: #fef3c7;
            --border: #e5e7eb;
            --text: #111827;
            --muted: #6b7280;
            --bg: #f9fafb;
        }

        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

        .header {
            background: #fff;
            border-bottom: 1px solid var(--border);
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .header-logo { font-size: 1.1rem; font-weight: 700; color: var(--text); }
        .header-badge { font-size: .8rem; color: var(--muted); }

        .container { max-width: 900px; margin: 0 auto; padding: 2rem 1.25rem 4rem; }

        .greeting { margin-bottom: 2rem; }
        .greeting h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: .25rem; }
        .greeting p { color: var(--muted); font-size: .9375rem; }

        .section { background: #fff; border: 1px solid var(--border); border-radius: .75rem; margin-bottom: 1.25rem; overflow: hidden; }
        .section-header { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: .625rem; }
        .section-icon { width: 2rem; height: 2rem; border-radius: .5rem; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
        .section-title { font-weight: 600; font-size: .9375rem; }
        .section-body { padding: 1.25rem; }

        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .875rem; }
        .detail-item label { display: block; font-size: .75rem; font-weight: 500; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; margin-bottom: .2rem; }
        .detail-item .value { font-size: .9375rem; color: var(--text); }
        .detail-item.full { grid-column: span 2; }

        .map-link { display: inline-flex; align-items: center; gap: .375rem; font-size: .875rem; color: var(--primary); text-decoration: none; margin-top: .375rem; }
        .map-link:hover { text-decoration: underline; }
        .map-link svg { width: 1rem; height: 1rem; flex-shrink: 0; }

        .whatsapp-link { display: inline-flex; align-items: center; gap: .375rem; background: #25d366; color: #fff; padding: .35rem .75rem; border-radius: 9999px; font-size: .8rem; font-weight: 600; text-decoration: none; margin-top: .25rem; }
        .whatsapp-link svg { width: .9rem; height: .9rem; }

        .strip-badge { display: inline-flex; align-items: center; gap: .375rem; padding: .35rem .75rem; border-radius: 9999px; font-size: .8125rem; font-weight: 600; }
        .strip-waiting  { background: var(--orange-light); color: var(--orange); }
        .strip-progress { background: #dbeafe; color: #1d4ed8; }
        .strip-review   { background: #f3e8ff; color: #7c3aed; }
        .strip-accepted { background: var(--green-light); color: var(--green); }
        .strip-done     { background: var(--green-light); color: var(--green); }

        /* Design review */
        .design-img { max-width: 100%; border-radius: .75rem; border: 1px solid var(--border); display: block; margin: 1rem 0; }
        .btn-accept { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; background: var(--green); color: #fff; padding: .875rem 2rem; border-radius: .5rem; font-weight: 700; font-size: 1rem; border: none; cursor: pointer; width: 100%; max-width: 340px; transition: background .15s; }
        .btn-accept:hover { background: #15803d; }
        .btn-comment-toggle { background: #fff; color: var(--primary); border: 2px solid var(--primary); padding: .625rem 1.25rem; border-radius: .5rem; font-weight: 600; cursor: pointer; font-size: .9rem; width: 100%; max-width: 340px; margin-top: .5rem; }
        .comment-form { display: none; margin-top: 1rem; background: var(--bg); border: 1px solid var(--border); border-radius: .5rem; padding: 1rem; }
        .comment-form textarea { width: 100%; padding: .625rem .75rem; border: 1px solid var(--border); border-radius: .375rem; font-size: .9375rem; resize: vertical; min-height: 90px; font-family: inherit; }
        .btn-submit { background: var(--primary); color: #fff; padding: .625rem 1.25rem; border-radius: .5rem; font-weight: 600; border: none; cursor: pointer; margin-top: .5rem; font-size: .9375rem; }

        .type-badge { display: inline-flex; align-items: center; gap: .375rem; background: var(--primary-light); color: var(--primary); padding: .3rem .75rem; border-radius: 9999px; font-size: .8125rem; font-weight: 600; }

        /* Items tabel */
        .items-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .items-table th { text-align: left; padding: .5rem .75rem; font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; color: var(--muted); background: var(--bg); border-bottom: 1px solid var(--border); }
        .items-table td { padding: .75rem; border-bottom: 1px solid var(--border); }
        .items-table tr:last-child td { border-bottom: none; }
        .items-table .total-row td { font-weight: 700; font-size: 1rem; background: var(--bg); }

        /* Betaalblok */
        .payment-section { background: #fff; border: 2px solid var(--primary); border-radius: .75rem; padding: 1.75rem; margin-top: 1.5rem; text-align: center; }
        .payment-amount { font-size: 2.5rem; font-weight: 800; color: var(--text); margin: .5rem 0 1.25rem; }
        .payment-amount span { font-size: 1.25rem; font-weight: 500; color: var(--muted); }
        .payment-paid { color: var(--green); font-size: 1.125rem; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: .5rem; }
        .btn-pay { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; background: var(--primary); color: #fff; padding: .875rem 2.5rem; border-radius: .5rem; font-size: 1rem; font-weight: 700; text-decoration: none; border: none; cursor: pointer; transition: background .15s; width: 100%; max-width: 360px; }
        .btn-pay:hover { background: #1d4ed8; }
        .payment-note { font-size: .8rem; color: var(--muted); margin-top: .75rem; }

        /* Alerts */
        .alert { padding: .875rem 1.125rem; border-radius: .5rem; margin-bottom: 1.25rem; font-size: .9rem; }
        .alert-success { background: var(--green-light); color: #166534; border: 1px solid #bbf7d0; }
        .alert-info    { background: var(--primary-light); color: #1e40af; border: 1px solid #bfdbfe; }
        .alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        .divider { border: none; border-top: 1px solid var(--border); margin: .875rem 0; }

        @media (max-width: 480px) {
            .detail-grid { grid-template-columns: 1fr; }
            .detail-item.full { grid-column: span 1; }
            .header { padding: 1rem; }
            .container { padding: 1.25rem 1rem 3rem; }
        }
    </style>
</head>
<body>

<header class="header">
    <div class="header-logo">{{ $booking->account->name }}</div>
    <div class="header-badge">Boeking {{ $booking->booking_number }}</div>
</header>

<div class="container">

    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('info'))
        <div class="alert alert-info">{{ session('info') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    {{-- Begroeting --}}
    <div class="greeting">
        <h1>Hallo {{ explode(' ', $booking->customer_name)[0] }}!</h1>
        <p>Hier vindt u alle details van uw boeking bij {{ $booking->account->name }}.</p>
    </div>

    {{-- Intake voortgang --}}
    @php
        $intakeSteps = [
            1 => ['icon' => '🎨', 'label' => 'Fotostrip ontwerp',  'desc' => 'Kies je ontwerp of upload je eigen'],
            2 => ['icon' => '🚚', 'label' => 'Bezorging',         'desc' => 'Wanneer bezorgen we de booth?'],
            3 => ['icon' => '📦', 'label' => 'Ophalen',           'desc' => 'Ophaalmoment en contactpersoon'],
        ];
        $currentStep = (int) $booking->intake_current_step;
        $completedSteps = $currentStep;
        $totalSteps = 3;
        $progressPercent = $booking->intake_completed ? 100 : round(($completedSteps / $totalSteps) * 100);
    @endphp

    @if($booking->show_intake || $booking->intake_completed)
    <div class="section" style="border:2px solid {{ $booking->intake_completed ? '#86efac' : '#fcd34d' }};">
        <div class="section-header" style="background:{{ $booking->intake_completed ? '#f0fdf4' : '#fffbeb' }};">
            <div class="section-icon" style="background:{{ $booking->intake_completed ? '#dcfce7' : '#fef3c7' }};">📋</div>
            <span class="section-title">Intake formulier</span>
            @if($booking->intake_completed)
                <span style="margin-left:auto;font-size:.8rem;font-weight:600;color:#16a34a;background:#dcfce7;padding:.25rem .75rem;border-radius:9999px;">✅ Compleet</span>
            @else
                <span style="margin-left:auto;font-size:.8rem;font-weight:600;color:#92400e;background:#fef3c7;padding:.25rem .75rem;border-radius:9999px;">{{ $completedSteps }}/{{ $totalSteps }} ingevuld</span>
            @endif
        </div>
        <div class="section-body" style="padding:0;">
            {{-- Voortgangsbalk --}}
            <div style="padding:1rem 1.25rem .5rem;">
                <div style="height:6px;background:#f3f4f6;border-radius:9999px;overflow:hidden;">
                    <div style="height:100%;width:{{ $progressPercent }}%;background:{{ $booking->intake_completed ? '#22c55e' : 'linear-gradient(90deg,#fcd34d,#f59e0b)' }};border-radius:9999px;transition:width .5s ease;"></div>
                </div>
            </div>

            {{-- Stappenlijst --}}
            <div style="padding:.5rem 0;">
                @foreach($intakeSteps as $stepNum => $step)
                    @php
                        $isDone = $stepNum <= $completedSteps || $booking->intake_completed;
                        $isCurrent = !$booking->intake_completed && $stepNum === $completedSteps + 1;
                        $isUpcoming = !$isDone && !$isCurrent;
                    @endphp
                    <div style="display:flex;align-items:center;gap:.875rem;padding:.75rem 1.25rem;{{ $isCurrent ? 'background:#fffbeb;' : '' }}{{ $stepNum < $totalSteps ? 'border-bottom:1px solid #f3f4f6;' : '' }}">
                        {{-- Status indicator --}}
                        <div style="width:2rem;height:2rem;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.85rem;
                            {{ $isDone ? 'background:#dcfce7;' : ($isCurrent ? 'background:#fef3c7;box-shadow:0 0 0 3px rgba(252,211,77,0.3);' : 'background:#f3f4f6;') }}">
                            @if($isDone)
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            @elseif($isCurrent)
                                <span style="display:block;width:10px;height:10px;background:#f59e0b;border-radius:50%;animation:intake-pulse 1.5s ease-in-out infinite;"></span>
                            @else
                                <span style="display:block;width:8px;height:8px;background:#d1d5db;border-radius:50%;"></span>
                            @endif
                        </div>

                        {{-- Stap icon + label --}}
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;align-items:center;gap:.5rem;">
                                <span style="font-size:1rem;">{{ $step['icon'] }}</span>
                                <span style="font-weight:600;font-size:.9rem;{{ $isUpcoming ? 'color:#9ca3af;' : 'color:#111827;' }}">{{ $step['label'] }}</span>
                            </div>
                            <div style="font-size:.8rem;color:#9ca3af;margin-top:.1rem;padding-left:1.5rem;">{{ $step['desc'] }}</div>
                        </div>

                        {{-- Stap status tekst --}}
                        <div style="flex-shrink:0;font-size:.75rem;font-weight:500;">
                            @if($isDone)
                                <span style="color:#16a34a;">Ingevuld</span>
                            @elseif($isCurrent)
                                <span style="color:#d97706;">Volgende</span>
                            @else
                                <span style="color:#d1d5db;">—</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- CTA --}}
            @if(!$booking->intake_completed)
            <div style="padding:1.25rem;border-top:1px solid #e5e7eb;text-align:center;background:#fffbeb;">
                <a href="{{ route('portal.intake', $booking->public_token) }}"
                   style="display:inline-flex;align-items:center;gap:.5rem;background:linear-gradient(135deg,#fcd34d,#f59e0b);color:#78350f;padding:.875rem 2rem;border-radius:.5rem;font-weight:700;font-size:1rem;text-decoration:none;box-shadow:0 4px 12px rgba(252,211,77,0.3);transition:transform .15s,box-shadow .15s;">
                    {{ $completedSteps > 0 ? '▶ Ga verder met stap ' . ($completedSteps + 1) : '▶ Start het formulier' }}
                </a>
                <p style="font-size:.8rem;color:#92400e;margin-top:.75rem;">Duurt ongeveer 3 minuten</p>
            </div>
            @elseif($booking->intake_completed_at)
            <div style="padding:1rem 1.25rem;border-top:1px solid #e5e7eb;text-align:center;background:#f0fdf4;">
                <p style="font-size:.85rem;color:#16a34a;">
                    Ingevuld op {{ $booking->intake_completed_at->translatedFormat('j F Y \o\m H:i') }}
                </p>
            </div>
            @endif
        </div>
    </div>

    <style>
        @keyframes intake-pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: .5; transform: scale(.8); }
        }
    </style>
    @endif

    {{-- ─── Fotostrip Templates (alleen als klant "template" koos) ─── --}}
    @if(($booking->intake_data['step_1']['design_choice'] ?? null) === 'template')
    <div class="section" style="border:2px solid #a78bfa;">
        <div class="section-header" style="background:#f5f3ff;">
            <div class="section-icon" style="background:#ede9fe;">🎨</div>
            <span class="section-title">Fotostrip templates</span>
            <span style="margin-left:auto;font-size:.75rem;font-weight:600;color:#6d28d9;background:#ede9fe;padding:.25rem .625rem;border-radius:9999px;">5×15 cm</span>
        </div>
        <div class="section-body">
            <p style="color:var(--muted);font-size:.9rem;margin-bottom:1.25rem;">
                Kies een template, download het bestand en pas het aan in Photoshop of Canva. Stuur het afgewerkte ontwerp naar ons.
            </p>

            {{-- Template previews + downloads --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1.5rem;">
                @foreach([
                    ['file' => 'template1', 'name' => 'Template 1'],
                    ['file' => 'template2', 'name' => 'Template 2'],
                    ['file' => 'template3', 'name' => 'Template 3'],
                    ['file' => 'template4', 'name' => 'Template 4'],
                ] as $tpl)
                <div style="border:1px solid var(--border);border-radius:.75rem;overflow:hidden;transition:box-shadow .2s;">
                    {{-- Preview op donkere achtergrond --}}
                    <div style="background:#1f2937;padding:1rem;display:flex;align-items:center;justify-content:center;min-height:140px;">
                        <img src="/templates/{{ $tpl['file'] }}.webp" alt="{{ $tpl['name'] }}"
                             style="max-width:100%;max-height:120px;object-fit:contain;border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,0.3);">
                    </div>
                    <div style="padding:.75rem;display:flex;align-items:center;justify-content:space-between;">
                        <span style="font-weight:600;font-size:.85rem;">{{ $tpl['name'] }}</span>
                        <a href="/templates/{{ $tpl['file'] }}.zip" download
                           style="display:inline-flex;align-items:center;gap:.25rem;font-size:.75rem;font-weight:600;color:#7c3aed;text-decoration:none;background:#ede9fe;padding:.3rem .625rem;border-radius:.375rem;transition:background .15s;">
                            📥 ZIP
                        </a>
                    </div>
                </div>
                @endforeach
            </div>

            <p style="font-size:.8rem;color:var(--muted);margin-top:.5rem;padding-top:1rem;border-top:1px solid var(--border);">
                💡 Tip: Stuur je afgewerkte ontwerp naar ons via e-mail of upload het via de fotostrip-designpagina.
            </p>
        </div>
    </div>
    @endif

    {{-- ─── Boekingdetails ─── --}}
    <div class="section">
        <div class="section-header">
            <div class="section-icon" style="background:#eff6ff;">📋</div>
            <span class="section-title">Boekingdetails</span>
        </div>
        <div class="section-body">
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Boekingsnummer</label>
                    <div class="value" style="font-family:monospace;font-weight:600;">{{ $booking->booking_number }}</div>
                </div>
                <div class="detail-item">
                    <label>Type boeking</label>
                    <div class="value">
                        <span class="type-badge">
                            {{ $booking->booking_type === 'full_service' ? '🚚 Full Service' : '🏠 To Go' }}
                        </span>
                    </div>
                </div>

                <div class="detail-item">
                    <label>Eventdatum</label>
                    <div class="value">{{ $booking->event_date->translatedFormat('l j F Y') }}</div>
                </div>
                <div class="detail-item">
                    <label>Tijdstip</label>
                    <div class="value">
                        @if($booking->event_start_time && $booking->event_end_time)
                            {{ \Carbon\Carbon::parse($booking->event_start_time)->format('H:i') }} – {{ \Carbon\Carbon::parse($booking->event_end_time)->format('H:i') }}
                        @elseif($booking->event_start_time)
                            Vanaf {{ \Carbon\Carbon::parse($booking->event_start_time)->format('H:i') }}
                        @else
                            —
                        @endif
                    </div>
                </div>

                @if($booking->booking_type === 'full_service')
                    @if($booking->delivery_at)
                    <div class="detail-item">
                        <label>Bezorging</label>
                        <div class="value">{{ $booking->delivery_at->translatedFormat('l j F') }} om {{ $booking->delivery_at->format('H:i') }}</div>
                    </div>
                    @endif
                    @if($booking->pickup_at)
                    <div class="detail-item">
                        <label>Ophalen (door ons)</label>
                        <div class="value">{{ $booking->pickup_at->translatedFormat('l j F') }} om {{ $booking->pickup_at->format('H:i') }}</div>
                    </div>
                    @endif
                @else
                    @if($booking->customer_pickup_at)
                    <div class="detail-item">
                        <label>Ophalen bij ons</label>
                        <div class="value">{{ $booking->customer_pickup_at->translatedFormat('l j F') }} om {{ $booking->customer_pickup_at->format('H:i') }}</div>
                    </div>
                    @endif
                    @if($booking->customer_return_at)
                    <div class="detail-item">
                        <label>Terugbrengen</label>
                        <div class="value">{{ $booking->customer_return_at->translatedFormat('l j F') }} om {{ $booking->customer_return_at->format('H:i') }}</div>
                    </div>
                    @endif
                @endif

                <div class="detail-item full">
                    <label>Locatie</label>
                    <div class="value">{{ $booking->event_location ?: '—' }}</div>
                    @php
                        $adres = collect([$booking->event_address, $booking->event_postcode, $booking->event_city])
                            ->filter()->implode(', ');
                    @endphp
                    @if($adres)
                        <div style="font-size:.875rem;color:var(--muted);margin-top:.2rem;">{{ $adres }}</div>
                        <a href="https://maps.google.com/?q={{ urlencode($adres) }}" target="_blank" class="map-link">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            Bekijk op Google Maps
                        </a>
                    @endif
                </div>

                <div class="detail-item full">
                    <label>Fotostrip status</label>
                    <div class="value">
                        @php
                            $strips = [
                                'waiting_input' => ['label' => '⏳ Input aanleveren',          'class' => 'strip-waiting'],
                                'designing'     => ['label' => '🎨 Ontwerpen',                 'class' => 'strip-progress'],
                                'review'        => ['label' => '👀 Wachten op jouw goedkeuring','class' => 'strip-review'],
                                'accepted'      => ['label' => '✅ Goedgekeurd',               'class' => 'strip-accepted'],
                                'ready'         => ['label' => '🎉 Ontwerp staat klaar',       'class' => 'strip-done'],
                            ];
                            $strip = $strips[$booking->strip_status ?? 'waiting_input'] ?? $strips['waiting_input'];
                        @endphp
                        <span class="strip-badge {{ $strip['class'] }}">{{ $strip['label'] }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ─── Fotostrip input aanleveren ─── --}}
    @if(in_array($booking->strip_status, ['waiting_input', 'designing']) || ! $booking->strip_intake_data)
    <div class="section">
        <div class="section-header">
            <div class="section-icon" style="background:#fef3c7;">✏️</div>
            <span class="section-title">Fotostrip input aanleveren</span>
        </div>
        <div class="section-body">
            @if($booking->strip_intake_data)
                {{-- Al eerder ingevuld — toon bevestiging + mogelijkheid om bij te werken --}}
                <div class="alert alert-success" style="margin-bottom:1.25rem;">
                    ✅ Je input is ontvangen op {{ \Carbon\Carbon::parse($booking->strip_intake_data['submitted_at'])->format('d M Y \o\m H:i') }}. We zijn bezig met je ontwerp!
                </div>
                @if($booking->strip_intake_data['text'])
                <div style="margin-bottom:1rem;padding:.875rem 1rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:.5rem;">
                    <div style="font-size:.75rem;font-weight:700;color:#374151;margin-bottom:.4rem;">Jouw omschrijving:</div>
                    <p style="margin:0;font-size:.9rem;white-space:pre-line;color:#374151;line-height:1.6;">{{ $booking->strip_intake_data['text'] }}</p>
                </div>
                @endif
                @if(! empty($booking->strip_intake_data['files']))
                <div style="margin-bottom:1rem;">
                    <div style="font-size:.75rem;font-weight:700;color:#374151;margin-bottom:.5rem;">Meegestuurde bestanden:</div>
                    @foreach($booking->strip_intake_data['files'] as $file)
                    <a href="{{ $file['url'] }}" target="_blank" style="display:inline-flex;align-items:center;gap:.4rem;padding:.4rem .75rem;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;font-size:.8rem;color:#1d4ed8;text-decoration:none;margin:.25rem .25rem .25rem 0;">
                        📎 {{ $file['filename'] }}
                    </a>
                    @endforeach
                </div>
                @endif
                <details style="margin-top:1rem;">
                    <summary style="font-size:.8rem;color:#6b7280;cursor:pointer;">Input bijwerken</summary>
                    <div style="margin-top:.75rem;">
                        @include('portal._strip_input_form', ['token' => $booking->public_token])
                    </div>
                </details>
            @else
                <p style="margin-bottom:1rem;color:var(--muted);font-size:.9rem;">Vertel ons wat je wilt voor jouw fotostrip! Omschrijf je wensen of voeg referentieafbeeldingen toe.</p>
                @include('portal._strip_input_form', ['token' => $booking->public_token])
            @endif
        </div>
    </div>
    @endif

    {{-- ─── Fotostrip ontwerp beoordelen ─── --}}
    @if($booking->strip_design_url && in_array($booking->strip_status, ['designing', 'review', 'accepted', 'ready']))
    <div class="section">
        <div class="section-header">
            <div class="section-icon" style="background:#f3e8ff;">🎨</div>
            <span class="section-title">Uw fotostrip ontwerp</span>
        </div>
        <div class="section-body">
            @if($booking->strip_status === 'designing')
                <div class="alert" style="background:#fef3c7;border:1px solid #fcd34d;color:#92400e;border-radius:.5rem;padding:.875rem 1rem;margin-bottom:1rem;">
                    ✏️ Je feedback is ontvangen! We verwerken je opmerkingen en sturen een nieuw ontwerp zodra dat klaar is.
                </div>
            @elseif($booking->strip_status === 'accepted')
                <div class="alert alert-success" style="margin-bottom:1rem;">
                    ✅ U heeft dit ontwerp goedgekeurd. We zetten het in op uw evenement!
                </div>
            @elseif($booking->strip_status === 'ready')
                <div class="alert alert-success" style="margin-bottom:1rem;">
                    🎉 Je fotostrip ontwerp staat klaar voor jouw evenement!
                </div>
            @endif

            @php
                $isImage = preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', parse_url($booking->strip_design_url, PHP_URL_PATH));
            @endphp

            @if($isImage)
                <img src="{{ $booking->strip_design_url }}" alt="Fotostrip ontwerp" class="design-img">
            @else
                <p style="margin-bottom:1rem;">
                    <a href="{{ $booking->strip_design_url }}" target="_blank" style="color:var(--primary);font-weight:600;font-size:1rem;">
                        📄 Bekijk het ontwerp (opent in nieuw venster)
                    </a>
                </p>
            @endif

            {{-- Opmerkingen gekoppeld aan het huidige ontwerp --}}
            @php
                $huidigeOpmerkingen = collect($booking->strip_comments ?? [])
                    ->filter(fn($c) => ($c['design_url'] ?? null) === $booking->strip_design_url)
                    ->reverse()
                    ->values();
            @endphp
            @if($huidigeOpmerkingen->count() > 0)
            <div style="margin:1rem 0;">
                <div style="font-size:.75rem;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.5rem;">
                    💬 Jouw opmerkingen ({{ $huidigeOpmerkingen->count() }})
                </div>
                @foreach($huidigeOpmerkingen as $comment)
                @php $commentDate = \Carbon\Carbon::parse($comment['created_at']); @endphp
                <div style="padding:.875rem 1rem;background:#fef3c7;border:1px solid #fcd34d;border-radius:.5rem;margin-bottom:.5rem;">
                    <div style="font-size:.75rem;color:#92400e;margin-bottom:.4rem;">
                        {{ $commentDate->format('d M Y \o\m H:i') }}
                    </div>
                    <p style="margin:0;font-size:.9rem;white-space:pre-line;line-height:1.6;color:#1e293b;">{{ $comment['text'] }}</p>
                </div>
                @endforeach
            </div>
            @endif

            @if($booking->strip_status === 'review')
                <hr class="divider">
                <p style="font-weight:600;margin-bottom:1rem;">Wat vind je van het ontwerp?</p>

                {{-- Goedkeuren --}}
                <form method="POST" action="{{ route('portal.strip-review', $booking->public_token) }}" style="margin-bottom:.75rem;">
                    @csrf
                    <input type="hidden" name="action" value="accept">
                    <button type="submit" class="btn-accept"
                        onclick="return confirm('Weet u zeker dat u het ontwerp goedkeurt?')">
                        ✅ Ontwerp goedkeuren
                    </button>
                </form>

                {{-- Feedback --}}
                <button type="button" class="btn-comment-toggle" onclick="this.style.display='none';document.getElementById('feedback-form').style.display='block'">
                    💬 Ik heb opmerkingen
                </button>

                <div id="feedback-form" class="comment-form">
                    <form method="POST" action="{{ route('portal.strip-review', $booking->public_token) }}">
                        @csrf
                        <input type="hidden" name="action" value="comment">
                        <label style="font-weight:600;display:block;margin-bottom:.375rem;">Uw opmerkingen:</label>
                        <textarea name="strip_notes" placeholder="Beschrijf wat u anders zou willen zien..." required>{{ old('strip_notes') }}</textarea>
                        <button type="submit" class="btn-submit">Feedback verzenden</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
    @endif

    {{-- ─── Galerij link ─── --}}
    @if($booking->gallery_url)
    <div class="section">
        <div class="section-header">
            <div class="section-icon" style="background:#f0fdf4;">📸</div>
            <span class="section-title">Uw foto's</span>
        </div>
        <div class="section-body" style="text-align:center;">
            <p style="margin-bottom:1.25rem;color:var(--muted);">Uw foto's van het evenement staan klaar!</p>
            <a href="{{ $booking->gallery_url }}" target="_blank" class="btn-pay" style="background:var(--green);max-width:300px;display:inline-flex;">
                📸 Bekijk de foto's
            </a>
        </div>
    </div>
    @endif

    {{-- ─── Klantgegevens ─── --}}
    <div class="section">
        <div class="section-header">
            <div class="section-icon" style="background:#f0fdf4;">👤</div>
            <span class="section-title">Uw gegevens</span>
        </div>
        <div class="section-body">
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Naam</label>
                    <div class="value">{{ $booking->customer_name }}</div>
                </div>
                <div class="detail-item">
                    <label>E-mailadres</label>
                    <div class="value">{{ $booking->customer_email ?: '—' }}</div>
                </div>
                <div class="detail-item">
                    <label>Telefoonnummer</label>
                    <div class="value">{{ $booking->customer_phone ?: '—' }}</div>
                    @if($booking->whatsapp_number)
                        <a href="https://wa.me/{{ ltrim($booking->whatsapp_number, '+') }}" target="_blank" class="whatsapp-link">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            WhatsApp
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ─── Factuur downloaden ─── --}}
    @if($booking->eboekhouden_invoice_id)
    <div class="section">
        <div class="section-header">
            <div class="section-icon" style="background:#f0fdf4;">📄</div>
            <span class="section-title">Factuur</span>
        </div>
        <div class="section-body">
            <p style="font-size:.875rem;color:var(--muted);margin:0 0 .75rem;">
                Jouw factuur is beschikbaar als PDF.
                @if($booking->eboekhouden_invoice_number)
                    Factuurnummer: <strong>{{ $booking->eboekhouden_invoice_number }}</strong>
                @endif
            </p>
            <a href="{{ route('portal.factuur-download', $booking->public_token) }}"
               target="_blank"
               style="display:inline-flex;align-items:center;gap:.5rem;background:#16a34a;color:#fff;padding:.625rem 1.25rem;border-radius:.5rem;font-size:.875rem;font-weight:600;text-decoration:none;">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Download factuur PDF
            </a>
        </div>
    </div>
    @endif

    {{-- ─── Boekingsoverzicht & kosten ─── --}}
    <div class="section">
        <div class="section-header">
            <div class="section-icon" style="background:#fefce8;">💰</div>
            <span class="section-title">Overzicht & kosten</span>
        </div>
        <div class="section-body" style="padding:0;">
            @if($booking->items->count())
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Omschrijving</th>
                            <th style="text-align:center;width:80px;">Aantal</th>
                            <th style="text-align:right;width:100px;">Prijs</th>
                            <th style="text-align:right;width:100px;">Totaal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($booking->items as $item)
                        <tr>
                            <td>{{ $item->name_snapshot }}</td>
                            <td style="text-align:center;">{{ $item->quantity }}×</td>
                            <td style="text-align:right;">€ {{ number_format($item->price_snapshot, 2, ',', '.') }}</td>
                            <td style="text-align:right;">€ {{ number_format($item->linetotal, 2, ',', '.') }}</td>
                        </tr>
                        @endforeach
                        @php $exBtw = $booking->total_price / 1.21; $btw = $booking->total_price - $exBtw; @endphp
                        <tr>
                            <td colspan="3" style="text-align:right;font-size:.8rem;color:var(--muted);">Subtotaal excl. BTW</td>
                            <td style="text-align:right;font-size:.8rem;color:var(--muted);">€ {{ number_format($exBtw, 2, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td colspan="3" style="text-align:right;font-size:.8rem;color:var(--muted);">BTW 21%</td>
                            <td style="text-align:right;font-size:.8rem;color:var(--muted);">€ {{ number_format($btw, 2, ',', '.') }}</td>
                        </tr>
                        <tr class="total-row">
                            <td colspan="3" style="text-align:right;">Totaal incl. BTW</td>
                            <td style="text-align:right;">€ {{ number_format($booking->total_price, 2, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>
            @else
                <div style="padding:1.25rem;color:var(--muted);font-size:.875rem;">
                    Geen regels toegevoegd.
                    <div style="margin-top:.25rem;font-size:.875rem;font-weight:600;color:var(--text);">Totaal: € {{ number_format($booking->total_price, 2, ',', '.') }}</div>
                </div>
            @endif

            @php
                $betaald = $booking->payments->where('status','paid')->sum('amount');
                $openstaand = $booking->amount_due;
            @endphp
            @if($betaald > 0)
            <div style="padding:.875rem 1.25rem;border-top:1px solid var(--border);display:flex;justify-content:space-between;font-size:.875rem;color:var(--green);">
                <span>Al betaald</span>
                <span>− € {{ number_format($betaald, 2, ',', '.') }}</span>
            </div>
            @endif
        </div>
    </div>

    {{-- ─── Betaling ─── --}}
    @if($openstaand > 0)
    <div class="payment-section">
        @if($booking->customer_type === 'zakelijk')
        @php $openExBtw = $openstaand / 1.21; $openBtw = $openstaand - $openExBtw; @endphp
        <div style="font-size:.875rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);">Openstaand bedrag</div>
        <div class="payment-amount">
            <span>€</span> {{ number_format($openExBtw, 2, ',', '.') }}
        </div>
        <p class="payment-note" style="margin-top:.25rem;font-size:.95rem;">
            € {{ number_format($openstaand, 2, ',', '.') }} incl. BTW
            <span style="color:var(--muted);font-size:.8rem;">(BTW € {{ number_format($openBtw, 2, ',', '.') }})</span>
        </p>
        @else
        <div style="font-size:.875rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);">Openstaand bedrag</div>
        <div class="payment-amount">
            <span>€</span> {{ number_format($openstaand, 2, ',', '.') }}
        </div>
        @php $openExBtw2 = $openstaand / 1.21; $openBtw2 = $openstaand - $openExBtw2; @endphp
        <p class="payment-note" style="margin-top:.25rem;">excl. BTW € {{ number_format($openExBtw2, 2, ',', '.') }} &nbsp;|&nbsp; BTW € {{ number_format($openBtw2, 2, ',', '.') }}</p>
        @endif
        @if($booking->payment_method === 'bij_levering' || !$booking->account->mollie_enabled)
        <p style="font-size:1rem;font-weight:600;color:var(--text);margin:.5rem 0 0;">Betaling vindt plaats bij levering.</p>
        <p class="payment-note">U hoeft nu niets te betalen.</p>
        @else
        <form method="POST" action="{{ route('portal.payment-start', $booking->public_token) }}">
            @csrf
            <button type="submit" class="btn-pay">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                Betaal nu via iDEAL
            </button>
        </form>
        <p class="payment-note">Veilig betalen via Mollie &mdash; iDEAL, creditcard en meer</p>
        @endif
    </div>
    @else
    <div class="payment-section" style="border-color:var(--green);">
        <div class="payment-paid">
            <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Betaling volledig voldaan
        </div>
    </div>
    @endif

</div>
</body>
</html>
