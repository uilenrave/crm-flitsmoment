<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mijn planning — {{ $staff->name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8fafc; color: #1e293b; min-height: 100vh; }

        .header { background: #fff; border-bottom: 1px solid #e2e8f0; padding: 1rem 1.25rem; display: flex; align-items: center; gap: .75rem; }
        .logo { height: 32px; }
        .header-title { font-size: 1rem; font-weight: 700; color: #1e293b; }
        .header-sub { font-size: .8rem; color: #64748b; margin-top: .1rem; }

        .content { max-width: 680px; margin: 0 auto; padding: 1.5rem 1rem 3rem; }

        .section-title { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #64748b; margin-bottom: .75rem; padding-bottom: .4rem; border-bottom: 1px solid #e2e8f0; }

        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: .75rem; margin-bottom: .75rem; overflow: hidden; }
        .card-header { padding: .875rem 1rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: flex-start; gap: .5rem; }
        .card-date { font-size: .75rem; font-weight: 700; color: #64748b; }
        .card-body { padding: .875rem 1rem; }

        .role-badge { display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .65rem; border-radius: 999px; font-size: .72rem; font-weight: 700; }
        .badge-delivery { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .badge-pickup   { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-handover { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }

        .detail-row { display: flex; gap: .5rem; align-items: flex-start; margin-bottom: .5rem; font-size: .875rem; }
        .detail-icon { font-size: 1rem; flex-shrink: 0; margin-top: .05rem; }
        .detail-text { color: #374151; line-height: 1.4; }
        .detail-label { font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #94a3b8; margin-bottom: .15rem; }

        .maps-link { display: inline-flex; align-items: center; gap: .35rem; margin-top: .35rem; padding: .35rem .7rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: .375rem; font-size: .8rem; color: #374151; text-decoration: none; font-weight: 500; }
        .maps-link:hover { background: #f1f5f9; }

        .empty { text-align: center; padding: 2.5rem 1rem; color: #94a3b8; }
        .empty-icon { font-size: 2rem; margin-bottom: .5rem; }

        .time-highlight { font-size: 1.1rem; font-weight: 700; color: #1e293b; }

        /* Tabs */
        .tabs { display: flex; gap: .25rem; margin-bottom: 1.25rem; background: #f1f5f9; border-radius: .65rem; padding: .25rem; }
        .tab-btn { flex: 1; padding: .5rem .75rem; border-radius: .45rem; font-size: .85rem; font-weight: 600; text-align: center; text-decoration: none; color: #64748b; transition: all .15s; }
        .tab-btn.active { background: #fff; color: #1e293b; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .tab-btn:hover:not(.active) { color: #1e293b; }

        /* Uren sectie */
        .uren-section { border-top: 1px solid #f1f5f9; padding: .875rem 1rem; background: #fafafa; }
        .uren-title { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #64748b; margin-bottom: .625rem; }

        .uren-form { display: flex; gap: .5rem; align-items: flex-end; flex-wrap: wrap; }
        .uren-input { padding: .45rem .65rem; border: 1px solid #e2e8f0; border-radius: .5rem; font-size: .875rem; background: #fff; width: 90px; }
        .uren-input:focus { outline: none; border-color: #7c3aed; box-shadow: 0 0 0 2px rgba(124,58,237,.1); }
        .uren-note { padding: .45rem .65rem; border: 1px solid #e2e8f0; border-radius: .5rem; font-size: .8rem; background: #fff; flex: 1; min-width: 140px; }
        .uren-note:focus { outline: none; border-color: #7c3aed; box-shadow: 0 0 0 2px rgba(124,58,237,.1); }
        .uren-btn { padding: .45rem .9rem; background: #7c3aed; color: #fff; border: none; border-radius: .5rem; font-size: .8rem; font-weight: 600; cursor: pointer; white-space: nowrap; }
        .uren-btn:hover { background: #6d28d9; }

        .uren-status-pending  { display: flex; align-items: center; gap: .5rem; font-size: .8rem; color: #92400e; background: #fef3c7; border: 1px solid #fcd34d; border-radius: .5rem; padding: .4rem .7rem; }
        .uren-status-approved { display: flex; align-items: center; gap: .5rem; font-size: .8rem; color: #166534; background: #dcfce7; border: 1px solid #86efac; border-radius: .5rem; padding: .4rem .7rem; }
        .uren-status-paid     { display: flex; align-items: center; gap: .5rem; font-size: .8rem; color: #5b21b6; background: #ede9fe; border: 1px solid #c4b5fd; border-radius: .5rem; padding: .4rem .7rem; }

        .flash-success { background: #dcfce7; border: 1px solid #86efac; color: #166534; border-radius: .75rem; padding: .75rem 1rem; margin-bottom: 1rem; font-size: .875rem; font-weight: 500; }
        .flash-error   { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; border-radius: .75rem; padding: .75rem 1rem; margin-bottom: 1rem; font-size: .875rem; font-weight: 500; }

        /* Combinatie-rit styling */
        .uren-status-combined { display: flex; align-items: center; gap: .5rem; font-size: .85rem; color: #5b21b6; background: #ede9fe; border: 1px solid #c4b5fd; border-radius: .5rem; padding: .55rem .75rem; }

        /* Assets-meenemen blok */
        .assets-block { background: #f0fdf4; border: 1px solid #86efac; border-radius: .55rem; padding: .65rem .85rem; margin-top: .65rem; }
        .assets-block-title { font-size: .72rem; font-weight: 700; color: #166534; text-transform: uppercase; letter-spacing: .04em; margin-bottom: .4rem; display: flex; align-items: center; gap: .3rem; }
        .assets-list { display: flex; flex-direction: column; gap: .2rem; }
        .asset-row { display: flex; align-items: center; gap: .4rem; font-size: .85rem; color: #1e293b; }
        .asset-row .icon { width: 1.1rem; flex-shrink: 0; text-align: center; }
        .asset-row .qty { color: #15803d; font-weight: 700; margin-left: auto; font-size: .8rem; background: #dcfce7; padding: .05rem .45rem; border-radius: 9999px; }
        .combineer-link { display: inline-flex; align-items: center; gap: .3rem; background: none; border: none; cursor: pointer; font-size: .78rem; color: #7c3aed; text-decoration: underline; text-underline-offset: 3px; padding: .35rem 0; margin-top: .4rem; }
        .combineer-link:hover { color: #5b21b6; }
        .combineer-form { display: none; margin-top: .65rem; padding: .75rem; background: #faf5ff; border: 1px solid #e9d5ff; border-radius: .5rem; }
        .combineer-form.open { display: block; }
        .combineer-form select { width: 100%; padding: .55rem .65rem; border: 1px solid #d1d5db; border-radius: .375rem; font-size: .85rem; background: #fff; margin-bottom: .55rem; }
        .combineer-actions { display: flex; gap: .5rem; }
        .combineer-btn { flex: 1; background: #7c3aed; color: #fff; border: none; padding: .55rem .85rem; border-radius: .375rem; font-size: .82rem; font-weight: 600; cursor: pointer; }
        .combineer-btn:hover { background: #6d28d9; }
        .combineer-btn[disabled] { opacity: .45; cursor: not-allowed; }
        .combineer-cancel { background: #fff; color: #64748b; border: 1px solid #cbd5e1; padding: .55rem .85rem; border-radius: .375rem; font-size: .82rem; font-weight: 600; cursor: pointer; }
        .combineer-cancel:hover { background: #f1f5f9; }

        @media (max-width: 480px) {
            .content { padding: 1rem .75rem 3rem; }
        }
    </style>
</head>
<body>

<div class="header">
    <img src="/logo.png?v=3" alt="Flitsmoment" class="logo">
    <div>
        <div class="header-title">Planning van {{ $staff->name }}</div>
        <div class="header-sub">Aankomende ritten & momenten</div>
    </div>
</div>

<div class="content">

    @if(session('hours_success'))
    <div class="flash-success">✅ {{ session('hours_success') }}</div>
    @endif
    @if(session('hours_error'))
    <div class="flash-error">⚠️ {{ session('hours_error') }}</div>
    @endif

    @php
        // Helper: bouw rittenlijst uit delivery + pickup collections
        function buildRitten($deliveries, $pickups): \Illuminate\Support\Collection {
            $ritten = collect();
            foreach ($deliveries as $b) {
                $datum = $b->delivery_at
                    ? \Carbon\Carbon::parse($b->delivery_at)
                    : ($b->booking_type === 'to_go' && $b->customer_pickup_at
                        ? \Carbon\Carbon::parse($b->customer_pickup_at)
                        : $b->event_date->startOfDay());
                $role = $b->booking_type === 'to_go' ? 'handover' : 'delivery';
                $ritten->push(['booking' => $b, 'type' => $role, 'role' => $role, 'datum' => $datum]);
            }
            foreach ($pickups as $b) {
                $datum = $b->booking_type === 'to_go'
                    ? ($b->customer_return_at ? \Carbon\Carbon::parse($b->customer_return_at) : $b->event_date->startOfDay())
                    : ($b->pickup_at ? \Carbon\Carbon::parse($b->pickup_at) : $b->event_date->startOfDay());
                $ritten->push(['booking' => $b, 'type' => 'pickup', 'role' => 'pickup', 'datum' => $datum]);
            }
            return $ritten;
        }

        $allRitten  = buildRitten($deliveryBookings, $pickupBookings)->sortBy('datum')->values();
        $pastRitten = buildRitten($pastDeliveryBookings, $pastPickupBookings)->sortByDesc('datum')->values();
    @endphp

    {{-- Tabs --}}
    <div class="tabs">
        <a href="{{ route('staff.portal', $staff->public_token) }}"
           class="tab-btn {{ $tab === 'aankomend' ? 'active' : '' }}">
            📅 Aankomend
            @if($allRitten->count() > 0)
                <span style="font-size:.72rem;background:{{ $tab === 'aankomend' ? '#fef3c7' : '#e2e8f0' }};color:{{ $tab === 'aankomend' ? '#92400e' : '#64748b' }};padding:.05rem .4rem;border-radius:999px;margin-left:.25rem;">{{ $allRitten->count() }}</span>
            @endif
        </a>
        <a href="{{ route('staff.portal', $staff->public_token) }}?tab=beschikbaar"
           class="tab-btn {{ $tab === 'beschikbaar' ? 'active' : '' }}">
            🔓 Beschikbaar
            @if($availableRitten->count() > 0)
                <span style="font-size:.72rem;background:{{ $tab === 'beschikbaar' ? '#dcfce7' : '#e2e8f0' }};color:{{ $tab === 'beschikbaar' ? '#166534' : '#64748b' }};padding:.05rem .4rem;border-radius:999px;margin-left:.25rem;">{{ $availableRitten->count() }}</span>
            @endif
        </a>
        <a href="{{ route('staff.portal', $staff->public_token) }}?tab=archief"
           class="tab-btn {{ $tab === 'archief' ? 'active' : '' }}">
            🗂 Archief
            @if($pastRitten->count() > 0)
                <span style="font-size:.72rem;background:{{ $tab === 'archief' ? '#fef3c7' : '#e2e8f0' }};color:{{ $tab === 'archief' ? '#92400e' : '#64748b' }};padding:.05rem .4rem;border-radius:999px;margin-left:.25rem;">{{ $pastRitten->count() }}</span>
            @endif
        </a>
    </div>

    @if($tab === 'beschikbaar')
        {{-- ── Beschikbare ritten: aanmelden ───────────────────────────── --}}
        @if($availableRitten->isEmpty())
            <div class="empty">
                <div class="empty-icon">🔓</div>
                <p style="font-weight:600;margin-bottom:.25rem;">Geen open ritten</p>
                <p style="font-size:.875rem;">Er staan op dit moment geen ritten open om je voor aan te melden.</p>
            </div>
        @else
            <p style="font-size:.875rem;color:#64748b;margin-bottom:1.25rem;">
                Meld je aan voor de ritten die jij kunt doen. Je beheerder bevestigt daarna wie de rit doet.
            </p>
            @php $lastMonth = null; @endphp
            @foreach($availableRitten as $rit)
            @php
                $b     = $rit['booking'];
                $type  = $rit['type'];
                $role  = $rit['role'];
                $datum = $rit['datum'];
                $month = $datum->format('Y-m');

                $adres = trim(implode(', ', array_filter([$b->event_address, $b->event_postcode, $b->event_city])));
                $mapsUrl = $adres ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($adres) : null;

                $myResp = $myResponses[$b->id . '|' . $role] ?? null;
            @endphp
            @if($month !== $lastMonth)
                @php $lastMonth = $month; @endphp
                <div class="section-title" style="margin-top:1.25rem;">{{ $datum->translatedFormat('F Y') }}</div>
            @endif
            <div class="card">
                <div class="card-header">
                    <div>
                        @if($type === 'delivery')
                            <span class="role-badge badge-delivery">⬇ Bezorgen</span>
                        @elseif($type === 'handover')
                            <span class="role-badge badge-handover">🤝 Afgeven (To Go)</span>
                        @else
                            <span class="role-badge badge-pickup">⬆ Ophalen{{ $b->booking_type === 'to_go' ? ' (To Go)' : '' }}</span>
                        @endif
                    </div>
                    <div style="text-align:right;">
                        <div class="card-date">{{ $datum->translatedFormat('l j F Y') }}</div>
                        @if($datum->format('H:i') !== '00:00')
                        <div class="time-highlight">{{ $datum->format('H:i') }}</div>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    @if($b->event_city || $adres)
                    <div class="detail-row">
                        <div class="detail-icon">📍</div>
                        <div class="detail-text">
                            <div class="detail-label">Locatie</div>
                            @if($b->event_city)<strong>{{ $b->event_city }}</strong>@endif
                            @if($adres)<br><span style="color:#64748b;">{{ $adres }}</span>@endif
                            @if($mapsUrl)<br><a href="{{ $mapsUrl }}" target="_blank" class="maps-link">🗺️ Open in Google Maps</a>@endif
                        </div>
                    </div>
                    @endif

                    @if($b->event_start_time)
                    <div class="detail-row">
                        <div class="detail-icon">🎉</div>
                        <div class="detail-text">
                            <div class="detail-label">Evenement</div>
                            {{ $b->event_date->translatedFormat('j F') }}
                            van {{ substr($b->event_start_time, 0, 5) }}
                            @if($b->event_end_time) tot {{ substr($b->event_end_time, 0, 5) }}@endif
                        </div>
                    </div>
                    @endif

                    @if($myResp === 'yes')
                    <div class="uren-status-approved" style="margin-top:.75rem;">
                        ✅ <strong>Je hebt je aangemeld</strong> — wacht op bevestiging
                    </div>
                    <form method="POST" action="{{ route('staff.portal.withdraw', $staff->public_token) }}" style="margin-top:.5rem;">
                        @csrf
                        <input type="hidden" name="booking_id" value="{{ $b->id }}">
                        <input type="hidden" name="role" value="{{ $role }}">
                        <button type="submit" class="combineer-link">↩ Aanmelding intrekken</button>
                    </form>
                    @elseif($myResp === 'no')
                    <div style="margin-top:.75rem;display:flex;align-items:center;gap:.5rem;font-size:.85rem;color:#991b1b;background:#fee2e2;border:1px solid #fca5a5;border-radius:.5rem;padding:.55rem .75rem;">
                        🚫 <strong>Je kunt deze rit niet</strong> — je wordt er niet meer voor gevraagd
                    </div>
                    <form method="POST" action="{{ route('staff.portal.withdraw', $staff->public_token) }}" style="margin-top:.5rem;">
                        @csrf
                        <input type="hidden" name="booking_id" value="{{ $b->id }}">
                        <input type="hidden" name="role" value="{{ $role }}">
                        <button type="submit" class="combineer-link">↩ Toch beschikbaar maken</button>
                    </form>
                    @else
                    <div style="margin-top:.75rem;display:flex;gap:.5rem;">
                        <form method="POST" action="{{ route('staff.portal.signup', $staff->public_token) }}" style="flex:1;">
                            @csrf
                            <input type="hidden" name="booking_id" value="{{ $b->id }}">
                            <input type="hidden" name="role" value="{{ $role }}">
                            <button type="submit" class="uren-btn" style="width:100%;padding:.6rem;">🙋 Ik kan deze</button>
                        </form>
                        <form method="POST" action="{{ route('staff.portal.decline', $staff->public_token) }}" style="flex:1;">
                            @csrf
                            <input type="hidden" name="booking_id" value="{{ $b->id }}">
                            <input type="hidden" name="role" value="{{ $role }}">
                            <button type="submit" style="width:100%;padding:.6rem;background:#fff;color:#991b1b;border:1px solid #fca5a5;border-radius:.5rem;font-size:.8rem;font-weight:600;cursor:pointer;">🚫 Ik kan niet</button>
                        </form>
                    </div>
                    @endif
                </div>
            </div>
            @endforeach
        @endif

    @else
    @php $ritten = $tab === 'archief' ? $pastRitten : $allRitten; @endphp

    @if($ritten->isEmpty())
        <div class="empty">
            <div class="empty-icon">{{ $tab === 'archief' ? '🗂' : '📭' }}</div>
            @if($tab === 'archief')
                <p style="font-weight:600;margin-bottom:.25rem;">Geen afgesloten ritten</p>
                <p style="font-size:.875rem;">Afgeronde ritten verschijnen hier.</p>
            @else
                <p style="font-weight:600;margin-bottom:.25rem;">Geen aankomende ritten</p>
                <p style="font-size:.875rem;">Er zijn nog geen boekingen aan jou gekoppeld.</p>
            @endif
        </div>
    @else
        <p style="font-size:.875rem;color:#64748b;margin-bottom:1.25rem;">
            {{ $ritten->count() }} {{ $ritten->count() === 1 ? 'rit' : 'ritten' }}
            {{ $tab === 'archief' ? 'afgerond' : 'ingepland' }}
        </p>

        @foreach($ritten as $rit)
        @php
            $b     = $rit['booking'];
            $type  = $rit['type'];
            $role  = $rit['role'];
            $datum = $rit['datum'];

            $adres = trim(implode(', ', array_filter([
                $b->event_address,
                $b->event_postcode,
                $b->event_city,
            ])));
            $mapsUrl = $adres
                ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($adres)
                : null;

            $hoursKey  = $b->id . '|' . $role;
            $hoursEntry = $existingHours[$hoursKey] ?? null;
            $isLocked  = $hoursEntry && in_array($hoursEntry->status, ['approved', 'paid']);
        @endphp
        <div class="card">
            <div class="card-header">
                <div>
                    @if($type === 'delivery')
                        <span class="role-badge badge-delivery">⬇ Bezorgen</span>
                    @elseif($type === 'handover')
                        <span class="role-badge badge-handover">🤝 Afgeven (To Go)</span>
                    @else
                        <span class="role-badge badge-pickup">⬆ Ophalen{{ $b->booking_type === 'to_go' ? ' (To Go)' : '' }}</span>
                    @endif
                </div>
                <div style="text-align:right;">
                    <div class="card-date">{{ $datum->translatedFormat('l j F Y') }}</div>
                    @if($datum->format('H:i') !== '00:00')
                    <div class="time-highlight">{{ $datum->format('H:i') }}</div>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="detail-row">
                    <div class="detail-icon">👤</div>
                    <div class="detail-text">
                        <div class="detail-label">Klant</div>
                        <strong>{{ $b->customer_name }}</strong>
                        @if($b->customer_phone)
                        <br><a href="tel:{{ $b->customer_phone }}" style="color:#3b82f6;font-size:.85rem;">{{ $b->customer_phone }}</a>
                        @endif
                    </div>
                </div>

                @if($b->location_contact_phone)
                @php
                    $lcLabels = ['ceremoniemeester' => 'Ceremoniemeester', 'eventmanager' => 'Eventmanager', 'eventlocatie' => 'Eventlocatie', 'wij_zelf' => 'Klant zelf', 'anders' => 'Anders'];
                    $lcLabel  = $lcLabels[$b->location_contact_type] ?? 'Contactpersoon';
                @endphp
                <div class="detail-row">
                    <div class="detail-icon">📇</div>
                    <div class="detail-text">
                        <div class="detail-label">Contactpersoon op locatie</div>
                        <strong>{{ $lcLabel }}</strong>
                        <br><a href="tel:{{ $b->location_contact_phone }}" style="color:#3b82f6;font-size:.85rem;">{{ $b->location_contact_phone }}</a>
                        @if($b->location_contact_email)
                        <br><a href="mailto:{{ $b->location_contact_email }}" style="color:#3b82f6;font-size:.85rem;">{{ $b->location_contact_email }}</a>
                        @endif
                    </div>
                </div>
                @endif

                @if($b->event_location || $adres)
                <div class="detail-row">
                    <div class="detail-icon">📍</div>
                    <div class="detail-text">
                        <div class="detail-label">Locatie</div>
                        @if($b->event_location)<strong>{{ $b->event_location }}</strong><br>@endif
                        @if($adres)<span style="color:#64748b;">{{ $adres }}</span>@endif
                        @if($mapsUrl)
                        <br><a href="{{ $mapsUrl }}" target="_blank" class="maps-link">🗺️ Open in Google Maps</a>
                        @endif
                    </div>
                </div>
                @endif

                @if($b->event_start_time)
                <div class="detail-row">
                    <div class="detail-icon">🎉</div>
                    <div class="detail-text">
                        <div class="detail-label">Evenement</div>
                        {{ $b->event_date->translatedFormat('j F') }}
                        van {{ substr($b->event_start_time, 0, 5) }}
                        @if($b->event_end_time) tot {{ substr($b->event_end_time, 0, 5) }}@endif
                    </div>
                </div>
                @endif

                @if($type === 'pickup' && $b->pickup_contact_person)
                <div class="detail-row">
                    <div class="detail-icon">📞</div>
                    <div class="detail-text">
                        <div class="detail-label">Contactpersoon ophaal</div>
                        {{ $b->pickup_contact_person }}
                        @if($b->pickup_contact_time) om {{ substr($b->pickup_contact_time, 0, 5) }}@endif
                    </div>
                </div>
                @endif

                @if($b->event_notes)
                <div class="detail-row">
                    <div class="detail-icon">📝</div>
                    <div class="detail-text">
                        <div class="detail-label">Opmerkingen</div>
                        <span style="white-space:pre-line;">{{ $b->event_notes }}</span>
                    </div>
                </div>
                @endif

                {{-- Leverinstructies (alleen tonen als ingevuld) --}}
                @php
                    $hasInstructions = trim((string) $b->delivery_instructions) !== ''
                        || ! empty($b->delivery_instructions_images);
                @endphp
                @if($hasInstructions)
                <div style="margin-top:.5rem;">
                    <button type="button" class="instructions-toggle"
                            onclick="toggleInstructions(this)"
                            style="width:100%;display:flex;align-items:center;gap:.5rem;background:#fffbeb;border:1px solid #fcd34d;border-radius:.5rem;padding:.55rem .75rem;font-size:.85rem;font-weight:600;color:#92400e;cursor:pointer;text-align:left;">
                        <span style="font-size:1rem;">📋</span>
                        <span style="flex:1;">Er zijn specifieke leverinstructies beschikbaar</span>
                        <span class="caret" style="font-size:.7rem;transition:transform .2s;">▼</span>
                    </button>
                    <div class="instructions-content" style="display:none;margin-top:.5rem;background:#fffbeb;border:1px solid #fcd34d;border-radius:.5rem;padding:.75rem .9rem;">
                        @if(trim((string) $b->delivery_instructions) !== '')
                        <div style="font-size:.875rem;color:#1e293b;line-height:1.5;white-space:pre-line;margin-bottom:.5rem;">{{ $b->delivery_instructions }}</div>
                        @endif
                        @if(! empty($b->delivery_instructions_images))
                        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:.5rem;margin-top:.5rem;">
                            @foreach($b->delivery_instructions_images as $img)
                                <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($img) }}" target="_blank"
                                   style="display:block;border-radius:.4rem;overflow:hidden;border:1px solid #fcd34d;">
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($img) }}"
                                         alt="Leverinstructie"
                                         style="width:100%;height:110px;object-fit:cover;display:block;">
                                </a>
                            @endforeach
                        </div>
                        <div style="font-size:.7rem;color:#92400e;margin-top:.4rem;">Tik op een foto om te vergroten.</div>
                        @endif
                    </div>
                </div>
                @endif
            </div>

            {{-- Wat moet je meenemen — alleen relevant voor bezorging/afgifte, niet bij ophalen --}}
            @if($type === 'delivery' && $b->items && $b->items->isNotEmpty())
            @php
                $catIcons = [
                    'photobooth' => '📷',
                    'background' => '🖼',
                    'prop_box'   => '🎩',
                    'extra'      => '✨',
                ];
                $itemsByCat = $b->items->filter(fn($i) => $i->asset)->groupBy(fn($i) => $i->asset->category);
            @endphp
            @if($itemsByCat->isNotEmpty())
            <div class="assets-block">
                <div class="assets-block-title">📦 Wat neem je mee</div>
                <div class="assets-list">
                    @foreach($itemsByCat as $cat => $items)
                        @foreach($items as $it)
                        <div class="asset-row">
                            <span class="icon">{{ $catIcons[$cat] ?? '📦' }}</span>
                            <span>{{ $it->asset->name }}@if($it->unit_number) — unit {{ $it->unit_number }}@endif</span>
                            @if($it->quantity > 1)<span class="qty">×{{ $it->quantity }}</span>@endif
                        </div>
                        @endforeach
                    @endforeach
                </div>
            </div>
            @endif
            @endif

            {{-- Uren sectie --}}
            <div class="uren-section">
                <div class="uren-title">⏱ Gewerkte uren</div>

                @if($isLocked)
                    {{-- Goedgekeurd of uitbetaald: readonly tonen --}}
                    @if($hoursEntry->status === 'paid')
                    <div class="uren-status-paid">
                        💸 <strong>{{ number_format($hoursEntry->effective_hours, 2, ',', '') }} uur uitbetaald</strong>
                    </div>
                    @else
                    <div class="uren-status-approved">
                        ✅ <strong>{{ number_format($hoursEntry->effective_hours, 2, ',', '') }} uur goedgekeurd</strong>
                    </div>
                    @endif

                @elseif($hoursEntry && $hoursEntry->status === 'combined')
                    {{-- Combinatierit: gemarkeerd, uren al ingediend bij andere rit --}}
                    @php
                        $linkedBooking = $hoursEntry->combinedWithBooking;
                        $linkedRoleLabel = $hoursEntry->combinedWithRoleLabel();
                    @endphp
                    <div class="uren-status-combined">
                        🔗 <span><strong>Gecombineerde rit</strong> — uren al ingediend bij
                        @if($linkedBooking)
                            @if($linkedRoleLabel) <strong>{{ $linkedRoleLabel }}</strong> van @endif
                            boeking <strong>{{ $linkedBooking->booking_number }}</strong>{{ $linkedBooking->customer_name ? ' ('.$linkedBooking->customer_name.')' : '' }}
                        @else
                            een andere rit
                        @endif</span>
                    </div>
                    <form method="POST" action="{{ route('staff.portal.hours.uncombine', ['token' => $staff->public_token, 'hoursId' => $hoursEntry->id]) }}" style="margin-top:.4rem;">
                        @csrf
                        <button type="submit" class="combineer-link" style="font-size:.78rem;">↩ Terugdraaien</button>
                    </form>

                @elseif($hoursEntry && $hoursEntry->status === 'pending')
                    {{-- Pending: toon status + mogelijkheid tot bijwerken --}}
                    <div class="uren-status-pending" style="margin-bottom:.625rem;">
                        ⏳ <span>{{ number_format($hoursEntry->hours, 2, ',', '') }} uur
                        @if($hoursEntry->km > 0) · {{ number_format($hoursEntry->km, 0) }} km @endif
                        ingediend — wacht op goedkeuring</span>
                    </div>
                    <form method="POST" action="{{ route('staff.portal.hours.submit', $staff->public_token) }}">
                        @csrf
                        <input type="hidden" name="booking_id" value="{{ $b->id }}">
                        <input type="hidden" name="role" value="{{ $role }}">
                        <div class="uren-form">
                            <div>
                                <div style="font-size:.7rem;color:#64748b;margin-bottom:.2rem;">Uren</div>
                                <input type="number" name="hours" step="0.25" min="0.25" max="24" class="uren-input"
                                       value="{{ number_format($hoursEntry->hours, 2, '.', '') }}" placeholder="2.50" required>
                            </div>
                            <div>
                                <div style="font-size:.7rem;color:#64748b;margin-bottom:.2rem;">Privé km</div>
                                <input type="number" name="km" step="1" min="0" max="9999" class="uren-input"
                                       value="{{ $hoursEntry->km ? number_format($hoursEntry->km, 0, '.', '') : '' }}"
                                       placeholder="0" oninput="updateKmPreview(this)">
                            </div>
                            <button type="submit" class="uren-btn">Bijwerken</button>
                        </div>
                        <div class="km-preview" style="display:none;font-size:.75rem;color:#64748b;margin-top:.4rem;">= €<span class="km-amount">0,00</span> km-vergoeding</div>
                    </form>

                    @include('staff._combineer_form', ['booking' => $b, 'role' => $role, 'staff' => $staff, 'allStaffRitten' => $allStaffRitten])

                @else
                    {{-- Nog niet ingediend --}}
                    <form method="POST" action="{{ route('staff.portal.hours.submit', $staff->public_token) }}">
                        @csrf
                        <input type="hidden" name="booking_id" value="{{ $b->id }}">
                        <input type="hidden" name="role" value="{{ $role }}">
                        <div class="uren-form">
                            <div>
                                <div style="font-size:.7rem;color:#64748b;margin-bottom:.2rem;">Uren</div>
                                <input type="number" name="hours" step="0.25" min="0.25" max="24" class="uren-input"
                                       placeholder="2.50" required>
                            </div>
                            <div>
                                <div style="font-size:.7rem;color:#64748b;margin-bottom:.2rem;">Privé km</div>
                                <input type="number" name="km" step="1" min="0" max="9999" class="uren-input"
                                       placeholder="0" oninput="updateKmPreview(this)">
                            </div>
                            <button type="submit" class="uren-btn">Indienen</button>
                        </div>
                        <div class="km-preview" style="display:none;font-size:.75rem;color:#64748b;margin-top:.4rem;">= €<span class="km-amount">0,00</span> km-vergoeding</div>
                    </form>

                    @include('staff._combineer_form', ['booking' => $b, 'role' => $role, 'staff' => $staff, 'allStaffRitten' => $allStaffRitten])
                @endif
            </div>
        </div>
        @endforeach
    @endif
    @endif

</div>
<script>
function toggleCombineerForm(btn) {
    const wrap = btn.closest('.combineer-wrap');
    const link = wrap.querySelector('.combineer-link');
    const form = wrap.querySelector('.combineer-form');
    const isOpen = form.classList.contains('open');
    form.classList.toggle('open', !isOpen);
    link.style.display = isOpen ? '' : 'none';
}

function updateKmPreview(input) {
    const form = input.closest('form');
    const preview = form.querySelector('.km-preview');
    const amountEl = form.querySelector('.km-amount');
    const km = parseFloat(input.value) || 0;
    if (km > 0) {
        const amount = (km * 0.25).toFixed(2).replace('.', ',');
        amountEl.textContent = amount;
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
}

function toggleInstructions(btn) {
    const content = btn.nextElementSibling;
    const caret   = btn.querySelector('.caret');
    const open    = content.style.display === 'block';
    content.style.display = open ? 'none' : 'block';
    if (caret) caret.style.transform = open ? 'rotate(0deg)' : 'rotate(180deg)';
}
</script>
</body>
</html>
