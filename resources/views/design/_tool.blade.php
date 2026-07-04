{{--
    Gedeelde ontwerp-tool — gebruikt door design/index.blade.php (standalone admin),
    design/booking.blade.php (admin, gekoppeld aan boeking) en portal/design-tool.blade.php (klant-wizard).

    Verwachte variabelen:
    - $dgMode: 'admin' | 'portal'
    - $urls: array met generate/logoCutout/promptUpdate/masksUpload/masksApply/masksDestroyBase/saveState/finish
    - $eventTypes, $eventType, $promptLabel, $promptKey, $promptDefault, $promptsByType, $logoEventTypes, $masks
    - $results, $input
--}}
<style>
    .dg-grid { display: grid; grid-template-columns: 1fr; gap: 1.25rem; align-items: start; }
    @media (min-width: 900px) { .dg-grid { grid-template-columns: 400px 1fr; } }
    .dg-preview-col { align-self: start; max-height: calc(100vh - 2.5rem); overflow: auto; }
    .dg-preview-col.dg-fixed { position: fixed; }
    .dg-label { display: block; font-size: .8rem; font-weight: 700; color: #334155; margin-bottom: .35rem; }
    .dg-hint { font-size: .72rem; color: #94a3b8; font-weight: 400; }
    .dg-field { margin-bottom: 1.1rem; }
    .dg-textarea { width: 100%; min-height: 140px; padding: .7rem .8rem; border: 1px solid #e2e8f0; border-radius: .5rem; font-size: .9rem; font-family: inherit; resize: vertical; box-sizing: border-box; }
    .dg-textarea:focus, .dg-file:focus, .dg-select:focus { outline: none; border-color: #7c3aed; box-shadow: 0 0 0 2px rgba(124,58,237,.12); }
    .dg-file, .dg-select { width: 100%; padding: .55rem .7rem; border: 1px solid #e2e8f0; border-radius: .5rem; font-size: .875rem; background: #fff; box-sizing: border-box; }
    .dg-result { border: 1px solid #e2e8f0; border-radius: .75rem; overflow: hidden; background: #fff; }
    .dg-result-head { display: flex; justify-content: space-between; align-items: center; padding: .6rem .9rem; border-bottom: 1px solid #f1f5f9; font-size: .8rem; }
    .dg-badge { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; padding: .15rem .5rem; border-radius: 999px; background: #ede9fe; color: #6d28d9; }
    .dg-result-body { display: flex; justify-content: center; background: #f8fafc; position: relative; }
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
    .dg-logo-toolbar { display: none; gap: .5rem; padding: .6rem .9rem; border-top: 1px solid #f1f5f9; }
    .dg-logo-layer { position: absolute; top: 50%; left: 50%; touch-action: none; user-select: none; cursor: move; }
    .dg-logo-layer img { display: block; width: 100%; height: 100%; pointer-events: none; }
    .dg-logo-handle { position: absolute; width: 16px; height: 16px; border-radius: 50%; background: #7c3aed; border: 2px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,.35); }
    .dg-logo-handle.resize { right: 2px; bottom: 2px; cursor: nwse-resize; }
    .dg-logo-handle.rotate { right: 2px; top: 2px; cursor: grab; }

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
    .dg-save-indicator { font-size: .7rem; color: #94a3b8; margin-left: .5rem; }

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

<div class="dg-grid">
    {{-- ── Links: alle edit-opties ── --}}
    <div>
    @if($dgMode === 'portal')
    <div class="dg-step-indicator" id="dg-step-indicator"></div>
    @endif

    <div id="dg-steps-wrapper" class="{{ $dgMode === 'portal' ? 'dg-wizard' : '' }}">
    <div class="dg-step" data-step="1">
    <form method="POST" action="{{ $urls['generate'] }}" enctype="multipart/form-data" class="card neu-card" style="padding:1.25rem;" onsubmit="dgSubmitting(this)">
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
        @error('event_type') <p style="color:#dc2626;font-size:.8rem;margin:0 0 .5rem;">{{ $message }}</p> @enderror
        @error('references.*') <p style="color:#dc2626;font-size:.8rem;margin:0 0 .5rem;">{{ $message }}</p> @enderror

        <button type="submit" id="dg-submit" class="btn btn-primary" style="width:100%;justify-content:center;">✨ Genereer achtergrond</button>
        <p class="dg-hint" style="margin:.6rem 0 0;text-align:center;">Genereren kan 10–30 seconden duren.</p>
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
            <label class="dg-label" for="logo_upload">Stap 2 — Logo <span class="dg-hint">— optioneel, wordt vrijgesteld en boven de achtergrond geplakt (max 8 MB)</span></label>
            <input id="logo_upload" type="file" class="dg-file" accept="image/*">
            <div class="dg-logo-actions">
                <button type="button" id="dg-logo-cutout-btn" class="dg-logo-btn" onclick="dgCutoutLogo()">✂️ Logo vrijstaand maken</button>
            </div>
            <p id="dg-logo-error" class="dg-logo-error" style="display:none;"></p>
            <div id="dg-logo-limit-notice" class="dg-limit-notice" style="display:none;"></div>
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
        <div class="dg-mask-step">
            <label class="dg-label" style="margin-bottom:.7rem;">Stap 3 — Transparantie <span class="dg-hint">— kies een masker, preview is direct</span></label>
            <div id="dg-mask-gallery" class="dg-mask-gallery"></div>

            <div id="dg-color-row" class="dg-color-row" style="display:none;">
                <label class="dg-label" for="dg-border-color" style="margin:0;">Randkleur</label>
                <input id="dg-border-color" type="color" value="#000000" oninput="dgBorderColorChanged()">
            </div>

            <div class="dg-logo-actions">
                <button type="button" id="dg-mask-apply-btn" class="dg-logo-btn" onclick="dgApplyMask()" disabled>✅ Toepassen op afbeelding</button>
            </div>
            <p id="dg-mask-error" class="dg-logo-error" style="display:none;"></p>
        </div>
        @if($dgMode === 'portal')
        <div class="dg-step-nav">
            <button type="button" class="dg-step-nav-btn" onclick="dgWizardGoTo(2)">← Vorige</button>
            <button type="button" class="dg-step-nav-btn" onclick="dgWizardGoTo(4)">Volgende →</button>
        </div>
        @endif
        </div>

        @if($dgMode === 'portal')
        <div class="dg-step" data-step="4">
        <div class="dg-finish-card">
            <div style="font-size:2rem;margin-bottom:.5rem;">🎉</div>
            <h3 style="margin:0 0 .5rem;font-size:1.05rem;color:#1e293b;">Helemaal tevreden met je ontwerp?</h3>
            <p class="dg-hint" style="margin:0 0 1rem;">Zodra je op "Hij is af!" klikt, laten we het weten aan Flitsmoment — zij nemen het ontwerp dan over voor productie. Je kunt hierna niet meer wijzigen.</p>
            <button type="button" class="btn btn-primary" onclick="dgFinishDesign()" id="dg-finish-btn">✅ Hij is af!</button>
        </div>
        <div class="dg-step-nav">
            <button type="button" class="dg-step-nav-btn" onclick="dgWizardGoTo(3)">← Vorige</button>
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
                    <div id="dg-result-body" class="dg-result-body">
                        <img src="{{ $results['url'] }}" alt="Gegenereerde achtergrond">
                        <div id="dg-svg-border-layer" class="dg-svg-border-layer"></div>
                    </div>
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
        @endif
    </div>
</div>
