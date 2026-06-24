@extends('layouts.app')

@section('title', 'Personeel inplannen')

@section('content')
<style>
    .sp-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
    .sp-table th { padding: .6rem 1rem; text-align: left; font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #64748b; background: #f8fafc; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
    .sp-table td { padding: .65rem 1rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .sp-table tr:last-child td { border-bottom: none; }
    .sp-table tr:hover td { background: #fafafa; }

    .booking-num { font-weight: 700; color: #1e293b; font-size: .8rem; }
    .booking-name { color: #374151; }
    .booking-date { font-size: .8rem; font-weight: 600; color: #1e293b; white-space: nowrap; }
    .booking-time { font-size: .72rem; color: #64748b; white-space: nowrap; }
    .booking-loc { font-size: .78rem; color: #64748b; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    .role-col { white-space: nowrap; }
    .role-badge { display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .6rem; border-radius: 999px; font-size: .7rem; font-weight: 700; }
    .badge-delivery { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
    .badge-pickup   { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
    .badge-handover { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }

    .staff-select { padding: .35rem .6rem; border: 1px solid #e2e8f0; border-radius: .375rem; font-size: .8rem; background: #fff; cursor: pointer; min-width: 150px; color: #374151; transition: border-color .15s; }
    .staff-select:focus { outline: none; border-color: #7c3aed; box-shadow: 0 0 0 2px rgba(124,58,237,.1); }
    .staff-select.saving { opacity: .6; pointer-events: none; }
    .staff-select.saved { border-color: #16a34a; }

    .status-dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; margin-right: .3rem; }
    .dot-empty { background: #e2e8f0; }
    .dot-filled { background: #16a34a; }

    .month-header td { background: #f1f5f9; font-weight: 700; font-size: .75rem; color: #475569; padding: .4rem 1rem; letter-spacing: .03em; }

    .filter-bar { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; margin-bottom: 1rem; }
    .filter-btn { padding: .35rem .85rem; border: 1px solid #e2e8f0; border-radius: .375rem; font-size: .8rem; background: #fff; cursor: pointer; font-weight: 500; color: #374151; transition: all .15s; }
    .filter-btn.active { background: #7c3aed; color: #fff; border-color: #7c3aed; }
    .filter-btn:hover:not(.active) { background: #f8fafc; }

    .no-staff-row { background: #fffbeb; }
    .no-staff-row td { border-bottom-color: #fef3c7; }

    .open-toggle { display: inline-flex; align-items: center; gap: .3rem; padding: .25rem .6rem; border: 1px solid #e2e8f0; border-radius: .375rem; font-size: .72rem; font-weight: 600; background: #fff; color: #64748b; cursor: pointer; transition: all .15s; white-space: nowrap; }
    .open-toggle:hover { background: #f8fafc; }
    .open-toggle.is-open { background: #ecfdf5; border-color: #6ee7b7; color: #047857; }
    .open-toggle.saving { opacity: .6; pointer-events: none; }

    .applicant-chip { display: inline-flex; align-items: center; gap: .25rem; padding: .25rem .55rem; border: 1px solid #ddd6fe; border-radius: 999px; font-size: .72rem; font-weight: 600; background: #f5f3ff; color: #6d28d9; cursor: pointer; transition: all .15s; }
    .applicant-chip:hover { background: #ede9fe; border-color: #c4b5fd; }
    .applicant-chip.saving { opacity: .6; pointer-events: none; }

    .decliner-chip { display: inline-flex; align-items: center; gap: .25rem; padding: .2rem .5rem; border: 1px solid #fecaca; border-radius: 999px; font-size: .68rem; font-weight: 600; background: #fef2f2; color: #b91c1c; text-decoration: line-through; }
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;flex-wrap:wrap;gap:.75rem;">
    <h2 style="margin:0;font-size:1.25rem;font-weight:700;color:#1e293b;">👥 Personeel inplannen</h2>
    <div style="display:flex;gap:.5rem;">
        <a href="{{ route('staff.index') }}" class="btn btn-secondary btn-sm">Medewerkers beheren</a>
        <a href="{{ route('bookings.planning') }}" class="btn btn-secondary btn-sm">Gantt planning</a>
    </div>
</div>

@if(!$staffMembers->isEmpty() && !$bookings->isEmpty())
<div class="card neu-card" style="padding:1rem 1.25rem;margin-bottom:1rem;display:flex;flex-wrap:wrap;gap:.75rem;align-items:center;justify-content:space-between;">
    <div style="font-size:.85rem;color:#475569;line-height:1.5;flex:1;min-width:240px;">
        <strong style="color:#1e293b;">🔓 Open ritten voor aanmelding</strong><br>
        Zet ritten open, dan kunnen medewerkers zich aanmelden. Jij ziet de aanmeldingen hieronder en wijst toe.
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
        <form method="POST" action="{{ route('bookings.open-all-rides') }}"
              onsubmit="return confirm('Alle nog niet-ingeplande ritten openzetten? Alle actieve medewerkers krijgen een mail.');">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm">🔓 Open alle vrije ritten</button>
        </form>
        <button type="button" class="btn btn-secondary btn-sm" onclick="copyBoardLink(this)">📋 Kopieer WhatsApp-link</button>
        <input type="text" id="board-url" value="{{ $boardUrl }}" readonly
               style="position:absolute;left:-9999px;" aria-hidden="true">
    </div>
</div>
@endif

@if($staffMembers->isEmpty())
<div class="card neu-card" style="text-align:center;padding:2.5rem 1.5rem;">
    <p style="font-size:1.5rem;margin-bottom:.5rem;">👤</p>
    <p style="font-weight:600;margin-bottom:.5rem;">Nog geen medewerkers</p>
    <p style="color:#64748b;font-size:.875rem;margin-bottom:1.25rem;">Voeg eerst medewerkers toe voordat je ze kunt inplannen.</p>
    <a href="{{ route('staff.create') }}" class="btn btn-primary" style="display:inline-flex;">+ Medewerker toevoegen</a>
</div>
@elseif($bookings->isEmpty())
<div class="card neu-card" style="text-align:center;padding:2.5rem 1.5rem;">
    <p style="font-size:1.5rem;margin-bottom:.5rem;">📭</p>
    <p style="font-weight:600;margin-bottom:.5rem;">Geen aankomende boekingen</p>
    <p style="color:#64748b;font-size:.875rem;">Er zijn geen bevestigde boekingen gevonden.</p>
</div>
@else

@php
    // Bouw een platte lijst van "ritten" (bezorging + ophaling per boeking)
    $ritten = collect();
    foreach ($bookings as $b) {
        $isToGo = $b->booking_type === 'to_go';

        // Rit 1: bezorging / afgifte
        $delivType = $isToGo ? 'handover' : 'delivery';
        $delivResp = $signups[$b->id . '|' . $delivType] ?? collect();
        $pickResp  = $signups[$b->id . '|pickup'] ?? collect();
        $delivDate = $b->delivery_at
            ? \Carbon\Carbon::parse($b->delivery_at)
            : ($isToGo && $b->customer_pickup_at
                ? \Carbon\Carbon::parse($b->customer_pickup_at)
                : $b->event_date->copy()->setTime(0,0));
        $ritten->push([
            'booking'    => $b,
            'type'       => $delivType,
            'datum'      => $delivDate,
            'field'      => 'delivery_staff_id',
            'current'    => $b->deliveryStaff,
            'open'       => (bool) $b->delivery_open,
            'applicants' => $delivResp->where('response', 'yes')->values(),
            'decliners'  => $delivResp->where('response', 'no')->values(),
        ]);

        // Rit 2: ophaling
        $pickDate = $isToGo
            ? ($b->customer_return_at ? \Carbon\Carbon::parse($b->customer_return_at) : $b->event_date->copy()->setTime(0,0))
            : ($b->pickup_at ? \Carbon\Carbon::parse($b->pickup_at) : $b->event_date->copy()->setTime(0,0));
        $ritten->push([
            'booking'    => $b,
            'type'       => 'pickup',
            'datum'      => $pickDate,
            'field'      => 'pickup_staff_id',
            'current'    => $b->pickupStaff,
            'open'       => (bool) $b->pickup_open,
            'applicants' => $pickResp->where('response', 'yes')->values(),
            'decliners'  => $pickResp->where('response', 'no')->values(),
        ]);
    }
    $ritten = $ritten->sortBy('datum')->values();
@endphp

<div class="filter-bar">
    <span style="font-size:.8rem;color:#64748b;font-weight:600;">Toon:</span>
    <button class="filter-btn active" id="filter-all" onclick="setFilter('all')">Alles ({{ $ritten->count() }})</button>
    <button class="filter-btn" id="filter-empty" onclick="setFilter('empty')">
        Nog niet ingepland ({{ $ritten->filter(fn($r) => !$r['current'])->count() }})
    </button>
</div>

<div class="card neu-card" style="padding:0;overflow:hidden;">
    <table class="sp-table" id="sp-table">
        <thead>
            <tr>
                <th>Boeking</th>
                <th>Klant</th>
                <th>Locatie</th>
                <th>Evenement</th>
                <th class="role-col">Rit</th>
                <th>Datum & tijd</th>
                <th style="min-width:180px;">Medewerker</th>
            </tr>
        </thead>
        <tbody>
            @php $lastMonth = null; @endphp
            @foreach($ritten as $rit)
            @php
                $b      = $rit['booking'];
                $datum  = $rit['datum'];
                $month  = $datum->format('Y-m');
                $hasStaff = (bool) $rit['current'];
            @endphp

            @if($month !== $lastMonth)
            @php $lastMonth = $month; @endphp
            <tr class="month-header" data-month-header="{{ $month }}">
                <td colspan="7">{{ $datum->translatedFormat('F Y') }}</td>
            </tr>
            @endif

            <tr data-has-staff="{{ $hasStaff ? '1' : '0' }}" data-month="{{ $month }}"
                class="{{ !$hasStaff ? 'no-staff-row' : '' }}"
                style="{{ $b->booking_type === 'to_go' ? 'border-left:3px solid #7c3aed;' : 'border-left:3px solid #3b82f6;' }}">
                <td>
                    @if($b->booking_type === 'to_go')
                        <span style="display:inline-flex;align-items:center;gap:.3rem;font-size:.65rem;font-weight:700;background:#ede9fe;color:#6d28d9;padding:.15rem .45rem;border-radius:999px;margin-bottom:.25rem;">🏠 To Go</span>
                    @else
                        <span style="display:inline-flex;align-items:center;gap:.3rem;font-size:.65rem;font-weight:700;background:#dbeafe;color:#1d4ed8;padding:.15rem .45rem;border-radius:999px;margin-bottom:.25rem;">🚚 Full Service</span>
                    @endif
                    <div class="booking-num">
                        <a href="{{ route('bookings.show', $b) }}" style="color:inherit;text-decoration:none;" title="Boeking openen">{{ $b->booking_number }}</a>
                    </div>
                </td>
                <td>
                    <div class="booking-name" style="font-weight:600;">{{ $b->customer_name }}</div>
                </td>
                <td>
                    @php $loc = $b->event_city ?: ($b->event_location ?: '—'); @endphp
                    <div class="booking-loc" title="{{ $b->event_location }}{{ $b->event_address ? ', '.$b->event_address : '' }}">
                        {{ $loc }}
                    </div>
                </td>
                <td>
                    <div class="booking-date">{{ $b->event_date->translatedFormat('D j M') }}</div>
                    @if($b->event_start_time)
                    <div class="booking-time">{{ substr($b->event_start_time,0,5) }}@if($b->event_end_time)–{{ substr($b->event_end_time,0,5) }}@endif</div>
                    @endif
                </td>
                <td class="role-col">
                    @if($rit['type'] === 'delivery')
                        <span class="role-badge badge-delivery">⬇ Bezorgen</span>
                    @elseif($rit['type'] === 'handover')
                        <span class="role-badge badge-handover">🤝 Afgeven</span>
                    @else
                        <span class="role-badge badge-pickup">⬆ Ophalen</span>
                    @endif
                </td>
                <td>
                    <div class="booking-date">{{ $datum->translatedFormat('D j M') }}</div>
                    @if($datum->format('H:i') !== '00:00')
                    <div class="booking-time">{{ $datum->format('H:i') }}</div>
                    @endif
                </td>
                <td>
                    <div style="display:flex;flex-direction:column;gap:.45rem;">
                        <div style="display:flex;align-items:center;gap:.5rem;">
                            <span class="status-dot {{ $hasStaff ? 'dot-filled' : 'dot-empty' }}"
                                  id="dot-{{ $b->id }}-{{ $rit['field'] }}"></span>
                            <select class="staff-select"
                                    data-booking="{{ $b->id }}"
                                    data-field="{{ $rit['field'] }}"
                                    onchange="assignStaff(this)">
                                <option value="">— Niemand —</option>
                                @foreach($staffMembers as $person)
                                <option value="{{ $person->id }}"
                                    {{ $rit['current']?->id == $person->id ? 'selected' : '' }}>
                                    {{ $person->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="assign-extra" style="{{ $hasStaff ? 'display:none;' : '' }}">
                            <div style="display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;">
                                <button type="button"
                                        class="open-toggle {{ $rit['open'] ? 'is-open' : '' }}"
                                        id="opentoggle-{{ $b->id }}-{{ $rit['field'] }}"
                                        data-booking="{{ $b->id }}"
                                        data-field="{{ $rit['field'] }}"
                                        onclick="toggleOpen(this)">
                                    {{ $rit['open'] ? '🔓 Open — sluiten' : '🔒 Openzetten' }}
                                </button>
                                @if($rit['applicants']->isNotEmpty())
                                <span style="font-size:.7rem;color:#64748b;">{{ $rit['applicants']->count() }} aanmelding{{ $rit['applicants']->count() === 1 ? '' : 'en' }}:</span>
                                @endif
                            </div>
                            @if($rit['applicants']->isNotEmpty())
                            <div style="display:flex;flex-wrap:wrap;gap:.3rem;margin-top:.35rem;">
                                @foreach($rit['applicants'] as $signup)
                                <button type="button" class="applicant-chip"
                                        onclick="assignFromChip(this)"
                                        data-booking="{{ $b->id }}"
                                        data-field="{{ $rit['field'] }}"
                                        data-staff="{{ $signup->staff_id }}"
                                        title="Wijs deze rit toe aan {{ $signup->staff?->name }}">
                                    🙋 {{ $signup->staff?->name }}
                                </button>
                                @endforeach
                            </div>
                            @endif
                            @if($rit['decliners']->isNotEmpty())
                            <div style="display:flex;flex-wrap:wrap;gap:.3rem;margin-top:.3rem;align-items:center;">
                                <span style="font-size:.68rem;color:#94a3b8;">Kan niet:</span>
                                @foreach($rit['decliners'] as $signup)
                                <span class="decliner-chip" title="{{ $signup->staff?->name }} kan deze rit niet">🚫 {{ $signup->staff?->name }}</span>
                                @endforeach
                            </div>
                            @endif
                        </div>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

@push('scripts')
<script>
const CSRF = '{{ csrf_token() }}';

async function assignStaff(select) {
    const bookingId = select.dataset.booking;
    const field     = select.dataset.field;
    const staffId   = select.value;
    const dot       = document.getElementById('dot-' + bookingId + '-' + field);
    const row       = select.closest('tr');

    select.classList.add('saving');

    try {
        const res = await fetch(`/bookings/${bookingId}/assign-staff`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ field, staff_id: staffId || null }),
        });
        const json = await res.json();

        if (json.ok) {
            select.classList.remove('saving');
            select.classList.add('saved');
            setTimeout(() => select.classList.remove('saved'), 1200);

            // Dot updaten
            if (dot) {
                dot.className = 'status-dot ' + (staffId ? 'dot-filled' : 'dot-empty');
            }
            // Rij markering updaten
            row.dataset.hasStaff = staffId ? '1' : '0';
            row.classList.toggle('no-staff-row', !staffId);

            // Open/aanmeld-blok verbergen zodra toegewezen, weer tonen bij loskoppelen
            const extra = row.querySelector('.assign-extra');
            if (extra) extra.style.display = staffId ? 'none' : '';

            // Filter hertoepassen
            applyCurrentFilter();
        }
    } catch(e) {
        select.classList.remove('saving');
        alert('Fout bij opslaan, probeer opnieuw.');
    }
}

async function toggleOpen(btn) {
    const bookingId = btn.dataset.booking;
    const field     = btn.dataset.field;
    const willOpen  = !btn.classList.contains('is-open');

    btn.classList.add('saving');

    try {
        const res = await fetch(`/bookings/${bookingId}/toggle-ride-open`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ field, open: willOpen }),
        });
        const json = await res.json();
        btn.classList.remove('saving');

        if (json.ok) {
            btn.classList.toggle('is-open', json.open);
            btn.innerHTML = json.open ? '🔓 Open — sluiten' : '🔒 Openzetten';
        } else {
            alert(json.error || 'Kon de rit niet openzetten.');
        }
    } catch(e) {
        btn.classList.remove('saving');
        alert('Fout bij opslaan, probeer opnieuw.');
    }
}

function assignFromChip(chip) {
    const row    = chip.closest('tr');
    const select = row.querySelector('.staff-select');
    if (!select) return;
    chip.classList.add('saving');
    select.value = chip.dataset.staff;
    assignStaff(select);
}

function copyBoardLink(btn) {
    const input = document.getElementById('board-url');
    if (!input) return;
    navigator.clipboard.writeText(input.value).then(() => {
        const original = btn.innerHTML;
        btn.innerHTML = '✅ Gekopieerd!';
        setTimeout(() => { btn.innerHTML = original; }, 1500);
    }).catch(() => {
        // Fallback: selecteer + execCommand
        input.style.position = 'static';
        input.select();
        document.execCommand('copy');
        input.style.position = 'absolute';
        const original = btn.innerHTML;
        btn.innerHTML = '✅ Gekopieerd!';
        setTimeout(() => { btn.innerHTML = original; }, 1500);
    });
}

let currentFilter = 'all';

function setFilter(filter) {
    currentFilter = filter;
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('filter-' + filter).classList.add('active');
    applyCurrentFilter();
}

function applyCurrentFilter() {
    const rows       = document.querySelectorAll('#sp-table tbody tr:not(.month-header)');
    const monthRows  = document.querySelectorAll('#sp-table tbody tr.month-header');

    rows.forEach(row => {
        if (currentFilter === 'empty') {
            row.style.display = row.dataset.hasStaff === '0' ? '' : 'none';
        } else {
            row.style.display = '';
        }
    });

    // Verberg maandheaders als alle rijen in die maand verborgen zijn
    monthRows.forEach(mh => {
        const month    = mh.dataset.monthHeader;
        const visible  = [...rows].some(r => r.dataset.month === month && r.style.display !== 'none');
        mh.style.display = visible ? '' : 'none';
    });
}
</script>
@endpush
@endsection
