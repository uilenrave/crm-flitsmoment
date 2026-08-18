@php
    $method = $booking->strip_design_method;
    $status = $booking->strip_status;
    $tpl    = $booking->stripTemplate;
    $canSwitch = ! in_array($status, ['accepted', 'ready']);

    $designEmail = 'ontwerp@flitsmoment.nl';

    // Photoshop-templates die al in /public/templates/ staan
    $photoshopTemplates = [
        ['file' => 'template1', 'name' => 'Template 1'],
        ['file' => 'template2', 'name' => 'Template 2'],
        ['file' => 'template3', 'name' => 'Template 3'],
        ['file' => 'template4', 'name' => 'Template 4'],
    ];

    // Bepaal de huidige UI-stap op basis van de DB-state.
    // - accepted/ready → altijd 'final' (afgerond), óók de oude Google Drive-boekingen zónder
    //   strip_design_url; die vielen voorheen foutief terug op de keuzekaarten.
    // - review vereist wél een ontwerp om te tonen.
    // - designing staat vóór de methode-keuze: zodra wij aan de slag zijn (hulp-formulier of admin)
    //   tonen we "wij zijn aan de slag" i.p.v. de keuzekaarten — anders zou de klant onze lopende
    //   sessie kunnen resetten door alsnog een methode te kiezen.
    // - Legacy: method 'self' (met of zonder oude tool) → template_hub; 'template'/'custom' → designing.
    $stripState =
        in_array($status, ['accepted', 'ready'])              ? 'final'
        : ($status === 'review' && $booking->strip_design_url ? 'final'
        : ($status === 'designing' ? 'designing'
        : (! $method               ? 'choose_method'
        : ($method === 'ai'        ? 'ai_wizard'
        : ($method === 'self'      ? 'template_hub'
        : 'designing')))));  // legacy 'template' + 'custom' → "wij zijn aan de slag"-paneel
@endphp

<style>
    /* Fotostrip ─────────────────────────────────────── */
    .strip-section { border-top: 3px solid #fcd34d; }
    .strip-section .section-icon { background: #fef3c7; }

    .strip-method-badge {
        margin-left: auto;
        display: inline-flex; align-items: center; gap: .35rem;
        background: #fef3c7; color: #92400e;
        padding: .25rem .75rem; border-radius: 9999px;
        font-size: .75rem; font-weight: 600;
    }

    .strip-step-label {
        display: inline-block; font-size: .72rem; font-weight: 700;
        color: #92400e; background: #fef3c7;
        padding: .25rem .65rem; border-radius: 9999px;
        margin-bottom: .75rem; letter-spacing: .03em; text-transform: uppercase;
    }
    .strip-step-question { font-size: 1.15rem; font-weight: 700; line-height: 1.35; margin-bottom: 1.25rem; color: var(--text); }
    .strip-step-help { color: var(--muted); font-size: .9rem; margin: -.75rem 0 1rem; line-height: 1.5; }

    /* Radio-style option cards */
    .strip-options { display: flex; flex-direction: column; gap: .65rem; margin-bottom: 1.25rem; }
    .strip-option {
        display: flex; align-items: center; gap: 1rem;
        background: #fff; border: 2px solid var(--border);
        border-radius: .75rem; padding: 1rem 1.2rem;
        cursor: pointer; text-align: left; width: 100%;
        transition: all .15s;
        position: relative;
    }
    .strip-option:hover { border-color: #fcd34d; background: #fffbeb; }
    .strip-option.is-selected {
        border-color: #f59e0b; background: #fffbeb;
        box-shadow: 0 0 0 4px rgba(245,158,11,.15);
    }
    .strip-option-radio {
        width: 1.25rem; height: 1.25rem; border-radius: 50%;
        border: 2px solid var(--border); flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        transition: all .15s;
    }
    .strip-option.is-selected .strip-option-radio {
        border-color: #f59e0b; background: #f59e0b;
    }
    .strip-option.is-selected .strip-option-radio::after {
        content: ''; width: .5rem; height: .5rem; border-radius: 50%; background: #fff;
    }
    .strip-option-icon {
        width: 2.5rem; height: 2.5rem; border-radius: .625rem;
        background: #fef3c7; display: flex; align-items: center; justify-content: center;
        font-size: 1.25rem; flex-shrink: 0;
    }
    .strip-option-body { flex: 1; min-width: 0; }
    .strip-option-title { font-weight: 700; font-size: 1rem; color: var(--text); margin-bottom: .15rem; }
    .strip-option-desc { font-size: .85rem; color: var(--muted); line-height: 1.45; }

    /* Filters */
    .strip-filter-group { margin-bottom: .875rem; }
    .strip-filter-label {
        font-size: .7rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: .05em; color: #92400e; margin-bottom: .375rem;
    }
    .strip-filter-row { display: flex; flex-wrap: wrap; gap: .375rem; }
    .strip-filter-btn {
        padding: .35rem .8rem; border-radius: 9999px;
        border: 1.5px solid #fcd34d; background: #fff; color: #78350f;
        font-size: .8rem; font-weight: 600; cursor: pointer;
        transition: all .15s;
    }
    .strip-filter-btn:hover { background: #fef3c7; }
    .strip-filter-btn.is-active { background: #f59e0b; border-color: #f59e0b; color: #fff; }

    /* Template galerij */
    .strip-gallery {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: .75rem; margin-top: 1rem;
    }
    .strip-tpl-card {
        background: #fff; border: 2px solid var(--border); border-radius: .625rem;
        overflow: hidden; cursor: pointer; transition: all .15s;
        display: flex; flex-direction: column;
    }
    .strip-tpl-card:hover {
        border-color: #f59e0b;
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(245,158,11,.18);
    }
    .strip-tpl-thumb { position: relative; background: #f9fafb; aspect-ratio: 1/1; overflow: hidden; }
    .strip-tpl-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .strip-tpl-number {
        position: absolute; top: .5rem; left: .5rem;
        background: rgba(17,24,39,.85); color: #fff;
        padding: .2rem .6rem; border-radius: .4rem;
        font-size: .75rem; font-weight: 700;
    }
    .strip-tpl-name {
        padding: .55rem .7rem; font-size: .8rem; font-weight: 600; color: var(--text);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .strip-no-results { text-align: center; padding: 2rem 1rem; color: var(--muted); font-size: .9rem; }

    /* Photoshop templates grid */
    .strip-psd-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; margin-top: 1rem; }
    .strip-psd-card {
        background: #fff; border: 1.5px solid var(--border); border-radius: .625rem;
        overflow: hidden; transition: all .15s;
    }
    .strip-psd-card:hover { border-color: #f59e0b; box-shadow: 0 4px 10px rgba(245,158,11,.12); }
    .strip-psd-preview {
        background: #1f2937; padding: .875rem;
        display: flex; align-items: center; justify-content: center; min-height: 130px;
    }
    .strip-psd-preview img {
        max-width: 100%; max-height: 110px; object-fit: contain;
        border-radius: 3px; box-shadow: 0 2px 8px rgba(0,0,0,.35);
    }
    .strip-psd-foot {
        display: flex; align-items: center; justify-content: space-between;
        padding: .55rem .75rem;
    }
    .strip-psd-foot span { font-weight: 600; font-size: .85rem; color: var(--text); }
    .strip-psd-download {
        display: inline-flex; align-items: center; gap: .25rem;
        font-size: .75rem; font-weight: 700; color: #b45309;
        text-decoration: none; background: #fef3c7;
        padding: .3rem .65rem; border-radius: .375rem;
        transition: background .15s;
    }
    .strip-psd-download:hover { background: #fcd34d; }

    /* Canva templates grid */
    .canva-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; margin-top: 1rem; }
    .canva-card {
        display: block; text-decoration: none; color: inherit;
        background: #fff; border: 1.5px solid var(--border); border-radius: .625rem;
        overflow: hidden; transition: all .15s; cursor: pointer;
    }
    .canva-card:hover { border-color: #f59e0b; transform: translateY(-2px); box-shadow: 0 6px 14px rgba(245,158,11,.18); }
    .canva-card-thumb { position: relative; aspect-ratio: 1/1; background: #f9fafb; overflow: hidden; padding: .35rem; display: flex; align-items: center; justify-content: center; }
    .canva-card-thumb img { max-width: 100%; max-height: 100%; width: auto; height: auto; object-fit: contain; display: block; }
    .canva-card-thumb::after {
        content: '↗'; position: absolute; top: .5rem; right: .5rem;
        background: rgba(17,24,39,.85); color: #fff;
        width: 1.6rem; height: 1.6rem; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: .85rem; font-weight: 700;
    }
    .canva-card-body { padding: .55rem .7rem .65rem; }
    .canva-card-title {
        font-size: .82rem; font-weight: 600; color: var(--text); line-height: 1.3;
        margin-bottom: .25rem;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .canva-card-format { font-size: .7rem; color: var(--muted); }
    @media (max-width: 380px) { .canva-grid { grid-template-columns: 1fr; } }

    /* Forms */
    .strip-form-block { background: #fff; border: 1.5px solid #fcd34d; border-radius: .75rem; padding: 1.25rem; }
    .strip-form-block label {
        display: block; font-size: .75rem; font-weight: 600;
        color: var(--text); margin-bottom: .35rem; margin-top: .5rem;
    }
    .strip-form-block textarea, .strip-form-block input[type=file] {
        width: 100%; padding: .65rem .8rem;
        border: 1.5px solid var(--border); border-radius: .5rem;
        font-size: .9rem; font-family: inherit; resize: vertical;
        background: var(--bg); color: var(--text);
        transition: all .15s;
    }
    .strip-form-block textarea:focus, .strip-form-block input[type=file]:focus {
        outline: none; border-color: #f59e0b; background: #fff;
        box-shadow: 0 0 0 3px rgba(245,158,11,.12);
    }
    .strip-form-block input[type=file] { padding: .55rem; cursor: pointer; }
    .strip-form-help { font-size: .85rem; color: var(--muted); margin: 0 0 1rem 0; line-height: 1.5; }

    /* Buttons */
    .strip-btn {
        display: inline-flex; align-items: center; justify-content: center; gap: .4rem;
        padding: .75rem 1.25rem; border-radius: .5rem;
        font-weight: 700; font-size: .9rem;
        border: none; cursor: pointer; text-decoration: none;
        transition: all .15s;
    }
    .strip-btn[disabled] { opacity: .45; cursor: not-allowed; }
    .strip-btn-primary {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: #fff; box-shadow: 0 2px 8px rgba(245,158,11,.3);
    }
    .strip-btn-primary:not([disabled]):hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(245,158,11,.4); }
    .strip-btn-secondary { background: #fff; color: var(--text); border: 1.5px solid var(--border); }
    .strip-btn-secondary:hover { background: var(--bg); border-color: #cbd5e1; }
    .strip-btn-full { width: 100%; }

    .strip-actions {
        display: flex; gap: .65rem; justify-content: space-between; align-items: center;
        margin-top: 1.25rem;
    }
    .strip-actions .strip-btn-primary { flex: 1; max-width: 240px; margin-left: auto; }

    /* Status panels (na keuze) */
    .strip-status-panel {
        display: flex; align-items: flex-start; gap: .875rem;
        background: #fff; border: 1.5px solid #fcd34d; border-radius: .75rem;
        padding: 1.1rem 1.25rem;
    }
    .strip-status-panel .icon { font-size: 1.5rem; flex-shrink: 0; line-height: 1; }
    .strip-status-panel-title { font-weight: 700; font-size: .95rem; color: var(--text); margin-bottom: .25rem; }
    .strip-status-panel-text { font-size: .875rem; color: var(--muted); line-height: 1.5; }
    .strip-status-panel-text strong { color: var(--text); }

    .strip-info-bar {
        background: var(--primary-light); border: 1px solid #bfdbfe;
        border-radius: .5rem; padding: .75rem .875rem;
        font-size: .875rem; color: #1e40af;
        display: flex; align-items: center; gap: .5rem;
        margin-top: .875rem;
    }
    .strip-warn-bar {
        background: var(--orange-light); border: 1px solid #fcd34d;
        border-radius: .5rem; padding: .75rem .875rem;
        font-size: .8125rem; color: #92400e;
        display: flex; align-items: center; gap: .5rem;
        margin-top: .875rem;
    }

    /* Email-callout */
    .strip-email-callout {
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        border: 1.5px solid #fcd34d; border-radius: .75rem;
        padding: 1rem 1.25rem; margin-top: 1rem;
    }
    .strip-email-callout-title { font-weight: 700; font-size: .9rem; color: #78350f; margin-bottom: .35rem; display: flex; align-items: center; gap: .35rem; }
    .strip-email-callout-text { font-size: .875rem; color: #78350f; line-height: 1.55; }
    .strip-email-callout code {
        display: inline-block; padding: .15rem .45rem;
        background: rgba(255,255,255,.55); border-radius: .25rem;
        font-weight: 700; font-family: -apple-system, BlinkMacSystemFont, monospace;
        font-size: .85rem;
    }

    /* Template preview kaart */
    .strip-tpl-preview {
        display: flex; gap: 1.1rem; align-items: flex-start;
        background: #fff; border: 1.5px solid #fcd34d; border-radius: .75rem;
        padding: 1rem 1.25rem; margin-bottom: 1rem;
    }
    .strip-tpl-preview-img {
        width: 110px; flex-shrink: 0; border-radius: .5rem; overflow: hidden;
        border: 1px solid var(--border); background: var(--bg);
    }
    .strip-tpl-preview-img img { width: 100%; display: block; }
    .strip-tpl-preview-meta-label { font-size: .7rem; font-weight: 700; color: #92400e; text-transform: uppercase; letter-spacing: .05em; margin-bottom: .25rem; }
    .strip-tpl-preview-title { font-weight: 700; font-size: 1.05rem; color: var(--text); }
    .strip-tag-row { display: flex; gap: .35rem; flex-wrap: wrap; margin-top: .5rem; }
    .strip-tag { font-size: .72rem; padding: .15rem .55rem; border-radius: 9999px; font-weight: 600; }
    .strip-tag-theme { background: #fef3c7; color: #92400e; }
    .strip-tag-format { background: #dbeafe; color: #1e40af; }

    /* Read-back */
    .strip-text-readback {
        margin-top: .875rem; background: var(--bg); border: 1px solid var(--border);
        border-radius: .5rem; padding: .75rem .875rem;
    }
    .strip-text-readback-label { font-size: .7rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; margin-bottom: .25rem; }
    .strip-text-readback-content { font-size: .875rem; color: var(--text); white-space: pre-line; line-height: 1.55; }

    /* Modal */
    .strip-modal-backdrop {
        display: none; position: fixed; inset: 0; z-index: 9999;
        background: rgba(17,24,39,.55); backdrop-filter: blur(2px);
        align-items: center; justify-content: center; padding: 1rem;
    }
    .strip-modal-backdrop.is-open { display: flex; animation: stripFade .2s ease; }
    @keyframes stripFade { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: translateY(0); } }
    .strip-modal {
        background: #fff; border-radius: 1rem;
        max-width: 540px; width: 100%; max-height: 90vh; overflow-y: auto;
        box-shadow: 0 24px 64px rgba(0,0,0,.25);
    }
    .strip-modal-body { padding: 1.75rem; }
    .strip-modal-image {
        width: 100%; max-height: 400px; object-fit: contain;
        border: 1px solid var(--border); border-radius: .5rem;
        background: var(--bg); display: block; margin: 0 auto 1.25rem;
    }
    .strip-modal-title { font-size: 1.25rem; font-weight: 700; text-align: center; color: var(--text); margin-bottom: .35rem; }
    .strip-modal-tags { display: flex; justify-content: center; gap: .4rem; margin-bottom: .875rem; }
    .strip-modal-note { text-align: center; font-size: .85rem; color: var(--muted); line-height: 1.5; margin-bottom: 1.25rem; }
    .strip-modal-actions { display: flex; gap: .625rem; justify-content: flex-end; }

    @media (max-width: 480px) {
        .strip-gallery { grid-template-columns: repeat(2, 1fr); gap: .55rem; }
        .strip-psd-grid { grid-template-columns: 1fr; }
        .strip-tpl-preview { flex-direction: column; align-items: stretch; }
        .strip-tpl-preview-img { width: 140px; margin: 0 auto; }
        .strip-actions { flex-direction: column-reverse; gap: .5rem; }
        .strip-actions .strip-btn { width: 100%; max-width: none; margin: 0; }
        .strip-modal-actions { flex-direction: column; }
        .strip-modal-actions .strip-btn { width: 100%; }
    }
</style>

<div class="section strip-section">
    <div class="section-header">
        <div class="section-icon">🎨</div>
        <span class="section-title">Fotostrip ontwerp</span>
        @if($method)
            <span class="strip-method-badge">
                @if($method === 'self') 🎨 Zelf met template
                @elseif($method === 'ai') ✨ AI-tool
                @elseif($method === 'template') 📋 Template (verouderd)
                @else ✏️ Wij ontwerpen
                @endif
            </span>
        @endif
    </div>
    <div class="section-body">

    {{-- ═══════════ STAP 1: HOE WIL JE ONTWERPEN? (2 kaarten) ═══════════ --}}
    @if($stripState === 'choose_method')
        <div class="strip-step-label">Fotostrip zelf ontwerpen</div>
        <div class="strip-step-question">Hoe wil je je fotostrip-ontwerp maken?</div>

        <form method="POST" action="{{ route('portal.strip-choose-path', $booking->public_token) }}" id="strip-method-form">
            @csrf
            <div class="strip-options">
                <label class="strip-option" data-method="template">
                    <input type="radio" name="path" value="template" style="display:none;" onchange="stripSelectMethod(this)">
                    <span class="strip-option-radio"></span>
                    <span class="strip-option-icon">🎨</span>
                    <span class="strip-option-body">
                        <span class="strip-option-title">Gebruik een template</span>
                        <span class="strip-option-desc">Kies een kant-en-klaar Canva-template (online, gratis) of download onze Photoshop-bestanden.</span>
                    </span>
                </label>
                <label class="strip-option" data-method="ai">
                    <input type="radio" name="path" value="ai" style="display:none;" onchange="stripSelectMethod(this)">
                    <span class="strip-option-radio"></span>
                    <span class="strip-option-icon">✨</span>
                    <span class="strip-option-body">
                        <span class="strip-option-title">Ontwerpen met onze AI-tool</span>
                        <span class="strip-option-desc">Genereer zelf een unieke achtergrond en ontwerp stap voor stap je fotostrip.</span>
                    </span>
                </label>
            </div>
            <div class="strip-actions">
                <button type="submit" class="strip-btn strip-btn-primary" id="strip-method-submit" disabled>
                    Volgende →
                </button>
            </div>
        </form>

    {{-- ═══════════ TEMPLATE-HUB: CANVA PROMINENT + PHOTOSHOP INKLAPBAAR ═══════════ --}}
    @elseif($stripState === 'template_hub')
        <div class="strip-step-label">Aan jou de eer 🎨</div>
        <div class="strip-step-question">Ontwerp je fotostrip met een template</div>

        @php
            $canvaTpls = $canvaTemplates ?? collect();
            $availFormats = $canvaTpls->pluck('format')->unique()->values();
        @endphp

        <p class="strip-form-help">Kies een Canva-template om mee te starten — online, in je browser, gratis account volstaat:</p>

        @if($canvaTpls->isEmpty())
            <p style="font-size:.9rem;color:var(--muted);">Op dit moment zijn er nog geen Canva-templates beschikbaar. Gebruik dan de Photoshop-templates hieronder, of vraag onze hulp.</p>
        @else
            {{-- Filter-pillen op format --}}
            @if($availFormats->count() > 1)
            <div class="strip-filter-group" style="margin-bottom:.5rem;">
                <div class="strip-filter-row">
                    <button type="button" class="strip-filter-btn is-active" data-filter="format" data-value="" onclick="canvaFilterApply(this)">Alle</button>
                    @foreach(\App\Models\CanvaTemplate::FORMAT_LABELS as $val => $lbl)
                        @if($availFormats->contains($val))
                        <button type="button" class="strip-filter-btn" data-filter="format" data-value="{{ $val }}" onclick="canvaFilterApply(this)">{{ $lbl }}</button>
                        @endif
                    @endforeach
                </div>
            </div>
            @endif

            <div class="canva-grid" id="canva-gallery">
                @foreach($canvaTpls as $tpl)
                <a href="{{ $tpl->canva_url }}" target="_blank" rel="noopener" class="canva-card" data-format="{{ $tpl->format }}">
                    <div class="canva-card-thumb">
                        <img src="{{ $tpl->image_url }}" alt="{{ $tpl->title }}">
                    </div>
                    <div class="canva-card-body">
                        <div class="canva-card-title">{{ $tpl->title }}</div>
                        <div class="canva-card-format">{{ $tpl->format_label }} · 📷 {{ $tpl->photo_count }} {{ $tpl->photo_count === 1 ? 'foto' : 'foto\'s' }}</div>
                    </div>
                </a>
                @endforeach
            </div>
            <p id="canva-no-results" class="strip-no-results" style="display:none;">Geen Canva-templates gevonden in deze categorie.</p>

            <ol style="padding-left:1.25rem;margin:1rem 0 0;font-size:.9rem;line-height:1.7;color:var(--text);">
                <li>Klik een template — die opent direct in Canva.</li>
                <li>Pas het ontwerp aan met je eigen tekst, kleuren en eventueel foto's.</li>
                <li>Klik <strong>Delen → Downloaden</strong> en kies <strong>PNG</strong> of <strong>PDF (afdruk)</strong>.</li>
            </ol>
        @endif

        {{-- Photoshop als bescheiden, inklapbaar alternatief --}}
        <details class="strip-psd-details" style="margin-top:1.25rem;border:1px solid var(--border);border-radius:.6rem;padding:.75rem 1rem;">
            <summary style="cursor:pointer;font-weight:600;font-size:.9rem;color:var(--text);">🖌 Liever Photoshop? Download hier de .psd-templates</summary>
            <div class="strip-psd-grid">
                @foreach($photoshopTemplates as $pt)
                <div class="strip-psd-card">
                    <div class="strip-psd-preview">
                        <img src="/templates/{{ $pt['file'] }}.webp" alt="{{ $pt['name'] }}">
                    </div>
                    <div class="strip-psd-foot">
                        <span>{{ $pt['name'] }}</span>
                        <a href="/templates/{{ $pt['file'] }}.zip" download class="strip-psd-download">📥 Download</a>
                    </div>
                </div>
                @endforeach
            </div>
            <ol style="padding-left:1.25rem;margin:.75rem 0 0;font-size:.9rem;line-height:1.7;color:var(--text);">
                <li>Download een template, pak de ZIP uit.</li>
                <li>Open het <code>.psd</code>-bestand in Photoshop.</li>
                <li>Pas tekstlagen, kleuren en eventueel foto's aan en exporteer als <strong>PNG</strong> of <strong>PDF</strong>.</li>
            </ol>
        </details>

        <div class="strip-email-callout">
            <div class="strip-email-callout-title">📨 Stuur je ontwerp naar ons</div>
            <div class="strip-email-callout-text">
                Mail het afgewerkte bestand naar <code>{{ $designEmail }}</code><br>
                met als onderwerp: <code>Fotostrip {{ $booking->booking_number }}</code>
            </div>
        </div>

        <div class="strip-warn-bar">⏳ <span><strong>Status: we wachten op je ontwerp.</strong> Zodra het binnen is, krijg je een voorbeeld ter goedkeuring.</span></div>

        @include('portal._strip_help_form')

    {{-- ═══════════ AI-TOOL: DOORVERWIJZING NAAR DE WIZARD-PAGINA ═══════════ --}}
    @elseif($stripState === 'ai_wizard')
        <div class="strip-step-label">Aan de slag ✨</div>
        <div class="strip-step-question">Ontwerp je fotostrip met onze AI-tool</div>
        <p class="strip-form-help">Je werkt stap voor stap door de onderdelen heen (achtergrond, logo, transparantie). Je voortgang wordt automatisch opgeslagen — je kunt altijd terugkomen.</p>
        <a href="{{ route('portal.design-tool', $booking->public_token) }}" class="strip-btn strip-btn-primary strip-btn-full">✨ Open de ontwerp-tool →</a>

        @include('portal._strip_help_form')

    {{-- ═══════════ IN PRODUCTIE — DESIGNING (wij zijn aan de slag) ═══════════ --}}
    @elseif($stripState === 'designing')
        @if($tpl)
        <div class="strip-tpl-preview">
            <div class="strip-tpl-preview-img"><img src="{{ $tpl->image_url }}" alt=""></div>
            <div style="flex:1;min-width:0;">
                <div class="strip-tpl-preview-meta-label">Gekozen template</div>
                <div class="strip-tpl-preview-title">#{{ $tpl->number }}{{ $tpl->name ? ' · '.$tpl->name : '' }}</div>
                <div class="strip-tag-row">
                    <span class="strip-tag strip-tag-theme">{{ $tpl->theme_label }}</span>
                    <span class="strip-tag strip-tag-format">{{ $tpl->format_label }}</span>
                </div>
            </div>
        </div>
        @endif

        <div class="strip-info-bar">
            @if($method === 'custom') ✏️
            @else ⏳
            @endif
            <span><strong>Wij zijn aan de slag.</strong> Je ontvangt binnenkort een voorbeeld ter goedkeuring.</span>
        </div>

        @if($booking->strip_intake_data['text'] ?? null)
        <div class="strip-text-readback">
            <div class="strip-text-readback-label">Jouw {{ $method === 'template' ? 'tekst' : 'wensen' }}</div>
            <div class="strip-text-readback-content">{{ $booking->strip_intake_data['text'] }}</div>
        </div>
        @endif

    {{-- ═══════════ STAP 5: FINAL — REVIEW / ACCEPTED / READY ═══════════ --}}
    @elseif($stripState === 'final')
        @if($status === 'review' && $booking->strip_design_url)
            <div class="strip-status-panel">
                <span class="icon">👀</span>
                <div>
                    <div class="strip-status-panel-title">Je voorbeeld staat klaar</div>
                    <div class="strip-status-panel-text">Bekijk het ontwerp en laat ons weten of we het zo kunnen printen, of dat er nog iets aangepast moet worden.</div>
                </div>
            </div>
            <div style="margin-top:1rem;">
                <a href="#fotostrip-ontwerp" class="strip-btn strip-btn-primary strip-btn-full">↓ Bekijk & beoordeel je ontwerp hieronder</a>
            </div>
        @elseif($status === 'accepted')
            <div class="strip-status-panel" style="border-color:#86efac;background:#f0fdf4;">
                <span class="icon">✅</span>
                <div>
                    <div class="strip-status-panel-title" style="color:#15803d;">Ontwerp goedgekeurd</div>
                    <div class="strip-status-panel-text">Bedankt! Je ontwerp staat klaar voor productie.</div>
                </div>
            </div>
        @elseif($status === 'ready')
            <div class="strip-status-panel" style="border-color:#86efac;background:#f0fdf4;">
                <span class="icon">🎉</span>
                <div>
                    <div class="strip-status-panel-title" style="color:#15803d;">Klaar voor je event</div>
                    <div class="strip-status-panel-text">Je fotostrip-ontwerp is geprint en wordt meegenomen.</div>
                </div>
            </div>
        @endif
    @endif

    {{-- Reset / wissel-knop — alleen als methode al gezet is én niet meer in flow-stappen die hun eigen "Vorige" hebben --}}
    @if($method && $canSwitch && in_array($stripState, ['template_hub', 'ai_wizard', 'designing']))
        <div style="margin-top:1rem;padding-top:1rem;border-top:1px dashed var(--border);text-align:right;">
            <form method="POST" action="{{ route('portal.strip-reset', $booking->public_token) }}" style="display:inline;">
                @csrf
                <button type="button" style="background:none;border:none;cursor:pointer;font-size:.8125rem;color:var(--muted);text-decoration:underline;text-underline-offset:3px;"
                        onclick="if(window.crmConfirm){crmConfirm('Andere ontwerpmethode kiezen? Je huidige keuze wordt teruggezet.',()=>this.closest('form').submit());}else if(confirm('Andere methode kiezen?')){this.closest('form').submit();}return false;">
                    🔄 Andere optie kiezen
                </button>
            </form>
        </div>
    @endif

    </div>
</div>

{{-- Hidden reset-form voor "Vorige" knoppen in flow-stappen --}}
@if($method && $canSwitch)
<form id="strip-hidden-reset" method="POST" action="{{ route('portal.strip-reset', $booking->public_token) }}" style="display:none;">@csrf</form>
@endif

<script>
function stripSelectMethod(input) {
    const form = input.form;
    form.querySelectorAll('.strip-option').forEach(o => o.classList.remove('is-selected'));
    input.closest('.strip-option').classList.add('is-selected');
    const submit = form.querySelector('button[type="submit"]');
    if (submit) submit.disabled = false;
}
function stripResetChoice() {
    const f = document.getElementById('strip-hidden-reset');
    if (f) f.submit();
}
function canvaFilterApply(btn) {
    // Scope tot Canva-galerij — voorkomt collision met strip-template filter
    const gallery = document.getElementById('canva-gallery');
    if (!gallery) return;
    const filterRow = btn.closest('.strip-filter-row');
    filterRow.querySelectorAll('.strip-filter-btn').forEach(b => b.classList.remove('is-active'));
    btn.classList.add('is-active');
    const wanted = btn.dataset.value;
    let visible = 0;
    gallery.querySelectorAll('.canva-card').forEach(card => {
        const ok = !wanted || card.dataset.format === wanted;
        card.style.display = ok ? '' : 'none';
        if (ok) visible++;
    });
    const noResults = document.getElementById('canva-no-results');
    if (noResults) noResults.style.display = visible === 0 ? 'block' : 'none';
}
</script>
