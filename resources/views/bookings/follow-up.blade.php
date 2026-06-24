@extends('layouts.app')
@section('title', 'Follow Up')

@section('actions')
    <a href="{{ route('bookings.create') }}" class="btn btn-primary">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Nieuwe boeking
    </a>
@endsection

@section('content')
<div style="padding:0 1.5rem;">
    @include('bookings.tabs')
</div>

<div class="card neu-card" style="margin-top:1.5rem;">
    <div style="padding:1.25rem;border-bottom:1px solid var(--gray-100);display:flex;align-items:center;justify-content:space-between;">
        <div>
            <h2 style="font-size:1rem;font-weight:700;margin:0;">📸 Follow Up — Galerij versturen</h2>
            <p style="font-size:.8rem;color:#64748b;margin:.25rem 0 0;">Events die voorbij zijn maar nog geen galerij link hebben. Vul de link in om direct de mail te versturen.</p>
        </div>
        <span style="background:#fef9c3;color:#854d0e;font-size:.75rem;font-weight:600;padding:.25rem .6rem;border-radius:1rem;border:1px solid #fde68a;">
            {{ $bookings->count() }} openstaand
        </span>
    </div>

    @if($bookings->isEmpty())
        <div style="padding:3rem;text-align:center;color:#94a3b8;">
            <div style="font-size:2rem;margin-bottom:.5rem;">🎉</div>
            <div style="font-weight:600;">Alles bijgewerkt!</div>
            <div style="font-size:.875rem;margin-top:.25rem;">Alle afgelopen events hebben een galerij link.</div>
        </div>
    @else
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nummer</th>
                    <th>Klant</th>
                    <th>Event datum</th>
                    <th>Locatie</th>
                    <th>Account</th>
                    <th>Galerij link invullen</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="follow-up-list">
                @foreach($bookings as $boeking)
                <tr id="row-{{ $boeking->id }}">
                    <td>
                        <a href="{{ route('bookings.show', $boeking) }}" style="font-weight:600;color:#2563eb;font-size:.8rem;">
                            {{ $boeking->booking_number }}
                        </a>
                    </td>
                    <td style="font-size:.875rem;">{{ $boeking->customer_name }}</td>
                    <td style="font-size:.875rem;white-space:nowrap;">
                        @php $dagen = now()->diffInDays($boeking->event_date, false) * -1; @endphp
                        {{ $boeking->event_date->format('d M Y') }}
                        <div style="font-size:.7rem;color:{{ $dagen > 14 ? '#dc2626' : '#d97706' }};">{{ $dagen }} dagen geleden</div>
                    </td>
                    <td style="font-size:.875rem;">{{ $boeking->event_location ?: '—' }}</td>
                    <td style="font-size:.75rem;color:#64748b;">{{ $boeking->account->name ?? '—' }}</td>
                    <td>
                        <div style="display:flex;gap:.5rem;align-items:center;">
                            <input type="url"
                                id="gallery-input-{{ $boeking->id }}"
                                placeholder="https://galerij.flitsmoment.nl/..."
                                style="flex:1;min-width:260px;font-size:.8rem;padding:.375rem .625rem;border:1px solid #e2e8f0;border-radius:.375rem;"
                                onkeydown="if(event.key==='Enter') saveGallery({{ $boeking->id }})">
                        </div>
                        <div id="msg-{{ $boeking->id }}" style="font-size:.72rem;margin-top:.2rem;display:none;"></div>
                    </td>
                    <td>
                        <div style="display:flex;gap:.4rem;align-items:center;">
                            <button onclick="saveGallery({{ $boeking->id }})"
                                id="btn-{{ $boeking->id }}"
                                class="btn btn-primary btn-sm"
                                style="white-space:nowrap;font-size:.75rem;">
                                📤 Verstuur mail
                            </button>
                            <button onclick="skipGallery({{ $boeking->id }})"
                                id="skip-{{ $boeking->id }}"
                                class="btn btn-secondary btn-sm"
                                style="white-space:nowrap;font-size:.75rem;color:#64748b;"
                                title="Verbergen zonder galerij te versturen">
                                Overslaan
                            </button>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

<script>
function saveGallery(bookingId) {
    const input = document.getElementById('gallery-input-' + bookingId);
    const btn   = document.getElementById('btn-' + bookingId);
    const msg   = document.getElementById('msg-' + bookingId);
    const url   = input.value.trim();

    if (!url) {
        input.style.borderColor = '#dc2626';
        input.focus();
        return;
    }
    input.style.borderColor = '#e2e8f0';

    btn.disabled = true;
    btn.textContent = '⏳ Bezig...';
    msg.style.display = 'none';

    fetch('/bookings/' + bookingId + '/gallery', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
        },
        body: JSON.stringify({ gallery_url: url }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Rij groen maken en na 1.5s verwijderen
            const row = document.getElementById('row-' + bookingId);
            row.style.background = '#f0fdf4';
            msg.style.display = 'block';
            msg.style.color = '#16a34a';
            msg.textContent = '✓ ' + data.message;
            btn.textContent = '✓ Verstuurd';
            btn.style.background = '#16a34a';
            input.disabled = true;

            // Branded deellink tonen
            if (data.share_url) {
                const shareDiv = document.createElement('div');
                shareDiv.style.cssText = 'margin-top:.4rem;font-size:.8rem;color:#065f46;';
                shareDiv.innerHTML = '🔗 <a href="' + data.share_url + '" target="_blank" style="color:#065f46;font-weight:600;">' + data.share_url + '</a>';
                msg.after(shareDiv);
            }

            setTimeout(() => {
                row.style.transition = 'opacity .4s';
                row.style.opacity = '0';
                setTimeout(() => {
                    row.remove();
                    // Update badge teller
                    const remaining = document.querySelectorAll('#follow-up-list tr').length;
                    const badge = document.querySelector('[data-follow-badge]');
                    if (badge) badge.textContent = remaining + ' openstaand';
                    if (remaining === 0) location.reload();
                }, 400);
            }, 1500);
        } else {
            msg.style.display = 'block';
            msg.style.color = '#dc2626';
            msg.textContent = '✗ ' + (data.message || 'Er is iets misgegaan.');
            btn.disabled = false;
            btn.textContent = '📤 Verstuur mail';
        }
    })
    .catch(() => {
        msg.style.display = 'block';
        msg.style.color = '#dc2626';
        msg.textContent = '✗ Netwerkfout, probeer opnieuw.';
        btn.disabled = false;
        btn.textContent = '📤 Verstuur mail';
    });
}

function skipGallery(bookingId) {
    crmConfirm('Boeking overslaan? Hij verdwijnt uit deze lijst zonder dat er een galerij verstuurd wordt.', () => {

    const btn = document.getElementById('skip-' + bookingId);
    btn.disabled = true;
    btn.textContent = '⏳';

    fetch('/bookings/' + bookingId + '/gallery-overslaan', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
        },
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const row = document.getElementById('row-' + bookingId);
            row.style.transition = 'opacity .3s';
            row.style.opacity = '0';
            setTimeout(() => {
                row.remove();
                if (document.querySelectorAll('#follow-up-list tr').length === 0) location.reload();
            }, 300);
        }
    });

    }); // end crmConfirm
}
</script>
@endsection
