@extends('layouts.app')
@section('title', 'Nieuw product')

@section('content')
<div class="card" style="max-width:560px;">
    <div class="card-header">
        <span class="card-title">Nieuw product</span>
        <a href="{{ route('assets.index') }}" class="btn btn-sm btn-secondary">Terug</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('assets.store') }}">
        @csrf

        <div class="form-grid">
            <div class="form-group" style="grid-column:span 2;">
                <label>Naam *</label>
                <input type="text" name="name" value="{{ old('name') }}" required>
            </div>
            <div class="form-group">
                <label>Categorie *</label>
                <select name="category" required>
                    <option value="">Kies categorie...</option>
                    <option value="photobooth"  @selected(old('category')==='photobooth')>Photobooth</option>
                    <option value="background"  @selected(old('category')==='background')>Achtergrond</option>
                    <option value="prop_box"    @selected(old('category')==='prop_box')>Attributenkist</option>
                    <option value="extra"       @selected(old('category')==='extra')>Extra</option>
                </select>
            </div>
            <div class="form-group">
                <label>Prijs (€) *</label>
                <input type="number" name="price" value="{{ old('price', '0.00') }}" step="0.01" min="0" required>
            </div>
            <div class="form-group">
                <label>Voorraad *</label>
                <input type="number" name="stock" value="{{ old('stock', 1) }}" min="0" required>
                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;margin-top:.5rem;font-size:.8rem;color:#64748b;">
                    <input type="checkbox" name="ignore_stock" value="1" {{ old('ignore_stock') ? 'checked' : '' }}
                        style="width:14px;height:14px;">
                    Niet tonen in planning (bijv. reiskosten, wegwerpartikelen)
                </label>
            </div>
            <div class="form-group" style="grid-column:span 2;">
                <label>Beschrijving</label>
                <textarea name="description" rows="3" style="resize:vertical;">{{ old('description') }}</textarea>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}
                        style="width:16px;height:16px;">
                    Actief (zichtbaar bij boekingen)
                </label>
            </div>
        </div>

        <div style="display:flex;gap:.75rem;margin-top:.5rem;">
            <button type="submit" class="btn btn-primary">Opslaan</button>
            <a href="{{ route('assets.index') }}" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>
@endsection
