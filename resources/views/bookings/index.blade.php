@extends('layouts.app')
@section('title', 'Boekingen')

@push('modals')
@foreach($bookings as $boeking)
<div id="modal-design-{{ $boeking->id }}" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.45);align-items:flex-start;justify-content:center;overflow-y:auto;padding:2rem 1rem;">
    <div style="background:#fff;border-radius:12px;padding:1.75rem;width:100%;max-width:520px;box-shadow:0 20px 60px rgba(0,0,0,.2);position:relative;margin:auto;">
        <button onclick="document.getElementById('modal-design-{{ $boeking->id }}').style.display='none'" style="position:absolute;top:1rem;right:1rem;background:none;border:none;font-size:1.25rem;cursor:pointer;color:#64748b;">✕</button>
        <h3 style="margin:0 0 .2rem;font-size:1rem;font-weight:700;">Ontwerp beheren</h3>
        <p style="margin:0 0 1.25rem;font-size:.85rem;color:#64748b;">{{ $boeking->customer_name }} — {{ $boeking->event_date->format('d M Y') }}</p>

        {{-- Upload zone --}}
        <form method="POST" action="{{ route('bookings.strip-design', $boeking) }}" enctype="multipart/form-data" id="form-design-{{ $boeking->id }}">
            @csrf
            <div id="dropzone-{{ $boeking->id }}"
                 style="border:2px dashed #cbd5e1;border-radius:10px;padding:1.5rem 1rem;text-align:center;cursor:pointer;transition:border-color .2s,background .2s;margin-bottom:1rem;background:#f8fafc;"
                 onclick="document.getElementById('file-{{ $boeking->id }}').click()"
                 ondragover="event.preventDefault();this.style.borderColor='#f97316';this.style.background='#fff7ed';"
                 ondragleave="this.style.borderColor='#cbd5e1';this.style.background='#f8fafc';"
                 ondrop="handleDrop(event,'{{ $boeking->id }}')">
                <div style="font-size:1.75rem;margin-bottom:.4rem;">🖼️</div>
                <div style="font-size:.875rem;font-weight:600;color:#374151;">Sleep een bestand hierheen</div>
                <div style="font-size:.75rem;color:#94a3b8;margin-top:.2rem;">of klik om te bladeren — JPG, PNG, GIF, WEBP, PDF (max 20 MB)</div>
                <div id="filename-{{ $boeking->id }}" style="margin-top:.6rem;font-size:.8rem;color:#f97316;font-weight:600;display:none;"></div>
            </div>
            <input type="file" id="file-{{ $boeking->id }}" name="strip_design_file" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" style="display:none;"
                   onchange="showFilename('{{ $boeking->id }}', this)">

            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.75rem;">
                <div style="flex:1;height:1px;background:#e2e8f0;"></div>
                <span style="font-size:.75rem;color:#94a3b8;white-space:nowrap;">of link plakken</span>
                <div style="flex:1;height:1px;background:#e2e8f0;"></div>
            </div>
            <div style="margin-bottom:1.25rem;">
                <input type="url" name="strip_design_url" placeholder="https://drive.google.com/..." style="width:100%;box-sizing:border-box;">
            </div>
            <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-bottom:1.25rem;">
                <button type="button" onclick="document.getElementById('modal-design-{{ $boeking->id }}').style.display='none'" class="btn btn-secondary btn-sm">Annuleren</button>
                <button type="submit" class="btn btn-primary btn-sm">📤 Uploaden & mail sturen</button>
            </div>
        </form>

        {{-- Ontwerpen lijst --}}
        @php $designs = array_reverse($boeking->strip_designs ?? []); @endphp
        @if(count($designs) > 0)
        <div style="border-top:1px solid #e2e8f0;padding-top:1rem;">
            <p style="font-size:.8rem;font-weight:600;color:#374151;margin:0 0 .6rem;">Geüploade ontwerpen ({{ count($designs) }})</p>
            <div style="display:flex;flex-direction:column;gap:.5rem;">
                @foreach($designs as $idx => $design)
                @php
                    $isActive = ($boeking->strip_design_url === $design['url']);
                    $isImage  = preg_match('/\.(jpg|jpeg|png|gif|webp)(\?|$)/i', $design['url']);
                    $uploadedAt = \Carbon\Carbon::parse($design['uploaded_at'] ?? now());
                @endphp
                <div style="display:flex;align-items:center;gap:.75rem;padding:.625rem .75rem;background:{{ $isActive ? '#fff7ed' : '#f8fafc' }};border:1px solid {{ $isActive ? '#fed7aa' : '#e2e8f0' }};border-radius:8px;">
                    {{-- Thumbnail --}}
                    @if($isImage)
                    <a href="{{ $design['url'] }}" target="_blank" style="flex-shrink:0;">
                        <img src="{{ $design['url'] }}" alt="ontwerp" style="width:44px;height:44px;object-fit:cover;border-radius:5px;border:1px solid #e2e8f0;">
                    </a>
                    @else
                    <a href="{{ $design['url'] }}" target="_blank" style="flex-shrink:0;width:44px;height:44px;display:flex;align-items:center;justify-content:center;background:#f1f5f9;border-radius:5px;font-size:1.25rem;text-decoration:none;">📄</a>
                    @endif

                    {{-- Info --}}
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:.8rem;font-weight:600;color:#374151;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            {{ $design['filename'] ?? 'Ontwerp ' . ($idx + 1) }}
                            @if($isActive)
                            <span style="font-size:.7rem;background:#fed7aa;color:#9a3412;padding:.1rem .4rem;border-radius:4px;margin-left:.4rem;">actief</span>
                            @endif
                        </div>
                        <div style="font-size:.72rem;color:#94a3b8;">{{ $uploadedAt->format('d M Y, H:i') }}</div>
                    </div>

                    {{-- Acties --}}
                    <div style="display:flex;gap:.4rem;flex-shrink:0;">
                        <a href="{{ $design['url'] }}" target="_blank" class="btn btn-sm btn-secondary" style="padding:.25rem .5rem;font-size:.75rem;" title="Bekijk">👁</a>
                        <form method="POST" action="{{ route('bookings.strip-design-delete', $boeking) }}"
                              onsubmit="return confirm('Dit ontwerp verwijderen?');" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="url" value="{{ $design['url'] }}">
                            <button type="submit" class="btn btn-sm" style="padding:.25rem .5rem;font-size:.75rem;background:#fee2e2;color:#dc2626;border:none;cursor:pointer;border-radius:6px;" title="Verwijder">🗑</button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@endforeach

<script>
function handleDrop(event, id) {
    event.preventDefault();
    const dropzone = document.getElementById('dropzone-' + id);
    dropzone.style.borderColor = '#cbd5e1';
    dropzone.style.background  = '#f8fafc';
    const files = event.dataTransfer.files;
    if (files.length > 0) {
        const fileInput = document.getElementById('file-' + id);
        const dt = new DataTransfer();
        dt.items.add(files[0]);
        fileInput.files = dt.files;
        showFilename(id, fileInput);
    }
}

function showFilename(id, input) {
    const label = document.getElementById('filename-' + id);
    if (input.files && input.files[0]) {
        label.textContent = '✓ ' + input.files[0].name;
        label.style.display = 'block';
    }
}

// Strip status AJAX dropdown
const stripColors = {
    waiting_input: { bg: '#fef3c7', text: '#92400e' },
    designing:     { bg: '#dbeafe', text: '#1e40af' },
    review:        { bg: '#f3e8ff', text: '#6b21a8' },
    accepted:      { bg: '#dcfce7', text: '#15803d' },
    ready:         { bg: '#d1fae5', text: '#065f46' },
};

document.addEventListener('change', function(e) {
    const sel = e.target.closest('.strip-status-select');
    if (!sel) return;

    const url   = sel.dataset.url;
    const value = sel.value;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
                   || '{{ csrf_token() }}';

    sel.disabled = true;
    sel.style.opacity = '.5';

    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        body: JSON.stringify({ strip_status: value }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const c = stripColors[value] || { bg: '#f1f5f9', text: '#475569' };
            sel.style.background   = c.bg;
            sel.style.color        = c.text;
            sel.style.borderColor  = c.bg;
        }
    })
    .catch(() => {})
    .finally(() => {
        sel.disabled = false;
        sel.style.opacity = '1';
    });
});
</script>
@endpush

@section('actions')
    <a href="{{ route('bookings.create') }}" class="btn btn-primary">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Nieuwe boeking
    </a>
@endsection

@section('content')
<div style="padding:0 1.5rem;">
    {{-- Tabs --}}
    @include('bookings.tabs')
</div>

<div class="card neu-card" style="margin-top: 1.5rem;">
    {{-- Search & Filter --}}
    <form method="GET" style="display:flex;gap:0.75rem;margin-bottom:1rem;flex-wrap:wrap;padding:1.25rem;border-bottom:1px solid var(--gray-100);">
        <input type="text" name="zoeken" value="{{ request('zoeken') }}" placeholder="Zoeken op naam, nummer..." style="flex:1;min-width:200px;">
        <select name="status" style="min-width:160px;">
            <option value="">Alle statussen</option>
            <option value="confirmed" @selected(request('status')=='confirmed')>Bevestigd</option>
            <option value="completed" @selected(request('status')=='completed')>Voltooid</option>
            <option value="cancelled" @selected(request('status')=='cancelled')>Geannuleerd</option>
            <option value="no_show" @selected(request('status')=='no_show')>No-show</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Zoeken</button>
        @if(request()->hasAny(['zoeken','status']))
            <a href="{{ route('bookings.index') }}" class="btn btn-secondary btn-sm">Wis filters</a>
        @endif
    </form>

    {{-- Table --}}
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nummer</th>
                    <th>Klant</th>
                    <th>Event datum</th>
                    <th>Locatie</th>
                    <th>Totaal</th>
                    <th>Status</th>
                    <th>Betaling</th>
                    <th>Fotostrip</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($bookings as $boeking)
                @php
                    // Color palette from design system (PHP variables for inline styles)
                    // CSS variables don't work reliably in inline style attributes, so we use PHP arrays
                    $colors = [
                        'primary' => '#f97316',
                        'primary-600' => '#ea580c',
                        'success' => '#16a34a',
                        'danger' => '#dc2626',
                        'warning' => '#d97706',
                        'gray' => '#737373',
                    ];

                    $statusKleuren = [
                        'confirmed' => $colors['primary-600'],     // Orange for pending
                        'completed' => $colors['success'],         // Green for done
                        'cancelled' => $colors['danger'],          // Red for cancelled
                        'no_show'   => $colors['warning']          // Amber for no-show
                    ];
                    $betalingKleuren = [
                        'unpaid'   => $colors['danger'],           // Red for unpaid
                        'partial'  => $colors['warning'],          // Amber for partial
                        'paid'     => $colors['success'],          // Green for paid
                        'cancelled' => $colors['gray'],            // Gray for cancelled
                        'refunded' => $colors['primary-600']       // Orange for refunded
                    ];
                    $statusLabels = ['confirmed'=>'Bevestigd','completed'=>'Voltooid','cancelled'=>'Geannuleerd','no_show'=>'No-show'];
                    $betalingLabels = ['unpaid'=>'Niet betaald','partial'=>'Gedeeltelijk','paid'=>'Betaald','cancelled'=>'Geannuleerd','refunded'=>'Terugbetaald'];

                    $stripKleuren = [
                        'waiting_input' => ['bg'=>'#fef3c7','text'=>'#92400e'],
                        'designing'     => ['bg'=>'#dbeafe','text'=>'#1e40af'],
                        'review'        => ['bg'=>'#f3e8ff','text'=>'#6b21a8'],
                        'accepted'      => ['bg'=>'#dcfce7','text'=>'#15803d'],
                        'ready'         => ['bg'=>'#d1fae5','text'=>'#065f46'],
                    ];
                    $stripLabels = [
                        'waiting_input' => '⏳ Input aanleveren',
                        'designing'     => '🎨 Ontwerpen',
                        'review'        => '👀 Wachten op goedkeuring',
                        'accepted'      => '✅ Goedgekeurd',
                        'ready'         => '🎉 Ontwerp staat klaar',
                    ];
                    $currentStrip = $boeking->strip_status ?? null;
                @endphp
                <tr>
                    <td class="text-xs text-muted">{{ $boeking->booking_number }}</td>
                    <td><strong>{{ $boeking->customer_name }}</strong></td>
                    <td>{{ $boeking->event_date->format('d M Y') }}</td>
                    <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $boeking->event_location }}</td>
                    <td>€ {{ number_format($boeking->total_price, 2, ',', '.') }}</td>
                    <td>
                        <span class="badge" style="background:{{ $statusKleuren[$boeking->status] ?? 'var(--gray-500)' }}20;color:{{ $statusKleuren[$boeking->status] ?? 'var(--gray-500)' }};">
                            {{ $statusLabels[$boeking->status] ?? $boeking->status }}
                        </span>
                    </td>
                    <td>
                        <span class="badge" style="background:{{ $betalingKleuren[$boeking->payment_status] ?? 'var(--gray-500)' }}20;color:{{ $betalingKleuren[$boeking->payment_status] ?? 'var(--gray-500)' }};">
                            {{ $betalingLabels[$boeking->payment_status] ?? $boeking->payment_status }}
                        </span>
                    </td>
                    <td>
                        @if($currentStrip)
                        @php $sk = $stripKleuren[$currentStrip] ?? ['bg'=>'#f1f5f9','text'=>'#475569']; @endphp
                        <select class="strip-status-select"
                                data-id="{{ $boeking->id }}"
                                data-url="{{ route('bookings.strip-status', $boeking) }}"
                                style="font-size:.75rem;padding:.25rem .5rem;border:1px solid {{ $sk['bg'] }};border-radius:6px;background:{{ $sk['bg'] }};color:{{ $sk['text'] }};cursor:pointer;font-weight:600;max-width:160px;">
                            @foreach($stripLabels as $val => $lbl)
                            <option value="{{ $val }}" @selected($currentStrip === $val)>{{ $lbl }}</option>
                            @endforeach
                        </select>
                        @else
                        <span style="font-size:.75rem;color:#94a3b8;">—</span>
                        @endif
                    </td>
                    <td style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                        <a href="{{ route('bookings.show', $boeking) }}" class="btn btn-sm btn-secondary">Bekijk</a>
                        <button type="button" onclick="document.getElementById('modal-design-{{ $boeking->id }}').style.display='flex'" class="btn btn-sm btn-secondary" title="{{ $boeking->strip_design_url ? 'Ontwerp aanpassen' : 'Ontwerp toevoegen' }}" style="{{ $boeking->strip_design_url ? 'color:#f97316;' : '' }}">🎨</button>
                        @if($boeking->account->eboekhouden_enabled)
                            @if($boeking->eboekhouden_invoice_id || $boeking->eboekhouden_invoice_number)
                                <span class="btn btn-sm badge-success" style="cursor:default;border:none;" title="Factuur: {{ $boeking->eboekhouden_invoice_number }} aangemaakt op {{ $boeking->eboekhouden_synced_at->format('d-m-Y H:i') }}">
                                    ✅ {{ $boeking->eboekhouden_invoice_number }}
                                </span>
                            @else
                                <form method="POST" action="{{ route('bookings.create-invoice', $boeking) }}" style="display:inline;" onsubmit="return confirm('Factuur aanmaken in e-boekhouden?');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        📄 Factuur
                                    </button>
                                </form>
                            @endif
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align:center;padding:2rem;color:var(--gray-400);">Geen boekingen gevonden.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div style="padding: 1.25rem; border-top: 1px solid var(--gray-100);">
        {{ $bookings->links() }}
    </div>
</div>
@endsection
