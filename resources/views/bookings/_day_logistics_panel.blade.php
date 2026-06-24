{{--
  Right-side overzichtspaneel: andere bezorg/ophaal-momenten op een specifieke dag.

  Parameters:
    $panelId      - uniek id ('event', 'pickup', etc.) voor scoping van elementen + JS
    $title        - paneel-titel (bv. "📅 Andere ritten op de eventdatum")
    $watchNames   - array van input-name-attributen om naar te luisteren (eerste niet-lege wint)
    $excludeId    - booking_id om te negeren (de huidige boeking, of 0)
    $initialDate  - Y-m-d datum bij eerste render (of null = nog niets)
--}}
@php
    $panelId     = $panelId     ?? 'default';
    $title       = $title       ?? '📅 Andere ritten deze dag';
    $watchNames  = $watchNames  ?? ['event_date'];
    $excludeId   = $excludeId   ?? 0;
    $initialDate = $initialDate ?? null;
@endphp

@once
<style>
.day-logistics-panel {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 1rem;
    padding: 1.1rem 1.15rem; box-shadow: -1px -1px 3px rgba(255,255,255,0.8), 2px 2px 5px rgba(0,0,0,0.06);
}
.day-logistics-stack { position: sticky; top: 1rem; display: flex; flex-direction: column; gap: 1rem; }
.dlp-title { font-size: .8rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: .4rem; margin: 0 0 .25rem; }
.dlp-date { font-size: .82rem; color: #64748b; margin-bottom: .85rem; }
.dlp-empty { font-size: .82rem; color: #94a3b8; font-style: italic; padding: 1rem .25rem; text-align: center; border: 1px dashed #e2e8f0; border-radius: .5rem; }
.dlp-list { display: flex; flex-direction: column; gap: .55rem; }
.dlp-item {
    background: #f8fafc; border: 1px solid #e2e8f0; border-left: 3px solid #94a3b8;
    border-radius: .55rem; padding: .55rem .7rem;
}
.dlp-item.type-delivery        { border-left-color: #2563eb; }
.dlp-item.type-pickup          { border-left-color: #16a34a; }
.dlp-item.type-customer_pickup { border-left-color: #d97706; }
.dlp-item.type-customer_return { border-left-color: #7c3aed; }
.dlp-line1 { display: flex; align-items: center; gap: .35rem; flex-wrap: wrap; font-size: .82rem; }
.dlp-time { font-weight: 700; color: #0f172a; }
.dlp-label { color: #475569; font-weight: 600; font-size: .72rem; background: #fff; padding: .05rem .4rem; border-radius: 999px; border: 1px solid #e2e8f0; }
.dlp-bnr { color: #2563eb; font-weight: 600; text-decoration: none; font-size: .72rem; }
.dlp-bnr:hover { text-decoration: underline; }
.dlp-customer { font-size: .8rem; color: #1e293b; margin-top: .2rem; }
.dlp-address { font-size: .72rem; color: #64748b; margin-top: .15rem; }
.dlp-maps { color: #2563eb; text-decoration: none; font-weight: 600; white-space: nowrap; }
.dlp-maps:hover { text-decoration: underline; }
.dlp-loading { font-size: .8rem; color: #94a3b8; padding: 1rem; text-align: center; }
.dlp-no-date { font-size: .8rem; color: #94a3b8; padding: 1rem .25rem; text-align: center; font-style: italic; }
@media (max-width: 980px) {
    .day-logistics-stack { position: static; margin-top: 1rem; }
}
</style>
@endonce

<div class="day-logistics-panel" id="dlp-{{ $panelId }}"
     data-exclude-id="{{ $excludeId }}"
     data-initial-date="{{ $initialDate }}"
     data-watch="{{ implode(',', $watchNames) }}">
    <div class="dlp-title">{!! $title !!}</div>
    <div class="dlp-date" id="dlp-{{ $panelId }}-date">
        @if($initialDate)
            {{ \Carbon\Carbon::parse($initialDate)->translatedFormat('l j F Y') }}
        @else
            Nog geen datum gekozen
        @endif
    </div>
    <div id="dlp-{{ $panelId }}-content">
        @if($initialDate)
            <div class="dlp-loading">Laden…</div>
        @else
            <div class="dlp-no-date">Vul een datum in om andere ritten van die dag te zien.</div>
        @endif
    </div>
</div>

<script>
(function() {
    const panelId   = @json($panelId);
    const watchNames= @json($watchNames);
    const url       = @json(route('bookings.day-logistics'));
    const panel     = document.getElementById('dlp-' + panelId);
    const dateLabel = document.getElementById('dlp-' + panelId + '-date');
    const content   = document.getElementById('dlp-' + panelId + '-content');
    if (!panel) return;

    const excludeId = parseInt(panel.dataset.excludeId || '0', 10);
    const inputs = watchNames
        .map(n => document.querySelector('input[name="' + n + '"]'))
        .filter(Boolean);
    if (inputs.length === 0) return;

    const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[c]));

    const extractDate = (raw) => {
        if (!raw) return '';
        const m = String(raw).match(/^(\d{4}-\d{2}-\d{2})/);
        return m ? m[1] : '';
    };

    // Eerst niet-lege input wint (gewenste prioriteit-volgorde via $watchNames)
    const currentDate = () => {
        for (const el of inputs) {
            const d = extractDate(el.value);
            if (d) return d;
        }
        return '';
    };

    const formatDutchDate = (iso) => {
        const d = new Date(iso + 'T00:00:00');
        return d.toLocaleDateString('nl-NL', { weekday:'long', day:'numeric', month:'long', year:'numeric' });
    };

    let lastDate = panel.dataset.initialDate || '';
    let pending  = null;

    function render(data) {
        dateLabel.textContent = formatDutchDate(data.date);
        if (data.count === 0) {
            content.innerHTML = '<div class="dlp-empty">✨ Geen andere ritten ingepland deze dag.</div>';
            return;
        }
        const rows = data.moments.map(m => {
            const mapsHref = m.address ? 'https://maps.google.com/?q=' + encodeURIComponent(m.address) : null;
            const bookingHref = '/bookings/' + m.id;
            const addr = m.address
                ? `<div class="dlp-address">📍 ${escapeHtml(m.address)} ${mapsHref ? '· <a href="'+mapsHref+'" target="_blank" rel="noopener" class="dlp-maps">Maps</a>' : ''}</div>`
                : '';
            return `
                <div class="dlp-item type-${m.type}">
                    <div class="dlp-line1">
                        <span>${m.icon}</span>
                        <span class="dlp-time">${escapeHtml(m.time)}</span>
                        <span class="dlp-label">${escapeHtml(m.label)}</span>
                        <a class="dlp-bnr" href="${bookingHref}" target="_blank">${escapeHtml(m.booking_number)}</a>
                    </div>
                    <div class="dlp-customer">${escapeHtml(m.customer_name)}</div>
                    ${addr}
                </div>
            `;
        }).join('');
        content.innerHTML = '<div class="dlp-list">' + rows + '</div>';
    }

    async function fetchFor(date) {
        if (!date) {
            content.innerHTML = '<div class="dlp-no-date">Vul een datum in om andere ritten van die dag te zien.</div>';
            dateLabel.textContent = 'Nog geen datum gekozen';
            return;
        }
        content.innerHTML = '<div class="dlp-loading">Laden…</div>';
        if (pending) pending.abort();
        pending = new AbortController();
        try {
            const r = await fetch(`${url}?date=${encodeURIComponent(date)}&exclude_id=${excludeId}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: pending.signal,
            });
            if (!r.ok) throw new Error('Fout');
            const data = await r.json();
            render(data);
        } catch (e) {
            if (e.name !== 'AbortError') {
                content.innerHTML = '<div class="dlp-empty">⚠️ Laden mislukt.</div>';
            }
        }
    }

    // Initial load
    if (lastDate) fetchFor(lastDate);

    // Listen — recalc na elke change/input op één van de inputs
    function maybeUpdate() {
        const d = currentDate();
        if (d !== lastDate) { lastDate = d; fetchFor(d); }
    }
    inputs.forEach(el => {
        el.addEventListener('change', maybeUpdate);
        el.addEventListener('input',  maybeUpdate);
    });
})();
</script>
