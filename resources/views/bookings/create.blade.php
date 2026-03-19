@extends('layouts.app')
@section('title', 'Nieuwe boeking')

@push('styles')
<style>
.pac-container { z-index: 9999; }
.assets-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: .75rem; margin-top: .5rem; }
.asset-card { border: 2px solid #e2e8f0; border-radius: .5rem; padding: .875rem; cursor: pointer; transition: border-color .15s, background .15s; position: relative; }
.asset-card:has(input:checked) { border-color: #2563eb; background: #eff6ff; }
.asset-card input[type=checkbox] { position: absolute; opacity: 0; }
.asset-card-name { font-weight: 600; font-size: .875rem; }
.asset-card-price { font-size: .8rem; color: #64748b; margin-top: .125rem; }
.asset-qty { display: none; margin-top: .5rem; }
.asset-card:has(input:checked) .asset-qty { display: flex; }
</style>
@endpush

@section('content')
<div class="card" style="max-width:760px;">
    <div class="card-header">
        <span class="card-title">Nieuwe boeking</span>
        <a href="{{ route('bookings.index') }}" class="btn btn-sm btn-secondary">Terug</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('bookings.store') }}" id="booking-form">
        @csrf
        @if($lead)
            <input type="hidden" name="lead_id" value="{{ $lead->id }}">
            <div class="alert alert-success" style="margin-bottom:1rem;">
                Gekoppeld aan lead: <strong>{{ $lead->name }}</strong> ({{ $lead->lead_number }})
            </div>
        @endif

        {{-- ── Klantgegevens ── --}}
        <h3 style="font-size:.875rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.75rem;">Klantgegevens</h3>
        <div class="form-grid">
            <div class="form-group">
                <label>Naam klant *</label>
                <input type="text" name="customer_name" value="{{ old('customer_name', $lead?->name) }}" required>
            </div>
            <div class="form-group">
                <label>E-mailadres</label>
                <input type="email" name="customer_email" value="{{ old('customer_email', $lead?->email) }}">
            </div>
            <div class="form-group">
                <label>Telefoonnummer</label>
                <input type="tel" name="customer_phone" value="{{ old('customer_phone', $lead?->phone) }}">
            </div>
        </div>

        <div class="form-group" style="margin-top:.75rem;">
            <label style="display:block;margin-bottom:.75rem;font-weight:600;font-size:.875rem;">Type klant *</label>
            <div style="display:flex;gap:1.5rem;">
                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                    <input type="radio" name="customer_type" value="particulier" @checked(old('customer_type', 'particulier') === 'particulier') required>
                    <span>Particulier (privépersoon)</span>
                </label>
                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                    <input type="radio" name="customer_type" value="zakelijk" @checked(old('customer_type') === 'zakelijk') required>
                    <span>Zakelijk (bedrijf)</span>
                </label>
            </div>
        </div>

        <div class="form-group" id="company-name-group" style="margin-top:.75rem;{{ old('customer_type', 'particulier') === 'zakelijk' ? '' : 'display:none;' }}">
            <label>Bedrijfsnaam *</label>
            <input type="text" name="company_name" id="company_name"
                value="{{ old('company_name') }}"
                placeholder="Naam van het bedrijf">
        </div>

        <div class="form-group" id="payment-method-group" style="margin-top:.75rem;{{ old('customer_type', 'particulier') === 'zakelijk' ? 'display:none;' : '' }}">
            <label style="display:block;margin-bottom:.75rem;font-weight:600;font-size:.875rem;">Betaalmethode *</label>
            <div style="display:flex;gap:1.5rem;">
                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                    <input type="radio" name="payment_method" value="ideal" @checked(old('payment_method', 'ideal') === 'ideal')>
                    <span>iDEAL (online betalen)</span>
                </label>
                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                    <input type="radio" name="payment_method" value="bij_levering" @checked(old('payment_method') === 'bij_levering')>
                    <span>Bij levering</span>
                </label>
            </div>
        </div>

        <hr style="margin:1.25rem 0;border:none;border-top:1px solid #e2e8f0;">

        {{-- ── Type & evenement ── --}}
        <h3 style="font-size:.875rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.75rem;">Type & evenement</h3>
        <div class="form-grid">
            <div class="form-group">
                <label>Type boeking *</label>
                <select name="booking_type" id="booking_type" required onchange="toggleType()">
                    <option value="full_service" @selected(old('booking_type','full_service')==='full_service')>🚚 Full Service (bezorgen + installeren)</option>
                    <option value="to_go" @selected(old('booking_type')==='to_go')>🏠 To Go (ophalen en terugbrengen)</option>
                </select>
            </div>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label>Eventdatum *</label>
                <input type="date" name="event_date" value="{{ old('event_date', $lead?->event_date?->format('Y-m-d')) }}" required>
            </div>
            <div class="form-group">
                <label>Starttijd</label>
                <input type="time" name="event_start_time" value="{{ old('event_start_time') }}">
            </div>
            <div class="form-group">
                <label>Eindtijd</label>
                <input type="time" name="event_end_time" value="{{ old('event_end_time') }}">
            </div>
        </div>

        {{-- Multi-day booking checkbox --}}
        <div class="form-group" style="margin-top:.75rem;">
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                <input type="checkbox" id="is_multi_day" name="is_multi_day" value="1" @checked(old('is_multi_day')) onchange="toggleEndDate()">
                <span>Boeking duurt meerdere dagen</span>
            </label>
        </div>

        {{-- Multi-day end date (conditionally shown) --}}
        <div id="end_date_container" class="form-group" style="display:{{ old('is_multi_day') ? 'block' : 'none' }};margin-top:.75rem;">
            <label>Einddatum *</label>
            <input type="date" name="event_end_date" id="event_end_date" value="{{ old('event_end_date') }}">
        </div>

        {{-- Full Service datums --}}
        <div id="fs-fields">
            <div class="form-grid">
                <div class="form-group">
                    <label>Bezorgdatum & tijd</label>
                    <input type="datetime-local" name="delivery_at" value="{{ old('delivery_at') }}">
                </div>
                <div class="form-group">
                    <label>Ophaaldatum & tijd (door ons)</label>
                    <input type="datetime-local" name="pickup_at" value="{{ old('pickup_at') }}">
                </div>
            </div>
        </div>

        {{-- To Go datums --}}
        <div id="togo-fields" style="display:none;">
            <div class="form-grid">
                <div class="form-group">
                    <label>Ophalen bij ons (datum & tijd)</label>
                    <input type="datetime-local" name="customer_pickup_at" value="{{ old('customer_pickup_at') }}">
                </div>
                <div class="form-group">
                    <label>Terugbrengen (datum & tijd)</label>
                    <input type="datetime-local" name="customer_return_at" value="{{ old('customer_return_at') }}">
                </div>
            </div>
        </div>

        <hr style="margin:1.25rem 0;border:none;border-top:1px solid #e2e8f0;">

        {{-- ── Locatie (Google Maps autocomplete) ── --}}
        <h3 style="font-size:.875rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.75rem;">Locatie</h3>
        <div class="form-group">
            <label>Naam eventlocatie / zoeken</label>
            <input type="text" id="location-search" placeholder="Typ naam of adres..." autocomplete="off" style="background:#fffbeb;">
            <div style="font-size:.75rem;color:#64748b;margin-top:.25rem;">Selecteer uit de suggesties om adresgegevens automatisch in te vullen.</div>
        </div>
        <div class="form-grid form-grid-3">
            <div class="form-group" style="grid-column:span 3;">
                <label>Locatienaam</label>
                <input type="text" name="event_location" id="event_location" value="{{ old('event_location', $lead?->event_location) }}" placeholder="Naam van de locatie">
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

        {{-- Reiskosten indicator --}}
        <div id="reiskosten-info" style="display:none;padding:.75rem 1rem;background:#eff6ff;border:1px solid #bfdbfe;border-radius:.5rem;font-size:.875rem;color:#1e40af;margin-top:.25rem;">
            <span id="reiskosten-tekst"></span>
        </div>

        <div class="form-group">
            <label>Opmerkingen</label>
            <textarea name="event_notes" rows="3" style="resize:vertical;">{{ old('event_notes') }}</textarea>
        </div>

        <hr style="margin:1.25rem 0;border:none;border-top:1px solid #e2e8f0;">

        {{-- ── Assets / producten ── --}}
        <h3 style="font-size:.875rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.75rem;">Producten & extra's</h3>

        @php
            $gegroepeerd = $assets->groupBy('category');
            $catLabels = ['photobooth'=>'Photobooth','background'=>'Achtergronden','prop_box'=>'Attributenkisten','extra'=>'Extra\'s'];
        @endphp

        @forelse($gegroepeerd as $cat => $items)
            <div style="margin-bottom:1.25rem;">
                <div style="font-size:.8rem;font-weight:600;color:#475569;margin-bottom:.5rem;">{{ $catLabels[$cat] ?? $cat }}</div>
                <div class="assets-grid">
                    @foreach($items as $asset)
                    @php
                        $leadItem     = $leadAssets->get($asset->id) ?? null;
                        $defaultPrice = $leadItem ? $leadItem->pivot->price : $asset->price;
                    @endphp

                    @if($asset->category === 'photobooth')
                    {{-- Photobooth: unit-selector via hidden inputs (betrouwbaar voor form-submission) --}}
                    @php $selectedUnits = array_filter(array_map('intval', old("assets.{$asset->id}.units", []))); @endphp
                    <div class="asset-card {{ count($selectedUnits) > 0 ? 'asset-pb-selected' : '' }}"
                         data-price="{{ $defaultPrice }}"
                         data-photobooth="1"
                         data-asset-id="{{ $asset->id }}">
                        <input type="hidden" name="assets[{{ $asset->id }}][asset_id]" value="{{ $asset->id }}">
                        <div class="asset-card-name" style="margin-bottom:.5rem;">{{ $asset->name }}</div>
                        {{-- Unit knoppen (spans, geen checkboxes) --}}
                        <div style="display:flex;flex-wrap:wrap;gap:.3rem;margin-bottom:.5rem;">
                            @for($u = 1; $u <= $asset->stock; $u++)
                                @php $isSel = in_array($u, $selectedUnits); @endphp
                                <span data-unit="{{ $u }}"
                                      data-asset-id="{{ $asset->id }}"
                                      data-selected="{{ $isSel ? '1' : '0' }}"
                                      data-booked="0"
                                      onclick="toggleUnit(this)"
                                      style="display:inline-flex;align-items:center;padding:.2rem .55rem;border-radius:.375rem;font-size:.75rem;font-weight:700;cursor:pointer;user-select:none;border:2px solid {{ $isSel ? '#2563eb' : '#cbd5e1' }};background:{{ $isSel ? '#dbeafe' : '#f8fafc' }};color:{{ $isSel ? '#1e40af' : '#475569' }};">
                                    {{ $u }}
                                </span>
                            @endfor
                        </div>
                        {{-- Hidden inputs — dit wordt meegezonden met het formulier --}}
                        <div id="unit-inputs-{{ $asset->id }}">
                            @foreach($selectedUnits as $su)
                                <input type="hidden" name="assets[{{ $asset->id }}][units][]" value="{{ $su }}">
                            @endforeach
                        </div>
                        <div style="display:flex;align-items:center;gap:.4rem;margin-top:.25rem;">
                            <label style="font-size:.72rem;color:#64748b;">Prijs/unit (€)</label>
                            <input type="number" name="assets[{{ $asset->id }}][price]"
                                value="{{ old("assets.{$asset->id}.price", $defaultPrice) }}"
                                min="0" step="0.01" style="width:80px;padding:.2rem .4rem;font-size:.8rem;border:1px solid #d1d5db;border-radius:.25rem;"
                                oninput="calcTotaal()">
                        </div>
                    </div>
                    @else
                    {{-- Niet-photobooth: bestaand gedrag --}}
                    @php
                        $isSelected = old("assets.{$asset->id}.selected", $leadItem ? '1' : '');
                        $defaultQty = $leadItem ? $leadItem->pivot->quantity : 1;
                    @endphp
                    <label class="asset-card" data-price="{{ $defaultPrice }}">
                        <input type="checkbox" name="assets[{{ $asset->id }}][selected]" value="1"
                            {{ $isSelected ? 'checked' : '' }}
                            onchange="calcTotaal()">
                        <input type="hidden" name="assets[{{ $asset->id }}][asset_id]" value="{{ $asset->id }}">
                        <div class="asset-card-name">{{ $asset->name }}</div>
                        <div class="asset-qty" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;margin-top:.25rem;">
                            <div>
                                <label style="font-size:.75rem;color:#64748b;">Prijs (€)</label>
                                <input type="number" name="assets[{{ $asset->id }}][price]"
                                    value="{{ old("assets.{$asset->id}.price", $defaultPrice) }}"
                                    min="0" step="0.01" style="width:90px;padding:.25rem .5rem;font-size:.875rem;"
                                    oninput="calcTotaal()">
                            </div>
                            <div>
                                <label style="font-size:.75rem;color:#64748b;">Aantal</label>
                                <input type="number" name="assets[{{ $asset->id }}][quantity]"
                                    value="{{ old("assets.{$asset->id}.quantity", $defaultQty) }}"
                                    min="1" style="width:70px;padding:.25rem .5rem;font-size:.875rem;"
                                    oninput="calcTotaal()">
                            </div>
                        </div>
                    </label>
                    @endif
                    @endforeach
                </div>
            </div>
        @empty
            <p style="color:#64748b;font-size:.875rem;margin-bottom:1rem;">
                Nog geen producten aangemaakt. <a href="{{ route('assets.create') }}">Voeg een product toe →</a>
            </p>
        @endforelse

        <hr style="margin:1.25rem 0;border:none;border-top:1px solid #e2e8f0;">

        {{-- ── Prijzen ── --}}
        <h3 style="font-size:.875rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.75rem;">Prijs</h3>
        <div class="form-grid">
            <div class="form-group">
                <label>Totaalprijs (€) <span style="font-weight:400;color:#94a3b8;">— automatisch berekend</span></label>
                <input type="number" name="total_price" id="total_price" value="{{ old('total_price', 0) }}" step="0.01" min="0" required
                    readonly style="background:#f8fafc;cursor:default;color:#64748b;">
            </div>
            <div class="form-group">
                <label>Fotostrip status</label>
                <select name="strip_status">
                    <option value="waiting_input" @selected(old('strip_status','waiting_input')==='waiting_input')>⏳ Input aanleveren</option>
                    <option value="designing"     @selected(old('strip_status')==='designing')>🎨 Ontwerpen</option>
                    <option value="review"        @selected(old('strip_status')==='review')>👀 Wachten op goedkeuring</option>
                    <option value="accepted"      @selected(old('strip_status')==='accepted')>✅ Goedgekeurd</option>
                    <option value="ready"         @selected(old('strip_status')==='ready')>🎉 Ontwerp staat klaar</option>
                </select>
            </div>
        </div>

        <div style="display:flex;gap:.75rem;margin-top:.5rem;">
            <button type="submit" class="btn btn-primary">Boeking opslaan</button>
            <a href="{{ route('bookings.index') }}" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
// ── Unit toggle (span + hidden input aanpak) ──────────────────
function toggleUnit(span) {
    if (span.dataset.booked === '1') return; // geblokkeerd door andere boeking

    const unit      = span.dataset.unit;
    const assetId   = span.dataset.assetId;
    const container = document.getElementById('unit-inputs-' + assetId);
    const card      = span.closest('.asset-card');
    const existing  = container.querySelector(`input[value="${unit}"]`);

    if (existing) {
        // Deselecteer
        existing.remove();
        span.dataset.selected  = '0';
        span.style.borderColor = '#cbd5e1';
        span.style.background  = '#f8fafc';
        span.style.color       = '#475569';
        span.style.borderWidth = '2px';
    } else {
        // Selecteer: voeg hidden input toe
        const inp  = document.createElement('input');
        inp.type   = 'hidden';
        inp.name   = `assets[${assetId}][units][]`;
        inp.value  = unit;
        container.appendChild(inp);
        span.dataset.selected  = '1';
        span.style.borderColor = '#2563eb';
        span.style.background  = '#dbeafe';
        span.style.color       = '#1e40af';
        span.style.borderWidth = '2px';
    }

    // Card border updaten
    const anySelected = container.querySelectorAll('input[type=hidden]').length > 0;
    card.style.borderColor = anySelected ? '#2563eb' : '';
    card.style.background  = anySelected ? '#eff6ff' : '';

    calcTotaal();
}

// ── Unit beschikbaarheid ophalen bij datumwijziging ───────────
function fetchUnitAvailability() {
    const eventDate    = document.querySelector('[name="event_date"]')?.value;
    const eventEndDate = document.querySelector('[name="event_end_date"]')?.value || eventDate;
    if (!eventDate) return;

    fetch(`/bookings/unit-availability?event_date=${eventDate}&event_end_date=${eventEndDate}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        document.querySelectorAll('.asset-card[data-photobooth]').forEach(card => {
            const assetId  = card.dataset.assetId;
            const booked   = data[assetId] || [];
            const container = document.getElementById('unit-inputs-' + assetId);
            card.querySelectorAll('span[data-unit]').forEach(span => {
                const unit     = parseInt(span.dataset.unit);
                const isBooked = booked.includes(unit);
                if (isBooked) {
                    span.dataset.booked = '1';
                    span.style.borderColor = '#ef4444';
                    span.style.background  = '#fee2e2';
                    span.style.color       = '#991b1b';
                    span.style.cursor      = 'not-allowed';
                    span.title             = 'Al geboekt door een andere boeking';
                    // Verwijder eventuele hidden input voor deze unit
                    container?.querySelector(`input[value="${unit}"]`)?.remove();
                    span.dataset.selected = '0';
                } else {
                    span.dataset.booked = '0';
                    span.style.cursor   = 'pointer';
                    span.title          = '';
                    if (span.dataset.selected === '1') {
                        span.style.borderColor = '#2563eb';
                        span.style.background  = '#dbeafe';
                        span.style.color       = '#1e40af';
                    } else {
                        span.style.borderColor = '#cbd5e1';
                        span.style.background  = '#f8fafc';
                        span.style.color       = '#475569';
                    }
                }
            });
        });
        calcTotaal();
    })
    .catch(() => {});
}

document.querySelector('[name="event_date"]')?.addEventListener('change', fetchUnitAvailability);
document.querySelector('[name="event_end_date"]')?.addEventListener('change', fetchUnitAvailability);
document.getElementById('is_multi_day')?.addEventListener('change', function() {
    if (!this.checked) fetchUnitAvailability();
});

// ── Auto-prijsberekening ──────────────────────────────────────
const totaalInput = document.getElementById('total_price');

function calcTotaal() {
    let totaal = 0;
    document.querySelectorAll('.asset-card').forEach(card => {
        const prijsInput = card.querySelector('input[name$="[price]"]');
        const prijs = parseFloat(prijsInput?.value || 0);
        if (card.dataset.photobooth) {
            const assetId   = card.dataset.assetId;
            const container = document.getElementById('unit-inputs-' + assetId);
            const unitCount = container ? container.querySelectorAll('input[type=hidden]').length : 0;
            totaal += prijs * unitCount;
        } else {
            const checkbox = card.querySelector('input[type=checkbox]');
            if (!checkbox || !checkbox.checked) return;
            const qtyInput = card.querySelector('input[name$="[quantity]"]');
            const qty = parseInt(qtyInput?.value || 1);
            totaal += prijs * qty;
        }
    });
    totaalInput.value = totaal.toFixed(2);
}
calcTotaal();
// ─────────────────────────────────────────────────────────────

function toggleType() {
    const type = document.getElementById('booking_type').value;
    document.getElementById('fs-fields').style.display   = type === 'full_service' ? '' : 'none';
    document.getElementById('togo-fields').style.display = type === 'to_go'        ? '' : 'none';
}
toggleType();

document.querySelectorAll('input[name="customer_type"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        const isZakelijk = this.value === 'zakelijk';
        document.getElementById('payment-method-group').style.display = isZakelijk ? 'none' : '';
        document.getElementById('company-name-group').style.display   = isZakelijk ? '' : 'none';
        const companyInput = document.getElementById('company_name');
        if (companyInput) companyInput.required = isZakelijk;
    });
});

function toggleEndDate() {
    const checkbox = document.getElementById('is_multi_day');
    const container = document.getElementById('end_date_container');
    const input = document.getElementById('event_end_date');

    if (checkbox.checked) {
        container.style.display = 'block';
        input.required = true;
    } else {
        container.style.display = 'none';
        input.required = false;
        input.value = '';
    }
}

// Google Maps Places Autocomplete
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
            if (types.includes('route'))                        straat      = comp.long_name;
            if (types.includes('street_number'))                huisnummer  = comp.long_name;
            if (types.includes('postal_code'))                  postcode    = comp.long_name;
            if (types.includes('locality') || types.includes('postal_town')) stad = comp.long_name;
        }

        document.getElementById('event_address').value  = [straat, huisnummer].filter(Boolean).join(' ');
        document.getElementById('event_postcode').value = postcode;
        document.getElementById('event_city').value     = stad;

        // Reiskosten berekenen na adres selectie
        berekenReiskosten();
    });
}

// ── Reiskosten ────────────────────────────────────────────────
let reiskostenTimer = null;

function berekenReiskosten() {
    const adres   = document.getElementById('event_address').value.trim();
    const postcode = document.getElementById('event_postcode').value.trim();
    const stad    = document.getElementById('event_city').value.trim();

    const volledigAdres = [adres, postcode, stad].filter(Boolean).join(', ');
    if (!volledigAdres || volledigAdres.length < 5) return;

    clearTimeout(reiskostenTimer);
    reiskostenTimer = setTimeout(() => {
        fetch(`/reiskosten?destination=${encodeURIComponent(volledigAdres)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.error) return;

            // Toon info-blok
            const info   = document.getElementById('reiskosten-info');
            const tekst  = document.getElementById('reiskosten-tekst');
            const kmInfo = data.distance_km <= data.free_km
                ? `${data.distance_km} km — binnen gratis zone (${data.free_km} km)`
                : `${data.distance_km} km — ${data.distance_km - data.free_km} km × €${data.rate_per_km}`;
            tekst.textContent = `🚗 Reisafstand: ${kmInfo} = €${data.cost.toFixed(2)}`;
            info.style.display = 'block';

            // Zoek het "Reiskosten" asset-card en vul prijs in + selecteer het
            document.querySelectorAll('.asset-card').forEach(card => {
                const naam = card.querySelector('.asset-card-name')?.textContent?.trim().toLowerCase();
                if (naam === 'reiskosten') {
                    const cb    = card.querySelector('input[type=checkbox]');
                    const prijs = card.querySelector('input[name$="[price]"]');
                    if (prijs) prijs.value = data.cost.toFixed(2);
                    if (cb && !cb.checked) cb.checked = true;
                    calcTotaal();
                }
            });
        })
        .catch(() => {});
    }, 800);
}

// Trigger bij handmatig invullen adresvelden
['event_address','event_postcode','event_city'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('change', berekenReiskosten);
});
</script>
<script
    src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_key') }}&libraries=places&callback=initMaps"
    async defer>
</script>
@endpush
