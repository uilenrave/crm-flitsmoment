@extends('layouts.app')
@section('title', $pageTitle ?? 'Boekingen')

@push('modals')
{{-- Één lazy modal voor ontwerp beheren --}}
<div id="modal-design-lazy" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.45);align-items:flex-start;justify-content:center;overflow-y:auto;padding:2rem 1rem;">
    <div style="background:#fff;border-radius:12px;padding:1.75rem;width:100%;max-width:520px;box-shadow:0 20px 60px rgba(0,0,0,.2);position:relative;margin:auto;">
        <button onclick="closeDesignModal()" style="position:absolute;top:1rem;right:1rem;background:none;border:none;font-size:1.25rem;cursor:pointer;color:#64748b;">✕</button>
        <div id="modal-design-body">
            <div style="text-align:center;padding:2rem 0;color:#94a3b8;">Laden…</div>
        </div>
    </div>
</div>

<script>
let currentModalId = null;

function openDesignModal(bookingId) {
    currentModalId = bookingId;
    const modal = document.getElementById('modal-design-lazy');
    const body  = document.getElementById('modal-design-body');
    body.innerHTML = '<div style="text-align:center;padding:2rem 0;color:#94a3b8;">Laden…</div>';
    modal.style.display = 'flex';

    fetch('/bookings/' + bookingId + '/design-modal', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.text())
    .then(html => { body.innerHTML = html; })
    .catch(() => { body.innerHTML = '<p style="color:#dc2626;padding:1rem;">Fout bij laden.</p>'; });
}

function closeDesignModal() {
    document.getElementById('modal-design-lazy').style.display = 'none';
    currentModalId = null;
}

document.getElementById('modal-design-lazy').addEventListener('click', function(e) {
    if (e.target === this) closeDesignModal();
});

function handleDrop(event, id) {
    event.preventDefault();
    const dropzone = document.getElementById('dropzone-' + id);
    dropzone.style.borderColor = '#cbd5e1';
    dropzone.style.background  = '#f8fafc';
    const files = event.dataTransfer.files;
    if (files.length > 0) {
        const fileInput = document.getElementById('file-' + id);
        const dt = new DataTransfer();
        dt.items.add(files[0]);
        fileInput.files = dt.files;
        showFilename(id, fileInput);
    }
}

const STRIP_MAX_BYTES = 2 * 1024 * 1024; // 2 MB — serverlimiet

function showFilename(id, input) {
    const label = document.getElementById('filename-' + id);
    if (input.files && input.files[0]) {
        const f = input.files[0];
        if (f.size > STRIP_MAX_BYTES) {
            const mb = (f.size / 1024 / 1024).toFixed(1);
            label.innerHTML = '⚠️ Dit bestand is ' + mb + ' MB — maximaal 2 MB.'
                + '<br><span style="font-weight:400;color:#64748b;">Exporteer de strip wat kleiner (lagere kwaliteit of resolutie) en probeer opnieuw.</span>';
            label.style.color = '#dc2626';
            label.style.display = 'block';
            input.value = ''; // blokkeer de upload zodat de 413-foutpagina niet verschijnt
            return;
        }
        label.textContent = '✓ ' + f.name;
        label.style.color = '#f97316';
        label.style.display = 'block';
    }
}

// Strip status AJAX dropdown — waiting_input (legacy) blijft voor stale-tab veiligheid.
const stripColors = {
    awaiting_customer_design: { bg: '#fef3c7', text: '#92400e' },
    waiting_input:            { bg: '#fef3c7', text: '#92400e' },
    customer_self_designing:  { bg: '#e0e7ff', text: '#3730a3' },
    designing:                { bg: '#dbeafe', text: '#1e40af' },
    review:                   { bg: '#f3e8ff', text: '#6b21a8' },
    accepted:                 { bg: '#dcfce7', text: '#15803d' },
    ready:                    { bg: '#d1fae5', text: '#065f46' },
};

const paymentColors = {
    unpaid:    '#dc2626',
    partial:   '#d97706',
    paid:      '#16a34a',
    cancelled: '#737373',
    refunded:  '#ea580c',
};

function ajaxSelect(sel, bodyKey) {
    const url   = sel.dataset.url;
    const value = sel.value;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

    sel.disabled = true;
    sel.style.opacity = '.5';

    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        body: JSON.stringify({ [bodyKey]: value }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok || data.success) {
            if (bodyKey === 'payment_status') {
                const c = paymentColors[value] || '#737373';
                sel.style.background  = c + '20';
                sel.style.color       = c;
                sel.style.borderColor = c + '40';
                sel.dataset.saved = value; // bijwerken na succesvolle save
            } else {
                // Bij een geaccepteerd AI-ontwerp zet de server de status automatisch door naar
                // 'ready' (productie klaargezet); volg dan de teruggegeven eindstatus.
                const finalValue = data.status || value;
                if (data.status && data.status !== value) sel.value = data.status;
                const c = stripColors[finalValue] || { bg: '#f1f5f9', text: '#475569' };
                sel.style.background   = c.bg;
                sel.style.color        = c.text;
                sel.style.borderColor  = c.bg;
            }
        }
    })
    .catch(() => {})
    .finally(() => {
        sel.disabled = false;
        sel.style.opacity = '1';
    });
}

const betalingLabels = {
    unpaid: 'Niet betaald', partial: 'Gedeeltelijk', paid: 'Betaald',
    cancelled: 'Geannuleerd', refunded: 'Terugbetaald'
};

document.addEventListener('change', function(e) {
    const strip = e.target.closest('.strip-status-select');
    if (strip) { ajaxSelect(strip, 'strip_status'); return; }

    const payment = e.target.closest('.payment-status-select');
    if (payment) {
        const newLabel = betalingLabels[payment.value] || payment.value;
        const confirmedValue = payment.value;
        payment.value = payment.dataset.saved; // reset visueel tot bevestigd
        crmConfirm(`Betaalstatus wijzigen naar "${newLabel}"?`, () => {
            payment.value = confirmedValue;
            ajaxSelect(payment, 'payment_status');
        });
        return;
    }
});
</script>
@endpush

@section('actions')
    @if(auth()->user()->account->eboekhouden_enabled)
        <form method="POST" action="{{ route('bookings.sync-all-payments') }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn-secondary" title="Check betalingen">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582M20 20v-5h-.581M5.635 19A9 9 0 1 0 4.582 9"/></svg>
            </button>
        </form>
    @endif
    <a href="{{ route('bookings.create') }}" class="btn btn-primary">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Nieuwe boeking
    </a>
@endsection

@push('styles')
<style>
/* ── Booking row: desktop = uitgelijnde kolommen-grid ── */
.bk-row {
    display: grid;
    grid-template-columns: 135px minmax(170px, 1.05fr) minmax(210px, 1.5fr) minmax(130px, .9fr) 195px;
    gap: .4rem 1.25rem;
    padding: .9rem 1.25rem;
    border-bottom: 1px solid #f1f5f9;
    transition: background .15s;
    align-items: start;
    cursor: pointer;
}
.bk-row:hover { background: #fafaf9; }
.bk-row-inactief { opacity: .45; background: #fafafa; }
.bk-row-inactief:hover { opacity: .7; background: #f5f5f4; }
.bk-status-badge { display: inline-block; width: fit-content; font-size: .62rem; font-weight: 700; padding: .12rem .5rem; border-radius: 999px; margin-bottom: .1rem; }
.bk-cell { min-width: 0; display: flex; flex-direction: column; gap: .3rem; }

/* Kolom 1: wanneer */
.bk-date { font-weight: 700; font-size: .85rem; color: #1e293b; }
.bk-time { font-weight: 500; color: #64748b; font-size: .76rem; }
.bk-pill { display: inline-block; width: fit-content; font-size: .65rem; font-weight: 700; padding: .12rem .5rem; border-radius: 999px; }
.bk-pill.fs { background: #dbeafe; color: #1d4ed8; }
.bk-pill.tg { background: #ede9fe; color: #6d28d9; }
.bk-bnr { font-size: .7rem; font-weight: 700; color: #94a3b8; }

/* Kolom 2: wie */
.bk-customer { font-weight: 700; font-size: .9rem; color: #111827; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.bk-contact { display: flex; align-items: center; gap: .35rem; font-size: .76rem; color: #64748b; }
.bk-contact a { color: inherit; text-decoration: none; white-space: nowrap; }
.bk-contact a:hover { color: #1e293b; }
.bk-wa { display: inline-flex; align-items: center; justify-content: center; width: 1.2rem; height: 1.2rem; background: #25d366; border-radius: 50%; flex-shrink: 0; }
.bk-email { font-size: .76rem; color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* Kolom 3: waar + logistiek */
.bk-location { font-size: .78rem; color: #475569; line-height: 1.45; }
.bk-location strong { color: #1e293b; }
.bk-location .addr { color: #94a3b8; }
.bk-logi { font-size: .76rem; color: #475569; line-height: 1.5; }
.bk-logi strong { color: #1e293b; font-weight: 600; }

/* Kolom 4: wat */
.bk-chips { display: flex; gap: .3rem; flex-wrap: wrap; }
.bk-chip { display: inline-flex; align-items: center; gap: .2rem; font-size: .68rem; font-weight: 600; padding: .12rem .45rem; border-radius: .35rem; background: #f1f5f9; color: #374151; border: 1px solid #e2e8f0; }

/* Kolom 5: prijs + status + acties */
.bk-side { display: flex; flex-direction: column; gap: .4rem; }
.bk-price { font-size: 1rem; font-weight: 700; color: #111827; text-align: right; }
.bk-side select { font-size: .72rem; padding: .28rem .5rem; border-radius: 6px; cursor: pointer; font-weight: 600; width: 100%; }
.bk-actions { display: flex; gap: .3rem; flex-wrap: wrap; justify-content: flex-end; }

/* ── Mobiel: stapelen ── */
@media (max-width: 900px) {
    .bk-row { display: flex; flex-direction: column; gap: .55rem; padding: .9rem 1rem; }
    .bk-price { text-align: left; }
    .bk-actions { justify-content: flex-start; }
}
</style>
@endpush

@section('content')
<div style="padding:0 1.5rem;">
    {{-- Tabs --}}
    @include('bookings.tabs')
</div>

<div class="card neu-card" style="margin-top: 1.5rem;">
    {{-- Search & Filter --}}
    @php $baseRoute = request()->routeIs('bookings.archive') ? route('bookings.archive') : route('bookings.index'); @endphp
    <form method="GET" style="display:flex;gap:0.75rem;margin-bottom:1rem;flex-wrap:wrap;padding:1.25rem;border-bottom:1px solid #f1f5f9;align-items:center;">
        <input type="text" name="zoeken" value="{{ request('zoeken') }}" placeholder="Zoeken op naam, e-mail of nummer..." style="flex:1;min-width:0;">
        <button type="submit" class="btn btn-primary btn-sm">Zoeken</button>
        @if(request()->hasAny(['zoeken','betaling']))
            <a href="{{ $baseRoute }}" class="btn btn-secondary btn-sm">Wis filters</a>
        @endif
        <div style="margin-left:auto;">
            @if(request('betaling') === 'openstaand')
                <a href="{{ $baseRoute }}" class="btn btn-sm" style="background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;font-weight:600;">💰 Openstaand ✕</a>
            @else
                <a href="{{ $baseRoute }}?betaling=openstaand" class="btn btn-sm" style="background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;font-weight:600;">💰 Openstaand</a>
            @endif
        </div>
    </form>

    {{-- Booking card rows --}}
    @php
        $betalingKleuren = ['unpaid'=>'#dc2626','partial'=>'#d97706','paid'=>'#16a34a','cancelled'=>'#737373','refunded'=>'#ea580c'];
        $betalingLabels  = ['unpaid'=>'Niet betaald','paid'=>'Betaald'];
        $stripKleuren    = \App\Models\Booking::STRIP_STATUS_COLORS;
        $stripLabels     = \App\Models\Booking::STRIP_STATUS_LABELS;
        $catIcons        = ['photobooth'=>'📸','background'=>'🖼️','prop_box'=>'🎩','extra'=>'✨'];
    @endphp

    <div>
        @forelse($bookings as $boeking)
        @php
            $currentStrip = $boeking->strip_status === 'waiting_input' ? 'awaiting_customer_design' : ($boeking->strip_status ?? null);
            $bk = $betalingKleuren[$boeking->payment_status] ?? '#737373';
            $sk = $currentStrip ? ($stripKleuren[$currentStrip] ?? ['bg'=>'#f1f5f9','text'=>'#475569']) : null;

            // Geannuleerd / no-show: rij vervagen + badge tonen (assets + agenda zijn al uitgesloten in de backend)
            $inactief    = in_array($boeking->status, ['cancelled', 'no_show']);
            $statusBadge = match($boeking->status) {
                'cancelled' => ['txt' => '❌ Geannuleerd', 'bg' => '#fee2e2', 'fg' => '#b91c1c'],
                'no_show'   => ['txt' => '🚫 No-show',     'bg' => '#fef3c7', 'fg' => '#92400e'],
                default     => null,
            };

            // Groepeer items op categorie
            $itemGroups = $boeking->items->filter(fn($i) => $i->asset)->groupBy(fn($i) => $i->asset->category);

            // Bezorg/ophaalinfo
            $isToGo = $boeking->booking_type === 'to_go';
            $deliverAt  = $isToGo ? $boeking->customer_pickup_at : $boeking->delivery_at;
            $pickupAt   = $isToGo ? $boeking->customer_return_at : $boeking->pickup_at;

            // Volledig adres
            $adresDelen = array_filter([$boeking->event_address, $boeking->event_postcode, $boeking->event_city]);
            $volledigAdres = implode(', ', $adresDelen);
        @endphp
        <div class="bk-row{{ $inactief ? ' bk-row-inactief' : '' }}" data-href="{{ route('bookings.show', $boeking) }}">

            {{-- Kolom 1: wanneer --}}
            <div class="bk-cell">
                @if($statusBadge)
                    <span class="bk-status-badge" style="background:{{ $statusBadge['bg'] }};color:{{ $statusBadge['fg'] }};">{{ $statusBadge['txt'] }}</span>
                @endif
                <span class="bk-date">{{ $boeking->event_date?->isoFormat('ddd D MMM YYYY') }}</span>
                @if($boeking->event_start_time)
                    <span class="bk-time">🕐 {{ substr($boeking->event_start_time,0,5) }}{{ $boeking->event_end_time ? '–'.substr($boeking->event_end_time,0,5) : '' }}</span>
                @endif
                @if($isToGo)
                    <span class="bk-pill tg">🏠 To Go</span>
                @else
                    <span class="bk-pill fs">🚚 Full Service</span>
                @endif
                <span class="bk-bnr">{{ $boeking->booking_number }}</span>
            </div>

            {{-- Kolom 2: wie --}}
            <div class="bk-cell">
                <span class="bk-customer">{{ $boeking->customer_name }}</span>
                @if($boeking->customer_phone)
                    @php
                        // Normaliseer naar internationaal formaat zonder + (vereist door wa.me)
                        $waNr = preg_replace('/[^0-9+]/', '', $boeking->customer_phone);
                        $waNr = ltrim($waNr, '+');
                        if (str_starts_with($waNr, '00')) $waNr = substr($waNr, 2);
                        if (str_starts_with($waNr, '0'))  $waNr = '31' . substr($waNr, 1);
                    @endphp
                    <span class="bk-contact">
                        <a href="tel:{{ $boeking->customer_phone }}">📞 {{ $boeking->customer_phone }}</a>
                        @if($waNr)
                        <a href="https://wa.me/{{ $waNr }}" target="_blank" rel="noopener" class="bk-wa" title="Open WhatsApp">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="#fff"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.86 9.86 0 0 0 4.74 1.21h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.91-7.02A9.87 9.87 0 0 0 12.04 2zm0 18.15h-.01a8.23 8.23 0 0 1-4.19-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.18 8.18 0 0 1-1.26-4.38c0-4.54 3.7-8.23 8.24-8.23 2.2 0 4.27.86 5.82 2.42a8.19 8.19 0 0 1 2.41 5.83c0 4.54-3.7 8.22-8.22 8.22zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.16.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.12-1.04-.38-1.99-1.22-.74-.66-1.23-1.47-1.37-1.72-.14-.25-.02-.39.11-.51.11-.11.25-.29.37-.43.12-.14.16-.25.25-.41.08-.17.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.22.25-.86.84-.86 2.06s.88 2.39 1 2.55c.12.17 1.74 2.66 4.22 3.73.59.26 1.05.41 1.41.52.59.19 1.13.16 1.55.1.47-.07 1.47-.6 1.67-1.18.21-.58.21-1.07.14-1.17-.06-.11-.22-.17-.46-.29z"/></svg>
                        </a>
                        @endif
                    </span>
                @endif
                @if($boeking->customer_email)
                    <span class="bk-email">✉️ {{ $boeking->customer_email }}</span>
                @endif
            </div>

            {{-- Kolom 3: waar + logistiek --}}
            <div class="bk-cell">
                @if($boeking->event_location || $volledigAdres)
                <span class="bk-location">
                    📍
                    @if($boeking->event_location)<strong>{{ $boeking->event_location }}</strong>@endif
                    @if($volledigAdres) <span class="addr">{{ $volledigAdres }}</span>@endif
                </span>
                @endif
                @if($deliverAt)
                <span class="bk-logi">{{ $isToGo ? '🏠 Ophalen' : '🚚 Bezorging' }}: <strong>{{ \Carbon\Carbon::parse($deliverAt)->isoFormat('ddd D MMM, HH:mm') }}</strong></span>
                @endif
                @if($pickupAt)
                <span class="bk-logi">{{ $isToGo ? '↩️ Retour' : '↩️ Ophalen' }}: <strong>{{ \Carbon\Carbon::parse($pickupAt)->isoFormat('ddd D MMM, HH:mm') }}</strong></span>
                @endif
            </div>

            {{-- Kolom 4: wat --}}
            <div class="bk-cell">
                @if($itemGroups->isNotEmpty())
                <div class="bk-chips">
                    @foreach($itemGroups as $cat => $items)
                        @foreach($items as $item)
                        <span class="bk-chip">{{ $catIcons[$cat] ?? '📦' }} {{ $item->asset->name }}@if($item->quantity > 1) ×{{ $item->quantity }}@endif</span>
                        @endforeach
                    @endforeach
                </div>
                @endif
            </div>

            {{-- Kolom 5: prijs + status + acties --}}
            <div class="bk-side">

                <div class="bk-price">€ {{ number_format($boeking->total_price, 2, ',', '.') }}</div>

                <select class="payment-status-select"
                        data-id="{{ $boeking->id }}"
                        data-url="{{ route('bookings.payment-status', $boeking) }}"
                        data-saved="{{ $boeking->payment_status }}"
                        style="border:1px solid {{ $bk }}40;background:{{ $bk }}20;color:{{ $bk }};">
                    @foreach($betalingLabels as $val => $lbl)
                    <option value="{{ $val }}" @selected($boeking->payment_status === $val)>{{ $lbl }}</option>
                    @endforeach
                </select>

                @if($sk)
                <select class="strip-status-select"
                        data-id="{{ $boeking->id }}"
                        data-url="{{ route('bookings.strip-status', $boeking) }}"
                        style="border:1px solid {{ $sk['bg'] }};background:{{ $sk['bg'] }};color:{{ $sk['text'] }};">
                    @foreach($stripLabels as $val => $lbl)
                    <option value="{{ $val }}" @selected($currentStrip === $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
                @endif

                <div class="bk-actions">
                    @if($boeking->public_token)
                    <a href="{{ route('portal.show', $boeking->public_token) }}" target="_blank" class="btn btn-sm btn-secondary" title="Open klantportaal">🔗</a>
                    @endif
                    <button type="button" onclick="openDesignModal({{ $boeking->id }})" class="btn btn-sm btn-secondary" title="{{ $boeking->strip_design_url ? 'Ontwerp aanpassen' : 'Ontwerp toevoegen' }}" style="{{ $boeking->strip_design_url ? 'color:#f97316;' : '' }}">🎨</button>
                    @if($account->eboekhouden_enabled)
                        @if($boeking->eboekhouden_invoice_id || $boeking->eboekhouden_invoice_number)
                            <span class="btn btn-sm" style="cursor:default;background:#dcfce7;color:#15803d;font-size:.72rem;" title="Factuur {{ $boeking->eboekhouden_invoice_number }}">✅ {{ $boeking->eboekhouden_invoice_number }}</span>
                        @elseif(!$boeking->eboekhouden_skip_invoice)
                            <form method="POST" action="{{ route('bookings.create-invoice', $boeking) }}" style="display:inline;" data-confirm="Factuur aanmaken in e-boekhouden?">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-primary">📄</button>
                            </form>
                        @endif
                    @endif
                </div>

            </div>
        </div>
        @empty
        <div style="text-align:center;padding:3rem;color:#94a3b8;">Geen boekingen gevonden.</div>
        @endforelse
    </div>
    </div>

    {{-- Pagination --}}
    <div style="padding: 1.25rem; border-top: 1px solid var(--gray-100);">
        {{ $bookings->links() }}
    </div>
</div>

<script>
// Hele rij klikbaar — behalve links, knoppen, selects en formulieren
document.querySelectorAll('.bk-row[data-href]').forEach(row => {
    row.addEventListener('click', function (e) {
        if (e.target.closest('a, button, select, form, input')) return;
        if (e.metaKey || e.ctrlKey) {
            window.open(row.dataset.href, '_blank');
        } else {
            window.location = row.dataset.href;
        }
    });
});
</script>
@endsection
