<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $booking->booking_type === 'to_go' ? 'Ophaal- & retourgegevens' : 'Bezorg- & ophaalgegevens' }} — {{ $booking->account->name }}</title>
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
            text-decoration: none;
            display: inline-flex; align-items: center;
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
@php
$isToGo = $booking->booking_type === 'to_go';
$pageTitle = $isToGo ? 'Ophaal- & retourgegevens' : 'Bezorg- & ophaalgegevens';
$totalDisplaySteps = $isToGo ? 1 : 2;

// Voorgestelde ophaal- en retourmomenten voor To Go
$suggestedPickupDate  = null;
$suggestedPickupLabel = null;
$suggestedPickupTime  = '10:00';
$suggestedReturnDate  = null;
$suggestedReturnLabel = null;
$suggestedReturnTime  = '10:00';

if ($isToGo && $booking->event_date) {
    if ($booking->customer_pickup_at) {
        $pickupCarbonDate = $booking->customer_pickup_at;
        $returnCarbonDate = $booking->customer_return_at ?? $booking->customer_pickup_at->copy()->addDays(2);
        $suggestedPickupTime = $pickupCarbonDate->format('H:i');
        $suggestedReturnTime = $returnCarbonDate->format('H:i');
    } else {
        $eDate = \Carbon\Carbon::parse($booking->event_date);
        $dow   = $eDate->dayOfWeek;

        if (in_array($dow, [0, 5, 6])) {
            $daysBack         = ($dow - 5 + 7) % 7;
            $daysForward      = $dow === 0 ? 1 : (8 - $dow) % 7;
            $pickupCarbonDate = $eDate->copy()->subDays($daysBack);
            $returnCarbonDate = $eDate->copy()->addDays($daysForward);
        } else {
            $pickupCarbonDate = $eDate->copy()->subDays($dow - 1);
            $returnCarbonDate = $eDate->copy()->addDays(5 - $dow);
        }
    }

    $suggestedPickupDate  = $pickupCarbonDate->format('Y-m-d');
    $suggestedPickupLabel = $pickupCarbonDate->translatedFormat('l j F');
    $suggestedReturnDate  = $returnCarbonDate->format('Y-m-d');
    $suggestedReturnLabel = $returnCarbonDate->translatedFormat('l j F');
}
@endphp
<body>

<header class="intake-header">
    <div class="intake-header-left">{{ $pageTitle }}</div>
    <div class="intake-header-right" id="step-indicator">Stap 1 van {{ $totalDisplaySteps }}</div>
</header>

<div class="progress-wrap">
    <div class="progress-bar" id="progress-bar" style="width: {{ $isToGo ? 100 : 50 }}%"></div>
</div>

<div class="steps-viewport">

    @if(!$isToGo)
    {{-- ─── STAP 2 (display: 1) — BEZORGING — alleen Full Service ─── --}}
    <div class="intake-step active" id="step-2">
        <div class="step-card">
            <div class="step-number">Stap 1 van 2</div>
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
                <a href="{{ route('portal.show', $booking->public_token) }}" class="btn-back">Terug naar boeking</a>
                <button class="btn-next" onclick="nextStep(2)" id="btn-2">
                    Volgende <span class="spinner"></span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ─── STAP 3 — OPHALEN (Full Service) / OPHAAL & RETOUR (To Go) ─── --}}
    <div class="intake-step {{ $isToGo ? 'active' : '' }}" id="step-3">
        <div class="step-card">
            <div class="step-number">{{ $isToGo ? 'Eenmalige stap' : 'Stap 2 van 2' }}</div>

            @if($isToGo)
            <h2 class="step-question">Wanneer wil je de photobooth ophalen en terugbrengen?</h2>

            @php
            $timeSlots = [];
            for ($h = 8; $h <= 15; $h++) {
                $timeSlots[] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
                if ($h < 15) $timeSlots[] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':30';
            }
            $savedStep3 = $booking->intake_data['step_3'] ?? [];
            $savedPickupTime = $savedStep3['pickup_time'] ?? '10:00';
            $savedReturnTime = $savedStep3['return_time'] ?? '10:00';
            @endphp

            <form id="form-step-3" data-always-enabled="1">
                <input type="hidden" name="pickup_option" value="suggested">
                <input type="hidden" name="pickup_date" value="{{ $suggestedPickupDate }}">
                <input type="hidden" name="return_date" value="{{ $suggestedReturnDate }}">

                <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:1.1rem 1.25rem;margin-bottom:1.25rem;">
                    <p style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;margin:0 0 .75rem 0;">Vaste datums</p>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                        <div>
                            <p style="font-size:.75rem;color:#6b7280;margin:0 0 .15rem 0;">📦 Ophalen bij ons</p>
                            <p style="font-size:.95rem;font-weight:700;color:#111827;margin:0;">{{ $suggestedPickupLabel }}</p>
                        </div>
                        <div>
                            <p style="font-size:.75rem;color:#6b7280;margin:0 0 .15rem 0;">🔄 Terugbrengen</p>
                            <p style="font-size:.95rem;font-weight:700;color:#111827;margin:0;">{{ $suggestedReturnLabel }}</p>
                        </div>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.75rem;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Ophaaltijdstip</label>
                        <select class="form-input" name="pickup_time">
                            @foreach($timeSlots as $slot)
                            <option value="{{ $slot }}" @selected($slot === $savedPickupTime)>{{ $slot }}{{ $slot === '10:00' ? ' ⭐' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Retourtijdstip</label>
                        <select class="form-input" name="return_time">
                            @foreach($timeSlots as $slot)
                            <option value="{{ $slot }}" @selected($slot === $savedReturnTime)>{{ $slot }}{{ $slot === '10:00' ? ' ⭐' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <p style="font-size:.75rem;color:#9ca3af;margin:0 0 1rem 0;">⭐ = onze voorkeur</p>
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:.75rem 1rem;font-size:.82rem;color:#166534;">
                    📍 <strong>Ophalen & terugbrengen bij:</strong><br>
                    Ravenswade 132, 3439 LD Nieuwegein
                    &nbsp;·&nbsp;
                    <a href="https://maps.google.com/?q=Ravenswade+132+3439+LD+Nieuwegein" target="_blank" style="color:#166534;text-decoration:underline;">Bekijk op kaart →</a>
                </div>

                <div class="field-error" id="error-3"></div>
            </form>

            @else
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
                        <input type="time" class="form-input" id="pickup_time" name="pickup_time" value="09:00" step="900">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="pickup_contact">Contactpersoon ter plaatse</label>
                        <input type="text" class="form-input" id="pickup_contact" name="pickup_contact_person"
                               placeholder="Naam en telefoonnummer">
                    </div>
                </div>

                <div class="field-error" id="error-3"></div>
            </form>
            @endif

            <div class="btn-row">
                @if($isToGo)
                <a href="{{ route('portal.show', $booking->public_token) }}" class="btn-back">Terug naar boeking</a>
                @else
                <button class="btn-back" onclick="prevStep(3)">Vorige</button>
                @endif
                <button class="btn-next" onclick="nextStep(3)" id="btn-3" {{ $isToGo ? '' : 'disabled' }}>
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
                Je {{ $isToGo ? 'ophaal- & retourgegevens zijn' : 'bezorg- & ophaalgegevens zijn' }} compleet. We hebben alles ontvangen en gaan ermee aan de slag.<br>
                Je kunt je boeking altijd bekijken via onderstaande link.
            </p>
            <a href="{{ route('portal.show', $booking->public_token) }}" class="btn-portal">
                Naar mijn boeking
            </a>
        </div>
    </div>

</div>

<script>
const TOKEN   = '{{ $booking->public_token }}';
const CSRF    = document.querySelector('meta[name="csrf-token"]').content;
const IS_TOGO = {{ $isToGo ? 'true' : 'false' }};
const TOTAL_DISPLAY = {{ $totalDisplaySteps }};

// Interne stappen: 2 (bezorging) en 3 (ophalen/togo)
// Display stappen: 1 en 2 (FS) of alleen 1 (ToGo)
const internalToDisplay = s => IS_TOGO ? 1 : (s === 2 ? 1 : 2);

// Bepaal de start-stap op basis van wat al ingevuld is
let current = (function() {
    const cs = {{ (int) ($booking->intake_current_step ?? 0) }};
    if (IS_TOGO) return 3;            // ToGo heeft alleen stap 3
    if (cs >= 2) return 3;            // Stap 2 al gedaan → ga naar stap 3
    return 2;                          // Anders begin bij stap 2
})();

let savedData = @json($booking->intake_data ?? []);

document.addEventListener('DOMContentLoaded', () => {
    restoreSavedData();
    showStep(current, false);
});

function restoreSavedData() {
    if (savedData.step_2) {
        const d = savedData.step_2;
        if (d.delivery_time_preference) {
            const el = document.getElementById('delivery_time');
            if (el) el.value = d.delivery_time_preference;
        }
        if (d.delivery_notes) {
            const el = document.getElementById('delivery_notes');
            if (el) el.value = d.delivery_notes;
        }
        enableBtn(2);
    }
    if (savedData.step_3 && savedData.step_3.pickup_preference) {
        const form = document.getElementById('form-step-3');
        if (form) {
            const r = form.querySelector(`input[value="${savedData.step_3.pickup_preference}"]`);
            if (r) { r.checked = true; r.closest('.option-card').classList.add('selected'); }
            if (savedData.step_3.pickup_preference === 'next_morning') {
                document.getElementById('pickup-details')?.classList.add('open');
                if (savedData.step_3.pickup_time) document.getElementById('pickup_time').value = savedData.step_3.pickup_time;
                if (savedData.step_3.pickup_contact_person) document.getElementById('pickup_contact').value = savedData.step_3.pickup_contact_person;
            }
            enableBtn(3);
        }
    }
}

function showStep(step, animate = true) {
    document.querySelectorAll('.intake-step').forEach(el => {
        const idAttr = el.id.replace('step-', '');
        const isDone = el.id === 'step-done';
        const id = isDone ? 99 : parseInt(idAttr);
        if ((isDone && step > 3) || id === step) {
            el.classList.add('active');
            el.classList.remove('left');
        } else if (id < step) {
            el.classList.remove('active');
            el.classList.add('left');
        } else {
            el.classList.remove('active', 'left');
        }
    });

    const ds  = step > 3 ? TOTAL_DISPLAY : internalToDisplay(step);
    const pct = step > 3 ? 100 : (ds / TOTAL_DISPLAY * 100);
    document.getElementById('progress-bar').style.width = pct + '%';
    document.getElementById('step-indicator').textContent = step > 3 ? 'Klaar!' : `Stap ${ds} van ${TOTAL_DISPLAY}`;
    current = step;
    enableBtn(step);
}

function selectOption(step, value) {
    const form = document.getElementById('form-step-' + step);
    form.querySelectorAll('.option-card').forEach(c => c.classList.remove('selected'));
    const radio = form.querySelector(`input[value="${value}"]`);
    if (radio) {
        radio.checked = true;
        radio.closest('.option-card').classList.add('selected');
    }

    if (step === 3) {
        const pickupDetails = document.getElementById('pickup-details');
        if (pickupDetails) pickupDetails.classList.toggle('open', value === 'next_morning');
    }

    enableBtn(step);
}

function enableBtn(step) {
    const btn = document.getElementById('btn-' + step);
    if (!btn) return;

    // Stap 2 (bezorging): altijd inschakelen — alle velden optioneel
    if (step === 2) { btn.disabled = false; return; }

    const form = document.getElementById('form-step-' + step);
    if (form && form.dataset.alwaysEnabled === '1') { btn.disabled = false; return; }

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
            showStep(99);
        } else {
            showStep(data.next_step);
        }
    } catch (e) {
        errEl.textContent = 'Verbindingsfout. Probeer opnieuw.';
        btn.classList.remove('loading');
    }
}

function prevStep(step) {
    if (step === 3 && !IS_TOGO) showStep(2);
}
</script>
</body>
</html>
