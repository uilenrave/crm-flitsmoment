{{--
    JS voor de gedeelde ontwerp-tool. Verwacht dezelfde variabelen als _tool.blade.php,
    plus optioneel $limits (['backgroundMax'=>20,'logoMax'=>20,'backgroundCount'=>0,'logoCount'=>0])
    en $initialState (de opgeslagen DesignSession->state, voor booking/portal-modi).
--}}
@php
    $limits = $limits ?? null;
    $initialState = $initialState ?? null;
    $justGenerated = $justGenerated ?? false;
@endphp
<script>
window.dgConfig = {
    mode: {{ Js::from($dgMode) }},
    urls: {!! Js::from($urls) !!},
    promptKey: {{ Js::from($promptKey) }},
    promptDefault: {{ Js::from($promptDefault) }},
    canManageMasks: {{ $dgMode === 'admin' ? 'true' : 'false' }},
    limits: {!! Js::from($limits) !!},
    initialState: {!! Js::from($initialState) !!},
    justGenerated: {{ $justGenerated ? 'true' : 'false' }},
    resultsLimit: {{ ($results['limit'] ?? false) ? 'true' : 'false' }},
};

const dgPromptsByType = {!! Js::from($promptsByType) !!};
const dgEventTypeLabels = {!! Js::from($eventTypes) !!};
const dgLogoEventTypes = {!! Js::from($logoEventTypes) !!};
const dgGoogleFontLabels = {!! Js::from($googleFonts) !!};
const dgDefaultFontSlug = {{ Js::from(\App\Services\GoogleFontRegistry::DEFAULT_SLUG) }};
const dgTemplateCategories = {!! Js::from($templateCategories ?? []) !!};

function dgEventTypeChanged() {
    const type = document.getElementById('event_type').value;
    const promptField = document.getElementById('dg-prompt-template');
    if (promptField) promptField.value = dgPromptsByType[type] || '';
    // Type-label leeg laten: het "Type event"-veld is verborgen, dus een "(Bruiloft)"-suffix is verwarrend.
    const typeLabel = document.getElementById('dg-current-type-label');
    if (typeLabel) typeLabel.textContent = '';
    dgMarkDirty();
}

function dgToggleSettings() {
    const open = document.getElementById('dg-settings').classList.toggle('open');
    const gear = document.getElementById('dg-gear');
    gear.classList.toggle('active', open);
    gear.title = open ? 'Klik om te sluiten' : 'Prompt aanpassen';
}

const dgBgSubmitLabels = {
    ai:       '✨ Genereer achtergrond',
    upload:   '📤 Upload afbeelding',
    color:    '🎨 Gebruik deze kleur',
    template: '🖼️ Gebruik deze template',
};
const dgBgSubmitHints = {
    ai:       'Genereren kan 10–30 seconden duren.',
    upload:   '',
    color:    '',
    template: '',
};

function dgSetBackgroundMethod(method) {
    const methodField = document.getElementById('dg-background-method');
    if (methodField) methodField.value = method;

    document.querySelectorAll('.dg-bg-tab').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.method === method);
    });
    document.querySelectorAll('.dg-bg-panel').forEach(panel => {
        panel.style.display = panel.id === ('dg-bg-panel-' + method) ? '' : 'none';
    });

    const inputField = document.getElementById('input');
    if (inputField) inputField.required = (method === 'ai');
    const uploadField = document.getElementById('background_upload');
    if (uploadField) uploadField.required = (method === 'upload');

    const submitBtn = document.getElementById('dg-submit');
    if (submitBtn) submitBtn.textContent = dgBgSubmitLabels[method] || dgBgSubmitLabels.ai;
    const hint = document.getElementById('dg-submit-hint');
    if (hint) hint.textContent = dgBgSubmitHints[method] ?? '';

    if (method === 'template') dgInitTemplatePicker();

    dgMarkDirty();
}

let dgTemplatePickerInitialized = false;
let dgActiveTemplateCategory = null;

function dgInitTemplatePicker() {
    if (dgTemplatePickerInitialized || !dgTemplateCategories.length) return;
    dgTemplatePickerInitialized = true;

    const catWrap = document.getElementById('dg-template-categories');
    dgTemplateCategories.forEach((cat, i) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'dg-template-cat-btn' + (i === 0 ? ' active' : '');
        btn.textContent = cat.label;
        btn.dataset.slug = cat.slug;
        btn.onclick = () => dgShowTemplateCategory(cat.slug);
        catWrap.appendChild(btn);
    });

    dgShowTemplateCategory(dgTemplateCategories[0].slug);
}

function dgShowTemplateCategory(slug) {
    dgActiveTemplateCategory = slug;
    document.querySelectorAll('.dg-template-cat-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.slug === slug);
    });

    const cat = dgTemplateCategories.find(c => c.slug === slug);
    const grid = document.getElementById('dg-template-grid');
    grid.innerHTML = '';
    if (!cat) return;

    const selectedId = document.getElementById('dg-template-id').value;
    cat.items.forEach(item => {
        const div = document.createElement('div');
        div.className = 'dg-template-item' + (String(item.id) === String(selectedId) ? ' selected' : '');
        div.title = item.label;
        div.onclick = () => dgSelectTemplate(item.id, div);
        const img = document.createElement('img');
        img.src = item.url;
        img.loading = 'lazy';
        img.alt = item.label;
        div.appendChild(img);
        grid.appendChild(div);
    });
}

function dgSelectTemplate(id, el) {
    document.getElementById('dg-template-id').value = id;
    document.querySelectorAll('.dg-template-item').forEach(item => item.classList.remove('selected'));
    el.classList.add('selected');
    dgMarkDirty();
}

// ── Elementenbibliotheek (vrijgestelde elementen herbruiken als laag) ──
const dgElementCategories = {!! Js::from($elementCategories ?? []) !!};
let dgElementPickerInitialized = false;
let dgActiveElementCategory = null;

function dgToggleElementLibrary() {
    const panel = document.getElementById('dg-element-library');
    if (!panel) return;
    const open = panel.style.display === 'none' || !panel.style.display;
    panel.style.display = open ? 'block' : 'none';
    if (open) dgInitElementPicker();
}

function dgInitElementPicker() {
    if (dgElementPickerInitialized || !dgElementCategories.length) return;
    dgElementPickerInitialized = true;

    const catWrap = document.getElementById('dg-element-categories');
    dgElementCategories.forEach((cat, i) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'dg-template-cat-btn' + (i === 0 ? ' active' : '');
        btn.textContent = cat.label;
        btn.dataset.slug = cat.slug;
        btn.onclick = () => dgShowElementCategory(cat.slug);
        catWrap.appendChild(btn);
    });

    dgShowElementCategory(dgElementCategories[0].slug);
}

function dgShowElementCategory(slug) {
    dgActiveElementCategory = slug;
    document.querySelectorAll('#dg-element-categories .dg-template-cat-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.slug === slug);
    });

    const cat = dgElementCategories.find(c => c.slug === slug);
    const grid = document.getElementById('dg-element-grid');
    grid.innerHTML = '';
    if (!cat) return;

    cat.items.forEach(item => {
        const div = document.createElement('div');
        div.className = 'dg-template-item';
        div.title = item.label || 'Element';
        div.onclick = () => dgSelectLibraryElement(item.id);
        const img = document.createElement('img');
        img.src = item.url;
        img.loading = 'lazy';
        img.alt = item.label || '';
        div.appendChild(img);
        grid.appendChild(div);
    });
}

// Plaats een goedgekeurd bibliotheek-element als nieuwe laag (geen AI-call).
function dgSelectLibraryElement(id) {
    const errorEl = document.getElementById('dg-logo-error');
    if (errorEl) errorEl.style.display = 'none';

    if (dgLogos.length >= DG_MAX_LOGOS) {
        if (errorEl) { errorEl.textContent = 'Maximaal ' + DG_MAX_LOGOS + ' elementen bereikt.'; errorEl.style.display = 'block'; }
        return;
    }
    if (!dgConfig.urls.elementUse) return;

    const fd = new FormData();
    fd.append('element_id', id);

    fetch(dgConfig.urls.elementUse, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
        body: fd,
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            dgAddLogoLayer(data.url, data.path, { originalName: 'Bibliotheek-element' });
            dgMarkDirty();
        } else if (errorEl) {
            errorEl.textContent = 'Element plaatsen mislukt: ' + (data.error || 'onbekende fout');
            errorEl.style.display = 'block';
        }
    })
    .catch(() => {
        if (errorEl) { errorEl.textContent = 'Element plaatsen mislukt (netwerkfout).'; errorEl.style.display = 'block'; }
    });
}

function dgSyncColorHex(value) {
    const hexField = document.getElementById('background_color_hex');
    if (hexField) hexField.value = value;
}

function dgSyncColorPicker(value) {
    if (!/^#[0-9a-fA-F]{6}$/.test(value)) return;
    const colorField = document.getElementById('background_color');
    if (colorField) colorField.value = value;
}

const dgGenerateLabels = {
    upload:   ['📤 Afbeelding wordt verwerkt…', 'Even geduld, dit duurt meestal maar een paar seconden.'],
    color:    ['🎨 Kleur wordt toegepast…', 'Even geduld, dit duurt meestal maar een paar seconden.'],
    template: ['🖼️ Template wordt toegepast…', 'Even geduld, dit duurt meestal maar een paar seconden.'],
    ai:       ['✨ Achtergrond wordt gegenereerd…', 'Dit duurt meestal 10 tot 30 seconden.'],
};

function dgSubmitting(form, event) {
    const method = document.getElementById('dg-background-method')?.value ?? 'ai';

    // Zodra er al een achtergrond staat, bestaan stap 2/3/4 al gewoon in de pagina — dan hoeft
    // het niet via een volledige pagina-herlaad. Zo kan je gewoon doorwerken (tekst toevoegen,
    // logo verslepen, ...) terwijl de nieuwe achtergrond op de achtergrond wordt gegenereerd.
    if (dgBackgroundPath && dgConfig.urls.generate) {
        event.preventDefault();
        dgRegenerateBackground(form, method);
        return;
    }

    // Eerste keer: er is nog geen achtergrond, dus ook nog geen stap 2/3/4 in de pagina — die
    // moet de server nog opbouwen, dus hier gewoon de normale pagina-navigatie laten doorgaan.
    const btn = document.getElementById('dg-submit');
    btn.disabled = true;
    btn.textContent = '⏳ Bezig…';
    btn.style.opacity = '.7';

    const overlay = document.getElementById('dg-generating-overlay');
    const title = document.getElementById('dg-generating-title');
    const hint = document.getElementById('dg-generating-hint');
    const [titleText, hintText] = dgGenerateLabels[method] ?? dgGenerateLabels.ai;
    if (title) title.textContent = titleText;
    if (hint) hint.textContent = method === 'ai' ? hintText + ' De pagina ververst automatisch zodra het klaar is.' : hintText;
    if (overlay) overlay.classList.add('show');

    // Zonder dit: als je vlak na het toevoegen van een logo/tekst meteen op "Genereer" klikt,
    // herlaadt de pagina vóórdat de automatische opslag (elke 10s) dat logo naar de server
    // heeft gestuurd — de nieuwe achtergrond wordt dan opgeslagen zonder het logo, en het
    // verdwijnt. Fix: bij niet-opgeslagen wijzigingen eerst synchroon opslaan, dán pas submitten.
    if (dgDirty && dgConfig.urls.saveState) {
        event.preventDefault();
        fetch(dgConfig.urls.saveState, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
            },
            body: JSON.stringify(dgCollectState()),
        }).finally(() => {
            dgDirty = false;
            form.submit();
        });
    }
}

/**
 * Regenereert de achtergrond via AJAX (geen pagina-herlaad) — gebruikt zodra er al een
 * achtergrond staat, zodat je ondertussen kan doorwerken. De server-kant herkent dit via de
 * "Accept: application/json"-header en retourneert dan JSON i.p.v. de hele pagina opnieuw.
 */
function dgRegenerateBackground(form, method) {
    const btn = document.getElementById('dg-submit');
    const original = btn.textContent;
    btn.disabled = true;
    btn.textContent = '⏳ Bezig…';

    const overlay = document.getElementById('dg-generating-overlay');
    const title = document.getElementById('dg-generating-title');
    const hint = document.getElementById('dg-generating-hint');
    const errorEl = document.getElementById('dg-generate-error');
    if (errorEl) errorEl.style.display = 'none';
    const [titleText, hintText] = dgGenerateLabels[method] ?? dgGenerateLabels.ai;
    if (title) title.textContent = titleText;
    if (hint) hint.textContent = hintText + ' Je kunt gewoon doorwerken.';
    if (overlay) overlay.classList.add('show');

    fetch(form.action, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
        },
        body: new FormData(form),
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = original;
        if (overlay) overlay.classList.remove('show');

        if (data.ok) {
            dgBackgroundPath = data.path;
            const img = document.getElementById('dg-design-img');
            if (img) img.src = data.url;
            dgPushHistory(data.path, data.url);
            dgMarkDirty();
            dgScheduleMaskApply();
            if (dgConfig.limits && method === 'ai') {
                dgConfig.limits.backgroundCount++;
                dgRenderLimitHints();
            }
        } else if (data.limit) {
            dgShowLimitNotice('dg-background-limit-notice');
        } else if (errorEl) {
            errorEl.textContent = 'Genereren mislukt: ' + (data.error || 'onbekende fout');
            errorEl.style.display = 'block';
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.textContent = original;
        if (overlay) overlay.classList.remove('show');
        if (errorEl) {
            errorEl.textContent = 'Genereren mislukt (netwerkfout).';
            errorEl.style.display = 'block';
        }
    });
}

function dgSavePrompt() {
    const prompt = document.getElementById('dg-prompt-template').value;
    const eventType = document.getElementById('event_type').value;
    fetch(dgConfig.urls.promptUpdate, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
        },
        body: JSON.stringify({ key: dgConfig.promptKey, event_type: eventType, prompt: prompt }),
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

const DG_MAX_LOGOS = 8;
let dgLogos = []; // [{ id, path, url, xPct, yPct, widthPct, aspect, rotateDeg }] — widthPct t.o.v. containerbreedte (== canvasbreedte)
let dgActiveLogoId = null;

function dgSelectLogo(id) {
    dgActiveLogoId = id;
    document.querySelectorAll('.dg-logo-layer').forEach(el => {
        el.classList.toggle('selected', el.dataset.logoId === id);
    });
    dgDeselectText();
}

function dgDeselectLogo() {
    if (!dgActiveLogoId) return;
    dgActiveLogoId = null;
    document.querySelectorAll('.dg-logo-layer').forEach(el => el.classList.remove('selected'));
}

function dgCutoutLogo() {
    const fileInput = document.getElementById('logo_upload');
    const errorEl = document.getElementById('dg-logo-error');
    errorEl.style.display = 'none';

    if (dgLogos.length >= DG_MAX_LOGOS) return;

    if (!fileInput.files.length) {
        errorEl.textContent = 'Kies eerst een logo-afbeelding.';
        errorEl.style.display = 'block';
        return;
    }

    const btn = document.getElementById('dg-logo-cutout-btn');
    const original = btn.textContent;
    const originalName = fileInput.files[0].name;
    btn.disabled = true;
    btn.textContent = '⏳ Vrijstellen…';

    const overlay = document.getElementById('dg-generating-overlay');
    const title = document.getElementById('dg-generating-title');
    const hint = document.getElementById('dg-generating-hint');
    if (title) title.textContent = '✂️ Element wordt vrijgesteld…';
    if (hint) hint.textContent = 'Dit duurt meestal 5 tot 15 seconden.';
    if (overlay) overlay.classList.add('show');

    const fd = new FormData();
    fd.append('logo', fileInput.files[0]);
    const descEl = document.getElementById('dg-cutout-description');
    if (descEl && descEl.value.trim()) fd.append('description', descEl.value.trim());

    fetch(dgConfig.urls.logoCutout, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
        body: fd,
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = original;
        if (overlay) overlay.classList.remove('show');
        if (data.ok) {
            fileInput.value = '';
            dgAddLogoLayer(data.url, data.path, { originalName: originalName });
            dgMarkDirty();
            if (dgConfig.limits) {
                dgConfig.limits.logoCount++;
                dgRenderLimitHints();
            }
        } else if (data.limit) {
            dgShowLimitNotice('dg-logo-limit-notice');
        } else {
            errorEl.textContent = 'Vrijstellen mislukt: ' + (data.error || 'onbekende fout');
            errorEl.style.display = 'block';
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.textContent = original;
        if (overlay) overlay.classList.remove('show');
        errorEl.textContent = 'Vrijstellen mislukt (netwerkfout).';
        errorEl.style.display = 'block';
    });
}

function dgUploadLogoDirect() {
    const fileInput = document.getElementById('logo_upload');
    const errorEl = document.getElementById('dg-logo-error');
    errorEl.style.display = 'none';

    if (dgLogos.length >= DG_MAX_LOGOS) return;

    if (!fileInput.files.length) {
        errorEl.textContent = 'Kies eerst een logo-afbeelding.';
        errorEl.style.display = 'block';
        return;
    }

    const btn = document.getElementById('dg-logo-upload-btn');
    const original = btn.textContent;
    const originalName = fileInput.files[0].name;
    btn.disabled = true;
    btn.textContent = '⏳ Toevoegen…';

    const overlay = document.getElementById('dg-generating-overlay');
    const title = document.getElementById('dg-generating-title');
    const hint = document.getElementById('dg-generating-hint');
    if (title) title.textContent = '➕ Logo wordt toegevoegd…';
    if (hint) hint.textContent = 'Dit duurt meestal maar een paar seconden.';
    if (overlay) overlay.classList.add('show');

    const fd = new FormData();
    fd.append('logo', fileInput.files[0]);

    fetch(dgConfig.urls.logoUpload, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
        body: fd,
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = original;
        if (overlay) overlay.classList.remove('show');
        if (data.ok) {
            fileInput.value = '';
            dgAddLogoLayer(data.url, data.path, { originalName: originalName });
            dgMarkDirty();
        } else {
            errorEl.textContent = 'Toevoegen mislukt: ' + (data.error || 'onbekende fout');
            errorEl.style.display = 'block';
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.textContent = original;
        if (overlay) overlay.classList.remove('show');
        errorEl.textContent = 'Toevoegen mislukt (netwerkfout).';
        errorEl.style.display = 'block';
    });
}

function dgAddLogoLayer(url, path, savedState, autoSelect = true) {
    const bgBody = document.getElementById('dg-result-body');
    if (!bgBody) return;
    if (dgLogos.length >= DG_MAX_LOGOS) return;

    const img = new Image();
    img.onload = () => {
        const id = 'l' + Date.now() + Math.floor(Math.random() * 1000);
        const layer = document.createElement('div');
        layer.id = 'dg-logo-layer-' + id;
        layer.className = 'dg-logo-layer';
        layer.dataset.logoId = id;

        const logoImg = document.createElement('img');
        logoImg.src = url;
        layer.appendChild(logoImg);

        const resizeHandle = document.createElement('div');
        resizeHandle.className = 'dg-logo-handle resize';
        layer.appendChild(resizeHandle);

        const rotateStem = document.createElement('div');
        rotateStem.className = 'dg-logo-rotate-stem';
        layer.appendChild(rotateStem);

        const rotateHandle = document.createElement('div');
        rotateHandle.className = 'dg-logo-handle rotate';
        layer.appendChild(rotateHandle);

        bgBody.appendChild(layer);

        const logo = {
            id: id,
            path: path,
            url: url,
            originalName: savedState?.originalName ?? 'Logo',
            xPct: savedState?.xPct ?? (50 + dgLogos.length * 8),
            yPct: savedState?.yPct ?? (50 + dgLogos.length * 8),
            widthPct: savedState?.widthPct ?? 35,
            aspect: img.naturalHeight / img.naturalWidth,
            rotateDeg: savedState?.rotateDeg ?? 0,
        };
        dgLogos.push(logo);

        dgRenderLogoLayer(logo);
        dgBindLogoDrag(layer, logo);
        dgBindResizeHandle(resizeHandle, logo);
        dgBindRotateHandle(rotateHandle, layer, logo);
        if (autoSelect) dgSelectLogo(id);

        dgRenderLogoList();
        dgUpdateLogoAddState();
    };
    img.src = url;
}

function dgRenderLogoLayer(logo) {
    const layer = document.getElementById('dg-logo-layer-' + logo.id);
    if (!layer) return;
    const containerWidth = layer.parentElement.clientWidth;
    const widthPx = containerWidth * logo.widthPct / 100;
    const h = widthPx * logo.aspect;
    layer.style.width = widthPx + 'px';
    layer.style.height = h + 'px';
    layer.style.left = logo.xPct + '%';
    layer.style.top = logo.yPct + '%';
    layer.style.transform = `translate(-50%, -50%) rotate(${logo.rotateDeg}deg)`;
}

function dgBindLogoDrag(layer, logo) {
    let dragging = false, startX, startY, startXPct, startYPct, rect;
    layer.addEventListener('pointerdown', (e) => {
        if (e.target.classList.contains('dg-logo-handle')) return;
        e.stopPropagation();
        dgSelectLogo(logo.id);
        dragging = true;
        rect = layer.parentElement.getBoundingClientRect();
        startX = e.clientX;
        startY = e.clientY;
        startXPct = logo.xPct;
        startYPct = logo.yPct;
        layer.setPointerCapture(e.pointerId);
    });
    layer.addEventListener('pointermove', (e) => {
        if (!dragging) return;
        const dxPct = (e.clientX - startX) / rect.width * 100;
        const dyPct = (e.clientY - startY) / rect.height * 100;
        logo.xPct = Math.min(100, Math.max(0, startXPct + dxPct));
        logo.yPct = Math.min(100, Math.max(0, startYPct + dyPct));
        dgRenderLogoLayer(logo);
    });
    layer.addEventListener('pointerup', () => { dragging = false; dgMarkDirty(); });
    layer.addEventListener('pointercancel', () => dragging = false);
}

function dgBindResizeHandle(handle, logo) {
    let dragging = false, startX, startWidthPct, containerWidth;
    handle.addEventListener('pointerdown', (e) => {
        e.stopPropagation();
        dragging = true;
        startX = e.clientX;
        startWidthPct = logo.widthPct;
        containerWidth = handle.parentElement.parentElement.clientWidth;
        handle.setPointerCapture(e.pointerId);
    });
    handle.addEventListener('pointermove', (e) => {
        if (!dragging) return;
        const dxPct = ((e.clientX - startX) * 2 / containerWidth) * 100;
        logo.widthPct = Math.max(4, startWidthPct + dxPct);
        dgRenderLogoLayer(logo);
    });
    handle.addEventListener('pointerup', () => { dragging = false; dgMarkDirty(); });
    handle.addEventListener('pointercancel', () => dragging = false);
}

function dgBindRotateHandle(handle, layer, logo) {
    let dragging = false;
    handle.addEventListener('pointerdown', (e) => {
        e.stopPropagation();
        dragging = true;
        handle.setPointerCapture(e.pointerId);
    });
    handle.addEventListener('pointermove', (e) => {
        if (!dragging) return;
        const rect = layer.getBoundingClientRect();
        const cx = rect.left + rect.width / 2;
        const cy = rect.top + rect.height / 2;
        const angle = Math.atan2(e.clientY - cy, e.clientX - cx) * 180 / Math.PI;
        // De handle rust (bij rotateDeg=0) recht boven het midden, oftewel op -90°.
        const rawDeg = angle + 90;
        // Snap op 12 standen (elke 30°) zodat een logo niet vrij rond te draaien is.
        logo.rotateDeg = Math.round(rawDeg / 30) * 30;
        dgRenderLogoLayer(logo);
    });
    handle.addEventListener('pointerup', () => { dragging = false; dgMarkDirty(); });
    handle.addEventListener('pointercancel', () => dragging = false);
}

function dgCenterLogo(id) {
    const logo = dgLogos.find(l => l.id === id);
    if (!logo) return;
    logo.xPct = 50;
    dgRenderLogoLayer(logo);
    dgMarkDirty();
}

function dgRemoveLogo(id) {
    const layer = document.getElementById('dg-logo-layer-' + id);
    if (layer) layer.remove();
    dgLogos = dgLogos.filter(l => l.id !== id);
    dgRenderLogoList();
    dgUpdateLogoAddState();
    dgMarkDirty();
}

function dgRenderLogoList() {
    const list = document.getElementById('dg-logo-list');
    if (!list) return;
    list.innerHTML = '';
    dgLogos.forEach((logo, idx) => {
        const item = document.createElement('div');
        item.className = 'dg-logo-list-item';

        const img = document.createElement('img');
        img.src = logo.url;
        item.appendChild(img);

        const label = document.createElement('span');
        label.className = 'label';
        label.textContent = logo.originalName;
        label.title = logo.originalName;
        item.appendChild(label);

        const centerBtn = document.createElement('button');
        centerBtn.type = 'button';
        centerBtn.className = 'dg-logo-list-btn';
        centerBtn.textContent = '↔ Centreren';
        centerBtn.onclick = () => dgCenterLogo(logo.id);
        item.appendChild(centerBtn);

        const delBtn = document.createElement('button');
        delBtn.type = 'button';
        delBtn.className = 'dg-logo-list-btn danger';
        delBtn.textContent = '✕ Verwijderen';
        delBtn.onclick = () => dgRemoveLogo(logo.id);
        item.appendChild(delBtn);

        list.appendChild(item);
    });
}

function dgUpdateLogoAddState() {
    const atMax = dgLogos.length >= DG_MAX_LOGOS;
    const btn = document.getElementById('dg-logo-cutout-btn');
    const uploadBtn = document.getElementById('dg-logo-upload-btn');
    const fileInput = document.getElementById('logo_upload');
    const notice = document.getElementById('dg-logo-max-notice');
    if (btn) btn.disabled = atMax;
    if (uploadBtn) uploadBtn.disabled = atMax;
    if (fileInput) fileInput.disabled = atMax;
    if (notice) notice.style.display = atMax ? 'block' : 'none';
}

function dgShowLimitNotice(elId) {
    const el = document.getElementById(elId);
    if (!el) return;
    el.innerHTML = 'We zien dat je moeite hebt om de goede afbeelding te maken. '
        + 'Neem gerust <a href="' + (dgConfig.urls.helpLink || '#') + '">contact met ons op voor een gratis ontwerp op maat</a>.';
    el.style.display = 'block';
}

// ── Tekst-lagen ──

let dgTexts = []; // [{ id, content, color, fontSlug, fontSizePct, xPct, yPct }] — fontSizePct t.o.v. containerbreedte
let dgActiveTextId = null;

function dgFontFamilyCss(slug) {
    const label = dgGoogleFontLabels[slug] || dgGoogleFontLabels[dgDefaultFontSlug];
    return `'${label}', sans-serif`;
}

function dgAddText(savedState) {
    const bgBody = document.getElementById('dg-result-body');
    if (!bgBody) return;

    const id = 't' + Date.now() + Math.floor(Math.random() * 1000);
    const text = {
        id: id,
        content: savedState?.content ?? 'Tekst',
        color: savedState?.color ?? '#000000',
        fontSlug: savedState?.fontSlug ?? dgDefaultFontSlug,
        fontSizePct: savedState?.fontSizePct ?? 5,
        xPct: savedState?.xPct ?? 50,
        yPct: savedState?.yPct ?? 50,
        rotateDeg: savedState?.rotateDeg ?? 0,
        fontWeight: savedState?.fontWeight ?? 400,
        letterSpacingEm: savedState?.letterSpacingEm ?? 0,
    };
    dgTexts.push(text);
    dgCreateTextLayer(text);
    dgSelectText(id);
    dgMarkDirty();
}

function dgCreateTextLayer(text) {
    const bgBody = document.getElementById('dg-result-body');
    if (!bgBody) return;

    const layer = document.createElement('div');
    layer.id = 'dg-text-layer-' + text.id;
    layer.className = 'dg-text-layer';
    layer.dataset.textId = text.id;

    // Tekst-inhoud in een eigen span, zodat de rotate-handle/stem geen deel van de tekst worden.
    const contentSpan = document.createElement('span');
    contentSpan.className = 'dg-text-content-span';
    layer.appendChild(contentSpan);

    const rotateStem = document.createElement('div');
    rotateStem.className = 'dg-logo-rotate-stem';
    layer.appendChild(rotateStem);

    const rotateHandle = document.createElement('div');
    rotateHandle.className = 'dg-logo-handle rotate';
    layer.appendChild(rotateHandle);

    bgBody.appendChild(layer);

    dgRenderTextLayer(text);
    dgBindTextDrag(layer, text);
    dgBindTextRotate(rotateHandle, layer, text);
}

function dgRenderTextLayer(text) {
    const layer = document.getElementById('dg-text-layer-' + text.id);
    if (!layer) return;
    const containerWidth = layer.parentElement.clientWidth;
    const span = layer.querySelector('.dg-text-content-span');
    if (span) span.textContent = text.content;
    layer.style.left = text.xPct + '%';
    layer.style.top = text.yPct + '%';
    layer.style.transform = `translate(-50%, -50%) rotate(${text.rotateDeg || 0}deg)`;
    layer.style.color = text.color;
    layer.style.fontFamily = dgFontFamilyCss(text.fontSlug);
    layer.style.fontSize = (containerWidth * text.fontSizePct / 100) + 'px';
    layer.style.fontWeight = text.fontWeight || 400;
    // Letterafstand in em (relatief aan de fontgrootte) → schaalonafhankelijk, matcht de server-render.
    layer.style.letterSpacing = (text.letterSpacingEm || 0) + 'em';
    // Niet nep-vetten/verdunnen: als het gekozen gewicht niet als echt TTF-bestand bestaat,
    // moet de browser (net als de server-render) terugvallen op het dichtstbijzijnde échte
    // gewicht i.p.v. een gesynthetiseerde variant — zo blijven preview en definitieve PNG gelijk.
    layer.style.fontSynthesis = 'none';
    layer.classList.toggle('selected', dgActiveTextId === text.id);
}

// Zelfde snap-rotatie (elke 30°) als bij logo's, maar op een tekstlaag.
function dgBindTextRotate(handle, layer, text) {
    let dragging = false;
    handle.addEventListener('pointerdown', (e) => {
        e.stopPropagation();
        dgSelectText(text.id);
        dragging = true;
        handle.setPointerCapture(e.pointerId);
    });
    handle.addEventListener('pointermove', (e) => {
        if (!dragging) return;
        const rect = layer.getBoundingClientRect();
        const cx = rect.left + rect.width / 2;
        const cy = rect.top + rect.height / 2;
        const angle = Math.atan2(e.clientY - cy, e.clientX - cx) * 180 / Math.PI;
        const rawDeg = angle + 90; // handle rust recht boven het midden (-90°) bij rotateDeg=0
        text.rotateDeg = Math.round(rawDeg / 30) * 30;
        dgRenderTextLayer(text);
    });
    handle.addEventListener('pointerup', () => { dragging = false; dgMarkDirty(); });
    handle.addEventListener('pointercancel', () => dragging = false);
}

function dgBindTextDrag(layer, text) {
    let dragging = false, moved = false, startX, startY, startXPct, startYPct, rect;
    layer.addEventListener('pointerdown', (e) => {
        if (e.target.classList.contains('dg-logo-handle')) return; // rotate-handle heeft eigen afhandeling
        e.stopPropagation();
        dragging = true;
        moved = false;
        rect = layer.parentElement.getBoundingClientRect();
        startX = e.clientX;
        startY = e.clientY;
        startXPct = text.xPct;
        startYPct = text.yPct;
        layer.setPointerCapture(e.pointerId);
    });
    layer.addEventListener('pointermove', (e) => {
        if (!dragging) return;
        const dxPct = (e.clientX - startX) / rect.width * 100;
        const dyPct = (e.clientY - startY) / rect.height * 100;
        if (Math.abs(dxPct) > 0.3 || Math.abs(dyPct) > 0.3) moved = true;
        text.xPct = Math.min(100, Math.max(0, startXPct + dxPct));
        text.yPct = Math.min(100, Math.max(0, startYPct + dyPct));
        dgRenderTextLayer(text);
    });
    layer.addEventListener('pointerup', () => {
        dragging = false;
        if (moved) {
            dgMarkDirty();
        } else {
            dgSelectText(text.id);
        }
    });
    layer.addEventListener('pointercancel', () => dragging = false);
}

function dgSelectText(id) {
    const text = dgTexts.find(t => t.id === id);
    if (!text) return;
    dgActiveTextId = id;
    dgDeselectLogo();

    document.querySelectorAll('.dg-text-layer').forEach(el => {
        el.classList.toggle('selected', el.dataset.textId === id);
    });

    const panel = document.getElementById('dg-text-settings');
    const hint = document.getElementById('dg-text-hint');
    if (panel) panel.style.display = 'block';
    if (hint) hint.style.display = 'none';

    const contentEl = document.getElementById('dg-text-content');
    const colorEl = document.getElementById('dg-text-color');
    const sizeEl = document.getElementById('dg-text-size');
    const weightEl = document.getElementById('dg-text-weight');
    const spacingEl = document.getElementById('dg-text-spacing');
    if (contentEl) contentEl.value = text.content;
    if (colorEl) colorEl.value = text.color;
    if (sizeEl) sizeEl.value = text.fontSizePct;
    if (weightEl) weightEl.value = text.fontWeight || 400;
    if (spacingEl) spacingEl.value = text.letterSpacingEm ?? 0;
    dgUpdateTextSizeReadout(text.fontSizePct);
    dgUpdateTextSpacingReadout(text.letterSpacingEm ?? 0);
    dgSyncFontPickerDisplay(text.fontSlug);
}

function dgDeselectText() {
    if (!dgActiveTextId) return;
    dgActiveTextId = null;
    document.querySelectorAll('.dg-text-layer').forEach(el => el.classList.remove('selected'));
    const panel = document.getElementById('dg-text-settings');
    const hint = document.getElementById('dg-text-hint');
    if (panel) panel.style.display = 'none';
    if (hint) hint.style.display = 'block';
}

// ── Custom font-dropdown (native <select>-opties stylen per item werkt onbetrouwbaar in browsers) ──

function dgToggleFontPicker() {
    document.getElementById('dg-font-picker-list')?.classList.toggle('open');
}

function dgPickFont(slug, label) {
    const hidden = document.getElementById('dg-text-font');
    if (hidden) hidden.value = slug;
    dgSyncFontPickerDisplay(slug, label);
    document.getElementById('dg-font-picker-list')?.classList.remove('open');
    dgUpdateActiveText();
}

function dgSyncFontPickerDisplay(slug, label) {
    label = label ?? dgGoogleFontLabels[slug] ?? slug;
    const btnLabel = document.getElementById('dg-font-picker-label');
    const hidden = document.getElementById('dg-text-font');
    if (hidden) hidden.value = slug;
    if (btnLabel) {
        btnLabel.textContent = label;
        btnLabel.style.fontFamily = `'${label}', sans-serif`;
    }
}

document.addEventListener('click', (e) => {
    const picker = document.getElementById('dg-font-picker');
    const list = document.getElementById('dg-font-picker-list');
    if (picker && list && !picker.contains(e.target)) {
        list.classList.remove('open');
    }
});

// Klik buiten een logo/tekst-laag (en buiten hun hele stap-paneel, incl. de "toevoegen"-knoppen)
// deselecteert weer — zodat de resize/rotate-knopjes van een logo en het randje van een tekst
// niet blijven "hangen". Beschermde zone = de volledige stap-container, niet alleen het
// instellingenpaneel, anders deselecteert bijv. de "➕ Tekst toevoegen"-knop zichzelf meteen weer.
document.addEventListener('click', (e) => {
    if (!e.target.closest('.dg-logo-layer') && !e.target.closest('.dg-logo-step')) {
        dgDeselectLogo();
    }
    if (!e.target.closest('.dg-text-layer') && !e.target.closest('.dg-text-step')) {
        dgDeselectText();
    }
});

function dgUpdateActiveText() {
    const text = dgTexts.find(t => t.id === dgActiveTextId);
    if (!text) return;
    text.content = document.getElementById('dg-text-content')?.value || 'Tekst';
    text.color = document.getElementById('dg-text-color')?.value || text.color;
    text.fontSlug = document.getElementById('dg-text-font')?.value || text.fontSlug;
    text.fontSizePct = parseFloat(document.getElementById('dg-text-size')?.value) || text.fontSizePct;
    text.fontWeight = parseInt(document.getElementById('dg-text-weight')?.value) || 400;
    const spacing = parseFloat(document.getElementById('dg-text-spacing')?.value);
    text.letterSpacingEm = Number.isFinite(spacing) ? spacing : (text.letterSpacingEm ?? 0);
    dgUpdateTextSizeReadout(text.fontSizePct);
    dgUpdateTextSpacingReadout(text.letterSpacingEm);
    dgRenderTextLayer(text);
    dgMarkDirty();
}

// Toont het huidige grootte-cijfer naast de schuifregelaar.
function dgUpdateTextSizeReadout(pct) {
    const el = document.getElementById('dg-text-size-val');
    if (el) el.textContent = (pct === null || pct === undefined) ? '—' : Number(pct).toFixed(1);
}

// Toont de huidige letterafstand naast de schuifregelaar.
function dgUpdateTextSpacingReadout(em) {
    const el = document.getElementById('dg-text-spacing-val');
    if (el) el.textContent = (em === null || em === undefined) ? '—' : Number(em).toFixed(2);
}

function dgCenterActiveText() {
    const text = dgTexts.find(t => t.id === dgActiveTextId);
    if (!text) return;
    text.xPct = 50;
    dgRenderTextLayer(text);
    dgMarkDirty();
}

function dgRemoveActiveText() {
    if (!dgActiveTextId) return;
    const layer = document.getElementById('dg-text-layer-' + dgActiveTextId);
    if (layer) layer.remove();
    dgTexts = dgTexts.filter(t => t.id !== dgActiveTextId);
    dgActiveTextId = null;

    const panel = document.getElementById('dg-text-settings');
    const hint = document.getElementById('dg-text-hint');
    if (panel) panel.style.display = 'none';
    if (hint) hint.style.display = 'block';
    dgMarkDirty();
}

let dgBackgroundPath = {!! Js::from($results['path'] ?? null) !!};
let dgMasks = {!! Js::from($masks) !!};
let dgSelectedMaskId = null;

// ── Geschiedenis van gegenereerde achtergronden (terugstappen naar een vorig ontwerp) ──
const DG_MAX_HISTORY = 10;
let dgBackgroundHistory = []; // [{ path, url }] nieuwste eerst

function dgPushHistory(path, url) {
    if (!path || !url) return;
    dgBackgroundHistory = dgBackgroundHistory.filter(h => h.path !== path); // dedupe
    dgBackgroundHistory.unshift({ path, url });
    if (dgBackgroundHistory.length > DG_MAX_HISTORY) dgBackgroundHistory = dgBackgroundHistory.slice(0, DG_MAX_HISTORY);
    dgRenderHistory();
}

function dgRenderHistory() {
    const wrap = document.getElementById('dg-history');
    const strip = document.getElementById('dg-history-strip');
    if (!wrap || !strip) return;

    // Alleen tonen als er iets valt terug te stappen (meer dan alleen de huidige).
    if (dgBackgroundHistory.length < 2) { wrap.style.display = 'none'; return; }
    wrap.style.display = 'block';

    strip.innerHTML = '';
    dgBackgroundHistory.forEach(h => {
        const div = document.createElement('div');
        div.className = 'dg-history-item' + (h.path === dgBackgroundPath ? ' active' : '');
        div.title = 'Gebruik dit ontwerp';
        div.onclick = () => dgSelectHistory(h.path, h.url);
        const img = document.createElement('img');
        img.src = h.url;
        img.loading = 'lazy';
        div.appendChild(img);
        strip.appendChild(div);
    });
}

function dgSelectHistory(path, url) {
    if (path === dgBackgroundPath) return;
    dgBackgroundPath = path;
    const img = document.getElementById('dg-design-img');
    if (img) img.src = url;
    dgRenderHistory();
    dgMarkDirty();
    dgScheduleMaskApply();
}

function dgRenderMaskGallery() {
    const gallery = document.getElementById('dg-mask-gallery');
    if (!gallery) return;
    gallery.innerHTML = '';
    dgMasks.forEach(m => {
        const wrap = document.createElement('div');

        const thumb = document.createElement('div');
        thumb.className = 'dg-mask-thumb' + (dgSelectedMaskId === m.id ? ' selected' : '');
        thumb.title = m.label;
        thumb.onclick = () => dgSelectMask(m.id);
        const img = document.createElement('img');
        img.src = m.thumbnailUrl;
        thumb.appendChild(img);
        wrap.appendChild(thumb);

        const label = document.createElement('div');
        label.className = 'dg-mask-thumb-label';
        label.textContent = m.label;
        wrap.appendChild(label);

        gallery.appendChild(wrap);
    });
}

function dgAutoSelectMaskIfNeeded() {
    if (dgSelectedMaskId || !dgMasks.length) return;
    if (!document.getElementById('dg-result-body')) return;
    dgSelectMask(dgMasks[0].id);
}

function dgSelectMask(id) {
    const bgBody = document.getElementById('dg-result-body');
    const img = document.getElementById('dg-design-img');
    if (!img) return;

    dgSelectedMaskId = id;
    dgRenderMaskGallery();

    const mask = dgMasks.find(m => m.id === dgSelectedMaskId);

    if (!mask) {
        img.style.webkitMaskImage = '';
        img.style.maskImage = '';
        return;
    }

    img.style.webkitMaskImage = `url(${mask.url})`;
    img.style.maskImage = `url(${mask.url})`;
    img.style.webkitMaskSize = '100% 100%';
    img.style.maskSize = '100% 100%';
    img.style.webkitMaskRepeat = 'no-repeat';
    img.style.maskRepeat = 'no-repeat';
    // Zonder dit interpreteert de browser soms de LUMINANTIE van het maskerbestand i.p.v. het alfakanaal,
    // waardoor ondoorzichtige-maar-gekleurde delen van het masker als deels doorzichtig (~grijswaarde) ogen —
    // dat zag eruit als "de hele strip wordt 50% transparant".
    img.style.maskMode = 'alpha';
    img.style.webkitMaskMode = 'alpha';

    dgMarkDirty();
    dgScheduleMaskApply();
    dgTogglePreviewMode();
}

/**
 * "Preview modus" plakt de aan het masker gekoppelde voorbeeldfoto's-afbeelding achter het
 * ontwerp, zodat je door de transparante foto-vensters heen ziet hoe het eruitziet met foto's
 * erin. De aan/uit-status wordt meegenomen in de sessie-state (zie dgCollectState).
 */
function dgTogglePreviewMode() {
    const toggle = document.getElementById('dg-preview-mode-toggle');
    const layer = document.getElementById('dg-preview-photos-layer');
    if (!toggle || !layer) return;

    if (!toggle.checked) {
        layer.style.display = 'none';
        dgMarkDirty();
        return;
    }

    const mask = dgMasks.find(m => m.id === dgSelectedMaskId);
    if (mask && mask.previewPhotosUrl) {
        layer.src = mask.previewPhotosUrl;
        layer.style.display = 'block';
        dgMarkDirty();
    } else {
        alert('Dit masker heeft geen voorbeeldfoto\'s. Voeg ze toe via de maskerbibliotheek (🎭 Maskers).');
        toggle.checked = false;
        layer.style.display = 'none';
    }
}

let dgMaskApplyTimer = null;

function dgScheduleMaskApply() {
    if (!dgSelectedMaskId || !dgBackgroundPath) return;
    if (dgMaskApplyTimer) clearTimeout(dgMaskApplyTimer);
    dgMaskApplyTimer = setTimeout(dgApplyMask, 500);
}

/**
 * De preview zelf is al direct zichtbaar via de CSS-mask (dgSelectMask/dgBorderColorChanged).
 * Deze functie bakt op de achtergrond een echte (server-side) versie zodat de downloadlink een
 * kloppend plat PNG geeft — de klant/admin hoeft hier nergens voor te klikken, het gebeurt
 * automatisch (gedebounced) zodra het masker of de randkleur wijzigt.
 */
function dgApplyMask() {
    const errorEl = document.getElementById('dg-mask-error');
    const statusEl = document.getElementById('dg-mask-apply-status');
    errorEl.style.display = 'none';
    if (!dgSelectedMaskId || !dgBackgroundPath) return;

    if (statusEl) statusEl.style.display = 'block';

    const payload = { background_path: dgBackgroundPath, mask_id: dgSelectedMaskId };

    fetch(dgConfig.urls.masksApply, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
        },
        body: JSON.stringify(payload),
    })
    .then(r => r.json())
    .then(data => {
        if (statusEl) statusEl.style.display = 'none';
        if (!data.ok) {
            errorEl.textContent = 'Toepassen mislukt: ' + (data.error || 'onbekende fout');
            errorEl.style.display = 'block';
        }
    })
    .catch(() => {
        if (statusEl) statusEl.style.display = 'none';
        errorEl.textContent = 'Toepassen mislukt (netwerkfout).';
        errorEl.style.display = 'block';
    });
}

// ── Maskers-beheervenster (alleen admin) ──

function dgOpenMasksModal() {
    dgRenderMaskList();
    document.getElementById('dg-masks-modal-overlay').classList.add('open');
}

function dgCloseMasksModal() {
    document.getElementById('dg-masks-modal-overlay').classList.remove('open');
}

function dgRenderMaskList() {
    const list = document.getElementById('dg-mask-list');
    if (!list) return;
    list.innerHTML = '';
    if (!dgMasks.length) {
        list.innerHTML = '<p class="dg-hint">Nog geen maskers toegevoegd.</p>';
        return;
    }
    dgMasks.forEach(m => {
        const item = document.createElement('div');
        item.className = 'dg-mask-list-item';

        const img = document.createElement('img');
        img.src = m.thumbnailUrl;
        item.appendChild(img);

        const label = document.createElement('span');
        label.className = 'label';
        label.textContent = m.label;
        item.appendChild(label);

        if (m.previewPhotosUrl) {
            const tag = document.createElement('span');
            tag.className = 'svg-tag';
            tag.textContent = 'preview-foto\'s';
            item.appendChild(tag);
        }

        const del = document.createElement('button');
        del.type = 'button';
        del.className = 'dg-mask-delete';
        del.textContent = '🗑 Verwijderen';
        del.onclick = () => dgDeleteMask(m.id);
        item.appendChild(del);

        list.appendChild(item);
    });
}

function dgUploadMaskToLibrary() {
    const labelInput = document.getElementById('modal_mask_label');
    const fileInput = document.getElementById('modal_mask_file');
    const thumbInput = document.getElementById('modal_mask_thumb');
    const previewPhotosInput = document.getElementById('modal_mask_preview_photos');
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
    if (thumbInput.files.length) fd.append('thumbnail', thumbInput.files[0]);
    if (previewPhotosInput.files.length) fd.append('preview_photos', previewPhotosInput.files[0]);

    fetch(dgConfig.urls.masksUpload, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
        body: fd,
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = original;
        if (data.ok) {
            dgMasks.push({
                id: data.id, label: data.label, url: data.url, thumbnailUrl: data.thumbnailUrl,
                previewPhotosUrl: data.previewPhotosUrl,
            });
            dgRenderMaskList();
            dgRenderMaskGallery();
            dgAutoSelectMaskIfNeeded();
            labelInput.value = '';
            fileInput.value = '';
            thumbInput.value = '';
            previewPhotosInput.value = '';
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

function dgDeleteMask(id) {
    if (!confirm('Dit masker verwijderen?')) return;
    fetch(`${dgConfig.urls.masksDestroyBase}/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            dgMasks = dgMasks.filter(m => m.id !== id);
            if (dgSelectedMaskId === id) dgSelectedMaskId = null;
            dgRenderMaskList();
            dgRenderMaskGallery();
            dgAutoSelectMaskIfNeeded();
        }
    });
}

// ── Autosave (alleen actief als dgConfig.urls.saveState gezet is — booking/portal-modi) ──

let dgDirty = false;

function dgMarkDirty() {
    dgDirty = true;
    dgSetSaveBadge('dirty');
}

function dgSetSaveBadge(state) {
    const badge = document.getElementById('dg-save-badge');
    if (!badge || !dgConfig.urls.saveState) return;
    badge.classList.add('show');
    badge.classList.toggle('dirty', state === 'dirty');
    badge.classList.toggle('saved', state === 'saved');
    // "Niet opgeslagen" toont een draaiende spinner i.p.v. een stilstaand bolletje, zodat het
    // voelt alsof hij actief aan het opslaan is (de automatische opslag volgt binnen 10s).
    badge.innerHTML = state === 'saved'
        ? '✓ Opgeslagen'
        : '<span class="dg-spinner-sm"></span> Niet opgeslagen';
}

function dgCollectState() {
    return {
        event_type: document.getElementById('event_type')?.value,
        input: document.getElementById('input')?.value,
        state: {
            backgroundPath: dgBackgroundPath,
            backgroundHistory: dgBackgroundHistory,
            maskId: dgSelectedMaskId,
            logos: dgLogos.map(l => ({
                path: l.path, xPct: l.xPct, yPct: l.yPct,
                widthPct: l.widthPct, rotateDeg: l.rotateDeg, originalName: l.originalName,
            })),
            texts: dgTexts.map(t => ({
                content: t.content, color: t.color, fontSlug: t.fontSlug,
                fontSizePct: t.fontSizePct, xPct: t.xPct, yPct: t.yPct, rotateDeg: t.rotateDeg,
                fontWeight: t.fontWeight, letterSpacingEm: t.letterSpacingEm ?? 0,
            })),
            previewMode: document.getElementById('dg-preview-mode-toggle')?.checked ?? false,
            step: dgConfig.mode === 'portal' ? dgWizardStep : undefined,
        },
    };
}

function dgAutosaveTick() {
    if (!dgDirty || !dgConfig.urls.saveState) return;
    dgDirty = false;

    fetch(dgConfig.urls.saveState, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
        },
        body: JSON.stringify(dgCollectState()),
    }).then(() => {
        dgSetSaveBadge('saved');
    });
}

if (dgConfig.urls.saveState) {
    setInterval(dgAutosaveTick, 10000);
    document.getElementById('input')?.addEventListener('input', dgMarkDirty);
}

// ── Initialisatie ──

function dgRestoreInitialState() {
    const s = dgConfig.initialState;
    if (!s) return;

    if (Array.isArray(s.backgroundHistory)) {
        dgBackgroundHistory = s.backgroundHistory.slice(0, DG_MAX_HISTORY);
    }

    if (s.maskId) dgSelectedMaskId = s.maskId;

    const savedLogos = s.logos ?? (s.logo ? [s.logo] : []); // s.logo = oude enkelvoudige vorm, voor lopende sessies van vóór de meerdere-logo's-update
    if (savedLogos.length && document.getElementById('dg-result-body')) {
        savedLogos.slice(0, DG_MAX_LOGOS).forEach(logoState => {
            if (!logoState?.path) return;
            const url = logoState.path.startsWith('http') ? logoState.path : ('/storage/' + logoState.path);
            dgAddLogoLayer(url, logoState.path, logoState, false);
        });
    }

    if (s.texts?.length && document.getElementById('dg-result-body')) {
        s.texts.forEach(textState => {
            if (!textState?.content) return;
            const text = {
                id: 't' + Date.now() + Math.floor(Math.random() * 1000),
                content: textState.content,
                color: textState.color ?? '#000000',
                fontSlug: textState.fontSlug ?? dgDefaultFontSlug,
                fontSizePct: textState.fontSizePct ?? 5,
                xPct: textState.xPct ?? 50,
                yPct: textState.yPct ?? 50,
                rotateDeg: textState.rotateDeg ?? 0,
                fontWeight: textState.fontWeight ?? 400,
                letterSpacingEm: textState.letterSpacingEm ?? 0,
            };
            dgTexts.push(text);
            dgCreateTextLayer(text);
        });
    }

    if (dgSelectedMaskId) {
        dgRenderMaskGallery();
        dgSelectMask(dgSelectedMaskId);
    }

    if (s.previewMode && document.getElementById('dg-result-body')) {
        const toggle = document.getElementById('dg-preview-mode-toggle');
        if (toggle) {
            toggle.checked = true;
            dgTogglePreviewMode();
        }
    }
}

// ── Wizard-navigatie (alleen mode=portal) ──

let dgWizardStep = 1;
const dgWizardStepLabels = ['Achtergrond', 'Logo', 'Tekst', 'Transparantie', 'Klaar'];

function dgWizardGoTo(step) {
    if (dgConfig.mode !== 'portal') return;
    dgWizardStep = step;
    document.querySelectorAll('.dg-step').forEach(el => {
        el.classList.toggle('active', parseInt(el.dataset.step, 10) === step);
    });
    dgRenderStepIndicator();
    dgMarkDirty();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function dgRenderStepIndicator() {
    const el = document.getElementById('dg-step-indicator');
    if (!el) return;
    el.innerHTML = '';
    dgWizardStepLabels.forEach((label, i) => {
        const step = i + 1;
        const dot = document.createElement('div');
        dot.className = 'dg-step-dot' + (step === dgWizardStep ? ' active' : (step < dgWizardStep ? ' done' : ''));
        dot.textContent = step < dgWizardStep ? '✓' : step;
        dot.title = label;
        el.appendChild(dot);
        if (step < dgWizardStepLabels.length) {
            const connector = document.createElement('div');
            connector.className = 'dg-step-connector';
            el.appendChild(connector);
        }
    });
}

function dgRenderLimitHints() {
    if (!dgConfig.limits) return;

    const bgHint = document.getElementById('dg-background-count-hint');
    if (bgHint) {
        const remaining = Math.max(0, dgConfig.limits.backgroundMax - dgConfig.limits.backgroundCount);
        bgHint.textContent = `Nog ${remaining} van de ${dgConfig.limits.backgroundMax} keer genereren over.`;
        bgHint.style.display = 'block';
    }

    const logoHint = document.getElementById('dg-logo-count-hint');
    if (logoHint) {
        const remaining = Math.max(0, dgConfig.limits.logoMax - dgConfig.limits.logoCount);
        logoHint.textContent = `Nog ${remaining} van de ${dgConfig.limits.logoMax} keer vrijstellen over.`;
        logoHint.style.display = 'block';
    }
}

function dgWizardInit() {
    if (dgConfig.mode !== 'portal') return;

    dgRenderLimitHints();

    if (dgConfig.resultsLimit) {
        dgShowLimitNotice('dg-background-limit-notice');
        const submitBtn = document.getElementById('dg-submit');
        if (submitBtn) submitBtn.disabled = true;
    }

    const nextBtn = document.getElementById('dg-step1-next');
    if (nextBtn && dgBackgroundPath) nextBtn.style.display = 'inline-flex';

    let startStep = 1;
    const savedStep = dgConfig.initialState?.step;
    if (! dgConfig.justGenerated && dgBackgroundPath && savedStep >= 1 && savedStep <= 5) {
        startStep = savedStep;
    }

    dgWizardGoTo(startStep);
}

function dgFinishDesign() {
    if (! confirm('Ontwerp indienen als definitief? Je kunt hierna niet meer wijzigen.')) return;

    const btn = document.getElementById('dg-finish-btn');
    const original = btn.textContent;
    btn.disabled = true;
    btn.textContent = '⏳ Bezig…';

    fetch(dgConfig.urls.finish, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
        },
        body: JSON.stringify({}),
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            document.getElementById('dg-steps-wrapper').innerHTML =
                '<div class="dg-finish-card"><div style="font-size:2.5rem;margin-bottom:.5rem;">🎉</div>'
                + '<h3 style="margin:0 0 .5rem;">Bedankt!</h3>'
                + '<p class="dg-hint">Je ontwerp is verstuurd. Wij nemen het van hier over.</p></div>';
            const indicator = document.getElementById('dg-step-indicator');
            if (indicator) indicator.style.display = 'none';
        } else {
            btn.disabled = false;
            btn.textContent = original;
            alert(data.error || 'Er ging iets mis, probeer het nog eens.');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.textContent = original;
        alert('Netwerkfout, probeer het nog eens.');
    });
}

dgRenderMaskGallery();
dgRestoreInitialState();
dgAutoSelectMaskIfNeeded();
dgEventTypeChanged();
dgWizardInit();

// Geschiedenis initialiseren: seed met de huidige achtergrond als hij er nog niet in staat
// (eerste generatie op deze pagina, of een herstelde sessie met alleen backgroundPath).
(function dgInitHistory() {
    const curPath = dgBackgroundPath;
    const curImg = document.getElementById('dg-design-img');
    if (curPath && curImg && !dgBackgroundHistory.some(h => h.path === curPath)) {
        dgBackgroundHistory.push({ path: curPath, url: curImg.src });
    }
    dgRenderHistory();
})();
</script>
