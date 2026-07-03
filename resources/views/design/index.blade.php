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
    .dg-textarea:focus, .dg-select:focus, .dg-file:focus { outline: none; border-color: #7c3aed; box-shadow: 0 0 0 2px rgba(124,58,237,.12); }
    .dg-select, .dg-file { width: 100%; padding: .55rem .7rem; border: 1px solid #e2e8f0; border-radius: .5rem; font-size: .875rem; background: #fff; box-sizing: border-box; }
    .dg-result { border: 1px solid #e2e8f0; border-radius: .75rem; overflow: hidden; background: #fff; }
    .dg-result-head { display: flex; justify-content: space-between; align-items: center; padding: .6rem .9rem; border-bottom: 1px solid #f1f5f9; font-size: .8rem; }
    .dg-badge { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; padding: .15rem .5rem; border-radius: 999px; }
    .dg-badge.gemini { background: #ede9fe; color: #6d28d9; }
    .dg-badge.openai { background: #dcfce7; color: #15803d; }
    .dg-result img { display: block; width: 100%; height: auto; background: #f8fafc; }
    .dg-adv { font-size: .78rem; color: #7c3aed; cursor: pointer; user-select: none; }
    .dg-adv-body { display: none; margin-top: .75rem; }
    .dg-adv-body.open { display: block; }
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem;">
    <h2 style="margin:0;font-size:1.25rem;font-weight:700;color:#1e293b;">✨ Ontwerp-generator</h2>
    <span style="font-size:.8rem;color:#64748b;">AI-fotostrip ontwerpen (Gemini / OpenAI)</span>
</div>

<div class="dg-grid">
    {{-- ── Formulier ── --}}
    <form method="POST" action="{{ route('design.generate') }}" enctype="multipart/form-data" class="card neu-card" style="padding:1.25rem;" onsubmit="dgSubmitting(this)">
        @csrf

        <div class="dg-field">
            <label class="dg-label" for="prompt">Ontwerp-opdracht <span class="dg-hint">— beschrijf de stijl, kleuren, sfeer, thema</span></label>
            <textarea id="prompt" name="prompt" class="dg-textarea" required placeholder="Bijv: Speelse fotostrip-achtergrond voor een 40e verjaardag, ballonnen en confetti in feestkleuren, plek bovenin voor het logo, ruimte voor 3 foto's onder elkaar.">{{ old('prompt', $prompt) }}</textarea>
        </div>

        <div class="dg-field">
            <label class="dg-label" for="references">Referenties <span class="dg-hint">— flyer / uitnodiging / huisstijl / logo (meerdere mag, max 2 MB p/st)</span></label>
            <input id="references" name="references[]" type="file" class="dg-file" accept="image/*" multiple>
        </div>

        <div class="dg-field">
            <label class="dg-label" for="provider">AI-provider</label>
            <select id="provider" name="provider" class="dg-select">
                <option value="gemini">Gemini (Nano Banana)</option>
                <option value="openai">OpenAI (gpt-image)</option>
                <option value="both">Beide (vergelijken)</option>
            </select>
        </div>

        <div class="dg-field" style="margin-bottom:.75rem;">
            <span class="dg-adv" onclick="document.getElementById('dg-adv').classList.toggle('open')">⚙️ Geavanceerd (mask)</span>
            <div id="dg-adv" class="dg-adv-body">
                <label class="dg-label" for="mask" style="margin-top:.5rem;">Mask (PNG) <span class="dg-hint">— transparant = bewerkbaar gebied. Alleen voor providers die masks ondersteunen.</span></label>
                <input id="mask" name="mask" type="file" class="dg-file" accept="image/png">
            </div>
        </div>

        @error('prompt') <p style="color:#dc2626;font-size:.8rem;margin:0 0 .5rem;">{{ $message }}</p> @enderror
        @error('references.*') <p style="color:#dc2626;font-size:.8rem;margin:0 0 .5rem;">{{ $message }}</p> @enderror

        <button type="submit" id="dg-submit" class="btn btn-primary" style="width:100%;justify-content:center;">✨ Genereer ontwerp</button>
        <p class="dg-hint" style="margin:.6rem 0 0;text-align:center;">Genereren kan 10–30 seconden duren.</p>
    </form>

    {{-- ── Resultaten ── --}}
    <div>
        @if($results === null)
            <div class="card neu-card" style="padding:2.5rem 1.5rem;text-align:center;color:#94a3b8;">
                <div style="font-size:2rem;margin-bottom:.5rem;">🖼️</div>
                <p style="font-weight:600;color:#64748b;margin-bottom:.25rem;">Nog geen ontwerp</p>
                <p style="font-size:.85rem;">Vul links een opdracht in en klik op “Genereer ontwerp”.</p>
            </div>
        @else
            @if($prompt)
            <div style="font-size:.8rem;color:#64748b;margin-bottom:.75rem;padding:.6rem .85rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:.5rem;">
                <strong>Opdracht:</strong> {{ \Illuminate\Support\Str::limit($prompt, 220) }}
            </div>
            @endif
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;">
                @foreach($results as $r)
                <div class="dg-result">
                    <div class="dg-result-head">
                        <span class="dg-badge {{ $r['provider'] }}">{{ $r['provider'] }}</span>
                        @if($r['ok'])
                            <a href="{{ $r['url'] }}" download style="font-size:.78rem;color:#7c3aed;font-weight:600;">⬇ Download ({{ $r['seconds'] }}s)</a>
                        @endif
                    </div>
                    @if($r['ok'])
                        <a href="{{ $r['url'] }}" target="_blank"><img src="{{ $r['url'] }}" alt="Gegenereerd ontwerp"></a>
                    @else
                        <div style="padding:1rem;color:#b91c1c;font-size:.8rem;background:#fef2f2;">
                            <strong>Mislukt.</strong><br>{{ \Illuminate\Support\Str::limit($r['error'], 300) }}
                        </div>
                    @endif
                </div>
                @endforeach
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
</script>
@endpush
@endsection
