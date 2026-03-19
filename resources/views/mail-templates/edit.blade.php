@extends('layouts.app')
@section('title', 'Template bewerken: ' . $template->name)

@section('content')

{{-- ── Alerts ── --}}
@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:1rem;">✓ {{ session('success') }}</div>
@endif
@if(session('test_success'))
    <div class="alert alert-success" style="margin-bottom:1rem;">📨 {{ session('test_success') }}</div>
@endif
@if(session('test_error'))
    <div class="alert alert-danger" style="margin-bottom:1rem;">{{ session('test_error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger" style="margin-bottom:1rem;">
        @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
@endif

{{-- ── Header ── --}}
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
    <div>
        <h2 style="margin:0;font-size:1rem;font-weight:700;">{{ $template->name }}</h2>
        <div style="font-size:.75rem;color:#64748b;margin-top:.125rem;">
            {{ $template->recipient_type === 'customer' ? '📧 Klantmail' : '🔧 Adminmail' }}
            &mdash; {{ $template->description }}
        </div>
    </div>
    <a href="{{ route('mail-templates.index') }}" class="btn btn-sm btn-secondary">← Terug</a>
</div>

{{-- ── Outer layout: editor + sidebar ── --}}
<div style="display:grid;grid-template-columns:1fr 260px;gap:1.25rem;align-items:start;">

    {{-- LEFT: form + split editor/preview --}}
    <div>
        <div class="card" style="margin-bottom:1rem;">

            {{-- Subject + active --}}
            <form id="templateForm" method="POST" action="{{ route('mail-templates.update', $template) }}">
                @csrf @method('PUT')

                <div style="display:grid;grid-template-columns:1fr auto;gap:1rem;align-items:end;margin-bottom:1rem;">
                    <div class="form-group" style="margin:0;">
                        <label>Onderwerp *</label>
                        <input type="text" name="subject" value="{{ old('subject', $template->subject) }}" required>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;white-space:nowrap;padding-bottom:2px;">
                            <input type="checkbox" name="is_active" value="1"
                                {{ old('is_active', $template->is_active) ? 'checked' : '' }}
                                style="width:16px;height:16px;">
                            Actief
                        </label>
                    </div>
                </div>

                {{-- Split: HTML editor | Live preview --}}
                <div style="margin-bottom:.5rem;display:flex;align-items:center;justify-content:space-between;">
                    <label style="margin:0;font-size:.875rem;font-weight:500;">HTML inhoud *</label>
                    <div style="display:flex;gap:.5rem;">
                        <button type="button" onclick="setView('split')" id="btn-split"
                            style="font-size:.72rem;padding:.2rem .5rem;border:1px solid #cbd5e1;border-radius:.3rem;background:#fff;cursor:pointer;font-weight:600;">
                            ◫ Split
                        </button>
                        <button type="button" onclick="setView('editor')" id="btn-editor"
                            style="font-size:.72rem;padding:.2rem .5rem;border:1px solid #cbd5e1;border-radius:.3rem;background:#fff;cursor:pointer;">
                            ‹/› Editor
                        </button>
                        <button type="button" onclick="setView('preview')" id="btn-preview"
                            style="font-size:.72rem;padding:.2rem .5rem;border:1px solid #cbd5e1;border-radius:.3rem;background:#fff;cursor:pointer;">
                            👁 Preview
                        </button>
                    </div>
                </div>

                <div style="font-size:.72rem;color:#94a3b8;margin-bottom:.5rem;">
                    Gebruik variabelen zoals <code style="background:#f1f5f9;padding:.1rem .3rem;border-radius:.2rem;">@verbatim{{klant_naam}}@endverbatim</code> — klik rechts om te kopiëren.
                </div>

                <div id="editorWrap" style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;min-height:500px;">
                    {{-- Textarea --}}
                    <div id="editorPane" style="display:flex;flex-direction:column;">
                        <textarea id="bodyEditor" name="body" rows="28"
                            style="flex:1;resize:none;font-family:'Fira Code','Cascadia Code','Courier New',monospace;font-size:.78rem;line-height:1.5;border:1px solid #e2e8f0;border-radius:.375rem;padding:.625rem .75rem;min-height:500px;">{{ old('body', $template->body) }}</textarea>
                    </div>

                    {{-- Live preview --}}
                    <div id="previewPane" style="display:flex;flex-direction:column;border:1px solid #e2e8f0;border-radius:.375rem;overflow:hidden;min-height:500px;">
                        <div style="background:#f8fafc;border-bottom:1px solid #e2e8f0;padding:.35rem .75rem;font-size:.72rem;color:#64748b;display:flex;align-items:center;justify-content:space-between;">
                            <span>👁 Live preview</span>
                            <span style="color:#94a3b8;font-size:.68rem;">Variabelen worden als placeholder getoond</span>
                        </div>
                        <iframe id="previewFrame"
                            style="flex:1;width:100%;border:none;background:#fff;"
                            sandbox="allow-same-origin"></iframe>
                    </div>
                </div>

                <div style="display:flex;gap:.75rem;align-items:center;margin-top:1rem;">
                    <button type="submit" class="btn btn-primary">Opslaan</button>
                    <a href="{{ route('mail-templates.index') }}" class="btn btn-secondary">Annuleren</a>
                </div>
            </form>
        </div>

        {{-- Testmail --}}
        <div class="card" style="background:#f8fafc;">
            <div class="card-header" style="padding-bottom:.625rem;">
                <span class="card-title" style="font-size:.9rem;">🧪 Testmail sturen</span>
            </div>
            <div style="font-size:.8rem;color:#64748b;margin-bottom:.75rem;">
                Stuur een testmail naar <strong>{{ auth()->user()->email }}</strong> met data van de meest recente boeking.
            </div>
            <form method="POST" action="{{ route('mail-templates.test', $template) }}">
                @csrf
                <button type="submit" class="btn btn-secondary"
                    onclick="return confirm('Testmail versturen naar {{ auth()->user()->email }}?')"
                    style="font-size:.875rem;">
                    📨 Stuur testmail
                </button>
            </form>
        </div>
    </div>

    {{-- RIGHT: variabelen sidebar + log --}}
    <div>
        <div class="card" style="margin-bottom:1rem;">
            <div class="card-header"><span class="card-title">Variabelen</span></div>
            <div style="font-size:.8rem;">
                <div style="font-size:.75rem;color:#64748b;margin-bottom:.5rem;">Klik om te kopiëren</div>
                @foreach([
                    '{{klant_naam}}'        => 'Volledige naam',
                    '{{klant_voornaam}}'    => 'Eerste naam',
                    '{{klant_email}}'       => 'E-mailadres',
                    '{{boeking_nummer}}'    => 'Boekingsnummer',
                    '{{event_datum}}'       => 'Eventdatum',
                    '{{event_locatie}}'     => 'Locatienaam',
                    '{{event_adres}}'       => 'Adres + stad',
                    '{{totaal_prijs}}'      => 'Totaalbedrag',
                    '{{openstaand_bedrag}}' => 'Openstaand',
                    '{{portal_link}}'       => 'Portaal URL',
                    '{{gallery_link}}'      => 'Galerij URL',
                    '{{ontwerp_link}}'      => 'Ontwerp URL',
                    '{{opmerkingen_klant}}' => 'Feedback klant',
                    '{{bedrijf_naam}}'      => 'Bedrijfsnaam',
                ] as $var => $desc)
                <div style="padding:.375rem 0;border-bottom:1px solid #f8fafc;">
                    <div onclick="insertVar('{{ addslashes($var) }}')"
                         id="var-{{ md5($var) }}"
                         title="Klik om in te voegen"
                         style="font-family:monospace;font-size:.72rem;background:#f0f9ff;border:1px solid #bae6fd;padding:.15rem .35rem;border-radius:.25rem;cursor:pointer;display:inline-block;margin-bottom:.15rem;color:#0369a1;transition:background .15s;">
                        {{ $var }}
                    </div>
                    <div style="color:#64748b;font-size:.75rem;">{{ $desc }}</div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Recente sends van deze template --}}
        @if($logs->isNotEmpty())
        <div class="card">
            <div class="card-header"><span class="card-title" style="font-size:.875rem;">Recente verzendingen</span></div>
            @foreach($logs as $log)
            <div style="padding:.5rem 0;border-bottom:1px solid #f8fafc;font-size:.75rem;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span style="color:{{ $log->status === 'sent' ? '#16a34a' : '#dc2626' }};font-weight:600;">
                        {{ $log->status === 'sent' ? '✓' : '✗' }} {{ $log->recipient_email }}
                    </span>
                    <span style="color:#94a3b8;">{{ $log->created_at->diffForHumans() }}</span>
                </div>
                @if($log->booking)
                <div style="color:#64748b;">{{ $log->booking->booking_number }}</div>
                @endif
                @if($log->status === 'failed' && $log->error_message)
                <div style="color:#dc2626;font-size:.7rem;margin-top:.15rem;">{{ Str::limit($log->error_message, 80) }}</div>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

<script>
@verbatim
const editor = document.getElementById('bodyEditor');
const frame  = document.getElementById('previewFrame');

// ── Voorbeelddata voor de preview ─────────────────────────
const previewData = {
    klant_naam:        'Jan de Vries',
    klant_voornaam:    'Jan',
    klant_email:       'jan@example.com',
    boeking_nummer:    'FM-2026-0042',
    event_datum:       '14 juni 2026',
    event_locatie:     'De Feestzaal Utrecht',
    event_adres:       'Voorstraat 12, Utrecht',
    totaal_prijs:      '€ 469,00',
    openstaand_bedrag: '€ 469,00',
    portal_link:       '#portaal',
    gallery_link:      '#galerij',
    ontwerp_link:      '#ontwerp',
    opmerkingen_klant: 'Graag roze ballonnen!',
    bedrijf_naam:      'Jansen B.V.',
};

// ── Live preview bijwerken ────────────────────────────────
function updatePreview() {
    const preview = editor.value.replace(/\{\{(\w+)\}\}/g, (_, name) => {
        const val = previewData[name];
        return val
            ? `<span style="background:#fef9c3;color:#854d0e;border-radius:3px;padding:0 2px;">${val}</span>`
            : `<span style="background:#fee2e2;color:#991b1b;border-radius:3px;padding:0 2px;">{{${name}}}</span>`;
    });
    frame.srcdoc = preview;
}

editor.addEventListener('input', updatePreview);
updatePreview();

// ── View modes (split / editor / preview) ─────────────────
function setView(mode) {
    const wrap = document.getElementById('editorWrap');
    const ep   = document.getElementById('editorPane');
    const pp   = document.getElementById('previewPane');
    const btns = { split: document.getElementById('btn-split'), editor: document.getElementById('btn-editor'), preview: document.getElementById('btn-preview') };

    Object.values(btns).forEach(b => {
        b.style.cssText = 'font-size:.72rem;padding:.2rem .5rem;border:1px solid #cbd5e1;border-radius:.3rem;background:#fff;cursor:pointer;color:#374151;font-weight:normal;';
    });
    btns[mode].style.cssText = 'font-size:.72rem;padding:.2rem .5rem;border:1px solid #93c5fd;border-radius:.3rem;background:#eff6ff;cursor:pointer;color:#1d4ed8;font-weight:600;';

    if (mode === 'split') {
        wrap.style.gridTemplateColumns = '1fr 1fr';
        ep.style.display = pp.style.display = 'flex';
    } else if (mode === 'editor') {
        wrap.style.gridTemplateColumns = '1fr';
        ep.style.display = 'flex';
        pp.style.display = 'none';
    } else {
        wrap.style.gridTemplateColumns = '1fr';
        ep.style.display = 'none';
        pp.style.display = 'flex';
    }
}
setView('split');

// ── Variabele invoegen op cursorpositie ───────────────────
// Sla de vartext op als data-attribuut zodat we zonder md5 kunnen werken
document.querySelectorAll('[id^="var-"]').forEach(el => {
    const m = el.getAttribute('onclick')?.match(/insertVar\('(.+?)'\)/);
    if (m) el.dataset.vartext = m[1];
});

function insertVar(varText) {
    const s = editor.selectionStart, e = editor.selectionEnd;
    editor.value = editor.value.slice(0, s) + varText + editor.value.slice(e);
    editor.selectionStart = editor.selectionEnd = s + varText.length;
    editor.focus();
    updatePreview();

    // Flash feedback op de badge
    document.querySelectorAll('[id^="var-"]').forEach(el => {
        if (el.dataset.vartext === varText) {
            el.style.background = '#dcfce7'; el.style.borderColor = '#86efac';
            setTimeout(() => { el.style.background = '#f0f9ff'; el.style.borderColor = '#bae6fd'; }, 700);
        }
    });
}
@endverbatim
</script>

@endsection
