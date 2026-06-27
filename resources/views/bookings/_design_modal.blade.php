<h3 style="margin:0 0 .2rem;font-size:1rem;font-weight:700;">Ontwerp beheren</h3>
<p style="margin:0 0 1.25rem;font-size:.85rem;color:#64748b;">{{ $booking->customer_name }} — {{ $booking->event_date?->format('d M Y') }}</p>

<form method="POST" action="{{ route('bookings.strip-design', $booking) }}" enctype="multipart/form-data" id="form-design-{{ $booking->id }}">
    @csrf
    <div id="dropzone-{{ $booking->id }}"
         style="border:2px dashed #cbd5e1;border-radius:10px;padding:1.5rem 1rem;text-align:center;cursor:pointer;transition:border-color .2s,background .2s;margin-bottom:1rem;background:#f8fafc;"
         onclick="document.getElementById('file-{{ $booking->id }}').click()"
         ondragover="event.preventDefault();this.style.borderColor='#f97316';this.style.background='#fff7ed';"
         ondragleave="this.style.borderColor='#cbd5e1';this.style.background='#f8fafc';"
         ondrop="handleDrop(event,'{{ $booking->id }}')">
        <div style="font-size:1.75rem;margin-bottom:.4rem;">🖼️</div>
        <div style="font-size:.875rem;font-weight:600;color:#374151;">Sleep een bestand hierheen</div>
        <div style="font-size:.75rem;color:#94a3b8;margin-top:.2rem;">of klik om te bladeren — JPG, PNG, GIF, WEBP, PDF (max 2 MB)</div>
        <div id="filename-{{ $booking->id }}" style="margin-top:.6rem;font-size:.8rem;color:#f97316;font-weight:600;display:none;"></div>
    </div>
    <input type="file" id="file-{{ $booking->id }}" name="strip_design_file" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" style="display:none;"
           onchange="showFilename('{{ $booking->id }}', this)">

    <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.75rem;">
        <div style="flex:1;height:1px;background:#e2e8f0;"></div>
        <span style="font-size:.75rem;color:#94a3b8;white-space:nowrap;">of link plakken</span>
        <div style="flex:1;height:1px;background:#e2e8f0;"></div>
    </div>
    <div style="margin-bottom:1rem;">
        <input type="url" name="strip_design_url" placeholder="https://drive.google.com/..." style="width:100%;box-sizing:border-box;">
    </div>
    <label style="display:flex;align-items:center;gap:.5rem;margin-bottom:1.25rem;font-size:.85rem;color:#374151;cursor:pointer;padding:.6rem .75rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">
        <input type="checkbox" name="mockup" value="1" checked>
        <span><strong>In mockup plaatsen</strong></span>
    </label>
    <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-bottom:1.25rem;">
        <button type="button" onclick="closeDesignModal()" class="btn btn-secondary btn-sm">Annuleren</button>
        <button type="submit" class="btn btn-primary btn-sm">📤 Uploaden & mail sturen</button>
    </div>
</form>

@php $designs = array_reverse($booking->strip_designs ?? []); @endphp
@if(count($designs) > 0)
<div style="border-top:1px solid #e2e8f0;padding-top:1rem;">
    <p style="font-size:.8rem;font-weight:600;color:#374151;margin:0 0 .6rem;">Geüploade ontwerpen ({{ count($designs) }})</p>
    <div style="display:flex;flex-direction:column;gap:.5rem;">
        @foreach($designs as $idx => $design)
        @php
            $isActive   = ($booking->strip_design_url === $design['url']);
            $isImage    = preg_match('/\.(jpg|jpeg|png|gif|webp)(\?|$)/i', $design['url']);
            $uploadedAt = \Carbon\Carbon::parse($design['uploaded_at'] ?? now());
        @endphp
        <div style="display:flex;align-items:center;gap:.75rem;padding:.625rem .75rem;background:{{ $isActive ? '#fff7ed' : '#f8fafc' }};border:1px solid {{ $isActive ? '#fed7aa' : '#e2e8f0' }};border-radius:8px;">
            @if($isImage)
            <a href="{{ $design['url'] }}" target="_blank" style="flex-shrink:0;">
                <img src="{{ $design['url'] }}" alt="ontwerp" style="width:44px;height:44px;object-fit:cover;border-radius:5px;border:1px solid #e2e8f0;">
            </a>
            @else
            <a href="{{ $design['url'] }}" target="_blank" style="flex-shrink:0;width:44px;height:44px;display:flex;align-items:center;justify-content:center;background:#f1f5f9;border-radius:5px;font-size:1.25rem;text-decoration:none;">📄</a>
            @endif
            <div style="flex:1;min-width:0;">
                <div style="font-size:.8rem;font-weight:600;color:#374151;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    {{ $design['filename'] ?? 'Ontwerp ' . ($idx + 1) }}
                    @if($isActive)<span style="font-size:.7rem;background:#fed7aa;color:#9a3412;padding:.1rem .4rem;border-radius:4px;margin-left:.4rem;">actief</span>@endif
                </div>
                <div style="font-size:.72rem;color:#94a3b8;">{{ $uploadedAt->format('d M Y, H:i') }}</div>
            </div>
            <div style="display:flex;gap:.4rem;flex-shrink:0;">
                <a href="{{ $design['url'] }}" target="_blank" class="btn btn-sm btn-secondary" style="padding:.25rem .5rem;font-size:.75rem;" title="Bekijk">👁</a>
                <form method="POST" action="{{ route('bookings.strip-design-delete', $booking) }}"
                      data-confirm="Dit ontwerp verwijderen?" style="display:inline;">
                    @csrf @method('DELETE')
                    <input type="hidden" name="url" value="{{ $design['url'] }}">
                    <button type="submit" class="btn btn-sm" style="padding:.25rem .5rem;font-size:.75rem;background:#fee2e2;color:#dc2626;border:none;cursor:pointer;border-radius:6px;">🗑</button>
                </form>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif
