@extends('layouts.app')

@section('title', $template->exists ? 'Template bewerken' : 'Template toevoegen')

@section('content')
<div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.5rem;">
    <a href="{{ route('strip-templates.index') }}" style="color:#64748b;text-decoration:none;font-size:.875rem;">← Strip templates</a>
    <span style="color:#cbd5e1;">/</span>
    <h2 style="margin:0;font-size:1.1rem;font-weight:700;color:#1e293b;">
        {{ $template->exists ? 'Bewerken: #'.$template->number : 'Template toevoegen' }}
    </h2>
</div>

<div class="card neu-card" style="max-width:620px;">
    <form method="POST"
          action="{{ $template->exists ? route('strip-templates.update', $template) : route('strip-templates.store') }}"
          enctype="multipart/form-data">
        @csrf
        @if($template->exists) @method('PUT') @endif

        {{-- Preview --}}
        <div class="form-group">
            <label class="form-label">Afbeelding {!! $template->exists ? '' : '<span style="color:#ef4444;">*</span>' !!}</label>
            <div style="display:flex;align-items:flex-start;gap:1rem;flex-wrap:wrap;">
                <div id="image-preview" style="width:160px;height:160px;border-radius:.5rem;background:#f1f5f9;display:flex;align-items:center;justify-content:center;overflow:hidden;border:2px solid #e2e8f0;flex-shrink:0;">
                    @if($template->image_path)
                        <img src="{{ $template->image_url }}" alt="" style="width:100%;height:100%;object-fit:cover;">
                    @else
                        <span style="font-size:2rem;color:#cbd5e1;">🖼</span>
                    @endif
                </div>
                <div style="flex:1;min-width:220px;">
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp" id="image-input"
                           style="font-size:.85rem;" onchange="previewImage(this)" {{ $template->exists ? '' : 'required' }}>
                    <div style="font-size:.72rem;color:#64748b;margin-top:.25rem;">JPG/PNG/WEBP, max 8 MB. Vierkant of strip-verhouding werkt het beste.</div>
                    @error('image') <div class="invalid-feedback" style="display:block;">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        <div class="form-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group">
                <label class="form-label">Nummer <span style="color:#ef4444;">*</span></label>
                <input type="number" name="number" min="1" max="9999"
                       value="{{ old('number', $template->number) }}"
                       class="form-control @error('number') is-invalid @enderror" required>
                <div style="font-size:.72rem;color:#64748b;margin-top:.25rem;">Uniek nummer dat de klant ziet op het portaal.</div>
                @error('number') <div class="invalid-feedback" style="display:block;">{{ $message }}</div> @enderror
            </div>
            <div class="form-group">
                <label class="form-label">Naam (optioneel)</label>
                <input type="text" name="name" maxlength="120"
                       value="{{ old('name', $template->name) }}"
                       class="form-control @error('name') is-invalid @enderror"
                       placeholder="bv. Gouden bruiloft elegant">
                @error('name') <div class="invalid-feedback" style="display:block;">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="form-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group">
                <label class="form-label">Thema <span style="color:#ef4444;">*</span></label>
                <select name="theme" class="form-control" required>
                    @foreach(\App\Models\StripTemplate::THEME_LABELS as $value => $label)
                        <option value="{{ $value }}" @selected(old('theme', $template->theme) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Formaat <span style="color:#ef4444;">*</span></label>
                <select name="format" class="form-control" required>
                    @foreach(\App\Models\StripTemplate::FORMAT_LABELS as $value => $label)
                        <option value="{{ $value }}" @selected(old('format', $template->format) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Sorteervolgorde</label>
            <input type="number" name="sort_order" min="0" max="9999"
                   value="{{ old('sort_order', $template->sort_order ?? 0) }}"
                   class="form-control" style="max-width:140px;">
            <div style="font-size:.72rem;color:#64748b;margin-top:.25rem;">Lager nummer = bovenaan in de lijst. Standaard 0.</div>
        </div>

        <div class="form-group" style="display:flex;align-items:center;gap:.75rem;">
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.9rem;font-weight:500;">
                <input type="checkbox" name="is_active" value="1"
                       {{ old('is_active', $template->exists ? $template->is_active : true) ? 'checked' : '' }}
                       style="width:1rem;height:1rem;accent-color:#7c3aed;">
                Actief (zichtbaar in klantgalerij)
            </label>
        </div>

        <div style="display:flex;gap:.75rem;margin-top:1.5rem;">
            <button type="submit" class="btn btn-primary">
                {{ $template->exists ? 'Opslaan' : 'Toevoegen' }}
            </button>
            <a href="{{ route('strip-templates.index') }}" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>

<script>
function previewImage(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('image-preview').innerHTML =
            '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover;">';
    };
    reader.readAsDataURL(input.files[0]);
}
</script>
@endsection
