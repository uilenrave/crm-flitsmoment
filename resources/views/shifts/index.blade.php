@extends('layouts.app')

@section('title', 'Shifts plannen')

@section('content')
<style>
    .shifts-top { display:flex; align-items:center; gap:1rem; flex-wrap:wrap; margin-bottom:1.25rem; }
    .shifts-top h1 { font-size:1.4rem; font-weight:800; margin:0; }
    .date-nav { display:flex; align-items:center; gap:.4rem; }
    .date-nav button { border:1px solid #e2e8f0; background:#fff; border-radius:.5rem; width:2rem; height:2rem; cursor:pointer; font-size:1rem; }
    .date-nav input[type=date] { border:1px solid #e2e8f0; border-radius:.5rem; padding:.35rem .6rem; font-size:.9rem; }

    /* Maandkalender */
    #calendar-wrap { margin-bottom:1.25rem; background:#fff; border:1px solid #e2e8f0; border-radius:.9rem; padding:.75rem .9rem; box-shadow:0 1px 3px rgba(0,0,0,.04); }
    .cal-head { display:flex; align-items:center; justify-content:center; gap:1rem; margin-bottom:.5rem; }
    .cal-head .cal-label { font-weight:800; font-size:1rem; min-width:9rem; text-align:center; }
    .cal-prev, .cal-next { border:1px solid #e2e8f0; background:#fff; border-radius:.5rem; width:1.9rem; height:1.9rem; cursor:pointer; font-size:1rem; }
    .cal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:.3rem; }
    .cal-wd { text-align:center; font-size:.68rem; font-weight:700; text-transform:uppercase; color:#94a3b8; padding-bottom:.15rem; }
    .cal-day { position:relative; min-height:clamp(48px, 9vh, 84px); border:1px solid #eef2f7; background:#fff; border-radius:.55rem; padding:.3rem .35rem; cursor:pointer; display:flex; flex-direction:column; align-items:flex-start; gap:.2rem; text-align:left; transition:border-color .12s, background .12s; }
    .cal-day:hover { border-color:#c4b5fd; background:#faf9ff; }
    .cal-day .cal-num { font-size:.8rem; font-weight:700; color:#334155; }
    .cal-out { background:#fafbfc; }
    .cal-out .cal-num { color:#cbd5e1; }
    .cal-today { border-color:#7c3aed; }
    .cal-sel { outline:2px solid #7c3aed; outline-offset:-1px; background:#f5f3ff; }
    .cal-hasopen { background:#fff7ed; border-color:#fed7aa; }
    .cal-hasopen.cal-sel { background:#fff1e0; }
    .cal-badges { display:flex; flex-direction:column; gap:.15rem; margin-top:auto; width:100%; }
    .cal-b { font-size:.66rem; font-weight:700; border-radius:.3rem; padding:.02rem .25rem; white-space:nowrap; }
    .cal-shift { background:#ecfeff; color:#0e7490; }
    .cal-open { background:#fee2e2; color:#b91c1c; }

    .sb-grid { display:grid; grid-template-columns:320px minmax(0,1fr); gap:1.25rem; align-items:start; }
    .sb-col { min-width:0; }
    @media (max-width:900px){ .sb-grid { grid-template-columns:1fr; } }
    .shift-card, .klus-card, .job-row { box-sizing:border-box; max-width:100%; }
    .shift-card input, .shift-card select { box-sizing:border-box; max-width:100%; }
    .jr-dur { width:3.4rem !important; flex:0 0 auto; }
    .sb-col-head { font-weight:800; font-size:.95rem; margin-bottom:.6rem; display:flex; align-items:center; gap:.5rem; }
    .sb-count { background:#eef2ff; color:#4338ca; border-radius:9999px; font-size:.72rem; font-weight:700; padding:.05rem .5rem; }
    .btn-new-shift { margin-left:auto; background:#7c3aed; color:#fff; border:none; border-radius:.5rem; padding:.4rem .8rem; font-weight:700; font-size:.82rem; cursor:pointer; }

    .droplist { display:flex; flex-direction:column; gap:.5rem; min-height:60px; }
    .sb-unplanned .droplist { background:#f8fafc; border:1px dashed #cbd5e1; border-radius:.75rem; padding:.6rem; min-height:120px; }
    .droplist-empty { color:#94a3b8; font-size:.82rem; font-style:italic; text-align:center; padding:1rem .25rem; }
    .droplist-empty.small { padding:.6rem; }

    .klus-card { background:#fff; border:1px solid #e2e8f0; border-left:4px solid #7c3aed; border-radius:.55rem; padding:.55rem .7rem; cursor:grab; box-shadow:0 1px 2px rgba(0,0,0,.04); }
    .klus-card .kc-head { font-weight:700; font-size:.82rem; display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; }
    .klus-card .kc-req { font-weight:700; color:#c2410c; font-size:.74rem; }
    .klus-card .kc-cust { font-size:.85rem; color:#1e293b; }
    .klus-card .kc-meta { font-size:.72rem; color:#64748b; margin-top:.15rem; }
    .klus-card .kc-times { font-size:.72rem; color:#334155; margin-top:.25rem; background:#f8fafc; border-radius:.35rem; padding:.2rem .4rem; }
    .klus-card .kc-staff { font-size:.74rem; font-weight:600; color:#15803d; margin-top:.2rem; }

    .laden-palette { margin-bottom:.85rem; }
    .laden-template { border-left-color:#0891b2; background:#ecfeff; cursor:grab; }
    .laden-template .kc-meta { color:#0e7490; }

    .shift-start.is-auto { background:#f0fdf4; border-color:#86efac; }
    .shift-start-auto { border:1px solid #e2e8f0; background:#fff; border-radius:.5rem; padding:.3rem .5rem; font-size:.72rem; cursor:pointer; white-space:nowrap; }
    .shift-staff2 { flex:1; padding:.4rem .5rem; border:1px solid #e2e8f0; border-radius:.5rem; font-size:.85rem; color:#64748b; }
    .shift-add2 { border:1px dashed #cbd5e1; background:#fff; border-radius:.5rem; padding:.3rem .6rem; font-size:.74rem; color:#64748b; cursor:pointer; }

    .shift-card { background:#fff; border:1px solid #e2e8f0; border-radius:.9rem; padding:.9rem; margin-bottom:1rem; box-shadow:0 2px 8px rgba(0,0,0,.05); }
    .shift-head { display:flex; gap:.5rem; align-items:center; margin-bottom:.5rem; }
    .shift-head .shift-staff { flex:1; padding:.4rem .5rem; border:1px solid #e2e8f0; border-radius:.5rem; font-size:.88rem; font-weight:600; }
    .shift-head .shift-start { border:1px solid #e2e8f0; border-radius:.5rem; padding:.35rem .5rem; font-size:.85rem; }
    .shift-del { border:none; background:#fee2e2; color:#b91c1c; border-radius:.5rem; width:1.9rem; height:1.9rem; cursor:pointer; font-weight:700; }
    .shift-locs { display:grid; grid-template-columns:1fr 1fr; gap:.5rem; margin-bottom:.6rem; }
    .shift-locs input { padding:.4rem .55rem; border:1px solid #e2e8f0; border-radius:.5rem; font-size:.8rem; }

    .shift-joblist { border:1px dashed #cbd5e1; border-radius:.6rem; padding:.5rem; background:#fafafa; min-height:52px; }
    .job-row { background:#fff; border:1px solid #e2e8f0; border-radius:.55rem; padding:.5rem .6rem; margin-bottom:.4rem; cursor:grab; }
    .job-row:last-child { margin-bottom:0; }
    .job-row.is-late { border-color:#fca5a5; background:#fff7f7; }
    .jr-travel { font-size:.7rem; color:#64748b; }
    .jr-main { display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; margin-top:.15rem; }
    .jr-time { font-weight:800; font-size:.82rem; color:#0f172a; }
    .jr-label { font-weight:700; font-size:.8rem; }
    .jr-cust { font-size:.82rem; color:#1e293b; }
    .jr-loc { font-size:.72rem; color:#64748b; }
    .jr-side { display:flex; align-items:center; gap:.3rem; margin-top:.35rem; }
    .jr-dur { width:3.4rem; padding:.25rem .35rem; border:1px solid #e2e8f0; border-radius:.4rem; font-size:.8rem; }
    .jr-min { font-size:.72rem; color:#64748b; }
    .jr-remove { margin-left:auto; border:none; background:#f1f5f9; color:#64748b; border-radius:.4rem; width:1.6rem; height:1.6rem; cursor:pointer; }
    .jr-warn { font-size:.72rem; color:#b91c1c; margin-top:.3rem; font-weight:600; }

    .shift-foot { margin-top:.6rem; border-top:1px solid #f1f5f9; padding-top:.55rem; }
    .sf-return { font-size:.75rem; color:#7c3aed; margin-bottom:.25rem; }
    .sf-total { font-size:.85rem; color:#1e293b; }
    .sf-warn { color:#b45309; font-weight:600; }
    .sf-note { color:#94a3b8; font-style:italic; }
    .sf-actions { display:flex; gap:.5rem; margin-top:.5rem; }
    .sf-actions button { border:1px solid #e2e8f0; background:#fff; border-radius:.5rem; padding:.3rem .7rem; font-size:.78rem; font-weight:600; cursor:pointer; }

    .sortable-ghost { opacity:.4; }
    #map-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,.55); z-index:9999; align-items:center; justify-content:center; padding:1rem; }
    #map-box { background:#fff; border-radius:.9rem; width:100%; max-width:820px; height:70vh; display:flex; flex-direction:column; overflow:hidden; }
    #map-box .mb-head { display:flex; justify-content:space-between; align-items:center; padding:.7rem 1rem; border-bottom:1px solid #e2e8f0; font-weight:700; }
    #map-box .mb-close { border:none; background:#f1f5f9; border-radius:.5rem; width:2rem; height:2rem; cursor:pointer; }
    #shift-map { flex:1; }
</style>

<div class="shifts-top">
    <h1>🚐 Shifts plannen</h1>
    <div class="date-nav">
        <button id="date-prev" title="Vorige dag">‹</button>
        <input type="date" id="date-input" value="{{ $date }}">
        <button id="date-next" title="Volgende dag">›</button>
    </div>
</div>

<div id="calendar-wrap">
    @include('shifts._calendar', $calendar + ['date' => $date])
</div>

<div id="board-wrap">
    @include('shifts._board', ['shifts' => $shifts, 'unplanned' => $unplanned, 'staffMembers' => $staffMembers, 'date' => $date, 'mapsKey' => $mapsKey])
</div>

<div id="map-overlay">
    <div id="map-box">
        <div class="mb-head"><span>Route</span><button class="mb-close" id="map-close">✕</button></div>
        <div id="shift-map"></div>
    </div>
</div>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.3/Sortable.min.js"></script>
<script>
window.CSRF = '{{ csrf_token() }}';
window.MAPS_KEY = @json($mapsKey);
(function () {
    const wrap = document.getElementById('board-wrap');
    let currentDate = @json($date);
    const routes = {
        store:    @json(route('shifts.store')),
        index:    @json(route('shifts.index')),
        shift:    (id) => @json(url('shifts')) + '/' + id,
        attach:   (id) => @json(url('shifts')) + '/' + id + '/jobs',
        reorder:  (id) => @json(url('shifts')) + '/' + id + '/reorder',
        recompute:(id) => @json(url('shifts')) + '/' + id + '/recompute',
        job:      (id) => @json(url('shift-jobs')) + '/' + id,
    };

    function api(method, url, body) {
        return fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.CSRF, 'Accept': 'application/json' },
            body: body ? JSON.stringify(body) : undefined,
        }).then(async r => {
            const data = await r.json().catch(() => ({}));
            if (!r.ok) throw new Error(data.error || 'Er ging iets mis.');
            return data;
        });
    }

    let currentMonth = @json($month);
    const calWrap = document.getElementById('calendar-wrap');

    let refreshing = false;
    async function refreshBoard() {
        refreshing = true;
        const html = await fetch(routes.index + '?date=' + encodeURIComponent(currentDate) + '&partial=1', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        }).then(r => r.text());
        wrap.innerHTML = html;
        initBoard();
        await refreshCalendar();   // tellingen + selectie bijwerken
        refreshing = false;
    }

    async function refreshCalendar() {
        const html = await fetch(routes.index + '?calendar=1&month=' + encodeURIComponent(currentMonth) + '&date=' + encodeURIComponent(currentDate), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        }).then(r => r.text());
        calWrap.innerHTML = html;
        initCalendar();
    }

    function initCalendar() {
        calWrap.querySelectorAll('.cal-day').forEach(el => el.addEventListener('click', () => {
            currentMonth = el.dataset.date.slice(0, 7);
            goToDate(el.dataset.date);
        }));
        const prev = calWrap.querySelector('.cal-prev');
        if (prev) prev.addEventListener('click', () => { currentMonth = prev.dataset.month; refreshCalendar(); });
        const next = calWrap.querySelector('.cal-next');
        if (next) next.addEventListener('click', () => { currentMonth = next.dataset.month; refreshCalendar(); });
    }

    const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

    function initBoard() {
        // Sortable op de "nog in te plannen"-lijst en op elke shift-lijst
        const lists = wrap.querySelectorAll('.droplist');
        lists.forEach(list => {
            Sortable.create(list, {
                group: 'klus',
                animation: 150,
                ghostClass: 'sortable-ghost',
                onAdd: onAdd,
                onUpdate: onUpdate,
            });
        });
        // "Laden"-palet: kloneren naar een shift, zelf niets ontvangen
        const palette = wrap.querySelector('.laden-palette');
        if (palette) {
            Sortable.create(palette, {
                group: { name: 'klus', pull: 'clone', put: false },
                sort: false,
                animation: 150,
            });
        }
        bindControls();
    }

    async function onAdd(evt) {
        const item = evt.item;
        const list = evt.to;
        const role = list.dataset.role;
        const bookingId = item.dataset.bookingId;
        const action    = item.dataset.action;
        const jobId     = item.dataset.jobId; // gezet als het een bestaande klus (job-row) is

        try {
            if (role === 'unplanned') {
                // Klus uit een shift gehaald → losmaken
                if (jobId) await api('DELETE', routes.job(jobId));
            } else if (role === 'shift') {
                const shiftId = list.dataset.shiftId;
                if (jobId) {
                    // Verplaatsen tussen shifts: eerst losmaken, dan koppelen
                    await api('DELETE', routes.job(jobId));
                }
                await api('POST', routes.attach(shiftId), { booking_id: bookingId, action });
            }
        } catch (e) { alert(e.message); }
        await refreshBoard();
    }

    async function onUpdate(evt) {
        const list = evt.to;
        if (list.dataset.role !== 'shift') return; // volgorde in "in te plannen" doet er niet toe
        const shiftId = list.dataset.shiftId;
        const ids = Array.from(list.querySelectorAll('.job-row')).map(el => el.dataset.jobId);
        try { await api('POST', routes.reorder(shiftId), { job_ids: ids }); } catch (e) { alert(e.message); }
        await refreshBoard();
    }

    function shiftPayload(card) {
        return {
            staff_id:      card.querySelector('.shift-staff').value || null,
            staff_id_2:    (card.querySelector('.shift-staff2') || {}).value || null,
            start_address: card.querySelector('.shift-startaddr').value || null,
            end_address:   card.querySelector('.shift-endaddr').value || null,
        };
    }

    function bindControls() {
        // Nieuwe shift
        const nb = wrap.querySelector('.btn-new-shift');
        if (nb) nb.addEventListener('click', async () => {
            try { await api('POST', routes.store, { shift_date: currentDate }); await refreshBoard(); }
            catch (e) { alert(e.message); }
        });

        wrap.querySelectorAll('.shift-card').forEach(card => {
            const id = card.dataset.shiftId;
            const update = () => api('PATCH', routes.shift(id), shiftPayload(card)).then(refreshBoard).catch(e => alert(e.message));
            const updateDebounced = debounce(update, 700);

            card.querySelector('.shift-staff').addEventListener('change', update);
            const staff2 = card.querySelector('.shift-staff2');
            if (staff2) staff2.addEventListener('change', update);
            card.querySelector('.shift-startaddr').addEventListener('input', updateDebounced);
            card.querySelector('.shift-endaddr').addEventListener('input', updateDebounced);

            // Starttijd handmatig zetten (start_manual=1) of terug naar automatisch (start_manual=0)
            card.querySelector('.shift-start').addEventListener('change', () => {
                const v = card.querySelector('.shift-start').value;
                api('PATCH', routes.shift(id), Object.assign(shiftPayload(card), { start_time: v || null, start_manual: 1 }))
                    .then(refreshBoard).catch(e => alert(e.message));
            });
            const autoBtn = card.querySelector('.shift-start-auto');
            if (autoBtn) autoBtn.addEventListener('click', () => {
                api('PATCH', routes.shift(id), Object.assign(shiftPayload(card), { start_manual: 0 }))
                    .then(refreshBoard).catch(e => alert(e.message));
            });

            card.querySelector('.shift-del').addEventListener('click', async () => {
                if (!confirm('Deze shift verwijderen? De klussen komen weer vrij.')) return;
                try { await api('DELETE', routes.shift(id)); await refreshBoard(); } catch (e) { alert(e.message); }
            });

            const rc = card.querySelector('.btn-recompute');
            if (rc) rc.addEventListener('click', async () => {
                rc.textContent = '↻ …';
                try { await api('POST', routes.recompute(id)); await refreshBoard(); } catch (e) { alert(e.message); }
            });

            const mb = card.querySelector('.btn-map');
            if (mb) mb.addEventListener('click', () => openMap(card));

            card.querySelectorAll('.job-row').forEach(row => {
                const jobId = row.dataset.jobId;
                row.querySelector('.jr-remove').addEventListener('click', async () => {
                    try { await api('DELETE', routes.job(jobId)); await refreshBoard(); } catch (e) { alert(e.message); }
                });
                const dur = row.querySelector('.jr-dur');
                dur.addEventListener('change', () => {
                    api('PATCH', routes.job(jobId), { duration_minutes: parseInt(dur.value || '0', 10) })
                        .then(refreshBoard).catch(e => alert(e.message));
                });
            });
        });
    }

    // ── Datumnavigatie ────────────────────────────────────────────────
    function fmtDate(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    function goToDate(d) { currentDate = d; currentMonth = d.slice(0, 7); document.getElementById('date-input').value = d; refreshBoard(); }
    function shiftDate(days) {
        const d = new Date(currentDate + 'T12:00:00'); d.setDate(d.getDate() + days);   // 12:00 = geen tijdzone-sprong
        goToDate(fmtDate(d));
    }
    document.getElementById('date-prev').addEventListener('click', () => shiftDate(-1));
    document.getElementById('date-next').addEventListener('click', () => shiftDate(1));
    document.getElementById('date-input').addEventListener('change', e => goToDate(e.target.value));

    // ── Kaart (Google Directions) ─────────────────────────────────────
    let mapsLoaded = false, gmap = null, directionsRenderer = null, directionsService = null;
    const overlay = document.getElementById('map-overlay');
    document.getElementById('map-close').addEventListener('click', () => overlay.style.display = 'none');
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.style.display = 'none'; });

    function loadMaps() {
        return new Promise((resolve, reject) => {
            if (mapsLoaded && window.google) return resolve();
            if (!window.MAPS_KEY) return reject(new Error('Geen Google Maps key.'));
            window.__mapsCb = () => { mapsLoaded = true; resolve(); };
            const s = document.createElement('script');
            s.src = 'https://maps.googleapis.com/maps/api/js?key=' + window.MAPS_KEY + '&callback=__mapsCb';
            s.async = true; s.onerror = () => reject(new Error('Kon Google Maps niet laden.'));
            document.head.appendChild(s);
        });
    }

    async function openMap(card) {
        let stops;
        try { stops = JSON.parse(card.dataset.stops || '[]'); } catch { stops = []; }
        stops = stops.filter(Boolean);
        if (stops.length < 2) { alert('Te weinig stops voor een route.'); return; }
        overlay.style.display = 'flex';
        try { await loadMaps(); } catch (e) { alert(e.message); overlay.style.display = 'none'; return; }
        if (!gmap) {
            gmap = new google.maps.Map(document.getElementById('shift-map'), { zoom: 9, center: { lat: 52.09, lng: 5.12 } });
            directionsService = new google.maps.DirectionsService();
            directionsRenderer = new google.maps.DirectionsRenderer({ map: gmap });
        }
        const waypoints = stops.slice(1, -1).map(a => ({ location: a, stopover: true }));
        directionsService.route({
            origin: stops[0], destination: stops[stops.length - 1],
            waypoints, travelMode: google.maps.TravelMode.DRIVING,
        }, (res, status) => {
            if (status === 'OK') directionsRenderer.setDirections(res);
            else alert('Route niet berekenbaar: ' + status);
        });
    }

    initBoard();
    initCalendar();
})();
</script>
@endpush
@endsection
