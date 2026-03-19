@extends('layouts.app')
@section('title', 'Product bewerken')

@section('content')
<div class="card" style="max-width:560px;">
    <div class="card-header">
        <span class="card-title">{{ $asset->name }}</span>
        <a href="{{ route('assets.index') }}" class="btn btn-sm btn-secondary">Terug</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('assets.update', $asset) }}">
        @csrf @method('PUT')

        <div class="form-grid">
            <div class="form-group" style="grid-column:span 2;">
                <label>Naam *</label>
                <input type="text" name="name" value="{{ old('name', $asset->name) }}" required>
            </div>
            <div class="form-group">
                <label>Categorie *</label>
                <select name="category" required>
                    <option value="">Kies categorie...</option>
                    <option value="photobooth"  @selected(old('category', $asset->category)==='photobooth')>Photobooth</option>
                    <option value="background"  @selected(old('category', $asset->category)==='background')>Achtergrond</option>
                    <option value="prop_box"    @selected(old('category', $asset->category)==='prop_box')>Attributenkist</option>
                    <option value="extra"       @selected(old('category', $asset->category)==='extra')>Extra</option>
                </select>
            </div>
            <div class="form-group">
                <label>Prijs (€) *</label>
                <input type="number" name="price" value="{{ old('price', $asset->price) }}" step="0.01" min="0" required>
            </div>
            <div class="form-group">
                <label>Voorraad *</label>
                <input type="number" name="stock" value="{{ old('stock', $asset->stock) }}" min="0" required>
            </div>
            <div class="form-group" style="grid-column:span 2;">
                <label>Beschrijving</label>
                <textarea name="description" rows="3" style="resize:vertical;">{{ old('description', $asset->description) }}</textarea>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $asset->is_active) ? 'checked' : '' }}
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
