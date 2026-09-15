@php
    // $b = booking, $type = 'delivery' | 'pickup' | 'handover'
    $adres = trim(implode(', ', array_filter([$b->event_address, $b->event_postcode, $b->event_city])));
    $mapsUrl = $adres ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($adres) : null;
@endphp

<div class="detail-row">
    <div class="detail-icon">👤</div>
    <div class="detail-text">
        <div class="detail-label">Klant</div>
        <strong>{{ $b->customer_name }}</strong>
        @if($b->customer_phone)
        <br><a href="tel:{{ $b->customer_phone }}" style="color:#3b82f6;font-size:.85rem;">{{ $b->customer_phone }}</a>
        @endif
    </div>
</div>

@if($b->location_contact_phone)
@php
    $lcLabels = ['ceremoniemeester' => 'Ceremoniemeester', 'eventmanager' => 'Eventmanager', 'eventlocatie' => 'Eventlocatie', 'wij_zelf' => 'Klant zelf', 'anders' => 'Anders'];
    $lcLabel  = $lcLabels[$b->location_contact_type] ?? 'Contactpersoon';
@endphp
<div class="detail-row">
    <div class="detail-icon">📇</div>
    <div class="detail-text">
        <div class="detail-label">Contactpersoon op locatie</div>
        <strong>{{ $lcLabel }}</strong>
        <br><a href="tel:{{ $b->location_contact_phone }}" style="color:#3b82f6;font-size:.85rem;">{{ $b->location_contact_phone }}</a>
        @if($b->location_contact_email)
        <br><a href="mailto:{{ $b->location_contact_email }}" style="color:#3b82f6;font-size:.85rem;">{{ $b->location_contact_email }}</a>
        @endif
    </div>
</div>
@endif

@if($b->event_location || $adres)
<div class="detail-row">
    <div class="detail-icon">📍</div>
    <div class="detail-text">
        <div class="detail-label">Locatie @include('partials.ze_badge', ['booking' => $b])</div>
        @if($b->event_location)<strong>{{ $b->event_location }}</strong><br>@endif
        @if($adres)<span style="color:#64748b;">{{ $adres }}</span>@endif
        @if($mapsUrl)
        <br><a href="{{ $mapsUrl }}" target="_blank" class="maps-link">🗺️ Open in Google Maps</a>
        @endif
    </div>
</div>
@endif

@if($b->event_start_time)
<div class="detail-row">
    <div class="detail-icon">🎉</div>
    <div class="detail-text">
        <div class="detail-label">Evenement</div>
        {{ $b->event_date->translatedFormat('j F') }}
        van {{ substr($b->event_start_time, 0, 5) }}
        @if($b->event_end_time) tot {{ substr($b->event_end_time, 0, 5) }}@endif
    </div>
</div>
@endif

@if($type === 'pickup' && $b->pickup_contact_person)
<div class="detail-row">
    <div class="detail-icon">📞</div>
    <div class="detail-text">
        <div class="detail-label">Contactpersoon ophaal</div>
        {{ $b->pickup_contact_person }}
        @if($b->pickup_contact_time) om {{ substr($b->pickup_contact_time, 0, 5) }}@endif
    </div>
</div>
@endif

@if($b->event_notes)
<div class="detail-row">
    <div class="detail-icon">📝</div>
    <div class="detail-text">
        <div class="detail-label">Opmerkingen</div>
        <span style="white-space:pre-line;">{{ $b->event_notes }}</span>
    </div>
</div>
@endif

@php
    $hasInstructions = trim((string) $b->delivery_instructions) !== '' || ! empty($b->delivery_instructions_images);
@endphp
@if($hasInstructions)
<div style="margin-top:.5rem;">
    <button type="button" class="instructions-toggle" onclick="toggleInstructions(this)"
            style="width:100%;display:flex;align-items:center;gap:.5rem;background:#fffbeb;border:1px solid #fcd34d;border-radius:.5rem;padding:.55rem .75rem;font-size:.85rem;font-weight:600;color:#92400e;cursor:pointer;text-align:left;">
        <span style="font-size:1rem;">📋</span>
        <span style="flex:1;">Er zijn specifieke leverinstructies beschikbaar</span>
        <span class="caret" style="font-size:.7rem;transition:transform .2s;">▼</span>
    </button>
    <div class="instructions-content" style="display:none;margin-top:.5rem;background:#fffbeb;border:1px solid #fcd34d;border-radius:.5rem;padding:.75rem .9rem;">
        @if(trim((string) $b->delivery_instructions) !== '')
        <div style="font-size:.875rem;color:#1e293b;line-height:1.5;white-space:pre-line;margin-bottom:.5rem;">{{ $b->delivery_instructions }}</div>
        @endif
        @if(! empty($b->delivery_instructions_images))
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:.5rem;margin-top:.5rem;">
            @foreach($b->delivery_instructions_images as $img)
                <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($img) }}" target="_blank" style="display:block;border-radius:.4rem;overflow:hidden;border:1px solid #fcd34d;">
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($img) }}" alt="Leverinstructie" style="width:100%;height:110px;object-fit:cover;display:block;">
                </a>
            @endforeach
        </div>
        <div style="font-size:.7rem;color:#92400e;margin-top:.4rem;">Tik op een foto om te vergroten.</div>
        @endif
    </div>
</div>
@endif

{{-- Wat moet je meenemen — alleen bij bezorgen/afgeven --}}
@if(in_array($type, ['delivery', 'handover']) && $b->items && $b->items->isNotEmpty())
@php
    $catIcons = ['photobooth' => '📷', 'background' => '🖼', 'prop_box' => '🎩', 'extra' => '✨'];
    $itemsByCat = $b->items->filter(fn ($i) => $i->asset)->groupBy(fn ($i) => $i->asset->category);
@endphp
@if($itemsByCat->isNotEmpty())
<div class="assets-block">
    <div class="assets-block-title">📦 Wat neem je mee</div>
    <div class="assets-list">
        @foreach($itemsByCat as $cat => $items)
            @foreach($items as $it)
            <div class="asset-row">
                <span class="icon">{{ $catIcons[$cat] ?? '📦' }}</span>
                <span>{{ $it->asset->name }}@if($it->unit_number) — unit {{ $it->unit_number }}@endif</span>
                @if($it->quantity > 1)<span class="qty">×{{ $it->quantity }}</span>@endif
            </div>
            @endforeach
        @endforeach
    </div>
</div>
@endif
@endif
