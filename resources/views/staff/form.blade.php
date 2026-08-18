@extends('layouts.app')

@section('title', $person->exists ? 'Medewerker bewerken' : 'Medewerker toevoegen')

@section('content')
<div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.5rem;">
    <a href="{{ route('staff.index') }}" style="color:#64748b;text-decoration:none;font-size:.875rem;">← Medewerkers</a>
    <span style="color:#cbd5e1;">/</span>
    <h2 style="margin:0;font-size:1.1rem;font-weight:700;color:#1e293b;">
        {{ $person->exists ? 'Bewerken: '.$person->name : 'Medewerker toevoegen' }}
    </h2>
</div>

<div class="card neu-card" style="max-width:560px;">
    <form method="POST" action="{{ $person->exists ? route('staff.update', $person) : route('staff.store') }}" enctype="multipart/form-data">
        @csrf
        @if($person->exists) @method('PUT') @endif

        {{-- Profielfoto --}}
        <div class="form-group">
            <label class="form-label">Profielfoto</label>
            <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                <div id="photo-preview" style="width:80px;height:80px;border-radius:50%;background:#f1f5f9;display:flex;align-items:center;justify-content:center;overflow:hidden;border:2px solid #e2e8f0;flex-shrink:0;">
                    @if($person->photo_path)
                        <img src="{{ $person->photo_url }}" alt="{{ $person->name }}" style="width:100%;height:100%;object-fit:cover;">
                    @else
                        <span style="font-size:1.5rem;color:#94a3b8;">👤</span>
                    @endif
                </div>
                <div style="flex:1;min-width:220px;">
                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" id="photo-input"
                           style="font-size:.85rem;" onchange="previewPhoto(this)">
                    <div style="font-size:.72rem;color:#64748b;margin-top:.25rem;">JPG/PNG/WEBP, max 5 MB. Wordt getoond aan klanten op het portaal.</div>
                    @if($person->photo_path)
                        <label style="display:inline-flex;align-items:center;gap:.4rem;margin-top:.5rem;font-size:.8rem;color:#dc2626;cursor:pointer;">
                            <input type="checkbox" name="remove_photo" value="1" style="accent-color:#dc2626;">
                            Foto verwijderen
                        </label>
                    @endif
                    @error('photo') <div class="invalid-feedback" style="display:block;">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Naam <span style="color:#ef4444;">*</span></label>
            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name', $person->name) }}" required autofocus>
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="form-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group">
                <label class="form-label">Telefoon</label>
                <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                       value="{{ old('phone', $person->phone) }}" placeholder="06-12345678">
                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="form-group">
                <label class="form-label">E-mail</label>
                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                       value="{{ old('email', $person->email) }}" placeholder="naam@example.com">
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Notities</label>
            <textarea name="notes" class="form-control" rows="3"
                      placeholder="Rijbewijs, beschikbaarheid, bijzonderheden...">{{ old('notes', $person->notes) }}</textarea>
        </div>

        <div class="form-group" style="display:flex;align-items:center;gap:.75rem;">
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.9rem;font-weight:500;">
                {{-- Verborgen 0-waarde zodat een uitgevinkte checkbox óók verstuurd wordt (anders blijft de medewerker altijd actief). --}}
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1"
                       {{ old('is_active', $person->exists ? $person->is_active : true) ? 'checked' : '' }}
                       style="width:1rem;height:1rem;accent-color:#7c3aed;">
                Actief (inplanbaar in boekingen)
            </label>
        </div>

        <div style="display:flex;gap:.75rem;margin-top:1.5rem;">
            <button type="submit" class="btn btn-primary">
                {{ $person->exists ? 'Opslaan' : 'Toevoegen' }}
            </button>
            <a href="{{ route('staff.index') }}" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>

<script>
function previewPhoto(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const reader = new FileReader();
    reader.onload = function(e) {
        const preview = document.getElementById('photo-preview');
        preview.innerHTML = '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover;">';
    };
    reader.readAsDataURL(file);
}
</script>
@endsection
