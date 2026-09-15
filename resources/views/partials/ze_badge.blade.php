{{-- Zero-emissiezone-badge — toon overal waar het bezorgadres verschijnt.
     Verwacht $booking (of losse $inZe / $zeName). --}}
@php
    $inZe   = $inZe   ?? ($booking->in_ze_zone ?? false);
    $zeName = $zeName ?? ($booking->ze_zone_name ?? null);
@endphp
@if($inZe)
<span class="ze-badge"
      title="Zero-emissiezone{{ $zeName ? ' — '.$zeName : '' }} · onze bus mag hier (nog) niet in"
      style="display:inline-flex;align-items:center;gap:.15rem;font-size:.62rem;font-weight:800;line-height:1;padding:.15rem .4rem;border-radius:999px;background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;white-space:nowrap;vertical-align:middle;">⚡ ZE-zone</span>
@endif
