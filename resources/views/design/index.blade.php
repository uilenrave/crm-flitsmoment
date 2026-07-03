@extends('layouts.app')

@section('title', 'Ontwerp-generator')

@section('content')
<style>
    .dg-grid { display: grid; grid-template-columns: 1fr; gap: 1.25rem; }
    @media (min-width: 1024px) { .dg-grid { grid-template-columns: 400px 1fr; align-items: start; } }
    .dg-label { display: block; font-size: .8rem; font-weight: 700; color: #334155; margin-bottom: .35rem; }
    .dg-hint { font-size: .72rem; color: #94a3b8; font-weight: 400; }
    .dg-field { margin-bottom: 1.1rem; }
    .dg-textarea { width: 100%; min-height: 140px; padding: .7rem .8rem; border: 1px solid #e2e8f0; border-radius: .5rem; font-size: .9rem; font-family: inherit; resize: vertical; box-sizing: border-box; }
    .dg-textarea:focus, .dg-file:focus, .dg-select:focus { outline: none; border-color: #7c3aed; box-shadow: 0 0 0 2px rgba(124,58,237,.12); }
    .dg-file, .dg-select { width: 100%; padding: .55rem .7rem; border: 1px solid #e2e8f0; border-radius: .5rem; font-size: .875rem; background: #fff; box-sizing: border-box; }
    .dg-result { border: 1px solid #e2e8f0; border-radius: .75rem; overflow: hidden; background: #fff; }
    .dg-result-head { display: flex; justify-content: space-between; align-items: center; padding: .6rem .9rem; border-bottom: 1px solid #f1f5f9; font-size: .8rem; }
    .dg-badge { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; padding: .15rem .5rem; border-radius: 999px; background: #ede9fe; color: #6d28d9; }
    .dg-result-body { display: flex; justify-content: center; background: #f8fafc; }
    .dg-result-body img { display: block; max-height: 80vh; width: auto; max-width: 100%; }
    .dg-gear { display: inline-flex; align-items: center; justify-content: center; width: 1.6rem; height: 1.6rem; border-radius: .4rem; border: 1px solid #e2e8f0; background: #fff; cursor: pointer; font-size: .85rem; margin-left: .4rem; vertical-align: middle; }
    .dg-gear:hover { background: #f8fafc; }
    .dg-gear.active { background: #ede9fe; border-color: #c4b5fd; }
    .dg-settings { display: none; margin: -.25rem 0 1.1rem; padding: .85rem .9rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: .5rem; }
    .dg-settings.open { display: block; }
    .dg-settings textarea { width: 100%; min-height: 160px; padding: .6rem .7rem; border: 1px solid #e2e8f0; border-radius: .45rem; font-size: .82rem; font-family: inherit; resize: vertical; box-sizing: border-box; }
    .dg-settings-actions { display: flex; justify-content: space-between; align-items: center; margin-top: .5rem; }
    .dg-settings-save { font-size: .78rem; font-weight: 700; color: #fff; background: #7c3aed; border: none; border-radius: .4rem; padding: .4rem .8rem; cursor: pointer; }
    .dg-settings-save:hover { background: #6d28d9; }
    .dg-settings-reset { font-size: .75rem; color: #64748b; cursor: pointer; text-decoration: underline; }
    .dg-settings-saved { font-size: .75rem; color: #16a34a; font-weight: 600; opacity: 0; transition: opacity .2s; }
    .dg-settings-saved.show { opacity: 1; }

    .dg-logo-step { border: 1px solid #e2e8f0; border-radius: .75rem; background: #fff; padding: 1rem 1.1rem; margin-top: 1.25rem; }
    .dg-logo-actions { display: flex; gap: .5rem; margin-top: .5rem; }
    .dg-logo-btn { font-size: .78rem; font-weight: 700; color: #7c3aed; background: #fff; border: 1px solid #ddd6fe; border-radius: .4rem; padding: .4rem .7rem; cursor: pointer; }
    .dg-logo-btn:hover { background: #f5f3ff; }
    .dg-logo-btn:disabled { opacity: .5; cursor: default; }
    .dg-logo-error { color: #b91c1c; font-size: .75rem; margin-top: .4rem; }
    .dg-result-body { position: relative; }
    .dg-logo-toolbar { display: none; gap: .5rem; padding: .6rem .9rem; border-top: 1px solid #f1f5f9; }
    .dg-logo-layer { position: absolute; top: 50%; left: 50%; touch-action: none; user-select: none; cursor: move; }
    .dg-logo-layer img { display: block; width: 100%; height: 100%; pointer-events: none; }
    .dg-logo-handle { position: absolute; width: 16px; height: 16px; border-radius: 50%; background: #7c3aed; border: 2px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,.35); }
    .dg-logo-handle.resize { right: 2px; bottom: 2px; cursor: nwse-resize; }
    .dg-logo-handle.rotate { right: 2px; top: 2px; cursor: grab; }

    .dg-mask-step { border: 1px solid #e2e8f0; border-radius: .75rem; background: #fff; padding: 1rem 1.1rem; margin-top: 1.25rem; }
    .dg-mask-sub { margin-top: .9rem; padding-top: .9rem; border-top: 1px solid #f1f5f9; }
    .dg-mask-preview-bg {
        background-image:
            linear-gradient(45deg, #e5e7eb 25%, transparent 25%),
            linear-gradient(-45deg, #e5e7eb 25%, transparent 25%),
            linear-gradient(45deg, transparent 75%, #e5e7eb 75%),
            linear-gradient(-45deg, transparent 75%, #e5e7eb 75%);
        background-size: 20px 20px;
        background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
    }
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem;">
    <h2 style="margin:0;font-size:1.25rem;font-weight:700;color:#1e293b;">✨ Ontwerp-generator — Achtergrond</h2>
    <span style="font-size:.8rem;color:#64748b;">AI-achtergrond genereren (Gemini)</span>
</div>
<p class="dg-hint" style="margin:-.5rem 0 1rem;">Onderdeel 1 van de fotostrip. Andere onderdelen (strip-kader, logo, …) volgen later.</p>

<div class="dg-grid">
    {{-- ── Formulier ── --}}
    <form method="POST" action="{{ route('design.generate') }}" enctype="multipart/form-data" class="card neu-card" style="padding:1.25rem;" onsubmit="dgSubmitting(this)">
        @csrf

        <div class="dg-field">
            <label class="dg-label" for="event_type">Type event</label>
            <select id="event_type" name="event_type" class="dg-select" onchange="dgEventTypeChanged()">
                @foreach($eventTypes as $value => $typeLabel)
                    <option value="{{ $value }}" @selected(old('event_type', $eventType) === $value)>{{ $typeLabel }}</option>
                @endforeach
            </select>
        </div>

        <div class="dg-field">
            <label class="dg-label">
                {{ $promptLabel }}-prompt <span id="dg-current-type-label"></span>
                <span id="dg-gear" class="dg-gear" title="Prompt aanpassen / sluiten" onclick="dgToggleSettings()">⚙️</span>
            </label>
            <div id="dg-settings" class="dg-settings">
                <label class="dg-label" for="dg-prompt-template">Vaste prompt <span class="dg-hint">— wordt onthouden per type event, {{ '{beschrijving}' }} wordt vervangen door je invoer hieronder</span></label>
                <textarea id="dg-prompt-template"></textarea>
                <div class="dg-settings-actions">
                    <span class="dg-settings-reset" onclick="document.getElementById('dg-prompt-template').value = {{ Js::from($promptDefault) }}">↺ Herstel standaardtekst</span>
                    <span style="display:flex;align-items:center;gap:.6rem;">
                        <span id="dg-settings-saved" class="dg-settings-saved">Opgeslagen ✓</span>
                        <button type="button" class="dg-settings-save" onclick="dgSavePrompt()">Opslaan</button>
                    </span>
                </div>
            </div>
        </div>

        <div class="dg-field">
            <label class="dg-label" for="input">Thema / sfeer voor dit ontwerp</label>
            <textarea id="input" name="input" class="dg-textarea" required placeholder="Bijv: Speelse achtergrond voor een 40e verjaardag, ballonnen en confetti in feestkleuren.">{{ old('input', $input) }}</textarea>
        </div>

        <div class="dg-field">
            <label class="dg-label" for="references">Referentieafbeelding <span class="dg-hint">— optioneel, bijv. flyer/huisstijl/sfeerbeeld (meerdere mag, max 8 MB p/st)</span></label>
            <input id="references" name="references[]" type="file" class="dg-file" accept="image/*" multiple>
        </div>

        @error('input') <p style="color:#dc2626;font-size:.8rem;margin:0 0 .5rem;">{{ $message }}</p> @enderror
        @error('event_type') <p style="color:#dc2626;font-size:.8rem;margin:0 0 .5rem;">{{ $message }}</p> @enderror
        @error('references.*') <p style="color:#dc2626;font-size:.8rem;margin:0 0 .5rem;">{{ $message }}</p> @enderror

        <button type="submit" id="dg-submit" class="btn btn-primary" style="width:100%;justify-content:center;">✨ Genereer achtergrond</button>
        <p class="dg-hint" style="margin:.6rem 0 0;text-align:center;">Genereren kan 10–30 seconden duren.</p>
    </form>

    {{-- ── Resultaat ── --}}
    <div>
        @if($results === null)
            <div class="card neu-card" style="padding:2.5rem 1.5rem;text-align:center;color:#94a3b8;">
                <div style="font-size:2rem;margin-bottom:.5rem;">🖼️</div>
                <p style="font-weight:600;color:#64748b;margin-bottom:.25rem;">Nog geen achtergrond</p>
                <p style="font-size:.85rem;">Vul links het thema in en klik op "Genereer achtergrond".</p>
            </div>
        @else
            @if($input)
            <div style="font-size:.8rem;color:#64748b;margin-bottom:.75rem;padding:.6rem .85rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:.5rem;">
                <strong>Categorie:</strong> {{ $eventTypes[$eventType] ?? $eventType }} — <strong>Thema:</strong> {{ \Illuminate\Support\Str::limit($input, 220) }}
            </div>
            @endif
            <div class="dg-result">
                <div class="dg-result-head">
                    <span class="dg-badge">gemini</span>
                    @if($results['ok'])
                        <a href="{{ $results['url'] }}" download style="font-size:.78rem;color:#7c3aed;font-weight:600;">⬇ Download ({{ $results['seconds'] }}s)</a>
                    @endif
                </div>
                @if($results['ok'])
                    <div id="dg-result-body" class="dg-result-body"><img src="{{ $results['url'] }}" alt="Gegenereerde achtergrond"></div>
                    <div id="dg-logo-toolbar" class="dg-logo-toolbar">
                        <button type="button" class="dg-logo-btn" onclick="dgCenterLogoHorizontally()">↔ Centreer horizontaal</button>
                        <button type="button" class="dg-logo-btn" onclick="dgRemoveLogo()">✕ Logo verwijderen</button>
                    </div>
                @else
                    <div style="padding:1rem;color:#b91c1c;font-size:.8rem;background:#fef2f2;">
                        <strong>Mislukt.</strong><br>{{ \Illuminate\Support\Str::limit($results['error'], 300) }}
                    </div>
                @endif
            </div>

            @if($results['ok'] && in_array($eventType, $logoEventTypes))
            <div class="dg-logo-step">
                <label class="dg-label" for="logo_upload">Stap 2 — Logo <span class="dg-hint">— optioneel, wordt vrijgesteld en boven de achtergrond geplakt (max 8 MB)</span></label>
                <input id="logo_upload" type="file" class="dg-file" accept="image/*">
                <div class="dg-logo-actions">
                    <button type="button" id="dg-logo-cutout-btn" class="dg-logo-btn" onclick="dgCutoutLogo()">✂️ Logo vrijstaand maken</button>
                </div>
                <p id="dg-logo-error" class="dg-logo-error" style="display:none;"></p>
            </div>
            @endif

            @if($results['ok'])
            <div class="dg-mask-step">
                <label class="dg-label" for="mask_select">Stap 3 — Transparantie <span class="dg-hint">— kies een masker (wit = zichtbaar, zwart = transparant), preview is direct</span></label>
                <select id="mask_select" class="dg-select" onchange="dgMaskSelected()">
                    <option value="">— geen masker —</option>
                    @foreach($masks as $m)
                        <option value="{{ $m->id }}" data-url="{{ $m->url }}">{{ $m->label }}</option>
                    @endforeach
                </select>
                <div class="dg-logo-actions">
                    <button type="button" id="dg-mask-apply-btn" class="dg-logo-btn" onclick="dgApplyMask()" disabled>✅ Toepassen op afbeelding</button>
                </div>
                <p id="dg-mask-error" class="dg-logo-error" style="display:none;"></p>

                <div class="dg-mask-sub">
                    <label class="dg-label" for="mask_new_file">Nieuw masker toevoegen aan bibliotheek</label>
                    <input id="mask_new_label" type="text" class="dg-file" placeholder="Naam (bijv. 'Ovaal middenvak')" style="margin-bottom:.5rem;">
                    <input id="mask_new_file" type="file" class="dg-file" accept="image/*">
                    <div class="dg-logo-actions">
                        <button type="button" id="dg-mask-upload-btn" class="dg-logo-btn" onclick="dgUploadMask()">⬆ Toevoegen aan bibliotheek</button>
                    </div>
                    <p id="dg-mask-upload-error" class="dg-logo-error" style="display:none;"></p>
                </div>
            </div>
            @endif
        @endif
    </div>
</div>

@push('scripts')
<script>
const dgPromptsByType = {!! Js::from($promptsByType) !!};
const dgEventTypeLabels = {!! Js::from($eventTypes) !!};

function dgEventTypeChanged() {
    const type = document.getElementById('event_type').value;
    document.getElementById('dg-prompt-template').value = dgPromptsByType[type] || '';
    document.getElementById('dg-current-type-label').textContent = '(' + (dgEventTypeLabels[type] || type) + ')';
}

function dgToggleSettings() {
    const open = document.getElementById('dg-settings').classList.toggle('open');
    const gear = document.getElementById('dg-gear');
    gear.classList.toggle('active', open);
    gear.title = open ? 'Klik om te sluiten' : 'Prompt aanpassen';
}

function dgSubmitting(form) {
    const btn = document.getElementById('dg-submit');
    btn.disabled = true;
    btn.textContent = '⏳ Bezig met genereren…';
    btn.style.opacity = '.7';
}

function dgSavePrompt() {
    const prompt = document.getElementById('dg-prompt-template').value;
    const eventType = document.getElementById('event_type').value;
    fetch("{{ route('design.prompt.update') }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
        },
        body: JSON.stringify({ key: {{ Js::from($promptKey) }}, event_type: eventType, prompt: prompt }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            dgPromptsByType[eventType] = prompt;
            const el = document.getElementById('dg-settings-saved');
            el.classList.add('show');
            setTimeout(() => el.classList.remove('show'), 2000);
        }
    });
}

let dgLogo = null; // { xPct, yPct, widthPx, aspect, rotateDeg }

function dgCutoutLogo() {
    const fileInput = document.getElementById('logo_upload');
    const errorEl = document.getElementById('dg-logo-error');
    errorEl.style.display = 'none';

    if (!fileInput.files.length) {
        errorEl.textContent = 'Kies eerst een logo-afbeelding.';
        errorEl.style.display = 'block';
        return;
    }

    const btn = document.getElementById('dg-logo-cutout-btn');
    const original = btn.textContent;
    btn.disabled = true;
    btn.textContent = '⏳ Vrijstellen…';

    const fd = new FormData();
    fd.append('logo', fileInput.files[0]);

    fetch("{{ route('design.logo.cutout') }}", {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}' },
        body: fd,
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = original;
        if (data.ok) {
            dgInitLogoEditor(data.url);
        } else {
            errorEl.textContent = 'Vrijstellen mislukt: ' + (data.error || 'onbekende fout');
            errorEl.style.display = 'block';
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.textContent = original;
        errorEl.textContent = 'Vrijstellen mislukt (netwerkfout).';
        errorEl.style.display = 'block';
    });
}

function dgInitLogoEditor(url) {
    const bgBody = document.getElementById('dg-result-body');
    if (!bgBody) return;

    const existing = document.getElementById('dg-logo-layer');
    if (existing) existing.remove();

    const img = new Image();
    img.onload = () => {
        const layer = document.createElement('div');
        layer.id = 'dg-logo-layer';
        layer.className = 'dg-logo-layer';

        const logoImg = document.createElement('img');
        logoImg.src = url;
        layer.appendChild(logoImg);

        const resizeHandle = document.createElement('div');
        resizeHandle.className = 'dg-logo-handle resize';
        layer.appendChild(resizeHandle);

        const rotateHandle = document.createElement('div');
        rotateHandle.className = 'dg-logo-handle rotate';
        layer.appendChild(rotateHandle);

        bgBody.appendChild(layer);

        dgLogo = {
            xPct: 50,
            yPct: 50,
            widthPx: Math.min(img.naturalWidth, bgBody.clientWidth * 0.35),
            aspect: img.naturalHeight / img.naturalWidth,
            rotateDeg: 0,
        };
        dgRenderLogoLayer();
        dgBindLogoDrag(layer);
        dgBindResizeHandle(resizeHandle);
        dgBindRotateHandle(rotateHandle);

        document.getElementById('dg-logo-toolbar').style.display = 'flex';
    };
    img.src = url;
}

function dgRenderLogoLayer() {
    const layer = document.getElementById('dg-logo-layer');
    if (!layer || !dgLogo) return;
    const h = dgLogo.widthPx * dgLogo.aspect;
    layer.style.width = dgLogo.widthPx + 'px';
    layer.style.height = h + 'px';
    layer.style.left = dgLogo.xPct + '%';
    layer.style.top = dgLogo.yPct + '%';
    layer.style.transform = `translate(-50%, -50%) rotate(${dgLogo.rotateDeg}deg)`;
}

function dgBindLogoDrag(layer) {
    let dragging = false, startX, startY, startXPct, startYPct, rect;
    layer.addEventListener('pointerdown', (e) => {
        if (e.target.classList.contains('dg-logo-handle')) return;
        dragging = true;
        rect = layer.parentElement.getBoundingClientRect();
        startX = e.clientX;
        startY = e.clientY;
        startXPct = dgLogo.xPct;
        startYPct = dgLogo.yPct;
        layer.setPointerCapture(e.pointerId);
    });
    layer.addEventListener('pointermove', (e) => {
        if (!dragging) return;
        const dxPct = (e.clientX - startX) / rect.width * 100;
        const dyPct = (e.clientY - startY) / rect.height * 100;
        dgLogo.xPct = Math.min(100, Math.max(0, startXPct + dxPct));
        dgLogo.yPct = Math.min(100, Math.max(0, startYPct + dyPct));
        dgRenderLogoLayer();
    });
    layer.addEventListener('pointerup', () => dragging = false);
    layer.addEventListener('pointercancel', () => dragging = false);
}

function dgBindResizeHandle(handle) {
    let dragging = false, startX, startWidth;
    handle.addEventListener('pointerdown', (e) => {
        e.stopPropagation();
        dragging = true;
        startX = e.clientX;
        startWidth = dgLogo.widthPx;
        handle.setPointerCapture(e.pointerId);
    });
    handle.addEventListener('pointermove', (e) => {
        if (!dragging) return;
        const dx = (e.clientX - startX) * 2;
        dgLogo.widthPx = Math.max(24, startWidth + dx);
        dgRenderLogoLayer();
    });
    handle.addEventListener('pointerup', () => dragging = false);
    handle.addEventListener('pointercancel', () => dragging = false);
}

function dgBindRotateHandle(handle) {
    let dragging = false;
    handle.addEventListener('pointerdown', (e) => {
        e.stopPropagation();
        dragging = true;
        handle.setPointerCapture(e.pointerId);
    });
    handle.addEventListener('pointermove', (e) => {
        if (!dragging) return;
        const layer = document.getElementById('dg-logo-layer');
        const rect = layer.getBoundingClientRect();
        const cx = rect.left + rect.width / 2;
        const cy = rect.top + rect.height / 2;
        const angle = Math.atan2(e.clientY - cy, e.clientX - cx) * 180 / Math.PI;
        dgLogo.rotateDeg = Math.round(angle + 45);
        dgRenderLogoLayer();
    });
    handle.addEventListener('pointerup', () => dragging = false);
    handle.addEventListener('pointercancel', () => dragging = false);
}

function dgCenterLogoHorizontally() {
    if (!dgLogo) return;
    dgLogo.xPct = 50;
    dgRenderLogoLayer();
}

function dgRemoveLogo() {
    const layer = document.getElementById('dg-logo-layer');
    if (layer) layer.remove();
    dgLogo = null;
    document.getElementById('dg-logo-toolbar').style.display = 'none';
}

let dgBackgroundPath = {!! Js::from($results['path'] ?? null) !!};

function dgMaskSelected() {
    const select = document.getElementById('mask_select');
    const bgBody = document.getElementById('dg-result-body');
    const img = bgBody?.querySelector('img');
    const applyBtn = document.getElementById('dg-mask-apply-btn');
    if (!img) return;

    if (!select.value) {
        img.style.maskImage = '';
        img.style.webkitMaskImage = '';
        bgBody.classList.remove('dg-mask-preview-bg');
        applyBtn.disabled = true;
        return;
    }

    const url = select.selectedOptions[0].dataset.url;
    img.style.webkitMaskImage = `url(${url})`;
    img.style.maskImage = `url(${url})`;
    img.style.webkitMaskSize = '100% 100%';
    img.style.maskSize = '100% 100%';
    img.style.webkitMaskRepeat = 'no-repeat';
    img.style.maskRepeat = 'no-repeat';
    bgBody.classList.add('dg-mask-preview-bg');
    applyBtn.disabled = false;
}

function dgApplyMask() {
    const select = document.getElementById('mask_select');
    const errorEl = document.getElementById('dg-mask-error');
    errorEl.style.display = 'none';
    if (!select.value || !dgBackgroundPath) return;

    const btn = document.getElementById('dg-mask-apply-btn');
    const original = btn.textContent;
    btn.disabled = true;
    btn.textContent = '⏳ Bezig…';

    fetch("{{ route('design.masks.apply') }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
        },
        body: JSON.stringify({ background_path: dgBackgroundPath, mask_id: select.value }),
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = original;
        if (data.ok) {
            const bgBody = document.getElementById('dg-result-body');
            const img = bgBody.querySelector('img');
            img.style.webkitMaskImage = '';
            img.style.maskImage = '';
            img.src = data.url;
            bgBody.classList.remove('dg-mask-preview-bg');
            select.value = '';
            btn.disabled = true;
            const dl = document.querySelector('.dg-result-head a[download]');
            if (dl) dl.href = data.url;
        } else {
            errorEl.textContent = 'Toepassen mislukt: ' + (data.error || 'onbekende fout');
            errorEl.style.display = 'block';
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.textContent = original;
        errorEl.textContent = 'Toepassen mislukt (netwerkfout).';
        errorEl.style.display = 'block';
    });
}

function dgUploadMask() {
    const labelInput = document.getElementById('mask_new_label');
    const fileInput = document.getElementById('mask_new_file');
    const errorEl = document.getElementById('dg-mask-upload-error');
    errorEl.style.display = 'none';

    if (!labelInput.value.trim() || !fileInput.files.length) {
        errorEl.textContent = 'Vul een naam in en kies een maskerafbeelding.';
        errorEl.style.display = 'block';
        return;
    }

    const btn = document.getElementById('dg-mask-upload-btn');
    const original = btn.textContent;
    btn.disabled = true;
    btn.textContent = '⏳ Uploaden…';

    const fd = new FormData();
    fd.append('label', labelInput.value.trim());
    fd.append('mask', fileInput.files[0]);

    fetch("{{ route('design.masks.upload') }}", {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}' },
        body: fd,
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = original;
        if (data.ok) {
            const select = document.getElementById('mask_select');
            const opt = document.createElement('option');
            opt.value = data.id;
            opt.textContent = data.label;
            opt.dataset.url = data.url;
            select.appendChild(opt);
            select.value = data.id;
            dgMaskSelected();
            labelInput.value = '';
            fileInput.value = '';
        } else {
            errorEl.textContent = 'Uploaden mislukt: ' + (data.error || 'onbekende fout');
            errorEl.style.display = 'block';
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.textContent = original;
        errorEl.textContent = 'Uploaden mislukt (netwerkfout).';
        errorEl.style.display = 'block';
    });
}

dgEventTypeChanged();
</script>
@endpush
@endsection
