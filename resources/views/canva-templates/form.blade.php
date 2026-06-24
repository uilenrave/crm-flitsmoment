@extends('layouts.app')

@section('title', $template->exists ? 'Canva-template bewerken' : 'Canva-template toevoegen')

@section('content')
<div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.5rem;">
    <a href="{{ route('canva-templates.index') }}" style="color:#64748b;text-decoration:none;font-size:.875rem;">← Canva templates</a>
    <span style="color:#cbd5e1;">/</span>
    <h2 style="margin:0;font-size:1.1rem;font-weight:700;color:#1e293b;">
        {{ $template->exists ? 'Bewerken: '.$template->title : 'Canva-template toevoegen' }}
    </h2>
</div>

<div class="card neu-card" style="max-width:620px;">
    <form method="POST"
          action="{{ $template->exists ? route('canva-templates.update', $template) : route('canva-templates.store') }}"
          enctype="multipart/form-data">
        @csrf
        @if($template->exists) @method('PUT') @endif

        {{-- Thumbnail --}}
        <div class="form-group">
            <label class="form-label">Thumbnail {!! $template->exists ? '' : '<span style="color:#ef4444;">*</span>' !!}</label>
            <div style="display:flex;align-items:flex-start;gap:1rem;flex-wrap:wrap;">
                <div id="image-preview" style="width:160px;height:160px;border-radius:.5rem;background:#f8fafc;display:flex;align-items:center;justify-content:center;overflow:hidden;border:2px solid #e2e8f0;flex-shrink:0;padding:.35rem;">
                    @if($template->image_path)
                        <img src="{{ $template->image_url }}" alt="" style="max-width:100%;max-height:100%;width:auto;height:auto;object-fit:contain;">
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

        <div class="form-group">
            <label class="form-label">Titel <span style="color:#ef4444;">*</span></label>
            <input type="text" name="title" maxlength="200" required
                   value="{{ old('title', $template->title) }}"
                   class="form-control @error('title') is-invalid @enderror"
                   placeholder="bv. Bruiloft elegant in goudtinten">
            @error('title') <div class="invalid-feedback" style="display:block;">{{ $message }}</div> @enderror
        </div>

        <div class="form-group">
            <label class="form-label">Canva URL <span style="color:#ef4444;">*</span></label>
            <input type="url" name="canva_url" maxlength="500" required
                   value="{{ old('canva_url', $template->canva_url) }}"
                   class="form-control @error('canva_url') is-invalid @enderror"
                   placeholder="https://www.canva.com/design/...">
            <div style="font-size:.72rem;color:#64748b;margin-top:.25rem;">De volledige Canva-link die de klant in een nieuw tabblad opent.</div>
            @error('canva_url') <div class="invalid-feedback" style="display:block;">{{ $message }}</div> @enderror
        </div>

        <div class="form-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group">
                <label class="form-label">Categorie <span style="color:#ef4444;">*</span></label>
                <select name="format" class="form-control" required>
                    @foreach(\App\Models\CanvaTemplate::FORMAT_LABELS as $value => $label)
                        <option value="{{ $value }}" @selected(old('format', $template->format) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Aantal foto's <span style="color:#ef4444;">*</span></label>
                <select name="photo_count" class="form-control" required>
                    @for($i = 1; $i <= 5; $i++)
                        <option value="{{ $i }}" @selected((int) old('photo_count', $template->photo_count ?? 1) === $i)>{{ $i }} {{ $i === 1 ? 'foto' : 'foto\'s' }}</option>
                    @endfor
                </select>
                <div style="font-size:.72rem;color:#64748b;margin-top:.25rem;">Hoeveel foto's passen in dit template.</div>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Sorteervolgorde</label>
            <input type="number" name="sort_order" min="0" max="9999"
                   value="{{ old('sort_order', $template->sort_order ?? 0) }}"
                   class="form-control" style="max-width:140px;">
            <div style="font-size:.72rem;color:#64748b;margin-top:.25rem;">Lager nummer = bovenaan.</div>
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
            <a href="{{ route('canva-templates.index') }}" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>

<script>
function previewImage(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('image-preview').innerHTML =
            '<img src="' + e.target.result + '" style="max-width:100%;max-height:100%;width:auto;height:auto;object-fit:contain;">';
    };
    reader.readAsDataURL(input.files[0]);
}
</script>
@endsection
