{{--
    JS voor de gedeelde ontwerp-tool. Verwacht dezelfde variabelen als _tool.blade.php,
    plus optioneel $limits (['backgroundMax'=>20,'logoMax'=>20,'backgroundCount'=>0,'logoCount'=>0])
    en $initialState (de opgeslagen DesignSession->state, voor booking/portal-modi).
--}}
@php
    $limits = $limits ?? null;
    $initialState = $initialState ?? null;
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
};

const dgPromptsByType = {!! Js::from($promptsByType) !!};
const dgEventTypeLabels = {!! Js::from($eventTypes) !!};
const dgLogoEventTypes = {!! Js::from($logoEventTypes) !!};

function dgEventTypeChanged() {
    const type = document.getElementById('event_type').value;
    document.getElementById('dg-prompt-template').value = dgPromptsByType[type] || '';
    document.getElementById('dg-current-type-label').textContent = '(' + (dgEventTypeLabels[type] || type) + ')';
    dgMarkDirty();
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

let dgLogo = null; // { xPct, yPct, widthPct, aspect, rotateDeg } — widthPct t.o.v. containerbreedte (== canvasbreedte)

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

    fetch(dgConfig.urls.logoCutout, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
        body: fd,
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = original;
        if (data.ok) {
            dgInitLogoEditor(data.url, data.path);
            dgMarkDirty();
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
        errorEl.textContent = 'Vrijstellen mislukt (netwerkfout).';
        errorEl.style.display = 'block';
    });
}

function dgInitLogoEditor(url, path) {
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
            path: path,
            xPct: 50,
            yPct: 50,
            widthPct: 35,
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
    const containerWidth = layer.parentElement.clientWidth;
    const widthPx = containerWidth * dgLogo.widthPct / 100;
    const h = widthPx * dgLogo.aspect;
    layer.style.width = widthPx + 'px';
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
    layer.addEventListener('pointerup', () => { dragging = false; dgMarkDirty(); });
    layer.addEventListener('pointercancel', () => dragging = false);
}

function dgBindResizeHandle(handle) {
    let dragging = false, startX, startWidthPct, containerWidth;
    handle.addEventListener('pointerdown', (e) => {
        e.stopPropagation();
        dragging = true;
        startX = e.clientX;
        startWidthPct = dgLogo.widthPct;
        containerWidth = handle.parentElement.parentElement.clientWidth;
        handle.setPointerCapture(e.pointerId);
    });
    handle.addEventListener('pointermove', (e) => {
        if (!dragging) return;
        const dxPct = ((e.clientX - startX) * 2 / containerWidth) * 100;
        dgLogo.widthPct = Math.max(4, startWidthPct + dxPct);
        dgRenderLogoLayer();
    });
    handle.addEventListener('pointerup', () => { dragging = false; dgMarkDirty(); });
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
    handle.addEventListener('pointerup', () => { dragging = false; dgMarkDirty(); });
    handle.addEventListener('pointercancel', () => dragging = false);
}

function dgCenterLogoHorizontally() {
    if (!dgLogo) return;
    dgLogo.xPct = 50;
    dgRenderLogoLayer();
    dgMarkDirty();
}

function dgRemoveLogo() {
    const layer = document.getElementById('dg-logo-layer');
    if (layer) layer.remove();
    dgLogo = null;
    document.getElementById('dg-logo-toolbar').style.display = 'none';
    dgMarkDirty();
}

function dgShowLimitNotice(elId) {
    const el = document.getElementById(elId);
    if (!el) return;
    el.innerHTML = 'We zien dat je moeite hebt om de goede afbeelding te maken. '
        + 'Neem gerust <a href="' + (dgConfig.urls.helpLink || '#') + '">contact met ons op voor een gratis ontwerp op maat</a>.';
    el.style.display = 'block';
}

let dgBackgroundPath = {!! Js::from($results['path'] ?? null) !!};
let dgMasks = {!! Js::from($masks) !!};
let dgSelectedMaskId = null;

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
    const img = bgBody?.querySelector('img');
    const applyBtn = document.getElementById('dg-mask-apply-btn');
    const colorRow = document.getElementById('dg-color-row');
    if (!img) return;

    dgSelectedMaskId = id;
    dgRenderMaskGallery();

    const mask = dgMasks.find(m => m.id === dgSelectedMaskId);
    const borderLayer = document.getElementById('dg-svg-border-layer');

    if (!mask) {
        img.style.webkitMaskImage = '';
        img.style.maskImage = '';
        bgBody.classList.remove('dg-mask-preview-bg');
        borderLayer.innerHTML = '';
        colorRow.style.display = 'none';
        applyBtn.disabled = true;
        return;
    }

    img.style.webkitMaskImage = `url(${mask.url})`;
    img.style.maskImage = `url(${mask.url})`;
    img.style.webkitMaskSize = '100% 100%';
    img.style.maskSize = '100% 100%';
    img.style.webkitMaskRepeat = 'no-repeat';
    img.style.maskRepeat = 'no-repeat';
    bgBody.classList.add('dg-mask-preview-bg');
    applyBtn.disabled = false;

    if (mask.svgContent) {
        borderLayer.innerHTML = mask.svgContent;
        colorRow.style.display = 'flex';
        dgBorderColorChanged();
    } else {
        borderLayer.innerHTML = '';
        colorRow.style.display = 'none';
    }

    dgMarkDirty();
}

function dgBorderColorChanged() {
    const color = document.getElementById('dg-border-color').value;
    const svg = document.querySelector('#dg-svg-border-layer svg');
    if (svg) {
        // Afspraak: de kleurbare rand gebruikt css-klasse "cls-2" binnen de <style> van de svg
        const styleEl = svg.querySelector('style');
        if (styleEl) {
            styleEl.textContent = styleEl.textContent.replace(/\.cls-2\s*\{[^}]*\}/i, `.cls-2{fill:${color};}`);
        }

        // Fallback voor svg's met inline fill/stroke-attributen
        svg.querySelectorAll('[fill]').forEach(el => {
            if (el.getAttribute('fill').toLowerCase() !== 'none') el.setAttribute('fill', color);
        });
        svg.querySelectorAll('[stroke]').forEach(el => {
            if (el.getAttribute('stroke').toLowerCase() !== 'none') el.setAttribute('stroke', color);
        });
    }
    dgMarkDirty();
}

function dgApplyMask() {
    const errorEl = document.getElementById('dg-mask-error');
    errorEl.style.display = 'none';
    if (!dgSelectedMaskId || !dgBackgroundPath) return;

    const btn = document.getElementById('dg-mask-apply-btn');
    const original = btn.textContent;
    btn.disabled = true;
    btn.textContent = '⏳ Bezig…';

    const mask = dgMasks.find(m => m.id === dgSelectedMaskId);
    const payload = { background_path: dgBackgroundPath, mask_id: dgSelectedMaskId };
    if (mask && mask.svgContent) payload.border_color = document.getElementById('dg-border-color').value;

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
        btn.disabled = false;
        btn.textContent = original;
        if (data.ok) {
            const bgBody = document.getElementById('dg-result-body');
            const img = bgBody.querySelector('img');
            img.style.webkitMaskImage = '';
            img.style.maskImage = '';
            img.src = data.url;
            bgBody.classList.remove('dg-mask-preview-bg');
            document.getElementById('dg-svg-border-layer').innerHTML = '';
            document.getElementById('dg-color-row').style.display = 'none';
            dgSelectedMaskId = null;
            dgRenderMaskGallery();
            dgAutoSelectMaskIfNeeded();
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

        if (m.svgContent) {
            const tag = document.createElement('span');
            tag.className = 'svg-tag';
            tag.textContent = 'svg-rand';
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
    const svgInput = document.getElementById('modal_mask_svg');
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
    if (svgInput.files.length) fd.append('svg', svgInput.files[0]);

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
            dgMasks.push({ id: data.id, label: data.label, url: data.url, thumbnailUrl: data.thumbnailUrl, svgContent: data.svgContent });
            dgRenderMaskList();
            dgRenderMaskGallery();
            dgAutoSelectMaskIfNeeded();
            labelInput.value = '';
            fileInput.value = '';
            thumbInput.value = '';
            svgInput.value = '';
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
}

function dgCollectState() {
    return {
        event_type: document.getElementById('event_type')?.value,
        input: document.getElementById('input')?.value,
        state: {
            backgroundPath: dgBackgroundPath,
            maskId: dgSelectedMaskId,
            borderColor: document.getElementById('dg-border-color')?.value,
            logo: dgLogo ? {
                path: dgLogo.path, xPct: dgLogo.xPct, yPct: dgLogo.yPct,
                widthPct: dgLogo.widthPct, rotateDeg: dgLogo.rotateDeg,
            } : null,
        },
    };
}

function dgAutosaveTick() {
    if (!dgDirty || !dgConfig.urls.saveState) return;
    dgDirty = false;

    const indicator = document.getElementById('dg-save-indicator');
    fetch(dgConfig.urls.saveState, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
        },
        body: JSON.stringify(dgCollectState()),
    }).then(() => {
        if (indicator) {
            indicator.textContent = 'Opgeslagen ✓';
            setTimeout(() => { indicator.textContent = ''; }, 2000);
        }
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

    if (s.maskId) dgSelectedMaskId = s.maskId;
    if (s.borderColor) {
        const colorInput = document.getElementById('dg-border-color');
        if (colorInput) colorInput.value = s.borderColor;
    }

    if (s.logo && s.logo.path && document.getElementById('dg-result-body')) {
        dgInitLogoEditorFromState(s.logo);
    }

    if (dgSelectedMaskId) {
        dgRenderMaskGallery();
        dgSelectMask(dgSelectedMaskId);
    }
}

function dgInitLogoEditorFromState(logoState) {
    const bgBody = document.getElementById('dg-result-body');
    if (!bgBody) return;

    const url = logoState.path.startsWith('http') ? logoState.path : ('/storage/' + logoState.path);

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
            path: logoState.path,
            xPct: logoState.xPct ?? 50,
            yPct: logoState.yPct ?? 50,
            widthPct: logoState.widthPct ?? 35,
            aspect: img.naturalHeight / img.naturalWidth,
            rotateDeg: logoState.rotateDeg ?? 0,
        };
        dgRenderLogoLayer();
        dgBindLogoDrag(layer);
        dgBindResizeHandle(resizeHandle);
        dgBindRotateHandle(rotateHandle);

        document.getElementById('dg-logo-toolbar').style.display = 'flex';
    };
    img.src = url;
}

dgRenderMaskGallery();
dgRestoreInitialState();
dgAutoSelectMaskIfNeeded();
dgEventTypeChanged();

function dgPositionPreviewColumn() {
    const grid = document.querySelector('.dg-grid');
    const col = document.querySelector('.dg-preview-col');
    if (!grid || !col) return;

    if (window.innerWidth < 900) {
        col.classList.remove('dg-fixed');
        col.style.top = '';
        col.style.left = '';
        col.style.width = '';
        return;
    }

    const gridRect = grid.getBoundingClientRect();
    const colWidths = getComputedStyle(grid).gridTemplateColumns.split(' ').map(parseFloat);
    const gap = parseFloat(getComputedStyle(grid).columnGap) || 0;
    const leftColWidth = colWidths[0] || 400;

    col.classList.add('dg-fixed');
    col.style.top = '1.25rem';
    col.style.left = (gridRect.left + leftColWidth + gap) + 'px';
    col.style.width = (gridRect.width - leftColWidth - gap) + 'px';
}

window.addEventListener('resize', dgPositionPreviewColumn);
document.getElementById('sidebar-toggle')?.addEventListener('click', () => setTimeout(dgPositionPreviewColumn, 280));
dgPositionPreviewColumn();
</script>
