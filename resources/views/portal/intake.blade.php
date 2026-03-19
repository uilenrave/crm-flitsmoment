<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Intake — {{ $booking->account->name }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary: #fcd34d;
            --primary-hover: #f59e0b;
            --primary-dark: #d97706;
            --bg: #f8f7f5;
            --card: #ffffff;
            --text: #111827;
            --muted: #6b7280;
            --border: #e5e7eb;
            --shadow: -1px -1px 3px rgba(255,255,255,0.8), 2px 2px 5px rgba(0,0,0,0.08);
            --shadow-lg: -2px -2px 5px rgba(255,255,255,0.8), 3px 3px 8px rgba(0,0,0,0.12);
            --radius: 1rem;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            overflow: hidden;
        }

        /* Header */
        .intake-header {
            position: fixed; top: 0; left: 0; right: 0; z-index: 50;
            background: var(--card);
            border-bottom: 1px solid var(--border);
            padding: 1rem 1.5rem;
            display: flex; align-items: center; justify-content: space-between;
        }
        .intake-header-left { font-size: .9rem; font-weight: 600; color: var(--text); }
        .intake-header-right { font-size: .8rem; color: var(--muted); }

        /* Progress bar */
        .progress-wrap {
            position: fixed; top: 60px; left: 0; right: 0; z-index: 49;
            background: var(--border); height: 4px;
        }
        .progress-bar {
            height: 100%; background: linear-gradient(90deg, var(--primary), var(--primary-hover));
            border-radius: 0 2px 2px 0;
            transition: width .4s ease;
        }

        /* Steps container */
        .steps-viewport {
            position: fixed; top: 64px; bottom: 0; left: 0; right: 0;
            overflow: hidden;
        }
        .intake-step {
            position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            display: flex; align-items: center; justify-content: center;
            padding: 2rem 1.25rem;
            transform: translateX(100%);
            transition: transform .45s cubic-bezier(.4, 0, .2, 1);
            overflow-y: auto;
        }
        .intake-step.active { transform: translateX(0); }
        .intake-step.left { transform: translateX(-100%); }

        /* Card */
        .step-card {
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            padding: 2.5rem 2rem;
            width: 100%; max-width: 540px;
        }

        .step-number {
            font-size: .75rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .08em; color: var(--primary-dark);
            margin-bottom: .75rem;
        }
        .step-question {
            font-size: 1.35rem; font-weight: 700; line-height: 1.35;
            margin-bottom: 1.75rem; color: var(--text);
        }

        /* Option cards */
        .option-card {
            display: block; cursor: pointer; position: relative;
            background: var(--card);
            border: 2px solid var(--border);
            border-radius: .75rem;
            padding: 1.125rem 1.25rem;
            margin-bottom: .75rem;
            transition: all .2s ease;
            box-shadow: var(--shadow);
        }
        .option-card:hover { border-color: var(--primary); }
        .option-card.selected {
            border-color: var(--primary);
            background: #fffbeb;
            box-shadow: 0 0 0 3px rgba(252, 211, 77, .25), var(--shadow);
        }
        .option-card input[type="radio"] { display: none; }
        .option-title { font-weight: 600; font-size: .95rem; margin-bottom: .25rem; }
        .option-desc { font-size: .8rem; color: var(--muted); line-height: 1.45; }

        /* Conditional sub-fields */
        .sub-fields {
            max-height: 0; overflow: hidden; opacity: 0;
            transition: max-height .35s ease, opacity .25s ease, margin .25s ease;
            margin-top: 0;
        }
        .sub-fields.open {
            max-height: 500px; opacity: 1; margin-top: 1rem;
        }

        /* File upload */
        .upload-zone {
            border: 2px dashed var(--border);
            border-radius: .75rem;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all .2s;
            background: #fafaf9;
        }
        .upload-zone:hover, .upload-zone.dragover {
            border-color: var(--primary);
            background: #fffbeb;
        }
        .upload-zone-icon { font-size: 1.75rem; margin-bottom: .5rem; }
        .upload-zone-text { font-size: .85rem; color: var(--muted); }
        .upload-zone-text strong { color: var(--primary-dark); }
        .file-list { margin-top: .75rem; text-align: left; }
        .file-item {
            display: flex; align-items: center; gap: .5rem;
            font-size: .8rem; padding: .375rem .5rem;
            background: var(--bg); border-radius: .375rem;
            margin-bottom: .375rem;
        }
        .file-item-remove {
            margin-left: auto; cursor: pointer; color: #dc2626;
            font-weight: 700; font-size: .9rem; border: none; background: none;
        }

        /* Input fields */
        .form-input, .form-textarea {
            width: 100%;
            padding: .75rem 1rem;
            border: 2px solid var(--border);
            border-radius: .75rem;
            font-size: 1rem;
            font-family: inherit;
            color: var(--text);
            background: var(--card);
            transition: border-color .2s;
            box-shadow: var(--shadow);
        }
        .form-input:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(252, 211, 77, .25);
        }
        .form-textarea { resize: vertical; min-height: 80px; }
        .form-label {
            display: block; font-size: .85rem; font-weight: 600;
            margin-bottom: .5rem; color: var(--text);
        }
        .form-group { margin-bottom: 1rem; }

        /* Buttons */
        .btn-row {
            display: flex; gap: .75rem; margin-top: 2rem;
            justify-content: space-between; align-items: center;
        }
        .btn-next {
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: #78350f; font-weight: 700; font-size: 1rem;
            padding: .875rem 2rem;
            border: none; border-radius: .75rem;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(252, 211, 77, .35);
            transition: all .2s;
        }
        .btn-next:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(252, 211, 77, .45); }
        .btn-next:disabled { opacity: .5; cursor: not-allowed; transform: none; }
        .btn-back {
            background: none; border: 2px solid var(--border);
            color: var(--muted); font-weight: 600; font-size: .9rem;
            padding: .75rem 1.5rem; border-radius: .75rem;
            cursor: pointer; transition: all .2s;
        }
        .btn-back:hover { border-color: var(--muted); color: var(--text); }

        /* Completion screen */
        .completion-icon { font-size: 3.5rem; margin-bottom: 1rem; }
        .completion-title { font-size: 1.5rem; font-weight: 700; margin-bottom: .5rem; }
        .completion-text { color: var(--muted); font-size: .95rem; margin-bottom: 1.5rem; line-height: 1.5; }
        .btn-portal {
            display: inline-flex; align-items: center; gap: .5rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: #78350f; font-weight: 700; font-size: 1rem;
            padding: .875rem 2rem; border-radius: .75rem;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(252, 211, 77, .35);
            transition: all .2s;
        }
        .btn-portal:hover { transform: translateY(-1px); }

        /* Errors */
        .field-error { color: #dc2626; font-size: .8rem; margin-top: .35rem; }

        /* Loading spinner */
        .spinner { display: none; width: 20px; height: 20px; border: 3px solid rgba(120,53,15,.2); border-top-color: #78350f; border-radius: 50%; animation: spin .6s linear infinite; margin-left: .5rem; }
        .btn-next.loading .spinner { display: inline-block; }
        .btn-next.loading { pointer-events: none; opacity: .7; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* External link */
        .ext-link { color: var(--primary-dark); font-weight: 600; text-decoration: underline; }

        @media (max-width: 480px) {
            .step-card { padding: 1.75rem 1.25rem; }
            .step-question { font-size: 1.15rem; }
            .btn-row { flex-direction: column-reverse; }
            .btn-next, .btn-back { width: 100%; text-align: center; justify-content: center; }
            body { font-size: 16px; }
            .form-input, .form-textarea { font-size: 16px; }
        }
    </style>
</head>
<body>

<header class="intake-header">
    <div class="intake-header-left">{{ $booking->account->name }}</div>
    <div class="intake-header-right" id="step-indicator">Stap 1 van 3</div>
</header>

<div class="progress-wrap">
    <div class="progress-bar" id="progress-bar" style="width: 33%"></div>
</div>

<div class="steps-viewport">

    {{-- ─── STAP 1: Fotostrip Design ─── --}}
    <div class="intake-step active" id="step-1">
        <div class="step-card">
            <div class="step-number">Stap 1 van 3</div>
            <h2 class="step-question">Wil je een eigen ontwerp voor de fotostrip, of kies je uit een van onze templates?</h2>

            <form id="form-step-1" enctype="multipart/form-data">
                <label class="option-card" onclick="selectOption(1, 'self_input')">
                    <input type="radio" name="design_choice" value="self_input">
                    <div class="option-title">Ik lever zelf input aan</div>
                    <div class="option-desc">Stuur je uitnodiging, logo, huisstijl of kleurkeuze en wij ontwerpen jouw fotostrip.</div>
                </label>

                <label class="option-card" onclick="selectOption(1, 'template')">
                    <input type="radio" name="design_choice" value="template">
                    <div class="option-title">Ik gebruik een van jullie templates</div>
                    <div class="option-desc">Je krijgt toegang tot onze Photoshop en Canva templates om zelf een ontwerp te maken.</div>
                </label>

                <div class="sub-fields" id="upload-section">
                    <div class="form-label">Upload je bestanden (logo, uitnodiging, branding)</div>
                    <div class="upload-zone" id="upload-zone" onclick="document.getElementById('file-input').click()">
                        <div class="upload-zone-icon">📎</div>
                        <div class="upload-zone-text"><strong>Klik om bestanden te kiezen</strong><br>of sleep ze hierheen (max 5 bestanden, 20MB per stuk)</div>
                    </div>
                    <input type="file" id="file-input" name="branding_files[]" multiple accept="image/*,.pdf,.ai,.psd,.svg" style="display:none">
                    <div class="file-list" id="file-list"></div>
                </div>

                <div class="field-error" id="error-1"></div>
            </form>

            <div class="btn-row">
                <a href="{{ route('portal.show', $booking->public_token) }}" class="btn-back">Terug naar boeking</a>
                <button class="btn-next" onclick="nextStep(1)" id="btn-1" disabled>
                    Volgende <span class="spinner"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- ─── STAP 2: Bezorgtijd ─── --}}
    <div class="intake-step" id="step-2">
        <div class="step-card">
            <div class="step-number">Stap 2 van 3</div>
            <h2 class="step-question">Heb je een gewenste of specifieke bezorgtijd?</h2>

            <form id="form-step-2">
                <div class="form-group">
                    <label class="form-label" for="delivery_time">Gewenste bezorgtijd</label>
                    <input type="text" class="form-input" id="delivery_time" name="delivery_time_preference"
                           placeholder="bijv. minimaal 1 uur voor aanvang" oninput="enableBtn(2)">
                </div>
                <div class="form-group">
                    <label class="form-label" for="delivery_notes">Bijzonderheden voor bezorging</label>
                    <textarea class="form-textarea" id="delivery_notes" name="delivery_notes"
                              placeholder="bijv. toegangstijden, contactpersoon ter plaatse, speciale instructies..."
                              oninput="enableBtn(2)"></textarea>
                </div>

                <div class="field-error" id="error-2"></div>
            </form>

            <div class="btn-row">
                <button class="btn-back" onclick="prevStep(2)">Vorige</button>
                <button class="btn-next" onclick="nextStep(2)" id="btn-2">
                    Volgende <span class="spinner"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- ─── STAP 3: Ophalen ─── --}}
    <div class="intake-step" id="step-3">
        <div class="step-card">
            <div class="step-number">Stap 3 van 3</div>
            <h2 class="step-question">Wanneer kunnen we de photobooth ophalen na het event?</h2>

            <form id="form-step-3">
                <label class="option-card" onclick="selectOption(3, 'same_evening')">
                    <input type="radio" name="pickup_preference" value="same_evening">
                    <div class="option-title">Dezelfde avond voor 22:00</div>
                    <div class="option-desc">We halen de photobooth dezelfde avond nog op.</div>
                </label>

                <label class="option-card" onclick="selectOption(3, 'next_morning')">
                    <input type="radio" name="pickup_preference" value="next_morning">
                    <div class="option-title">De volgende ochtend (werkdagen)</div>
                    <div class="option-desc">We halen de photobooth de eerstvolgende werkdag op.</div>
                </label>

                <div class="sub-fields" id="pickup-details">
                    <div class="form-group">
                        <label class="form-label" for="pickup_time">Gewenst ophaaltijdstip</label>
                        <input type="time" class="form-input" id="pickup_time" name="pickup_time" value="09:00">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="pickup_contact">Contactpersoon ter plaatse</label>
                        <input type="text" class="form-input" id="pickup_contact" name="pickup_contact_person"
                               placeholder="Naam en telefoonnummer">
                    </div>
                </div>

                <div class="field-error" id="error-3"></div>
            </form>

            <div class="btn-row">
                <button class="btn-back" onclick="prevStep(3)">Vorige</button>
                <button class="btn-next" onclick="nextStep(3)" id="btn-3" disabled>
                    Verstuur <span class="spinner"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- ─── COMPLETION ─── --}}
    <div class="intake-step" id="step-done">
        <div class="step-card" style="text-align:center;">
            <div class="completion-icon">🎉</div>
            <h2 class="completion-title">Bedankt!</h2>
            <p class="completion-text">
                Je intake is compleet. We hebben alle informatie ontvangen en gaan ermee aan de slag.<br>
                Je kunt je boeking altijd bekijken via onderstaande link.
            </p>
            <a href="{{ route('portal.show', $booking->public_token) }}" class="btn-portal">
                Naar mijn boeking
            </a>
        </div>
    </div>

</div>

<script>
const TOKEN = '{{ $booking->public_token }}';
const CSRF  = document.querySelector('meta[name="csrf-token"]').content;
const TOTAL = 3;
let current = {{ max(1, ($booking->intake_current_step ?? 0) + 1) }};
let savedData = @json($booking->intake_data ?? []);
let selectedFiles = [];

// Init: restore saved state
document.addEventListener('DOMContentLoaded', () => {
    restoreSavedData();
    showStep(current, false);
});

function restoreSavedData() {
    for (let s = 1; s <= TOTAL; s++) {
        const key = 'step_' + s;
        if (!savedData[key]) continue;
        const d = savedData[key];
        const form = document.getElementById('form-step-' + s);
        if (!form) continue;

        if (s === 1 && d.design_choice) {
            const r = form.querySelector(`input[value="${d.design_choice}"]`);
            if (r) { r.checked = true; r.closest('.option-card').classList.add('selected'); }
            if (d.design_choice === 'self_input') document.getElementById('upload-section').classList.add('open');
            enableBtn(1);
        }
        if (s === 2) {
            if (d.delivery_time_preference) document.getElementById('delivery_time').value = d.delivery_time_preference;
            if (d.delivery_notes) document.getElementById('delivery_notes').value = d.delivery_notes;
            enableBtn(2);
        }
        if (s === 3 && d.pickup_preference) {
            const r = form.querySelector(`input[value="${d.pickup_preference}"]`);
            if (r) { r.checked = true; r.closest('.option-card').classList.add('selected'); }
            if (d.pickup_preference === 'next_morning') {
                document.getElementById('pickup-details').classList.add('open');
                if (d.pickup_time) document.getElementById('pickup_time').value = d.pickup_time;
                if (d.pickup_contact_person) document.getElementById('pickup_contact').value = d.pickup_contact_person;
            }
            enableBtn(3);
        }
    }
}

function showStep(step, animate = true) {
    document.querySelectorAll('.intake-step').forEach(el => {
        const id = parseInt(el.id.replace('step-', '')) || 99;
        if (id === step || el.id === 'step-done' && step > TOTAL) {
            el.classList.add('active');
            el.classList.remove('left');
        } else if (id < step) {
            el.classList.remove('active');
            el.classList.add('left');
        } else {
            el.classList.remove('active', 'left');
        }
    });

    const pct = step <= TOTAL ? (step / TOTAL * 100) : 100;
    document.getElementById('progress-bar').style.width = pct + '%';
    document.getElementById('step-indicator').textContent = step <= TOTAL ? `Stap ${step} van ${TOTAL}` : 'Klaar!';
    current = step;
}

function selectOption(step, value) {
    const form = document.getElementById('form-step-' + step);
    form.querySelectorAll('.option-card').forEach(c => c.classList.remove('selected'));
    const radio = form.querySelector(`input[value="${value}"]`);
    if (radio) {
        radio.checked = true;
        radio.closest('.option-card').classList.add('selected');
    }

    // Conditional sub-fields
    if (step === 1) {
        document.getElementById('upload-section').classList.toggle('open', value === 'self_input');
    }
    if (step === 3) {
        document.getElementById('pickup-details').classList.toggle('open', value === 'next_morning');
    }

    enableBtn(step);
}

function enableBtn(step) {
    const btn = document.getElementById('btn-' + step);
    if (!btn) return;

    if (step === 2) {
        btn.disabled = false; // text fields are always optional
        return;
    }

    const form = document.getElementById('form-step-' + step);
    const checked = form.querySelector('input[type="radio"]:checked');
    btn.disabled = !checked;
}

async function nextStep(step) {
    const btn = document.getElementById('btn-' + step);
    const errEl = document.getElementById('error-' + step);
    errEl.textContent = '';
    btn.classList.add('loading');

    const form = document.getElementById('form-step-' + step);
    const formData = new FormData(form);

    // For step 1, append selected files
    if (step === 1 && selectedFiles.length) {
        formData.delete('branding_files[]');
        selectedFiles.forEach(f => formData.append('branding_files[]', f));
    }

    try {
        const res = await fetch(`/boeking/${TOKEN}/intake/${step}`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: formData,
        });

        const data = await res.json();

        if (!res.ok) {
            if (data.errors) {
                const msgs = Object.values(data.errors).flat();
                errEl.textContent = msgs.join('. ');
            } else {
                errEl.textContent = data.message || 'Er ging iets mis.';
            }
            btn.classList.remove('loading');
            return;
        }

        btn.classList.remove('loading');

        if (data.completed) {
            showStep(TOTAL + 1);
        } else {
            showStep(data.next_step);
        }
    } catch (e) {
        errEl.textContent = 'Verbindingsfout. Probeer opnieuw.';
        btn.classList.remove('loading');
    }
}

function prevStep(step) {
    if (step > 1) showStep(step - 1);
}

// ─── File Upload ───
const uploadZone = document.getElementById('upload-zone');
const fileInput = document.getElementById('file-input');
const fileListEl = document.getElementById('file-list');

['dragenter', 'dragover'].forEach(e => {
    uploadZone.addEventListener(e, (ev) => { ev.preventDefault(); uploadZone.classList.add('dragover'); });
});
['dragleave', 'drop'].forEach(e => {
    uploadZone.addEventListener(e, (ev) => { ev.preventDefault(); uploadZone.classList.remove('dragover'); });
});
uploadZone.addEventListener('drop', (ev) => {
    const files = [...ev.dataTransfer.files];
    addFiles(files);
});
fileInput.addEventListener('change', () => {
    addFiles([...fileInput.files]);
    fileInput.value = '';
});

function addFiles(files) {
    files.forEach(f => {
        if (selectedFiles.length >= 5) return;
        if (f.size > 20 * 1024 * 1024) return;
        selectedFiles.push(f);
    });
    renderFileList();
    enableBtn(1);
}

function removeFile(idx) {
    selectedFiles.splice(idx, 1);
    renderFileList();
}

function renderFileList() {
    fileListEl.innerHTML = selectedFiles.map((f, i) => `
        <div class="file-item">
            <span>📄 ${f.name}</span>
            <span style="color:var(--muted);font-size:.75rem;">${(f.size / 1024 / 1024).toFixed(1)} MB</span>
            <button type="button" class="file-item-remove" onclick="removeFile(${i})">✕</button>
        </div>
    `).join('');
}
</script>
</body>
</html>
