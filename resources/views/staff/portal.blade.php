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

        /* Shift-kaart: duidelijk afgebakend blok per shift */
        .shift-portal-card { border: 1px solid #cffafe; border-left: 5px solid #06b6d4; border-radius: 1rem; margin-bottom: 2rem; box-shadow: 0 6px 20px rgba(8,145,178,.12); }
        .shift-portal-header { background: linear-gradient(90deg,#ecfeff,#f0fdff); border-bottom: 1px solid #cffafe; padding: .9rem 1.1rem; align-items: center; }
        .shift-portal-badge { display: inline-flex; align-items: center; gap: .3rem; background: #06b6d4; color: #fff; font-weight: 800; font-size: .8rem; padding: .28rem .7rem; border-radius: 999px; }
        .shift-portal-date { font-size: 1.05rem; font-weight: 800; color: #0e7490; margin-top: .35rem; text-transform: capitalize; }
        .shift-portal-time { font-size: 1.25rem; font-weight: 800; color: #0e7490; }
        .shift-portal-card .card-body { padding: 1rem 1.1rem; }
        .klus-block { position: relative; border: 1px solid #e2e8f0; border-radius: .7rem; padding: .75rem .85rem .75rem 2.5rem; margin-bottom: .85rem; background: #fff; }
        .klus-block:last-of-type { margin-bottom: 1rem; }
        .klus-step { position: absolute; left: .7rem; top: .8rem; width: 1.5rem; height: 1.5rem; border-radius: 50%; background: #0e7490; color: #fff; font-size: .8rem; font-weight: 800; display: flex; align-items: center; justify-content: center; }
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
                            <div class="detail-label">Locatie @include('partials.ze_badge', ['booking' => $b])</div>
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
    @php $shiftsToShow = $tab === 'archief' ? $pastShifts : $upcomingShifts; @endphp

    @if($shiftsToShow->isEmpty())
        <div class="empty">
            <div class="empty-icon">{{ $tab === 'archief' ? '🗂' : '📭' }}</div>
            @if($tab === 'archief')
                <p style="font-weight:600;margin-bottom:.25rem;">Geen afgesloten shifts</p>
                <p style="font-size:.875rem;">Afgeronde shifts verschijnen hier.</p>
            @else
                <p style="font-weight:600;margin-bottom:.25rem;">Geen aankomende shifts</p>
                <p style="font-size:.875rem;">Er zijn nog geen shifts aan jou gekoppeld.</p>
            @endif
        </div>
    @else
        <p style="font-size:.875rem;color:#64748b;margin-bottom:1.25rem;">
            {{ $shiftsToShow->count() }} {{ $shiftsToShow->count() === 1 ? 'shift' : 'shifts' }}
            {{ $tab === 'archief' ? 'afgerond' : 'ingepland' }}
        </p>

        @foreach($shiftsToShow as $shift)
            @include('staff._shift_card', ['shift' => $shift, 'staff' => $staff, 'shiftHours' => $shiftHours])
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
