@extends('layouts.app')
@section('title', 'Nieuwe lead')

@push('styles')
<style>
.pac-container { z-index: 9999; }
.assets-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: .75rem; margin-top: .5rem; }
.asset-card { border: 2px solid #e2e8f0; border-radius: .5rem; padding: .875rem; cursor: pointer; transition: border-color .15s, background .15s; position: relative; }
.asset-card:has(input[type=checkbox]:checked) { border-color: #2563eb; background: #eff6ff; }
.asset-card input[type=checkbox] { position: absolute; opacity: 0; }
.asset-card-name { font-weight: 600; font-size: .875rem; }
.asset-card-price { font-size: .8rem; color: #64748b; margin-top: .125rem; }
.asset-qty { display: none; margin-top: .5rem; }
.asset-card:has(input[type=checkbox]:checked) .asset-qty { display: block; }
</style>
@endpush

@section('content')
<div class="card" style="max-width:760px;">
    <div class="card-header">
        <span class="card-title">Nieuwe lead aanmaken</span>
        <a href="{{ route('leads.index') }}" class="btn btn-sm btn-secondary">Terug</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('leads.store') }}">
        @csrf

        {{-- ── Klantgegevens ── --}}
        <h3 style="font-size:.875rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.75rem;">Klantgegevens</h3>
        <div class="form-grid">
            <div class="form-group">
                <label>Volledige naam *</label>
                <input type="text" name="name" value="{{ old('name') }}" required>
            </div>
            <div class="form-group">
                <label>Status *</label>
                <select name="status_id" required>
                    <option value="">Kies status...</option>
                    @foreach($statussen as $status)
                        <option value="{{ $status->id }}" @selected(old('status_id') == $status->id)>{{ $status->display_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>E-mailadres</label>
                <input type="email" name="email" value="{{ old('email') }}">
            </div>
            <div class="form-group">
                <label>Telefoonnummer</label>
                <input type="tel" name="phone" value="{{ old('phone') }}">
            </div>
        </div>

        <hr style="margin:1.25rem 0;border:none;border-top:1px solid #e2e8f0;">

        {{-- ── Eventdetails ── --}}
        <h3 style="font-size:.875rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.75rem;">Eventdetails</h3>
        <div class="form-grid">
            <div class="form-group">
                <label>Eventdatum</label>
                <input type="date" name="event_date" value="{{ old('event_date') }}">
            </div>
            <div class="form-group">
                <label>Type event</label>
                <select name="event_type_id">
                    <option value="">Onbekend</option>
                    @foreach($typen as $type)
                        <option value="{{ $type->id }}" @selected(old('event_type_id') == $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Bron</label>
                <select name="source_id">
                    <option value="">Onbekend</option>
                    @foreach($bronnen as $bron)
                        <option value="{{ $bron->id }}" @selected(old('source_id') == $bron->id)>{{ $bron->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <hr style="margin:1.25rem 0;border:none;border-top:1px solid #e2e8f0;">

        {{-- ── Locatie ── --}}
        <h3 style="font-size:.875rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.75rem;">Locatie</h3>
        <div class="form-group">
            <label>Naam eventlocatie / zoeken</label>
            <input type="text" id="location-search" placeholder="Typ naam of adres..." autocomplete="off" style="background:#fffbeb;">
            <div style="font-size:.75rem;color:#64748b;margin-top:.25rem;">Selecteer uit de suggesties om adresgegevens automatisch in te vullen.</div>
        </div>
        <div class="form-grid form-grid-3">
            <div class="form-group" style="grid-column:span 3;">
                <label>Locatienaam</label>
                <input type="text" name="event_location" id="event_location" value="{{ old('event_location') }}" placeholder="Naam van de locatie">
            </div>
            <div class="form-group" style="grid-column:span 2;">
                <label>Adres (straat + huisnummer)</label>
                <input type="text" name="event_address" id="event_address" value="{{ old('event_address') }}">
            </div>
            <div class="form-group">
                <label>Postcode</label>
                <input type="text" name="event_postcode" id="event_postcode" value="{{ old('event_postcode') }}">
            </div>
            <div class="form-group" style="grid-column:span 3;">
                <label>Plaatsnaam</label>
                <input type="text" name="event_city" id="event_city" value="{{ old('event_city') }}">
            </div>
        </div>

        <hr style="margin:1.25rem 0;border:none;border-top:1px solid #e2e8f0;">

        {{-- ── Producten & prijsopbouw ── --}}
        <h3 style="font-size:.875rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.25rem;">Producten & prijsopbouw <span style="font-weight:400;font-style:italic;">(indicatief)</span></h3>
        <p style="font-size:.8rem;color:#64748b;margin-bottom:.75rem;">Selecteer producten en pas de prijs aan. Totaal wordt automatisch berekend.</p>

        @php
            $gegroepeerd = $assets->groupBy('category');
            $catLabels = ['photobooth'=>'Photobooth','background'=>'Achtergronden','prop_box'=>'Attributenkisten','extra'=>"Extra's"];
        @endphp

        @forelse($gegroepeerd as $cat => $items)
            <div style="margin-bottom:1.25rem;">
                <div style="font-size:.8rem;font-weight:600;color:#475569;margin-bottom:.5rem;">{{ $catLabels[$cat] ?? $cat }}</div>
                <div class="assets-grid">
                    @foreach($items as $asset)
                    @php
                        $oldSelected = old("assets.{$asset->id}.selected");
                        $oldQty      = old("assets.{$asset->id}.quantity", 1);
                        $oldPrice    = old("assets.{$asset->id}.price", $asset->price);
                    @endphp
                    <label class="asset-card" data-price="{{ $asset->price }}">
                        <input type="checkbox" name="assets[{{ $asset->id }}][selected]" value="1"
                            {{ $oldSelected ? 'checked' : '' }}
                            onchange="calcTotaal()">
                        <input type="hidden" name="assets[{{ $asset->id }}][asset_id]" value="{{ $asset->id }}">
                        <div class="asset-card-name">{{ $asset->name }}</div>
                        <div class="asset-card-price">€ {{ number_format($asset->price, 2, ',', '.') }}</div>
                        <div class="asset-qty">
                            <label style="font-size:.75rem;color:#64748b;">Prijs (€)</label>
                            <input type="number" name="assets[{{ $asset->id }}][price]"
                                value="{{ old("assets.{$asset->id}.price", $asset->price) }}"
                                min="0" step="0.01" style="width:90px;padding:.25rem .5rem;font-size:.875rem;"
                                oninput="calcTotaal()">
                            <label style="font-size:.75rem;color:#64748b;margin-top:.25rem;">Aantal</label>
                            <input type="number" name="assets[{{ $asset->id }}][quantity]"
                                value="{{ $oldQty }}"
                                min="1" style="width:70px;padding:.25rem .5rem;font-size:.875rem;"
                                oninput="calcTotaal()">
                        </div>
                    </label>
                    @endforeach
                </div>
            </div>
        @empty
            <p style="color:#64748b;font-size:.875rem;margin-bottom:1rem;">
                Nog geen producten. <a href="{{ route('assets.create') }}">Voeg een product toe →</a>
            </p>
        @endforelse

        <div class="form-group" style="max-width:300px;">
            <label>Totaalprijs (€) <span style="font-weight:400;color:#94a3b8;">— automatisch berekend</span></label>
            <input type="number" name="total_price" id="total_price" value="{{ old('total_price', 0) }}" step="0.01" min="0"
                style="font-size:1.25rem;font-weight:700;">
        </div>

        <hr style="margin:1.25rem 0;border:none;border-top:1px solid #e2e8f0;">

        <div class="form-group">
            <label>Notities</label>
            <textarea name="notes" rows="4" style="resize:vertical;">{{ old('notes') }}</textarea>
        </div>

        <div style="display:flex;gap:.75rem;">
            <button type="submit" class="btn btn-primary">Lead opslaan</button>
            <a href="{{ route('leads.index') }}" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
const totaalInput = document.getElementById('total_price');

function calcTotaal() {
    let totaal = 0;
    document.querySelectorAll('.asset-card').forEach(card => {
        const cb = card.querySelector('input[type=checkbox]');
        if (!cb?.checked) return;
        const prijsInput = card.querySelector('input[name$="[price]"]');
        const qtyInput   = card.querySelector('input[name$="[quantity]"]');
        const prijs = parseFloat(prijsInput?.value || 0);
        const qty   = parseInt(qtyInput?.value || 1);
        totaal += prijs * qty;
    });
    totaalInput.value = totaal.toFixed(2);
}

calcTotaal();

function initMaps() {
    const input = document.getElementById('location-search');
    const ac = new google.maps.places.Autocomplete(input, {
        types: ['establishment', 'geocode'],
        componentRestrictions: { country: 'nl' },
        fields: ['name', 'formatted_address', 'address_components'],
    });

    ac.addListener('place_changed', () => {
        const place = ac.getPlace();
        if (!place.address_components) return;

        document.getElementById('event_location').value = place.name || '';

        let straat = '', huisnummer = '', postcode = '', stad = '';
        for (const comp of place.address_components) {
            const types = comp.types;
            if (types.includes('route'))                        straat     = comp.long_name;
            if (types.includes('street_number'))                huisnummer = comp.long_name;
            if (types.includes('postal_code'))                  postcode   = comp.long_name;
            if (types.includes('locality') || types.includes('postal_town')) stad = comp.long_name;
        }

        document.getElementById('event_address').value  = [straat, huisnummer].filter(Boolean).join(' ');
        document.getElementById('event_postcode').value = postcode;
        document.getElementById('event_city').value     = stad;
    });
}
</script>
<script
    src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_key') }}&libraries=places&callback=initMaps"
    async defer>
</script>
@endpush
