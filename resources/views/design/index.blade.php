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
    .dg-textarea:focus, .dg-file:focus { outline: none; border-color: #7c3aed; box-shadow: 0 0 0 2px rgba(124,58,237,.12); }
    .dg-file { width: 100%; padding: .55rem .7rem; border: 1px solid #e2e8f0; border-radius: .5rem; font-size: .875rem; background: #fff; box-sizing: border-box; }
    .dg-result { border: 1px solid #e2e8f0; border-radius: .75rem; overflow: hidden; background: #fff; }
    .dg-result-head { display: flex; justify-content: space-between; align-items: center; padding: .6rem .9rem; border-bottom: 1px solid #f1f5f9; font-size: .8rem; }
    .dg-badge { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; padding: .15rem .5rem; border-radius: 999px; background: #ede9fe; color: #6d28d9; }
    .dg-result-body { display: flex; justify-content: center; background: #f8fafc; }
    .dg-result-body img { display: block; max-height: 80vh; width: auto; max-width: 100%; }
    .dg-gear { display: inline-flex; align-items: center; justify-content: center; width: 1.6rem; height: 1.6rem; border-radius: .4rem; border: 1px solid #e2e8f0; background: #fff; cursor: pointer; font-size: .85rem; margin-left: .4rem; vertical-align: middle; }
    .dg-gear:hover { background: #f8fafc; }
    .dg-settings { display: none; margin: -.25rem 0 1.1rem; padding: .85rem .9rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: .5rem; }
    .dg-settings.open { display: block; }
    .dg-settings textarea { width: 100%; min-height: 160px; padding: .6rem .7rem; border: 1px solid #e2e8f0; border-radius: .45rem; font-size: .82rem; font-family: inherit; resize: vertical; box-sizing: border-box; }
    .dg-settings-actions { display: flex; justify-content: space-between; align-items: center; margin-top: .5rem; }
    .dg-settings-save { font-size: .78rem; font-weight: 700; color: #fff; background: #7c3aed; border: none; border-radius: .4rem; padding: .4rem .8rem; cursor: pointer; }
    .dg-settings-save:hover { background: #6d28d9; }
    .dg-settings-reset { font-size: .75rem; color: #64748b; cursor: pointer; text-decoration: underline; }
    .dg-settings-saved { font-size: .75rem; color: #16a34a; font-weight: 600; opacity: 0; transition: opacity .2s; }
    .dg-settings-saved.show { opacity: 1; }
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
            <label class="dg-label">
                {{ $promptLabel }}-prompt
                <span class="dg-gear" title="Prompt aanpassen" onclick="document.getElementById('dg-settings').classList.toggle('open')">⚙️</span>
            </label>
            <div id="dg-settings" class="dg-settings">
                <label class="dg-label" for="dg-prompt-template">Vaste prompt <span class="dg-hint">— wordt onthouden, {{ '{beschrijving}' }} wordt vervangen door je invoer hieronder</span></label>
                <textarea id="dg-prompt-template">{{ $promptTemplate }}</textarea>
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
            <label class="dg-label" for="references">Referenties <span class="dg-hint">— flyer / uitnodiging / huisstijl (meerdere mag, max 8 MB p/st)</span></label>
            <input id="references" name="references[]" type="file" class="dg-file" accept="image/*" multiple>
        </div>

        @error('input') <p style="color:#dc2626;font-size:.8rem;margin:0 0 .5rem;">{{ $message }}</p> @enderror
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
                <strong>Thema:</strong> {{ \Illuminate\Support\Str::limit($input, 220) }}
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
                    <a href="{{ $results['url'] }}" target="_blank" class="dg-result-body"><img src="{{ $results['url'] }}" alt="Gegenereerde achtergrond"></a>
                @else
                    <div style="padding:1rem;color:#b91c1c;font-size:.8rem;background:#fef2f2;">
                        <strong>Mislukt.</strong><br>{{ \Illuminate\Support\Str::limit($results['error'], 300) }}
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
function dgSubmitting(form) {
    const btn = document.getElementById('dg-submit');
    btn.disabled = true;
    btn.textContent = '⏳ Bezig met genereren…';
    btn.style.opacity = '.7';
}

function dgSavePrompt() {
    const prompt = document.getElementById('dg-prompt-template').value;
    fetch("{{ route('design.prompt.update') }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
        },
        body: JSON.stringify({ key: {{ Js::from($promptKey) }}, prompt: prompt }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            const el = document.getElementById('dg-settings-saved');
            el.classList.add('show');
            setTimeout(() => el.classList.remove('show'), 2000);
        }
    });
}
</script>
@endpush
@endsection
