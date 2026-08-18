@extends('layouts.app')
@section('title', 'Boeking bewerken')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
.pac-container { z-index: 9999; }
.assets-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: .75rem; margin-top: .5rem; }
.asset-card { border: 2px solid #e2e8f0; border-radius: .5rem; padding: .875rem; cursor: pointer; transition: border-color .15s, background .15s; position: relative; }
.asset-card:has(input:checked) { border-color: #2563eb; background: #eff6ff; }
.asset-card input[type=checkbox] { position: absolute; opacity: 0; }
.asset-card-name { font-weight: 600; font-size: .875rem; }
.asset-card-price { font-size: .8rem; color: #64748b; margin-top: .125rem; }
.asset-qty { display: none; margin-top: .5rem; }
.asset-card:has(input:checked) .asset-qty { display: flex; }
/* Photobooth unit-selector card */
.asset-pb-selected { border-color: #2563eb; background: #eff6ff; }
.flatpickr-input { background: #fff !important; }
</style>
@endpush

@section('content')
<div style="display:grid;grid-template-columns:minmax(0,760px) minmax(280px,320px);gap:1.25rem;align-items:start;" class="bk-edit-layout">
<div class="card" style="max-width:760px;">
    <div class="card-header">
        <span class="card-title">{{ $booking->booking_number }} bewerken</span>
        <a href="{{ route('bookings.show', $booking) }}" class="btn btn-sm btn-secondary">Terug</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('bookings.update', $booking) }}" id="booking-form" enctype="multipart/form-data">
        @csrf @method('PUT')

        {{-- ── Klantgegevens ── --}}
        <h3 style="font-size:.875rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.75rem;">Klantgegevens</h3>
        <div class="form-grid">
            <div class="form-group">
                <label>Naam klant *</label>
                <input type="text" name="customer_name" value="{{ old('customer_name', $booking->customer_name) }}" required>
            </div>
            <div class="form-group">
                <label>E-mailadres</label>
                <input type="email" name="customer_email" value="{{ old('customer_email', $booking->customer_email) }}">
            </div>
            <div class="form-group">
                <label>Telefoonnummer</label>
                <input type="tel" name="customer_phone" value="{{ old('customer_phone', $booking->customer_phone) }}">
            </div>
        </div>

        <div class="form-group" style="margin-top:.75rem;">
            <label style="display:block;margin-bottom:.75rem;font-weight:600;font-size:.875rem;">Type klant *</label>
            <div style="display:flex;gap:1.5rem;">
                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                    <input type="radio" name="customer_type" value="particulier" @checked(old('customer_type', $booking->customer_type) === 'particulier') required>
                    <span>Particulier (privépersoon)</span>
                </label>
                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                    <input type="radio" name="customer_type" value="zakelijk" @checked(old('customer_type', $booking->customer_type) === 'zakelijk') required>
                    <span>Zakelijk (bedrijf)</span>
                </label>
            </div>
        </div>

        @php $currentCustomerType = old('customer_type', $booking->customer_type); @endphp
        <div class="form-group" id="company-name-group" style="margin-top:.75rem;{{ $currentCustomerType === 'zakelijk' ? '' : 'display:none;' }}">
            <label>Bedrijfsnaam *</label>
            <input type="text" name="company_name" id="company_name"
                value="{{ old('company_name', $booking->company_name) }}"
                placeholder="Naam van het bedrijf"
                {{ $currentCustomerType === 'zakelijk' ? 'required' : '' }}>
        </div>

        <div class="form-group" id="payment-method-group" style="margin-top:.75rem;{{ $currentCustomerType === 'zakelijk' ? 'display:none;' : '' }}">
            <label style="display:block;margin-bottom:.75rem;font-weight:600;font-size:.875rem;">Betaalmethode *</label>
            <div style="display:flex;gap:1.5rem;">
                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                    <input type="radio" name="payment_method" value="ideal" @checked(old('payment_method', $booking->payment_method ?? 'ideal') === 'ideal')>
                    <span>iDEAL (online betalen)</span>
                </label>
                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                    <input type="radio" name="payment_method" value="bij_levering" @checked(old('payment_method', $booking->payment_method) === 'bij_levering')>
                    <span>Bij levering</span>
                </label>
            </div>
        </div>

        <div class="form-group" style="margin-top:.75rem;">
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-weight:500;">
                <input type="hidden" name="hide_prices" value="0">
                <input type="checkbox" name="hide_prices" value="1"
                    @checked(old('hide_prices', $booking->hide_prices))
                    style="width:1rem;height:1rem;cursor:pointer;">
                <span>🏪 Reseller / doorverhuur — verberg prijzen in het klantportaal</span>
            </label>
            <div style="font-size:.75rem;color:#94a3b8;margin-top:.3rem;padding-left:1.5rem;">
                De klant ziet het portaal zonder kosten, betalingsknop en factuur. Handig als een feestlocatie de boeking doorfactureert aan hun klant.
            </div>
        </div>

        <hr style="margin:1.25rem 0;border:none;border-top:1px solid #e2e8f0;">

        {{-- ── Type & evenement ── --}}
        <h3 style="font-size:.875rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.75rem;">Type & evenement</h3>
        <div class="form-grid">
            <div class="form-group">
                <label>Type boeking *</label>
                <select name="booking_type" id="booking_type" required onchange="toggleType()">
                    <option value="full_service" @selected(old('booking_type', $booking->booking_type)==='full_service')>🚚 Full Service (bezorgen + installeren)</option>
                    <option value="to_go" @selected(old('booking_type', $booking->booking_type)==='to_go')>🏠 To Go (ophalen en terugbrengen)</option>
                </select>
            </div>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label>Eventdatum *</label>
                <input type="text" name="event_date" class="fp-date" value="{{ old('event_date', $booking->event_date->format('Y-m-d')) }}" required autocomplete="off">
            </div>
            <div class="form-group">
                <label>Starttijd</label>
                <input type="text" name="event_start_time" class="fp-time" value="{{ old('event_start_time', $booking->event_start_time) }}" autocomplete="off" placeholder="--:--">
            </div>
            <div class="form-group">
                <label>Eindtijd</label>
                <input type="text" name="event_end_time" class="fp-time" value="{{ old('event_end_time', $booking->event_end_time) }}" autocomplete="off" placeholder="--:--">
            </div>
        </div>

        {{-- Multi-day booking checkbox --}}
        <div class="form-group" style="margin-top:.75rem;">
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                <input type="checkbox" id="is_multi_day" name="is_multi_day" value="1" @checked(old('is_multi_day', $booking->is_multi_day)) onchange="toggleEndDate()">
                <span>Boeking duurt meerdere dagen</span>
            </label>
        </div>

        {{-- Multi-day end date (conditionally shown) --}}
        <div id="end_date_container" class="form-group" style="display:{{ old('is_multi_day', $booking->is_multi_day) ? 'block' : 'none' }};margin-top:.75rem;">
            <label>Einddatum *</label>
            <input type="text" name="event_end_date" id="event_end_date" class="fp-date" value="{{ old('event_end_date', $booking->event_end_date?->format('Y-m-d')) }}" autocomplete="off">
        </div>

        {{-- Full Service datums --}}
        <div id="fs-fields">
            <div class="form-grid">
                <div class="form-group">
                    <label>Bezorgdatum & tijd</label>
                    <input type="text" name="delivery_at" class="fp-datetime" autocomplete="off"
                        value="{{ old('delivery_at', $booking->delivery_at?->format('Y-m-d H:i')) }}">
                </div>
                <div class="form-group">
                    <label>Ophaaldatum & tijd (door ons)</label>
                    <input type="text" name="pickup_at" class="fp-datetime" autocomplete="off"
                        value="{{ old('pickup_at', $booking->pickup_at?->format('Y-m-d H:i')) }}">
                </div>
            </div>
        </div>

        {{-- To Go datums --}}
        <div id="togo-fields" style="display:none;">
            <div class="form-grid">
                <div class="form-group">
                    <label>Ophalen bij ons (datum & tijd)</label>
                    <input type="text" name="customer_pickup_at" class="fp-datetime" autocomplete="off"
                        value="{{ old('customer_pickup_at', $booking->customer_pickup_at?->format('Y-m-d H:i')) }}">
                </div>
                <div class="form-group">
                    <label>Terugbrengen (datum & tijd)</label>
                    <input type="text" name="customer_return_at" class="fp-datetime" autocomplete="off"
                        value="{{ old('customer_return_at', $booking->customer_return_at?->format('Y-m-d H:i')) }}">
                </div>
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
                <input type="text" name="event_location" id="event_location" value="{{ old('event_location', $booking->event_location) }}" placeholder="Naam van de locatie">
            </div>
            <div class="form-group" style="grid-column:span 2;">
                <label>Adres (straat + huisnummer)</label>
                <input type="text" name="event_address" id="event_address" value="{{ old('event_address', $booking->event_address) }}">
            </div>
            <div class="form-group">
                <label>Postcode</label>
                <input type="text" name="event_postcode" id="event_postcode" value="{{ old('event_postcode', $booking->event_postcode) }}">
            </div>
            <div class="form-group" style="grid-column:span 3;">
                <label>Plaatsnaam</label>
                <input type="text" name="event_city" id="event_city" value="{{ old('event_city', $booking->event_city) }}">
            </div>
        </div>

        {{-- Reiskosten indicator --}}
        <div id="reiskosten-info" style="display:none;padding:.75rem 1rem;background:#eff6ff;border:1px solid #bfdbfe;border-radius:.5rem;font-size:.875rem;color:#1e40af;margin-top:.25rem;">
            <span id="reiskosten-tekst"></span>
        </div>

        <div class="form-group">
            <label>Opmerkingen</label>
            <textarea name="event_notes" rows="3" style="resize:vertical;">{{ old('event_notes', $booking->event_notes) }}</textarea>
        </div>

        {{-- ── Leverinstructies (alleen voor admin, zichtbaar voor personeel) ── --}}
        <div class="form-group" style="background:#fffbeb;border:1px solid #fcd34d;border-radius:.75rem;padding:1rem 1.1rem;">
            <label style="display:flex;align-items:center;gap:.4rem;font-weight:600;color:#92400e;">
                🚚 Leverinstructies voor personeel
            </label>
            <div style="font-size:.78rem;color:#92400e;margin-bottom:.6rem;">
                Alleen zichtbaar voor jou en het ingeplande personeel — niet voor de klant.
            </div>
            <textarea name="delivery_instructions" rows="4" style="resize:vertical;width:100%;background:#fff;"
                      placeholder="Bijv. via achteringang, lift met code 1234, parkeren in vak 7…">{{ old('delivery_instructions', $booking->delivery_instructions) }}</textarea>
            @error('delivery_instructions') <div class="error-msg">{{ $message }}</div> @enderror

            {{-- Bestaande afbeeldingen --}}
            @if(!empty($booking->delivery_instructions_images))
                <div style="margin-top:.75rem;">
                    <div style="font-size:.72rem;font-weight:600;color:#92400e;margin-bottom:.4rem;">Bestaande afbeeldingen</div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:.5rem;">
                        @foreach($booking->delivery_instructions_images as $img)
                            <div style="position:relative;border:1px solid #fcd34d;border-radius:.5rem;overflow:hidden;background:#fff;">
                                <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($img) }}" target="_blank">
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($img) }}"
                                         style="width:100%;height:90px;object-fit:cover;display:block;">
                                </a>
                                <button type="button" data-img-path="{{ $img }}" class="delete-delivery-img"
                                        style="position:absolute;top:4px;right:4px;background:rgba(220,38,38,.92);color:#fff;border:none;border-radius:50%;width:24px;height:24px;cursor:pointer;font-size:.85rem;line-height:1;">×</button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Nieuwe upload --}}
            <div style="margin-top:.75rem;">
                <label style="font-size:.72rem;font-weight:600;color:#92400e;display:block;margin-bottom:.3rem;">Foto's toevoegen (optioneel)</label>
                <input type="file" name="delivery_instructions_files[]" accept="image/jpeg,image/png,image/webp" multiple
                       style="font-size:.85rem;">
                <div style="font-size:.7rem;color:#92400e;margin-top:.25rem;">JPG/PNG/WEBP, max 8 MB per bestand. Meerdere foto's kunnen worden geselecteerd.</div>
                @error('delivery_instructions_files.*') <div class="error-msg">{{ $message }}</div> @enderror
            </div>
        </div>

        <hr style="margin:1.25rem 0;border:none;border-top:1px solid #e2e8f0;">

        {{-- ── Team / medewerkers ── --}}
        <h3 style="font-size:.875rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.75rem;">Team</h3>

        @if($staffMembers->isEmpty())
        <p style="font-size:.875rem;color:#94a3b8;margin-bottom:1rem;">
            Nog geen medewerkers aangemaakt.
            <a href="{{ route('staff.create') }}" style="color:#7c3aed;">Toevoegen →</a>
        </p>
        @else
        <div class="form-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
            <div class="form-group" style="margin-bottom:0;">
                <label id="delivery-staff-label">
                    {{ old('booking_type', $booking->booking_type) === 'to_go' ? 'Afgever (To Go)' : 'Bezorger' }}
                </label>
                <select name="delivery_staff_id" class="form-control">
                    <option value="">— Niemand —</option>
                    @foreach($staffMembers as $person)
                    <option value="{{ $person->id }}"
                        {{ old('delivery_staff_id', $booking->delivery_staff_id) == $person->id ? 'selected' : '' }}>
                        {{ $person->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" id="pickup-staff-group" style="margin-bottom:0;">
                <label id="pickup-staff-label">{{ old('booking_type', $booking->booking_type) === 'to_go' ? 'Ophaler (To Go)' : 'Ophaler' }}</label>
                <select name="pickup_staff_id" class="form-control">
                    <option value="">— Niemand —</option>
                    @foreach($staffMembers as $person)
                    <option value="{{ $person->id }}"
                        {{ old('pickup_staff_id', $booking->pickup_staff_id) == $person->id ? 'selected' : '' }}>
                        {{ $person->name }}
                    </option>
                    @endforeach
                </select>
            </div>
        </div>
        @endif

        <hr style="margin:1.25rem 0;border:none;border-top:1px solid #e2e8f0;">

        {{-- ── Assets / producten ── --}}
        <h3 style="font-size:.875rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.75rem;">Producten & extra's</h3>

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
                        if ($asset->category === 'photobooth') {
                            // Unit-gebaseerd
                            $assetItems      = $itemsByAsset->get($asset->id, collect());
                            $selectedUnits   = old("assets.{$asset->id}.units",
                                                   $assetItems->pluck('unit_number')->filter()->unique()->values()->toArray());
                            // Fallback voor legacy boekingen (unit_number=null, qty>0): auto-assign 1..qty
                            if (empty($selectedUnits) && $assetItems->isNotEmpty()) {
                                $legacyQty     = (int) $assetItems->sum('quantity');
                                $selectedUnits = $legacyQty > 0 ? range(1, min($legacyQty, $asset->stock)) : [];
                            }
                            $bookedByOthers  = $bookedUnitsByAsset[$asset->id] ?? [];
                            $priceValue      = old("assets.{$asset->id}.price",
                                                   $assetItems->first()?->price_snapshot ?? $asset->price);
                            $hasAnyUnit      = count($selectedUnits) > 0;
                        } else {
                            $item       = $selectedItems->get($asset->id);
                            $isSelected = $item !== null;
                            $qty        = $item?->quantity ?? 1;
                        }
                    @endphp

                    @if($asset->category === 'photobooth')
                    {{-- Photobooth: unit-selector --}}
                    <div class="asset-card {{ $hasAnyUnit ? 'asset-pb-selected' : '' }}"
                         data-price="{{ $asset->price }}"
                         data-photobooth="1"
                         data-asset-id="{{ $asset->id }}">
                        <input type="hidden" name="assets[{{ $asset->id }}][asset_id]" value="{{ $asset->id }}">
                        <div class="asset-card-name" style="margin-bottom:.5rem;">{{ $asset->name }}</div>

                        {{-- Unit knoppen (spans + hidden inputs) --}}
                        <div style="display:flex;flex-wrap:wrap;gap:.3rem;margin-bottom:.5rem;">
                            @for($u = 1; $u <= $asset->stock; $u++)
                                @php
                                    $isSel        = in_array($u, (array) $selectedUnits);
                                    $isOtherBooked = in_array($u, $bookedByOthers);
                                @endphp
                                <span data-unit="{{ $u }}"
                                      data-asset-id="{{ $asset->id }}"
                                      data-selected="{{ $isSel ? '1' : '0' }}"
                                      data-booked="{{ $isOtherBooked ? '1' : '0' }}"
                                      data-warning="0"
                                      data-warning-msg=""
                                      onclick="toggleUnit(this)"
                                      title="{{ $isOtherBooked ? 'Al geboekt door een andere boeking' : 'Unit '.$u }}"
                                      style="display:inline-flex;align-items:center;padding:.2rem .55rem;border-radius:.375rem;font-size:.75rem;font-weight:700;cursor:{{ $isOtherBooked ? 'not-allowed' : 'pointer' }};user-select:none;border:2px solid {{ $isSel ? '#2563eb' : ($isOtherBooked ? '#ef4444' : '#cbd5e1') }};background:{{ $isSel ? '#dbeafe' : ($isOtherBooked ? '#fee2e2' : '#f8fafc') }};color:{{ $isSel ? '#1e40af' : ($isOtherBooked ? '#991b1b' : '#475569') }};">
                                    {{ $isOtherBooked ? '🔒 ' : '' }}{{ $u }}
                                </span>
                            @endfor
                        </div>
                        {{-- Hidden inputs voor geselecteerde units --}}
                        <div id="unit-inputs-{{ $asset->id }}">
                            @foreach((array) $selectedUnits as $su)
                                @if($su)
                                <input type="hidden" name="assets[{{ $asset->id }}][units][]" value="{{ $su }}">
                                @endif
                            @endforeach
                        </div>

                        {{-- Waarschuwingsbanner (verschijnt als een unit met korte tussentijd geselecteerd is) --}}
                        <div id="unit-warning-{{ $asset->id }}" style="display:none;background:#fef3c7;border:1px solid #fcd34d;border-radius:.4rem;padding:.4rem .65rem;font-size:.77rem;color:#92400e;margin-top:.4rem;line-height:1.5;"></div>

                        {{-- Prijs per unit --}}
                        <div style="display:flex;align-items:center;gap:.4rem;margin-top:.25rem;">
                            <label style="font-size:.72rem;color:#64748b;">Prijs/unit (€)</label>
                            <input type="number" name="assets[{{ $asset->id }}][price]"
                                value="{{ $priceValue }}"
                                min="0" step="0.01" style="width:80px;padding:.2rem .4rem;font-size:.8rem;border:1px solid #d1d5db;border-radius:.25rem;"
                                oninput="calcTotaal()">
                        </div>
                    </div>
                    @else
                    {{-- Niet-photobooth: bestaand gedrag --}}
                    <label class="asset-card" data-price="{{ $asset->price }}">
                        <input type="checkbox" name="assets[{{ $asset->id }}][selected]" value="1"
                            {{ old("assets.{$asset->id}.selected", $isSelected ? '1' : '') ? 'checked' : '' }}
                            onchange="calcTotaal()">
                        <input type="hidden" name="assets[{{ $asset->id }}][asset_id]" value="{{ $asset->id }}">
                        <div class="asset-card-name">{{ $asset->name }}</div>
                        <div class="asset-qty" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;margin-top:.25rem;">
                            <div>
                                <label style="font-size:.75rem;color:#64748b;">Prijs (€)</label>
                                <input type="number" name="assets[{{ $asset->id }}][price]"
                                    value="{{ old("assets.{$asset->id}.price", $item?->price_snapshot ?? $asset->price) }}"
                                    min="0" step="0.01" style="width:90px;padding:.25rem .5rem;font-size:.875rem;"
                                    oninput="calcTotaal()">
                            </div>
                            <div>
                                <label style="font-size:.75rem;color:#64748b;">Aantal</label>
                                <input type="number" name="assets[{{ $asset->id }}][quantity]"
                                    value="{{ old("assets.{$asset->id}.quantity", $qty) }}"
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

        {{-- ── Fotostrip ontwerp ── --}}
        <h3 style="font-size:.875rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.75rem;">Fotostrip ontwerp</h3>
        <div style="font-size:.8rem;color:#64748b;margin-bottom:.75rem;">Upload het ontwerp → de klant ziet het automatisch in het portaal en ontvangt een mail. De strip-status wordt automatisch op "Ter beoordeling" gezet.</div>

        @if($booking->strip_design_url)
        <div style="margin-bottom:.75rem;padding:.75rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:.5rem;display:flex;align-items:center;gap:.75rem;">
            @php
                $isImage = preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', parse_url($booking->strip_design_url, PHP_URL_PATH));
            @endphp
            @if($isImage)
                <img src="{{ $booking->strip_design_url }}" alt="Huidig ontwerp" style="max-height:80px;max-width:120px;object-fit:contain;border-radius:.25rem;border:1px solid #e2e8f0;">
            @else
                <div style="font-size:2rem;">📄</div>
            @endif
            <div>
                <div style="font-size:.8rem;font-weight:600;">Huidig ontwerp</div>
                <a href="{{ $booking->strip_design_url }}" target="_blank" style="font-size:.75rem;color:#2563eb;">Bekijken →</a>
            </div>
        </div>
        @endif

        <div class="form-grid">
            <div class="form-group" style="grid-column:span 2;">
                <label>Nieuw ontwerp uploaden <span style="font-weight:400;color:#64748b;">(jpg, png, gif, webp, pdf — max 2 MB)</span></label>
                <input type="file" name="strip_design_file" accept="image/*,.pdf" style="padding:.375rem;"
                       onchange="(function(inp){var w=document.getElementById('strip-size-warn');if(!w)return;if(inp.files[0]&&inp.files[0].size>2*1024*1024){w.innerHTML='⚠️ Dit bestand is '+(inp.files[0].size/1048576).toFixed(1)+' MB — maximaal 2 MB.<br><span style=\'font-weight:400;color:#64748b;\'>Exporteer de strip wat kleiner en probeer opnieuw.</span>';w.style.display='block';inp.value='';}else{w.style.display='none';}})(this)">
                <div id="strip-size-warn" style="display:none;margin-top:.4rem;font-size:.8rem;color:#dc2626;font-weight:600;"></div>
                <label style="display:flex;align-items:center;gap:.5rem;margin-top:.5rem;font-size:.85rem;color:#374151;cursor:pointer;">
                    <input type="checkbox" name="mockup" value="1" checked>
                    <span><strong>In mockup plaatsen</strong></span>
                </label>
            </div>
        </div>

        @if($booking->strip_notes)
        <div style="margin-bottom:.75rem;padding:.75rem;background:#fffbeb;border:1px solid #fde68a;border-radius:.5rem;">
            <div style="font-size:.75rem;font-weight:600;color:#92400e;margin-bottom:.25rem;">💬 Feedback van klant:</div>
            <div style="font-size:.875rem;white-space:pre-line;">{{ $booking->strip_notes }}</div>
        </div>
        @endif

        <hr style="margin:1.25rem 0;border:none;border-top:1px solid #e2e8f0;">

        {{-- ── Fotostrip status en methode ── --}}
        @php $curStripStatus = old('strip_status', $booking->strip_status); @endphp
        <h3 style="font-size:.875rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.75rem;">🎨 Fotostrip workflow</h3>
        <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:.75rem;padding:1rem 1.1rem;margin-bottom:1.25rem;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group" style="margin-bottom:0;">
                    <label>Status</label>
                    <select name="strip_status" class="form-control">
                        <option value="" @selected($curStripStatus === null)>— Geen —</option>
                        @foreach(\App\Models\Booking::STRIP_STATUS_LABELS as $val => $lbl)
                            <option value="{{ $val }}" @selected($curStripStatus === $val)>{{ $lbl }}</option>
                        @endforeach
                        {{-- Legacy: als deze boeking nog de oude waarde heeft, blijft die kiesbaar zodat opslaan 'm niet wegvaagt. --}}
                        @if($curStripStatus === 'waiting_input')
                            <option value="waiting_input" selected>⏳ Wacht op input klant (verouderd)</option>
                        @endif
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label>Methode</label>
                    <select name="strip_design_method" class="form-control">
                        <option value="" @selected(old('strip_design_method', $booking->strip_design_method) === null)>— Klant heeft niet gekozen —</option>
                        <option value="self"     @selected(old('strip_design_method', $booking->strip_design_method)==='self')>🎨 Zelf met template</option>
                        <option value="ai"       @selected(old('strip_design_method', $booking->strip_design_method)==='ai')>✨ AI-tool</option>
                        <option value="custom"   @selected(old('strip_design_method', $booking->strip_design_method)==='custom')>✏️ Wij ontwerpen</option>
                        <option value="template" @selected(old('strip_design_method', $booking->strip_design_method)==='template')>📋 Template-galerij (verouderd)</option>
                    </select>
                </div>
            </div>
            <div style="font-size:.72rem;color:#92400e;margin-top:.75rem;">
                💡 Deze velden worden normaal door de klant ingevuld via het portaal. Hier kun je ze handmatig overschrijven.
            </div>
        </div>

        {{-- ── Gallerij & Prijs & status ── --}}
        <h3 style="font-size:.875rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.75rem;">Gallerij, prijs & status</h3>
        <div class="form-grid">
            <div class="form-group" style="grid-column:span 2;">
                <label>Gallerij-link (URL naar foto's)</label>
                <input type="url" name="gallery_url" value="{{ old('gallery_url', $booking->gallery_url) }}" placeholder="https://...">
                <div style="font-size:.75rem;color:#64748b;margin-top:.25rem;">Als je dit invult ontvangt de klant automatisch een mail met deze link.</div>
            </div>
            <div class="form-group">
                <label>Totaalprijs (€) <span style="font-weight:400;color:#94a3b8;">— automatisch berekend</span></label>
                <input type="number" id="total_price" name="total_price" value="{{ old('total_price', $booking->total_price) }}" step="0.01" min="0" required
                    readonly style="background:#f8fafc;cursor:default;color:#64748b;">
            </div>
            <div class="form-group">
                <label>Boekingstatus</label>
                <select name="status">
                    <option value="confirmed"  @selected(old('status', $booking->status)==='confirmed')>Bevestigd</option>
                    <option value="completed"  @selected(old('status', $booking->status)==='completed')>Voltooid</option>
                    <option value="cancelled"  @selected(old('status', $booking->status)==='cancelled')>Geannuleerd</option>
                    <option value="no_show"    @selected(old('status', $booking->status)==='no_show')>No-show</option>
                </select>
            </div>
            <div class="form-group">
                <label>Betalingsstatus</label>
                <select name="payment_status">
                    <option value="unpaid"    @selected(old('payment_status', $booking->payment_status)==='unpaid')>Niet betaald</option>
                    <option value="partial"   @selected(old('payment_status', $booking->payment_status)==='partial')>Gedeeltelijk betaald</option>
                    <option value="paid"      @selected(old('payment_status', $booking->payment_status)==='paid')>Betaald</option>
                    <option value="cancelled" @selected(old('payment_status', $booking->payment_status)==='cancelled')>Geannuleerd</option>
                    <option value="refunded"  @selected(old('payment_status', $booking->payment_status)==='refunded')>Terugbetaald</option>
                </select>
            </div>
        </div>

        <div style="display:flex;gap:.75rem;margin-top:.5rem;">
            <button type="submit" class="btn btn-primary">Opslaan</button>
            <a href="{{ route('bookings.show', $booking) }}" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>

{{-- Rechter-paneels: andere ritten op event-datum en op ophaal-datum --}}
<div class="day-logistics-stack">
    @include('bookings._day_logistics_panel', [
        'panelId'     => 'event',
        'title'       => '📅 Andere ritten op eventdatum',
        'watchNames'  => ['event_date'],
        'excludeId'   => $booking->id,
        'initialDate' => $booking->event_date?->toDateString(),
    ])
    @include('bookings._day_logistics_panel', [
        'panelId'     => 'pickup',
        'title'       => '↩ Andere ritten op ophaaldatum',
        'watchNames'  => ['pickup_at', 'customer_return_at'],
        'excludeId'   => $booking->id,
        'initialDate' => $booking->pickup_at?->toDateString() ?? $booking->customer_return_at?->toDateString(),
    ])
</div>

</div>{{-- /bk-edit-layout grid --}}

<style>
@media (max-width: 980px) {
    .bk-edit-layout { display: block !important; }
    .bk-edit-layout > div > .card { max-width: 100% !important; }
}
</style>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
// ── Flatpickr initialisatie ───────────────────────────────────
const fpNl = {
    firstDayOfWeek: 1,
    weekAbbreviation: 'Wk',
    weekdays: { shorthand:['zo','ma','di','wo','do','vr','za'], longhand:['zondag','maandag','dinsdag','woensdag','donderdag','vrijdag','zaterdag'] },
    months: { shorthand:['jan','feb','mrt','apr','mei','jun','jul','aug','sep','okt','nov','dec'], longhand:['januari','februari','maart','april','mei','juni','juli','augustus','september','oktober','november','december'] },
};
// Live input commit: waarde opslaan zodra het format compleet is tijdens typen
function fpLive(picker, regex) {
    picker.input.addEventListener('input', function() {
        const v = this.value.trim();
        if (regex.test(v)) picker.setDate(v, true);
    });
}

// Bereken ophaal- en retourdatum voor To Go op basis van eventdatum
// Weekdag → ophalen maandag die week, retour vrijdag die week
// Vrijdag → ophalen die vrijdag, retour maandag erna
// Zaterdag → ophalen vrijdag ervoor, retour maandag erna
// Zondag → ophalen vrijdag ervoor, retour die maandag
function calculateToGoDates(dateStr) {
    if (!dateStr) return;
    if (document.getElementById('booking_type').value !== 'to_go') return;

    // Datum parsen in lokale tijdzone (niet UTC) om dag-verschuiving te voorkomen
    const parts = dateStr.split('-').map(Number);
    const d   = new Date(parts[0], parts[1] - 1, parts[2]); // lokale midnight
    const dow = d.getDay(); // 0=zo, 1=ma, 2=di, 3=wo, 4=do, 5=vr, 6=za

    const addDays = (date, n) => {
        const r = new Date(date);
        r.setDate(r.getDate() + n);
        return r;
    };

    let pickup, ret;
    if (dow === 5) {            // vrijdag → ophalen vrijdag, retour maandag
        pickup = addDays(d, 0);
        ret    = addDays(d, 3);
    } else if (dow === 6) {     // zaterdag → ophalen vrijdag, retour maandag
        pickup = addDays(d, -1);
        ret    = addDays(d, 2);
    } else if (dow === 0) {     // zondag → ophalen vrijdag, retour maandag
        pickup = addDays(d, -2);
        ret    = addDays(d, 1);
    } else {                    // ma t/m do → ophalen maandag die week, retour vrijdag die week
        pickup = addDays(d, -(dow - 1));
        ret    = addDays(d, 5 - dow);
    }

    const fmt = (date) => {
        const y   = date.getFullYear();
        const m   = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${day} 10:00`;
    };

    const pickupEl = document.querySelector('[name="customer_pickup_at"]');
    const returnEl = document.querySelector('[name="customer_return_at"]');
    // Alleen suggereren in LEGE velden — nooit een reeds ingevuld (opgeslagen of handmatig
    // aangepast) ophaal/retourmoment overschrijven. Voorkomt dat tijden bij het bewerken van
    // een boeking stilletjes resetten naar de standaardsuggestie.
    if (pickupEl?._flatpickr && !pickupEl.value) pickupEl._flatpickr.setDate(fmt(pickup), true);
    if (returnEl?._flatpickr && !returnEl.value) returnEl._flatpickr.setDate(fmt(ret), true);
}

document.querySelectorAll('.fp-date').forEach(el => {
    const onChange = el.name === 'event_date'
        ? [(dates, dateStr) => { calculateToGoDates(dateStr); fetchUnitAvailability(); }]
        : [];
    const p = flatpickr(el, { dateFormat:'Y-m-d', locale:fpNl, disableMobile:true, allowInput:true, onChange });
    fpLive(p, /^\d{4}-\d{2}-\d{2}$/);
});
document.querySelectorAll('.fp-datetime').forEach(el => {
    const p = flatpickr(el, { enableTime:true, dateFormat:'Y-m-d H:i', time_24hr:true, minuteIncrement:15, locale:fpNl, disableMobile:true, allowInput:true,
        defaultHour: 10, defaultMinute: 0,
        onChange: fetchUnitAvailability });
    fpLive(p, /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/);
});
document.querySelectorAll('.fp-time').forEach(el => {
    const p = flatpickr(el, { enableTime:true, noCalendar:true, dateFormat:'H:i', time_24hr:true, minuteIncrement:15, disableMobile:true, allowInput:true });
    fpLive(p, /^\d{2}:\d{2}$/);
});

// ── Unit toggle (span + hidden input aanpak) ──────────────────
function toggleUnit(span) {
    if (span.dataset.booked === '1') return;

    const unit      = span.dataset.unit;
    const assetId   = span.dataset.assetId;
    const container = document.getElementById('unit-inputs-' + assetId);
    const card      = span.closest('.asset-card');
    const existing  = container.querySelector(`input[value="${unit}"]`);
    const isWarn    = span.dataset.warning === '1';

    if (existing) {
        existing.remove();
        span.dataset.selected = '0';
        if (isWarn) {
            span.style.borderColor = '#f59e0b';
            span.style.background  = '#fef3c7';
            span.style.color       = '#92400e';
        } else {
            span.style.borderColor = '#cbd5e1';
            span.style.background  = '#f8fafc';
            span.style.color       = '#475569';
        }
    } else {
        const inp = document.createElement('input');
        inp.type  = 'hidden';
        inp.name  = `assets[${assetId}][units][]`;
        inp.value = unit;
        container.appendChild(inp);
        span.dataset.selected = '1';
        if (isWarn) {
            span.style.borderColor = '#d97706';
            span.style.background  = '#fef9c3';
            span.style.color       = '#92400e';
        } else {
            span.style.borderColor = '#2563eb';
            span.style.background  = '#dbeafe';
            span.style.color       = '#1e40af';
        }
    }

    updateUnitWarningBanner(assetId, card);

    const anySelected = container.querySelectorAll('input[type=hidden]').length > 0;
    card.style.borderColor = anySelected ? '#2563eb' : '';
    card.style.background  = anySelected ? '#eff6ff' : '';
    calcTotaal();
}

function updateUnitWarningBanner(assetId, card) {
    const banner    = document.getElementById('unit-warning-' + assetId);
    const container = document.getElementById('unit-inputs-' + assetId);
    if (!banner) return;
    const msgs = [];
    container.querySelectorAll('input[type=hidden]').forEach(inp => {
        const s = card.querySelector(`span[data-unit="${inp.value}"]`);
        if (s?.dataset.warning === '1' && s.dataset.warningMsg) {
            msgs.push('⚠️ Unit ' + inp.value + ': ' + s.dataset.warningMsg);
        }
    });
    banner.innerHTML    = msgs.join('<br>');
    banner.style.display = msgs.length ? 'block' : 'none';
}

// ── Unit beschikbaarheid ophalen bij datumwijziging ───────────
const EXCLUDE_BOOKING_ID = {{ $booking->id }};

function fetchUnitAvailability() {
    const eventDate         = document.querySelector('[name="event_date"]')?.value;
    const eventEndDate      = document.querySelector('[name="event_end_date"]')?.value || eventDate;
    const bookingType       = document.querySelector('[name="booking_type"]')?.value || '';
    const deliveryAt        = document.querySelector('[name="delivery_at"]')?.value || '';
    const pickupAt          = document.querySelector('[name="pickup_at"]')?.value || '';
    const customerPickupAt  = document.querySelector('[name="customer_pickup_at"]')?.value || '';
    const customerReturnAt  = document.querySelector('[name="customer_return_at"]')?.value || '';
    if (!eventDate) return;

    const params = new URLSearchParams({ event_date: eventDate, event_end_date: eventEndDate,
        booking_type: bookingType, delivery_at: deliveryAt, pickup_at: pickupAt,
        customer_pickup_at: customerPickupAt, customer_return_at: customerReturnAt,
        exclude_booking_id: EXCLUDE_BOOKING_ID });
    fetch(`/bookings/unit-availability?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        document.querySelectorAll('.asset-card[data-photobooth]').forEach(card => {
            const assetId    = card.dataset.assetId;
            const assetData  = data[assetId] || {};
            const booked     = Array.isArray(assetData) ? assetData : (assetData.booked  || []);
            const warning    = Array.isArray(assetData) ? []        : (assetData.warning || []);
            const warnInfo   = Array.isArray(assetData) ? {}        : (assetData.warningInfo || {});
            const container  = document.getElementById('unit-inputs-' + assetId);

            card.querySelectorAll('span[data-unit]').forEach(span => {
                const unit      = parseInt(span.dataset.unit);
                const isBooked  = booked.includes(unit);
                const isWarning = !isBooked && warning.includes(unit);

                if (isBooked) {
                    span.dataset.booked      = '1';
                    span.dataset.warning     = '0';
                    span.dataset.warningMsg  = '';
                    span.style.borderColor   = '#ef4444';
                    span.style.background    = '#fee2e2';
                    span.style.color         = '#991b1b';
                    span.style.cursor        = 'not-allowed';
                    span.title               = '🔒 Bezet — tijdconflict met andere boeking';
                    container?.querySelector(`input[value="${unit}"]`)?.remove();
                    span.dataset.selected    = '0';
                } else if (isWarning) {
                    const msg = warnInfo[unit]?.message || '';
                    span.dataset.booked      = '0';
                    span.dataset.warning     = '1';
                    span.dataset.warningMsg  = msg;
                    span.style.cursor        = 'pointer';
                    span.title               = '⚠️ ' + (msg || 'Korte tijd tussen boekingen');
                    if (span.dataset.selected === '1') {
                        span.style.borderColor = '#d97706';
                        span.style.background  = '#fef9c3';
                        span.style.color       = '#92400e';
                    } else {
                        span.style.borderColor = '#f59e0b';
                        span.style.background  = '#fef3c7';
                        span.style.color       = '#92400e';
                    }
                } else {
                    span.dataset.booked      = '0';
                    span.dataset.warning     = '0';
                    span.dataset.warningMsg  = '';
                    span.style.cursor        = 'pointer';
                    span.title               = 'Unit ' + unit;
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
            updateUnitWarningBanner(assetId, card);
        });
        calcTotaal();
    })
    .catch(() => {});
}

// Haal bij paginaload ook de beschikbaarheid op (voor initieel weergeven van warnings)
document.addEventListener('DOMContentLoaded', fetchUnitAvailability);
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
    // Bij wisselen naar To Go: datums direct berekenen op basis van huidige eventdatum
    if (type === 'to_go') {
        const eventDate = document.querySelector('[name="event_date"]')?.value;
        calculateToGoDates(eventDate);
    }
    // Team labels
    const delivLabel  = document.getElementById('delivery-staff-label');
    const pickupLabel = document.getElementById('pickup-staff-label');
    if (delivLabel)  delivLabel.textContent  = type === 'to_go' ? 'Afgever (To Go)' : 'Bezorger';
    if (pickupLabel) pickupLabel.textContent = type === 'to_go' ? 'Ophaler (To Go)' : 'Ophaler';
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
        input._flatpickr?.clear();
    }
}

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

        berekenReiskosten();
    });
}

// ── Reiskosten ────────────────────────────────────────────────
let reiskostenTimer = null;

function berekenReiskosten() {
    const adres    = document.getElementById('event_address').value.trim();
    const postcode = document.getElementById('event_postcode').value.trim();
    const stad     = document.getElementById('event_city').value.trim();

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

            const info  = document.getElementById('reiskosten-info');
            const tekst = document.getElementById('reiskosten-tekst');
            const kmInfo = data.distance_km <= data.free_km
                ? `${data.distance_km} km — binnen gratis zone (${data.free_km} km)`
                : `${data.distance_km} km — ${data.distance_km - data.free_km} km × €${data.rate_per_km}`;
            tekst.textContent = `🚗 Reisafstand: ${kmInfo} = €${data.cost.toFixed(2)}`;
            info.style.display = 'block';

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

['event_address','event_postcode','event_city'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('change', berekenReiskosten);
});

// ── Leverinstructies: afbeelding verwijderen via AJAX ──
document.querySelectorAll('.delete-delivery-img').forEach(btn => {
    btn.addEventListener('click', function() {
        const path = this.dataset.imgPath;
        const wrapper = this.closest('div');
        crmConfirm('Deze afbeelding verwijderen?', () => {
            fetch(@json(route('bookings.delivery-image-delete', $booking)), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: '_method=DELETE&_token=' + encodeURIComponent(@json(csrf_token())) + '&path=' + encodeURIComponent(path),
            })
            .then(r => { if (r.ok) wrapper.remove(); })
            .catch(() => alert('Verwijderen mislukt'));
        });
    });
});
</script>
<script
    src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_key') }}&libraries=places&callback=initMaps"
    async defer>
</script>
@endpush
