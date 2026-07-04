{{--
    Gedeelde ontwerp-tool — gebruikt door design/index.blade.php (standalone admin),
    design/booking.blade.php (admin, gekoppeld aan boeking) en portal/design-tool.blade.php (klant-wizard).

    Verwachte variabelen:
    - $dgMode: 'admin' | 'portal'
    - $urls: array met generate/logoCutout/promptUpdate/masksUpload/masksApply/masksDestroyBase/saveState/finish
    - $eventTypes, $eventType, $promptLabel, $promptKey, $promptDefault, $promptsByType, $logoEventTypes, $masks
    - $results, $input
    - $googleFonts: array slug => label, voor de tekst-tool (App\Services\GoogleFontRegistry)
--}}
<link rel="stylesheet" href="{{ \App\Services\GoogleFontRegistry::googleFontsCssUrl() }}">
<style>
    /* Lokale kaart/knop-stijl — de tool draait zowel in het admin-layout (heeft .card/.btn-primary)
       als in het portaal-layout (heeft die klassen niet), dus hier zelfvoorzienend gedefinieerd. */
    .dg-card { background: #fff; border-radius: 1rem; box-shadow: -1px -1px 3px rgba(255,255,255,.8), 2px 2px 5px rgba(0,0,0,.08); }
    .dg-btn-primary { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; padding: .6rem 1.125rem; border: none; border-radius: .75rem; background: linear-gradient(135deg, #fcd34d 0%, #f59e0b 100%); color: #fff; font-size: .9rem; font-weight: 700; text-decoration: none; cursor: pointer; box-shadow: -2px -2px 5px rgba(255,255,255,.3), 2px 2px 6px rgba(139,111,71,.3), 0 4px 8px rgba(252,211,77,.2); transition: all .2s ease; }
    .dg-btn-primary:hover { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
    .dg-btn-primary:disabled { opacity: .5; cursor: not-allowed; }

    .dg-grid { display: grid; grid-template-columns: 1fr; gap: 1.25rem; align-items: start; }
    @media (min-width: 900px) { .dg-grid { grid-template-columns: 400px 1fr; } }
    /* position: sticky binnen een grid-item blijft altijd binnen de grid-rij (== hier gelijk aan
       .dg-grid, want er is maar 1 rij) — de rijhoogte is automatisch de langste van de twee kolommen.
       .dg-left-col krijgt een min-height zodat er bij een korte stap (bijv. "Tekst") toch genoeg
       rijhoogte overblijft om in mee te scrollen i.p.v. meteen mee weg te scrollen. */
    .dg-preview-col { align-self: start; position: sticky; top: 1.25rem; max-height: calc(100vh - 2.5rem); overflow: auto; }
    @media (min-width: 900px) { .dg-left-col { min-height: calc(100vh - 2.5rem); } }
    .dg-label { display: block; font-size: .8rem; font-weight: 700; color: #334155; margin-bottom: .35rem; }
    .dg-hint { font-size: .72rem; color: #94a3b8; font-weight: 400; }
    .dg-field { margin-bottom: 1.1rem; }
    .dg-bg-tabs { display: flex; gap: .5rem; margin-bottom: 1.1rem; }
    .dg-bg-tab { flex: 1; font-size: .78rem; font-weight: 700; color: #64748b; background: #fff; border: 1px solid #e2e8f0; border-radius: .5rem; padding: .55rem .4rem; cursor: pointer; text-align: center; }
    .dg-bg-tab:hover { background: #f8fafc; }
    .dg-bg-tab.active { background: #ede9fe; border-color: #c4b5fd; color: #6d28d9; }
    .dg-textarea { width: 100%; min-height: 140px; padding: .7rem .8rem; border: 1px solid #e2e8f0; border-radius: .5rem; font-size: .9rem; font-family: inherit; resize: vertical; box-sizing: border-box; }
    .dg-textarea:focus, .dg-file:focus, .dg-select:focus { outline: none; border-color: #7c3aed; box-shadow: 0 0 0 2px rgba(124,58,237,.12); }
    .dg-file, .dg-select { width: 100%; padding: .55rem .7rem; border: 1px solid #e2e8f0; border-radius: .5rem; font-size: .875rem; background: #fff; box-sizing: border-box; }
    .dg-result { border: 1px solid #e2e8f0; border-radius: .75rem; overflow: hidden; background: #fff; }
    .dg-result-head { display: flex; justify-content: flex-end; align-items: center; padding: .6rem .9rem; border-bottom: 1px solid #f1f5f9; font-size: .8rem; }
    .dg-result-frame { background: #f8fafc; padding: 1.75rem 1rem; }
    .dg-result-body { display: flex; justify-content: center; position: relative; }

    /* Mockup-weergave: dezelfde achtergrond+plakband als StripMockupService::render() (het manuele
       upload-pad), zodat de live preview er hetzelfde uitziet als het uiteindelijke mockup-bestand. */
    .dg-mockup-frame { position: relative; width: 100%; border-radius: .75rem; overflow: hidden; background: #f8fafc; }
    .dg-mockup-bg { display: block; width: 100%; height: auto; }
    .dg-mockup-strip-slot { position: absolute; transform: translateX(-50%); }
    .dg-mockup-strip-slot .dg-result-frame { background: transparent; padding: 0; filter: drop-shadow(0 10px 18px rgba(0,0,0,.35)); }
    .dg-mockup-strip-slot .dg-result-body img { max-height: none; }
    .dg-mockup-tape { position: absolute; transform: translate(-50%, -50%); pointer-events: none; }
    .dg-result-body img { display: block; max-height: 76vh; width: auto; max-width: 100%; }
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
    .dg-logo-list { display: flex; flex-direction: column; gap: .5rem; margin-top: .8rem; }
    .dg-logo-list-item { display: flex; align-items: center; gap: .6rem; padding: .5rem; border: 1px solid #f1f5f9; border-radius: .5rem; }
    .dg-logo-list-item img { width: 36px; height: 36px; object-fit: contain; border-radius: .3rem; background: #f8fafc repeating-conic-gradient(#e5e7eb 0% 25%, #f8fafc 0% 50%) 50% / 8px 8px; }
    .dg-logo-list-item .label { flex: 1; font-size: .8rem; font-weight: 600; color: #334155; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
    .dg-logo-list-btn { font-size: .72rem; font-weight: 600; color: #334155; background: #fff; border: 1px solid #e2e8f0; border-radius: .35rem; padding: .3rem .55rem; cursor: pointer; }
    .dg-logo-list-btn:hover { background: #f8fafc; }
    .dg-logo-list-btn.danger { color: #b91c1c; }
    .dg-logo-layer { position: absolute; top: 50%; left: 50%; touch-action: none; user-select: none; cursor: move; }
    .dg-logo-layer img { display: block; width: 100%; height: 100%; pointer-events: none; }
    .dg-logo-handle { position: absolute; width: 18px; height: 18px; border-radius: 50%; background: #7c3aed; border: 2px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,.35); touch-action: none; }
    .dg-logo-handle.resize { right: 2px; bottom: 2px; cursor: nwse-resize; }
    .dg-logo-handle.rotate { top: -26px; left: 50%; right: auto; transform: translateX(-50%); cursor: grab; }
    .dg-logo-rotate-stem { position: absolute; left: 50%; top: -10px; width: 2px; height: 10px; background: #7c3aed; transform: translateX(-50%); pointer-events: none; }

    .dg-text-step { border: 1px solid #e2e8f0; border-radius: .75rem; background: #fff; padding: 1rem 1.1rem; margin-top: 1.25rem; }
    .dg-text-settings { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #f1f5f9; }
    .dg-text-layer { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); touch-action: none; user-select: none; cursor: move; white-space: nowrap; padding: .15rem .3rem; line-height: 1.2; }
    .dg-text-layer.selected { outline: 2px dashed #7c3aed; outline-offset: 3px; border-radius: .2rem; }

    .dg-mask-step { border: 1px solid #e2e8f0; border-radius: .75rem; background: #fff; padding: 1rem 1.1rem; margin-top: 1.25rem; }
    .dg-mask-preview-bg {
        background-image:
            linear-gradient(45deg, #e5e7eb 25%, transparent 25%),
            linear-gradient(-45deg, #e5e7eb 25%, transparent 25%),
            linear-gradient(45deg, transparent 75%, #e5e7eb 75%),
            linear-gradient(-45deg, transparent 75%, #e5e7eb 75%);
        background-size: 20px 20px;
        background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
    }
    .dg-svg-border-layer { position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none; }
    .dg-svg-border-layer svg { width: 100%; height: 100%; display: block; }

    .dg-mask-gallery { display: flex; flex-wrap: wrap; gap: .6rem; margin-bottom: .8rem; }
    .dg-mask-thumb { width: 64px; height: 64px; border: 2px solid #e2e8f0; border-radius: .5rem; padding: 2px; cursor: pointer; background: #f8fafc repeating-conic-gradient(#e5e7eb 0% 25%, #f8fafc 0% 50%) 50% / 10px 10px; }
    .dg-mask-thumb img { width: 100%; height: 100%; object-fit: contain; display: block; }
    .dg-mask-thumb.selected { border-color: #7c3aed; box-shadow: 0 0 0 2px rgba(124,58,237,.2); }
    .dg-mask-thumb-label { font-size: .68rem; text-align: center; color: #64748b; margin-top: .2rem; max-width: 64px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .dg-color-row { display: flex; align-items: center; gap: .6rem; margin-bottom: .7rem; }
    .dg-color-row input[type=color] { width: 2.4rem; height: 2rem; border: 1px solid #e2e8f0; border-radius: .4rem; padding: 2px; cursor: pointer; }

    .dg-masks-btn { display: inline-flex; align-items: center; gap: .35rem; font-size: .8rem; font-weight: 600; color: #334155; background: #fff; border: 1px solid #e2e8f0; border-radius: .5rem; padding: .4rem .75rem; cursor: pointer; }
    .dg-masks-btn:hover { background: #f8fafc; }
    .dg-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15,23,42,.5); z-index: 1000; align-items: center; justify-content: center; padding: 1.5rem; }
    .dg-modal-overlay.open { display: flex; }
    .dg-modal { background: #fff; border-radius: .85rem; width: 100%; max-width: 640px; max-height: 85vh; overflow-y: auto; padding: 1.25rem 1.4rem; }
    .dg-modal-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
    .dg-modal-close { cursor: pointer; font-size: 1.1rem; color: #94a3b8; background: none; border: none; }
    .dg-mask-list { display: flex; flex-direction: column; gap: .5rem; margin-bottom: 1.2rem; }
    .dg-mask-list-item { display: flex; align-items: center; gap: .7rem; padding: .5rem; border: 1px solid #f1f5f9; border-radius: .5rem; }
    .dg-mask-list-item img { width: 40px; height: 40px; object-fit: contain; border-radius: .3rem; background: #f8fafc; }
    .dg-mask-list-item .label { flex: 1; font-size: .82rem; font-weight: 600; color: #334155; }
    .dg-mask-list-item .svg-tag { font-size: .65rem; color: #7c3aed; background: #ede9fe; padding: .1rem .4rem; border-radius: 999px; }
    .dg-mask-delete { color: #b91c1c; background: none; border: none; cursor: pointer; font-size: .8rem; }
    .dg-modal-form-row { margin-bottom: .7rem; }

    .dg-limit-notice { border: 1px solid #fde68a; background: #fffbeb; border-radius: .6rem; padding: .8rem .9rem; font-size: .82rem; color: #92400e; margin-top: .6rem; }
    .dg-limit-notice a { color: #7c3aed; font-weight: 700; }

    .dg-generating-overlay { display: none; position: fixed; inset: 0; background: rgba(255,255,255,.94); z-index: 2000; flex-direction: column; align-items: center; justify-content: center; gap: 1rem; text-align: center; padding: 2rem; }
    .dg-generating-overlay.show { display: flex; }
    .dg-spinner { width: 3rem; height: 3rem; border: 4px solid #ede9fe; border-top-color: #7c3aed; border-radius: 50%; animation: dg-spin .8s linear infinite; }
    @keyframes dg-spin { to { transform: rotate(360deg); } }
    .dg-save-badge { display: none; position: fixed; right: 1.25rem; bottom: 1.25rem; z-index: 500; align-items: center; gap: .4rem; background: #fff; border: 1px solid #e2e8f0; border-radius: 999px; padding: .55rem 1rem; font-size: .78rem; font-weight: 700; color: #64748b; box-shadow: 0 4px 16px rgba(15,23,42,.12); }
    .dg-save-badge.show { display: flex; }
    .dg-save-badge.saved { color: #16a34a; border-color: #bbf7d0; background: #f0fdf4; }
    .dg-save-badge.dirty { color: #b45309; border-color: #fde68a; background: #fffbeb; }

    /* Wizard-chrome — alleen actief in mode=portal */
    .dg-wizard .dg-step { display: none; }
    .dg-wizard .dg-step.active { display: block; }
    .dg-step-indicator { display: none; gap: .5rem; margin-bottom: 1.25rem; align-items: center; }
    .dg-wizard .dg-step-indicator { display: flex; }
    .dg-step-dot { width: 2rem; height: 2rem; border-radius: 50%; background: #e2e8f0; color: #64748b; display: flex; align-items: center; justify-content: center; font-size: .8rem; font-weight: 700; flex-shrink: 0; }
    .dg-step-dot.active { background: #7c3aed; color: #fff; }
    .dg-step-dot.done { background: #16a34a; color: #fff; }
    .dg-step-connector { flex: 1; height: 2px; background: #e2e8f0; }
    .dg-step-nav { display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; }
    .dg-step-nav-btn { font-size: .82rem; font-weight: 700; color: #64748b; background: #fff; border: 1px solid #e2e8f0; border-radius: .5rem; padding: .5rem .9rem; cursor: pointer; }
    .dg-step-nav-btn:hover { background: #f8fafc; }
    .dg-finish-card { border: 1px solid #e2e8f0; border-radius: .75rem; background: #fff; padding: 1.5rem; text-align: center; }
</style>

@php $dgMode = $dgMode ?? 'admin'; @endphp

@if($dgMode === 'admin')
<div style="display:flex;justify-content:flex-end;margin-bottom:.75rem;">
    <button type="button" class="dg-masks-btn" onclick="dgOpenMasksModal()">🎭 Maskers</button>
</div>

{{-- ── Maskers-beheervenster (alleen admin) ── --}}
<div id="dg-masks-modal-overlay" class="dg-modal-overlay" onclick="if(event.target === this) dgCloseMasksModal()">
    <div class="dg-modal">
        <div class="dg-modal-head">
            <h3 style="margin:0;font-size:1.05rem;font-weight:700;color:#1e293b;">🎭 Maskerbibliotheek</h3>
            <button type="button" class="dg-modal-close" onclick="dgCloseMasksModal()">✕</button>
        </div>

        <div id="dg-mask-list" class="dg-mask-list"></div>

        <div style="border-top:1px solid #f1f5f9;padding-top:1rem;">
            <p class="dg-label" style="margin-bottom:.6rem;">Nieuw masker toevoegen</p>
            <div class="dg-modal-form-row">
                <input id="modal_mask_label" type="text" class="dg-file" placeholder="Naam (bijv. 'Ovaal middenvak')">
            </div>
            <div class="dg-modal-form-row">
                <label class="dg-label" for="modal_mask_file">Masker <span class="dg-hint">— zwart-wit (wit = zichtbaar, zwart = transparant)</span></label>
                <input id="modal_mask_file" type="file" class="dg-file" accept="image/*">
            </div>
            <div class="dg-modal-form-row">
                <label class="dg-label" for="modal_mask_thumb">Thumbnail <span class="dg-hint">— optioneel, anders wordt het maskerbestand zelf gebruikt</span></label>
                <input id="modal_mask_thumb" type="file" class="dg-file" accept="image/*">
            </div>
            <div class="dg-modal-form-row">
                <label class="dg-label" for="modal_mask_svg">Rand-svg <span class="dg-hint">— optioneel, gevulde vorm (geen stroke), exact over het masker</span></label>
                <input id="modal_mask_svg" type="file" class="dg-file" accept=".svg,image/svg+xml">
            </div>
            <button type="button" id="dg-mask-upload-btn" class="dg-logo-btn" onclick="dgUploadMaskToLibrary()">⬆ Toevoegen aan bibliotheek</button>
            <p id="dg-mask-upload-error" class="dg-logo-error" style="display:none;"></p>
        </div>
    </div>
</div>
@endif

<div id="dg-save-badge" class="dg-save-badge"></div>

<div id="dg-generating-overlay" class="dg-generating-overlay">
    <div class="dg-spinner"></div>
    <div id="dg-generating-title" style="font-size:1rem;font-weight:700;color:#1e293b;">✨ Achtergrond wordt gegenereerd…</div>
    <div id="dg-generating-hint" style="font-size:.85rem;color:#64748b;max-width:320px;">Dit duurt meestal 10 tot 30 seconden. De pagina ververst automatisch zodra het klaar is.</div>
</div>

<div class="dg-grid">
    {{-- ── Links: alle edit-opties ── --}}
    <div class="dg-left-col">
    @if($dgMode === 'portal')
    <div class="dg-step-indicator" id="dg-step-indicator"></div>
    @endif

    <div id="dg-steps-wrapper" class="{{ $dgMode === 'portal' ? 'dg-wizard' : '' }}">
    <div class="dg-step" data-step="1">
    <form method="POST" action="{{ $urls['generate'] }}" enctype="multipart/form-data" class="dg-card" style="padding:1.25rem;" onsubmit="dgSubmitting(this)">
        @csrf

        <div class="dg-field">
            <label class="dg-label" for="event_type">Type event</label>
            <select id="event_type" name="event_type" class="dg-select" onchange="dgEventTypeChanged()">
                @foreach($eventTypes as $value => $typeLabel)
                    <option value="{{ $value }}" @selected(old('event_type', $eventType) === $value)>{{ $typeLabel }}</option>
                @endforeach
            </select>
        </div>

        <div class="dg-bg-tabs">
            <button type="button" class="dg-bg-tab active" data-method="ai" onclick="dgSetBackgroundMethod('ai')">✨ AI genereren</button>
            <button type="button" class="dg-bg-tab" data-method="upload" onclick="dgSetBackgroundMethod('upload')">📤 Eigen afbeelding</button>
            <button type="button" class="dg-bg-tab" data-method="color" onclick="dgSetBackgroundMethod('color')">🎨 Kleur kiezen</button>
        </div>
        <input type="hidden" name="background_method" id="dg-background-method" value="ai">

        <div id="dg-bg-panel-ai" class="dg-bg-panel">
            <div class="dg-field">
                <label class="dg-label">
                    {{ $promptLabel }}-prompt <span id="dg-current-type-label"></span>
                    @if($dgMode === 'admin')
                    <span id="dg-gear" class="dg-gear" title="Prompt aanpassen / sluiten" onclick="dgToggleSettings()">⚙️</span>
                    @endif
                </label>
                @if($dgMode === 'admin')
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
                @endif
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
            @error('references.*') <p style="color:#dc2626;font-size:.8rem;margin:0 0 .5rem;">{{ $message }}</p> @enderror
        </div>

        <div id="dg-bg-panel-upload" class="dg-bg-panel" style="display:none;">
            <div class="dg-field">
                <label class="dg-label" for="background_upload">Eigen afbeelding <span class="dg-hint">— wordt automatisch bijgesneden op het canvasformaat (max 15 MB)</span></label>
                <input id="background_upload" name="background_upload" type="file" class="dg-file" accept="image/*">
            </div>
            @error('background_upload') <p style="color:#dc2626;font-size:.8rem;margin:0 0 .5rem;">{{ $message }}</p> @enderror
        </div>

        <div id="dg-bg-panel-color" class="dg-bg-panel" style="display:none;">
            <div class="dg-field">
                <label class="dg-label" for="background_color">Kleur</label>
                <div style="display:flex;align-items:center;gap:.6rem;">
                    <input id="background_color" name="background_color" type="color" value="#ffffff" oninput="dgSyncColorHex(this.value)">
                    <input id="background_color_hex" type="text" class="dg-file" style="max-width:140px;" value="#ffffff" oninput="dgSyncColorPicker(this.value)" placeholder="#ffffff">
                </div>
            </div>
            @error('background_color') <p style="color:#dc2626;font-size:.8rem;margin:0 0 .5rem;">{{ $message }}</p> @enderror
        </div>

        @error('event_type') <p style="color:#dc2626;font-size:.8rem;margin:0 0 .5rem;">{{ $message }}</p> @enderror

        <button type="submit" id="dg-submit" class="dg-btn-primary" style="width:100%;">✨ Genereer achtergrond</button>
        <p id="dg-submit-hint" class="dg-hint" style="margin:.6rem 0 0;text-align:center;">Genereren kan 10–30 seconden duren.</p>
        <p id="dg-background-count-hint" class="dg-hint" style="margin:.3rem 0 0;text-align:center;display:none;"></p>
        <div id="dg-background-limit-notice" class="dg-limit-notice" style="display:none;"></div>
    </form>
    @if($dgMode === 'portal')
    <div class="dg-step-nav">
        <span></span>
        <button type="button" class="dg-step-nav-btn" id="dg-step1-next" style="display:none;" onclick="dgWizardGoTo(2)">Volgende →</button>
    </div>
    @endif
    </div>

    @if($results['ok'] ?? false)
        @if($dgMode === 'portal' || in_array($eventType, $logoEventTypes))
        <div class="dg-step" data-step="2">
        <div class="dg-logo-step">
            <label class="dg-label" for="logo_upload">Stap 2 — Logo's <span class="dg-hint">— optioneel, max 3 stuks, elk wordt vrijgesteld en op de achtergrond geplakt (max 8 MB p/st)</span></label>
            <input id="logo_upload" type="file" class="dg-file" accept="image/*">
            <div class="dg-logo-actions">
                <button type="button" id="dg-logo-cutout-btn" class="dg-logo-btn" onclick="dgCutoutLogo()">✂️ Logo vrijstaand maken</button>
            </div>
            <p id="dg-logo-error" class="dg-logo-error" style="display:none;"></p>
            <p id="dg-logo-count-hint" class="dg-hint" style="display:none;margin-top:.4rem;"></p>
            <p id="dg-logo-max-notice" class="dg-hint" style="display:none;margin-top:.4rem;">Maximaal 3 logo's — verwijder er eerst één om een nieuwe toe te voegen.</p>
            <div id="dg-logo-limit-notice" class="dg-limit-notice" style="display:none;"></div>
            <div id="dg-logo-list" class="dg-logo-list"></div>
        </div>
        @if($dgMode === 'portal')
        <div class="dg-step-nav">
            <button type="button" class="dg-step-nav-btn" onclick="dgWizardGoTo(1)">← Vorige</button>
            <button type="button" class="dg-step-nav-btn" onclick="dgWizardGoTo(3)">Volgende →</button>
        </div>
        @endif
        </div>
        @endif

        <div class="dg-step" data-step="3">
        <div class="dg-text-step">
            <label class="dg-label" style="margin-bottom:.7rem;">Stap 3 — Tekst <span class="dg-hint">— optioneel, voeg tekstregels toe en versleep ze in het voorbeeld</span></label>
            <button type="button" class="dg-logo-btn" onclick="dgAddText()">➕ Tekst toevoegen</button>

            <div id="dg-text-settings" class="dg-text-settings" style="display:none;">
                <div class="dg-field">
                    <label class="dg-label" for="dg-text-content">Tekst</label>
                    <input id="dg-text-content" type="text" class="dg-file" oninput="dgUpdateActiveText()">
                </div>
                <div style="display:flex;gap:.75rem;">
                    <div class="dg-field" style="flex:1;">
                        <label class="dg-label" for="dg-text-color">Kleur</label>
                        <input id="dg-text-color" type="color" style="width:100%;height:2.3rem;border:1px solid #e2e8f0;border-radius:.5rem;cursor:pointer;" oninput="dgUpdateActiveText()">
                    </div>
                    <div class="dg-field" style="flex:2;">
                        <label class="dg-label" for="dg-text-font">Lettertype</label>
                        <select id="dg-text-font" class="dg-select" onchange="dgUpdateActiveText()">
                            @foreach($googleFonts as $slug => $label)
                                <option value="{{ $slug }}" style="font-family:'{{ $label }}', sans-serif;">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="dg-field">
                    <label class="dg-label" for="dg-text-size">Grootte</label>
                    <input id="dg-text-size" type="range" min="1.5" max="14" step="0.5" style="width:100%;" oninput="dgUpdateActiveText()">
                </div>
                <button type="button" class="dg-logo-list-btn danger" onclick="dgRemoveActiveText()">✕ Deze tekst verwijderen</button>
            </div>
            <p id="dg-text-hint" class="dg-hint" style="margin-top:.6rem;">Klik op een tekst in het voorbeeld om 'm te bewerken.</p>
        </div>
        @if($dgMode === 'portal')
        <div class="dg-step-nav">
            <button type="button" class="dg-step-nav-btn" onclick="dgWizardGoTo(2)">← Vorige</button>
            <button type="button" class="dg-step-nav-btn" onclick="dgWizardGoTo(4)">Volgende →</button>
        </div>
        @endif
        </div>

        <div class="dg-step" data-step="4">
        <div class="dg-mask-step">
            <label class="dg-label" style="margin-bottom:.7rem;">Stap 4 — Transparantie <span class="dg-hint">— kies een masker, preview is direct</span></label>
            <div id="dg-mask-gallery" class="dg-mask-gallery"></div>

            <div id="dg-color-row" class="dg-color-row" style="display:none;">
                <label class="dg-label" style="margin:0;display:flex;align-items:center;gap:.4rem;cursor:pointer;">
                    <input type="checkbox" id="dg-border-enabled" onchange="dgBorderColorChanged()"> Gekleurde rand tonen
                </label>
                <input id="dg-border-color" type="color" value="#000000" oninput="dgBorderColorChanged()" style="display:none;">
            </div>

            <p id="dg-mask-apply-status" class="dg-hint" style="display:none;margin-top:.6rem;">⏳ Wordt toegepast…</p>
            <p id="dg-mask-error" class="dg-logo-error" style="display:none;"></p>
        </div>
        @if($dgMode === 'portal')
        <div class="dg-step-nav">
            <button type="button" class="dg-step-nav-btn" onclick="dgWizardGoTo(3)">← Vorige</button>
            <button type="button" class="dg-step-nav-btn" onclick="dgWizardGoTo(5)">Volgende →</button>
        </div>
        @endif
        </div>

        @if($dgMode === 'portal')
        <div class="dg-step" data-step="5">
        <div class="dg-finish-card">
            <div style="font-size:2rem;margin-bottom:.5rem;">🎉</div>
            <h3 style="margin:0 0 .5rem;font-size:1.05rem;color:#1e293b;">Helemaal tevreden met je ontwerp?</h3>
            <p class="dg-hint" style="margin:0 0 1rem;">Zodra je op "Hij is af!" klikt, laten we het weten aan Flitsmoment — zij nemen het ontwerp dan over voor productie. Je kunt hierna niet meer wijzigen.</p>
            <button type="button" class="dg-btn-primary" onclick="dgFinishDesign()" id="dg-finish-btn">✅ Hij is af!</button>
        </div>
        <div class="dg-step-nav">
            <button type="button" class="dg-step-nav-btn" onclick="dgWizardGoTo(4)">← Vorige</button>
            <span></span>
        </div>
        </div>
        @endif
    @endif
    </div>
    </div>

    {{-- ── Rechts: alleen het voorbeeld, blijft staan tijdens scrollen ── --}}
    <div class="dg-preview-col">
        @if($results === null)
            <div class="dg-card" style="padding:2.5rem 1.5rem;text-align:center;color:#94a3b8;">
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
                    @if($results['ok'])
                        <a href="{{ $results['url'] }}" download style="font-size:.78rem;color:#7c3aed;font-weight:600;">⬇ Download ({{ $results['seconds'] }}s)</a>
                    @endif
                </div>
                @if($results['ok'])
                    @php($mockupCfg = config('mockup'))
                    <div class="dg-mockup-frame">
                        <img src="{{ asset('mockups/' . $mockupCfg['background_file']) }}" class="dg-mockup-bg" alt="">
                        <div class="dg-mockup-strip-slot" style="left:{{ $mockupCfg['strip']['center_x'] * 100 }}%;top:{{ $mockupCfg['strip']['top'] * 100 }}%;width:{{ $mockupCfg['strip']['width'] * 100 }}%;">
                            <div class="dg-result-frame">
                                <div id="dg-result-body" class="dg-result-body">
                                    <img src="{{ $results['url'] }}" alt="Gegenereerde achtergrond">
                                    <div id="dg-svg-border-layer" class="dg-svg-border-layer"></div>
                                </div>
                            </div>
                        </div>
                        <img src="{{ asset('mockups/' . $mockupCfg['tape_file']) }}" class="dg-mockup-tape" alt="" style="left:{{ $mockupCfg['tape']['center_x'] * 100 }}%;top:{{ $mockupCfg['tape']['center_y'] * 100 }}%;width:{{ $mockupCfg['tape']['width'] * 100 }}%;">
                    </div>
                @else
                    <div style="padding:1rem;color:#b91c1c;font-size:.8rem;background:#fef2f2;">
                        <strong>Mislukt.</strong><br>{{ \Illuminate\Support\Str::limit($results['error'], 300) }}
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
