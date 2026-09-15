<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#fdfdfc">
    <title>{{ $titel ?? 'Taken' }} — Flitsmoment</title>
    <link rel="icon" type="image/png" href="/logo.png?v=3">
    <link rel="apple-touch-icon" href="/favicon-app.png">
    <link rel="manifest" href="{{ route('todo.manifest') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <style>
        *,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:        #fdfdfc;
            --nav:       #f5f4f1;
            --line:      #efeeea;
            --line-soft: #f4f3f0;
            --tekst:     #1c1c1e;
            --gedempt:   #8a8a8e;
            --zacht:     #b0b0b5;
            --accent:    #2f6fed;
            --amber:     #f59e0b;
            --rood:      #d1483c;
            --groen:     #2e6b4f;
        }

        html { -webkit-text-size-adjust: 100%; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg); color: var(--tekst);
            font-size: 15px; line-height: 1.5;
            display: flex; min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }
        a { color: inherit; }

        /* ── Linkerkolom ──────────────────────────────────────────── */
        .nav {
            width: 208px; flex-shrink: 0; background: var(--nav);
            border-right: 1px solid var(--line); padding: 18px 12px 24px;
            display: flex; flex-direction: column; gap: 2px;
            position: sticky; top: 0; height: 100vh; overflow-y: auto;
        }
        .merk {
            display: flex; align-items: center; gap: 8px;
            font-size: 13px; font-weight: 700; letter-spacing: -.01em;
            padding: 0 8px; margin-bottom: 16px; text-decoration: none;
        }
        .merk i { width: 16px; height: 16px; border-radius: 5px; flex: none;
                  background: linear-gradient(135deg, #fcd34d, var(--amber)); }

        .ni {
            display: flex; align-items: center; justify-content: space-between; gap: 8px;
            padding: 6px 9px; border-radius: 8px; font-size: 13.5px;
            color: #54545a; text-decoration: none; transition: background .12s;
        }
        .ni:hover { background: rgba(0,0,0,.035); }
        .ni.actief { background: #fff; color: var(--tekst); font-weight: 600;
                     box-shadow: 0 1px 2px rgba(0,0,0,.06); }
        .ni em { font-style: normal; font-size: 11.5px; color: var(--zacht);
                 font-variant-numeric: tabular-nums; }
        .ni.laat em { color: var(--rood); font-weight: 600; }
        .ni span.punt { width: 8px; height: 8px; border-radius: 50%; flex: none; margin-right: 2px; }
        .ni .lbl { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .scheiding { height: 1px; background: var(--line); margin: 10px 6px; }

        .nav-voet { margin-top: auto; padding-top: 12px; display: flex; flex-direction: column; gap: 2px; }
        .nav-voet .ni { font-size: 12.5px; color: var(--zacht); }

        /* ── Hoofdkolom ───────────────────────────────────────────── */
        .hoofd { flex: 1; min-width: 0; display: flex; flex-direction: column; }
        .binnen { width: 100%; max-width: 720px; padding: 26px 28px 140px; }

        .kop h1 { font-size: 21px; font-weight: 700; letter-spacing: -.015em; }
        .kop p { color: var(--gedempt); font-size: 12.5px; margin-top: 2px; }
        .kop { margin-bottom: 20px; display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }

        .menuknop { display: none; background: none; border: none; cursor: pointer;
                    padding: 6px; color: var(--gedempt); border-radius: 8px; }
        .menuknop:hover { background: rgba(0,0,0,.05); }

        /* ── Secties en regels ────────────────────────────────────── */
        .sectie + .sectie { margin-top: 26px; }
        .sectie-kop {
            font-size: 10.5px; font-weight: 700; letter-spacing: .09em;
            text-transform: uppercase; color: var(--zacht); margin-bottom: 6px;
        }

        .item {
            display: flex; gap: 11px; align-items: flex-start;
            padding: 9px 4px; border-bottom: 1px solid var(--line-soft);
            position: relative; background: var(--bg);
            touch-action: pan-y;
        }
        .item:last-child { border-bottom: 0; }
        .item.weg { opacity: 0; transform: translateX(14px); transition: opacity .28s, transform .28s; }
        .item.schuift { transition: transform .18s ease-out; }

        .vink {
            width: 16px; height: 16px; border-radius: 50%; flex: none; margin-top: 3px;
            border: 1.5px solid #c7c7cc; background: none; cursor: pointer; padding: 0;
            transition: border-color .12s, background .12s;
        }
        .vink:hover { border-color: var(--accent); }
        .vink.aan { background: var(--accent); border-color: var(--accent); }
        .vink.aan::after { content: ''; display: block; width: 4px; height: 8px;
            border: solid #fff; border-width: 0 1.5px 1.5px 0; transform: rotate(45deg);
            margin: 1px 0 0 5px; }

        .tekst { flex: 1; min-width: 0; cursor: pointer; }
        .titel { font-size: 13.5px; word-wrap: break-word; }
        .item.af .titel { color: var(--zacht); text-decoration: line-through; }
        .onder { display: flex; flex-wrap: wrap; align-items: center; gap: 6px;
                 color: var(--gedempt); font-size: 11.5px; margin-top: 2px; }
        .onder .prijs { color: var(--groen); font-weight: 600; font-variant-numeric: tabular-nums; }

        .bron {
            font-size: 9.5px; letter-spacing: .02em; color: #7d8a9c;
            background: #eef2f7; border-radius: 4px; padding: 1px 5px;
            text-decoration: none; white-space: nowrap;
        }
        .bron:hover { background: #e2e9f2; }
        .vlag { color: var(--amber); font-size: 11px; }

        .wanneer { font-size: 11.5px; color: var(--gedempt); white-space: nowrap;
                   padding-top: 1px; font-variant-numeric: tabular-nums; }
        .wanneer.laat { color: var(--rood); font-weight: 600; }
        .wanneer.nu { color: var(--tekst); }

        /* ── Leads (opvolgen) ─────────────────────────────────────── */
        .leadnr { font-size: 10.5px; color: var(--zacht); margin-left: 5px;
                  font-variant-numeric: tabular-nums; }
        .contactknoppen { display: flex; gap: 6px; align-items: center; padding-top: 1px; }
        .rond {
            width: 30px; height: 30px; border-radius: 50%; flex: none;
            display: inline-flex; align-items: center; justify-content: center;
            text-decoration: none; transition: background .12s, color .12s;
        }
        .rond.bellen   { background: #f1f0ed; color: #54545a; }
        .rond.bellen:hover { background: #e6e4e0; color: var(--tekst); }
        .rond.whatsapp { background: rgba(37,211,102,.12); color: #128c4a; }
        .rond.whatsapp:hover { background: rgba(37,211,102,.22); }

        .tijdlijn { border-left: 2px solid var(--line); padding-left: 11px; margin-bottom: 12px;
                    display: flex; flex-direction: column; gap: 4px; }
        .tl-regel { display: flex; gap: 9px; font-size: 12px; color: var(--gedempt); }
        .tl-datum { color: var(--zacht); font-size: 11px; white-space: nowrap;
                    min-width: 42px; font-variant-numeric: tabular-nums; }
        .tl-regel b { color: #54545a; font-weight: 600; }

        /* ── Nieuwe lijst ─────────────────────────────────────────── */
        .nieuwe-lijst { padding: 4px 9px 0; }
        .nieuwe-lijst input {
            width: 100%; border: 1px dashed #d9d7d2; background: none; border-radius: 7px;
            padding: 5px 8px; font: inherit; font-size: 12.5px; color: var(--tekst); outline: none;
        }
        .nieuwe-lijst input::placeholder { color: var(--zacht); }
        .nieuwe-lijst input:focus { border-style: solid; border-color: #cfd8e6; background: #fff; }

        /* ── Detail bij uitklappen ────────────────────────────────── */
        .detail { display: none; padding: 4px 4px 16px 31px; border-bottom: 1px solid var(--line-soft); }
        .detail.open { display: block; }
        .detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; }
        .veld label { display: block; font-size: 10px; text-transform: uppercase;
                      letter-spacing: .07em; color: var(--zacht); margin-bottom: 3px; font-weight: 600; }
        .veld input, .veld select, .detail textarea {
            width: 100%; border: 1px solid var(--line); border-radius: 8px;
            padding: 7px 9px; font: inherit; font-size: 12.5px; background: #fff;
            color: var(--tekst); outline: none;
        }
        .detail textarea { min-height: 56px; resize: vertical; margin-bottom: 10px; }
        .veld input:focus, .veld select:focus, .detail textarea:focus { border-color: #cfd8e6; }
        .detail-acties { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
        .knopje {
            border: 1px solid var(--line); background: #fff; border-radius: 8px;
            padding: 5px 10px; font-size: 12px; color: #54545a; cursor: pointer;
        }
        .knopje:hover { background: #faf9f7; }
        .knopje.weghalen { color: var(--rood); }
        .detail .bron-link { display: inline-block; margin-top: 10px; font-size: 12px; color: var(--accent); }

        /* ── Leeg ─────────────────────────────────────────────────── */
        .leeg { color: var(--zacht); font-size: 13.5px; padding: 26px 4px; }

        /* ── Invoerbalk ───────────────────────────────────────────── */
        .invoer {
            position: fixed; bottom: 0; left: 208px; right: 0;
            background: linear-gradient(to bottom, rgba(253,253,252,0), var(--bg) 22%);
            padding: 20px 28px calc(18px + env(safe-area-inset-bottom));
        }
        .invoer-binnen { max-width: 720px; }
        .invoer-rij {
            display: flex; align-items: center; gap: 8px; background: #fff;
            border: 1px solid var(--line); border-radius: 12px; padding: 4px 6px 4px 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
        }
        .invoer-rij input {
            flex: 1; border: none; outline: none; font: inherit; font-size: 13.5px;
            padding: 8px 0; background: none; color: var(--tekst); min-width: 0;
        }
        .soorten { display: flex; gap: 2px; background: #f4f3f0; border-radius: 8px; padding: 2px; }
        .soort {
            border: none; background: none; border-radius: 6px; padding: 5px 9px;
            font-size: 11.5px; color: var(--gedempt); cursor: pointer; white-space: nowrap;
        }
        .soort.aan { background: #fff; color: var(--tekst); font-weight: 600; box-shadow: 0 1px 2px rgba(0,0,0,.07); }
        .hint { font-size: 11px; color: var(--zacht); margin-top: 7px; display: flex; gap: 12px; flex-wrap: wrap; }
        kbd { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 10.5px;
              background: #f2f1ef; border-radius: 4px; padding: 1px 5px; color: #79797e; }

        /* ── Zoeken ───────────────────────────────────────────────── */
        .zoekbalk { display: none; margin-bottom: 16px; }
        .zoekbalk.open { display: block; }
        .zoekbalk input {
            width: 100%; border: 1px solid var(--line); border-radius: 10px;
            padding: 9px 12px; font: inherit; font-size: 13.5px; outline: none; background: #fff;
        }

        .melding { background: #eef7f1; color: var(--groen); border-radius: 9px;
                   padding: 9px 12px; font-size: 12.5px; margin-bottom: 16px; }

        /* ── Mobiel ───────────────────────────────────────────────── */
        .scherm { display: none; }
        @media (max-width: 760px) {
            body { display: block; }
            .nav {
                position: fixed; z-index: 30; left: 0; top: 0; bottom: 0; height: 100%;
                transform: translateX(-100%); transition: transform .22s ease; width: 230px;
            }
            .nav.open { transform: none; }
            .scherm.open { display: block; position: fixed; inset: 0; background: rgba(0,0,0,.25); z-index: 20; }
            .menuknop { display: block; }
            .binnen { padding: 18px 16px 150px; max-width: none; }
            .invoer { left: 0; padding: 16px 16px calc(14px + env(safe-area-inset-bottom)); }
            .hint { display: none; }
        }
        @media (prefers-reduced-motion: reduce) {
            .item, .item.weg, .nav { transition: none; }
        }
    </style>
</head>
<body>

<aside class="nav" id="nav">
    <a class="merk" href="{{ route('todo.vandaag') }}"><i></i>Taken</a>

    <a class="ni {{ $weergave === 'vandaag' ? 'actief' : '' }}" href="{{ route('todo.vandaag') }}">
        <span class="lbl">Vandaag</span><em>{{ $telVandaag ?: '' }}</em>
    </a>
    @if ($telTeLaat > 0)
        <a class="ni laat" href="{{ route('todo.vandaag') }}"><span class="lbl">Te laat</span><em>{{ $telTeLaat }}</em></a>
    @endif
    <a class="ni {{ $weergave === 'binnenkort' ? 'actief' : '' }}" href="{{ route('todo.binnenkort') }}">
        <span class="lbl">Binnenkort</span><em>{{ $telBinnenkort ?: '' }}</em>
    </a>

    <div class="scheiding"></div>

    <a class="ni {{ $weergave === 'kopen' ? 'actief' : '' }}" href="{{ route('todo.kopen') }}">
        <span class="lbl">Kopen</span><em>{{ $somKopen ? '€ ' . number_format($somKopen / 100, 0, ',', '.') : ($telKopen ?: '') }}</em>
    </a>
    <a class="ni {{ $weergave === 'leren' ? 'actief' : '' }}" href="{{ route('todo.leren') }}">
        <span class="lbl">Leren</span><em>{{ $telLeren ?: '' }}</em>
    </a>
    <a class="ni {{ $weergave === 'opvolgen' ? 'actief' : '' }}" href="{{ route('todo.opvolgen') }}">
        <span class="lbl">Opvolgen</span><em>{{ $telOpvolgen ?: '' }}</em>
    </a>
    <a class="ni {{ $weergave === 'crm' ? 'actief' : '' }}" href="{{ route('todo.crm') }}">
        <span class="lbl">Uit het CRM</span><em>{{ $telCrm ?: '' }}</em>
    </a>

    <div class="scheiding"></div>

    @foreach ($lijsten as $lijst)
        <a class="ni {{ ($actieveLijst ?? null) === $lijst->id ? 'actief' : '' }}" href="{{ route('todo.lijst', $lijst) }}">
            <span class="punt" style="background: {{ $lijst->color }}"></span>
            <span class="lbl">{{ $lijst->title }}</span>
            <em>{{ $lijst->open_count ?: '' }}</em>
        </a>
    @endforeach

    <form class="nieuwe-lijst" method="POST" action="{{ route('todo.lijsten.store') }}">
        @csrf
        <input type="text" name="title" placeholder="+ Nieuwe lijst" maxlength="100" aria-label="Nieuwe lijst">
    </form>

    <div class="nav-voet">
        <a class="ni {{ $weergave === 'klaar' ? 'actief' : '' }}" href="{{ route('todo.klaar') }}">
            <span class="lbl">Klaar</span><em>{{ $telKlaar ?: '' }}</em>
        </a>
        <a class="ni {{ $weergave === 'instellingen' ? 'actief' : '' }}" href="{{ route('todo.instellingen') }}">
            <span class="lbl">Instellingen</span>
        </a>
        <a class="ni" href="{{ rtrim(config('app.url'), '/') }}">
            <span class="lbl">Naar het CRM</span>
        </a>
    </div>
</aside>
<div class="scherm" id="scherm" onclick="menuDicht()"></div>

<main class="hoofd">
    <div class="binnen">
        @yield('inhoud')
    </div>
</main>

@unless ($geenInvoer ?? false)
<div class="invoer">
    <div class="invoer-binnen">
        <form class="invoer-rij" id="invoerform" autocomplete="off">
            <input type="text" id="invoerveld" placeholder="Nieuw…"
                   aria-label="Nieuw item" enterkeyhint="done">
            <div class="soorten" role="group" aria-label="Soort item">
                <button type="button" class="soort aan" data-soort="taak">Taak</button>
                <button type="button" class="soort" data-soort="kopen">Kopen</button>
                <button type="button" class="soort" data-soort="leren">Leren</button>
            </div>
        </form>
        <div class="hint">
            <span><kbd>N</kbd> taak · <kbd>K</kbd> kopen · <kbd>L</kbd> leren</span>
            <span><kbd>/</kbd> zoeken</span>
            <span>Herkent zelf: <kbd>morgen</kbd> <kbd>€ 89</kbd> <kbd>@conrad</kbd></span>
        </div>
    </div>
</div>
@endunless

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const ROUTES = {
    store:   @json(route('todo.items.store')),
    item:    id => @json(route('todo.items.update', ['task' => '__ID__'])).replace('__ID__', id),
    zoeken:  @json(route('todo.zoeken')),
};
let soort = 'taak';
let nieuweLijst = @json($lijstId ?? null);

function verstuur(url, methode, data) {
    return fetch(url, {
        method: methode,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: data ? JSON.stringify(data) : null,
    }).then(r => r.ok ? r.json() : Promise.reject(r));
}

/* Ververst de lijst en de tellers zonder de pagina te laten knipperen */
function zachtHerladen() {
    fetch(location.href, { headers: { 'X-Requested-With': 'fetch' } })
        .then(r => r.text())
        .then(html => {
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const nieuw = doc.querySelector('#lijst');
            const oud   = document.querySelector('#lijst');
            if (nieuw && oud) oud.replaceWith(nieuw);

            const navNieuw = doc.querySelector('#nav');
            if (navNieuw) {
                const open = document.getElementById('nav').classList.contains('open');
                document.getElementById('nav').replaceWith(navNieuw);
                if (open) navNieuw.classList.add('open');
            }
            const kop = doc.querySelector('.kop p');
            if (kop) document.querySelector('.kop p').textContent = kop.textContent;
            koppel();
        });
}

/* ── Afvinken ─────────────────────────────────────────────────── */
function vinkAf(knop) {
    const rij = knop.closest('.item');
    const id  = rij.dataset.id;
    const af  = !knop.classList.contains('aan');

    knop.classList.toggle('aan', af);
    rij.classList.toggle('af', af);

    verstuur(ROUTES.item(id) + '/toggle', 'POST').then(() => {
        if (af && !rij.dataset.blijf) {
            rij.classList.add('weg');
            setTimeout(zachtHerladen, 300);
        } else {
            zachtHerladen();
        }
    });
}

/* ── Uitklappen ───────────────────────────────────────────────── */
function klapUit(el) {
    const detail = el.closest('.item').nextElementSibling;
    if (detail && detail.classList.contains('detail')) {
        detail.classList.toggle('open');
        if (detail.classList.contains('open')) detail.querySelector('textarea,input')?.focus();
    }
}

function bewaarVeld(veld) {
    const id = veld.closest('[data-id]').dataset.id;
    verstuur(ROUTES.item(id), 'PATCH', { [veld.name]: veld.value === '' ? null : veld.value })
        .then(zachtHerladen);
}

function haalWeg(knop) {
    const id = knop.closest('[data-id]').dataset.id;
    verstuur(ROUTES.item(id), 'DELETE').then(zachtHerladen);
}

function morgen(knop, dagen) {
    const id = knop.closest('[data-id]').dataset.id;
    verstuur(ROUTES.item(id) + '/snooze', 'POST', { dagen: dagen || 1 }).then(zachtHerladen);
}

function vlagOm(knop) {
    const id = knop.closest('[data-id]').dataset.id;
    verstuur(ROUTES.item(id) + '/flag', 'POST').then(zachtHerladen);
}

/* ── Nieuw item ───────────────────────────────────────────────── */
document.getElementById('invoerform')?.addEventListener('submit', e => {
    e.preventDefault();
    const veld = document.getElementById('invoerveld');
    const titel = veld.value.trim();
    if (!titel) return;

    veld.value = '';
    verstuur(ROUTES.store, 'POST', { title: titel, type: soort, task_list_id: nieuweLijst })
        .then(zachtHerladen)
        .catch(() => { veld.value = titel; alert('Opslaan lukte niet. Probeer het nog eens.'); });
});

document.querySelectorAll('.soort').forEach(knop => {
    knop.addEventListener('click', () => {
        document.querySelectorAll('.soort').forEach(k => k.classList.remove('aan'));
        knop.classList.add('aan');
        soort = knop.dataset.soort;
        document.getElementById('invoerveld').focus();
    });
});

function kiesSoort(naam) {
    const knop = document.querySelector(`.soort[data-soort="${naam}"]`);
    if (knop) knop.click(); else document.getElementById('invoerveld')?.focus();
}

/* ── Sneltoetsen ──────────────────────────────────────────────── */
document.addEventListener('keydown', e => {
    const inVeld = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName);

    if (e.key === 'Escape') { document.activeElement.blur(); return; }
    if (inVeld || e.metaKey || e.ctrlKey || e.altKey) return;

    if (e.key === 'n' || e.key === 'N') { e.preventDefault(); kiesSoort('taak'); }
    if (e.key === 'k' || e.key === 'K') { e.preventDefault(); kiesSoort('kopen'); }
    if (e.key === 'l' || e.key === 'L') { e.preventDefault(); kiesSoort('leren'); }
    if (e.key === '/')                  { e.preventDefault(); zoekOpen(); }
});

function zoekOpen() {
    const balk = document.getElementById('zoekbalk');
    if (!balk) { location.href = ROUTES.zoeken; return; }
    balk.classList.add('open');
    balk.querySelector('input').focus();
}

/* ── Vegen op mobiel ──────────────────────────────────────────── */
function koppel() {
    document.querySelectorAll('.item').forEach(rij => {
        if (rij.dataset.gekoppeld) return;
        rij.dataset.gekoppeld = '1';

        let startX = 0, startY = 0, bezig = false;

        rij.addEventListener('touchstart', e => {
            startX = e.touches[0].clientX; startY = e.touches[0].clientY; bezig = false;
            rij.classList.remove('schuift');
        }, { passive: true });

        rij.addEventListener('touchmove', e => {
            const dx = e.touches[0].clientX - startX;
            const dy = e.touches[0].clientY - startY;
            if (!bezig && Math.abs(dx) < Math.abs(dy)) return;
            bezig = true;
            rij.style.transform = `translateX(${dx}px)`;
        }, { passive: true });

        rij.addEventListener('touchend', e => {
            const dx = e.changedTouches[0].clientX - startX;
            rij.classList.add('schuift');
            rij.style.transform = '';
            if (!bezig) return;
            if (dx > 80)  vinkAf(rij.querySelector('.vink'));
            if (dx < -80) morgen(rij.querySelector('.vink'), 1);
        });
    });
}

function menuOpen()  { document.getElementById('nav').classList.add('open'); document.getElementById('scherm').classList.add('open'); }
function menuDicht() { document.getElementById('nav').classList.remove('open'); document.getElementById('scherm').classList.remove('open'); }

koppel();
</script>
@stack('scripts')
</body>
</html>
