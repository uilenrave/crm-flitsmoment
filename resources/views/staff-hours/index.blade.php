@extends('layouts.app')

@section('title', 'Medewerker uren')

@section('content')
<style>
    .sh-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
    .sh-table th { padding: .6rem 1rem; text-align: left; font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #64748b; background: #f8fafc; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
    .sh-table td { padding: .7rem 1rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .sh-table tr:last-child td { border-bottom: none; }
    .sh-table tr:hover td { background: #fafafa; }

    .status-badge { display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .6rem; border-radius: 999px; font-size: .7rem; font-weight: 700; }
    .badge-pending  { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
    .badge-approved { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
    .badge-paid     { background: #ede9fe; color: #5b21b6; border: 1px solid #c4b5fd; }
    .badge-combined { background: #f5f3ff; color: #6d28d9; border: 1px solid #ddd6fe; }

    .filter-bar { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; margin-bottom: 1rem; }
    .filter-btn { padding: .35rem .85rem; border: 1px solid #e2e8f0; border-radius: .375rem; font-size: .8rem; background: #fff; text-decoration: none; font-weight: 500; color: #374151; transition: all .15s; }
    .filter-btn.active { background: #7c3aed; color: #fff; border-color: #7c3aed; }
    .filter-btn:hover:not(.active) { background: #f8fafc; }

    .hours-num { font-size: 1rem; font-weight: 700; color: #1e293b; }
    .hours-sub { font-size: .75rem; color: #64748b; }
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;flex-wrap:wrap;gap:.75rem;">
    <h2 style="margin:0;font-size:1.25rem;font-weight:700;color:#1e293b;">⏱ Medewerker uren</h2>
</div>

@if($counts['pending'] > 0)
<div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:.75rem;padding:.75rem 1rem;margin-bottom:1rem;display:flex;align-items:center;gap:.65rem;">
    <span style="font-size:1.1rem;">⏳</span>
    <span style="font-size:.875rem;font-weight:600;color:#92400e;">{{ $counts['pending'] }} {{ $counts['pending'] === 1 ? 'inzending wacht' : 'inzendingen wachten' }} op goedkeuring</span>
</div>
@endif

<div class="filter-bar">
    <span style="font-size:.8rem;color:#64748b;font-weight:600;">Toon:</span>
    <a href="{{ route('staff-hours.index') }}" class="filter-btn {{ $status === 'all' ? 'active' : '' }}">
        Alles ({{ $counts['pending'] + $counts['approved'] + $counts['paid'] }})
    </a>
    <a href="{{ route('staff-hours.index', ['status' => 'pending']) }}" class="filter-btn {{ $status === 'pending' ? 'active' : '' }}">
        ⏳ Wacht op goedkeuring ({{ $counts['pending'] }})
    </a>
    <a href="{{ route('staff-hours.index', ['status' => 'approved']) }}" class="filter-btn {{ $status === 'approved' ? 'active' : '' }}">
        ✅ Goedgekeurd ({{ $counts['approved'] }})
    </a>
    <a href="{{ route('staff-hours.index', ['status' => 'paid']) }}" class="filter-btn {{ $status === 'paid' ? 'active' : '' }}">
        💸 Uitbetaald ({{ $counts['paid'] }})
    </a>
    @if($counts['combined'] > 0)
    <a href="{{ route('staff-hours.index', ['status' => 'combined']) }}" class="filter-btn {{ $status === 'combined' ? 'active' : '' }}">
        🔗 Combinatie ({{ $counts['combined'] }})
    </a>
    @endif
</div>

@if($entries->isEmpty())
<div class="card neu-card" style="text-align:center;padding:2.5rem 1.5rem;">
    <p style="font-size:1.5rem;margin-bottom:.5rem;">📭</p>
    <p style="font-weight:600;margin-bottom:.5rem;">Geen ureninzendingen</p>
    <p style="color:#64748b;font-size:.875rem;">Medewerkers kunnen via hun portaal uren indienen.</p>
</div>
@else
<div class="card" style="padding:0;overflow:hidden;">
    <table class="sh-table">
        <thead>
            <tr>
                <th>Medewerker</th>
                <th>Boeking</th>
                <th>Rol</th>
                <th>Uren ingediend</th>
                <th>Uren goedgekeurd</th>
                <th>Privé km</th>
                <th>Status</th>
                <th>Ingediend op</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($entries as $entry)
            <tr>
                <td>
                    <div style="font-weight:600;color:#1e293b;">{{ $entry->staff->name }}</div>
                </td>
                <td>
                    <a href="{{ route('bookings.show', $entry->booking_id) }}" style="color:#3b82f6;font-weight:600;font-size:.85rem;text-decoration:none;">
                        {{ $entry->booking->booking_number ?? '—' }}
                    </a>
                    <div style="font-size:.75rem;color:#64748b;">{{ $entry->booking->customer_name ?? '' }}</div>
                </td>
                <td>
                    @if($entry->role === 'delivery')
                        <span style="font-size:.75rem;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;padding:.15rem .5rem;border-radius:999px;font-weight:700;">⬇ Bezorging</span>
                    @elseif($entry->role === 'handover')
                        <span style="font-size:.75rem;background:#fef3c7;color:#92400e;border:1px solid #fcd34d;padding:.15rem .5rem;border-radius:999px;font-weight:700;">🤝 Afgifte</span>
                    @else
                        <span style="font-size:.75rem;background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;padding:.15rem .5rem;border-radius:999px;font-weight:700;">⬆ Ophaling</span>
                    @endif
                </td>
                <td>
                    @if($entry->status === 'combined')
                        <span style="color:#94a3b8;font-size:.8rem;">—</span>
                    @else
                        <span class="hours-num">{{ number_format($entry->hours, 2, ',', '') }}</span>
                        <span class="hours-sub"> uur</span>
                    @endif
                </td>
                <td>
                    @if($entry->status === 'combined')
                        @if($entry->combinedWithBooking)
                            <a href="{{ route('bookings.show', $entry->combinedWithBooking) }}"
                               style="font-size:.78rem;color:#6d28d9;font-weight:600;text-decoration:none;">
                                ↗ {{ $entry->combinedWithBooking->booking_number }}
                            </a>
                            <div style="font-size:.7rem;color:#94a3b8;">{{ $entry->combinedWithRoleLabel() ?: 'gecombineerd' }}</div>
                        @else
                            <span style="color:#94a3b8;font-size:.8rem;">—</span>
                        @endif
                    @elseif($entry->hours_approved !== null)
                        <span class="hours-num">{{ number_format($entry->hours_approved, 2, ',', '') }}</span>
                        <span class="hours-sub"> uur</span>
                    @else
                        <span style="color:#94a3b8;font-size:.8rem;">—</span>
                    @endif
                </td>
                <td>
                    @if($entry->km > 0)
                        <span style="font-weight:600;color:#1e293b;">{{ number_format($entry->km, 0, ',', '') }} km</span>
                        <div style="font-size:.75rem;color:#64748b;">€ {{ number_format($entry->km_allowance, 2, ',', '') }}</div>
                    @else
                        <span style="color:#94a3b8;font-size:.8rem;">—</span>
                    @endif
                </td>
                <td>
                    @if($entry->status === 'pending')
                        <span class="status-badge badge-pending">⏳ Wacht</span>
                    @elseif($entry->status === 'approved')
                        <span class="status-badge badge-approved">✅ Goedgekeurd</span>
                    @elseif($entry->status === 'combined')
                        <span class="status-badge badge-combined">🔗 Combinatie</span>
                    @else
                        <span class="status-badge badge-paid">💸 Uitbetaald</span>
                    @endif
                </td>
                <td style="font-size:.8rem;color:#64748b;white-space:nowrap;">
                    {{ $entry->submitted_at?->format('d M Y, H:i') ?? '—' }}
                </td>
                <td>
                    <div style="display:flex;gap:.4rem;align-items:center;">
                        @if($entry->status === 'pending')
                            <a href="{{ route('staff-hours.show', $entry) }}" class="btn btn-primary btn-sm">Beoordelen</a>
                        @elseif($entry->status === 'approved')
                            <a href="{{ route('staff-hours.show', $entry) }}" class="btn btn-secondary btn-sm">Bekijken</a>
                            <form method="POST" action="{{ route('staff-hours.mark-paid', $entry) }}" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn btn-sm" style="background:#ede9fe;color:#5b21b6;border:none;cursor:pointer;border-radius:.75rem;padding:.3rem .65rem;font-size:.8rem;font-weight:500;"
                                        onclick="crmConfirm('Markeren als uitbetaald?', () => this.closest('form').submit()); return false;">
                                    💸 Uitbetaald
                                </button>
                            </form>
                        @elseif($entry->status === 'combined')
                            @if($entry->combinedWithBooking)
                                <a href="{{ route('bookings.show', $entry->combinedWithBooking) }}" class="btn btn-secondary btn-sm">Naar boeking →</a>
                            @endif
                        @else
                            <a href="{{ route('staff-hours.show', $entry) }}" class="btn btn-secondary btn-sm">Bekijken</a>
                        @endif
                        <button type="button"
                            onclick="openDeleteModal({{ $entry->id }}, '{{ addslashes($entry->staff->name) }}', '{{ $entry->booking->booking_number ?? '?' }}')"
                            style="background:none;border:none;cursor:pointer;color:#94a3b8;padding:.25rem .35rem;border-radius:.375rem;line-height:1;font-size:1rem;"
                            title="Verwijderen">🗑</button>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

@php
    $totalPending    = $entries->where('status', 'pending')->sum('hours');
    $totalApproved   = $entries->where('status', 'approved')->sum(fn($e) => $e->effective_hours);
    $totalPaid       = $entries->where('status', 'paid')->sum(fn($e) => $e->effective_hours);
    $totalKmAllow    = $entries->sum(fn($e) => $e->km_allowance);
@endphp
<div style="display:flex;gap:1rem;margin-top:1rem;flex-wrap:wrap;">
    <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:.75rem;padding:.75rem 1.25rem;">
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:#92400e;letter-spacing:.04em;">Openstaand</div>
        <div style="font-size:1.5rem;font-weight:700;color:#92400e;">{{ number_format($totalPending, 1, ',', '') }} uur</div>
    </div>
    <div style="background:#dcfce7;border:1px solid #86efac;border-radius:.75rem;padding:.75rem 1.25rem;">
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:#166534;letter-spacing:.04em;">Goedgekeurd</div>
        <div style="font-size:1.5rem;font-weight:700;color:#166534;">{{ number_format($totalApproved, 1, ',', '') }} uur</div>
    </div>
    <div style="background:#ede9fe;border:1px solid #c4b5fd;border-radius:.75rem;padding:.75rem 1.25rem;">
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:#5b21b6;letter-spacing:.04em;">Uitbetaald</div>
        <div style="font-size:1.5rem;font-weight:700;color:#5b21b6;">{{ number_format($totalPaid, 1, ',', '') }} uur</div>
    </div>
    @if($totalKmAllow > 0)
    <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:.75rem;padding:.75rem 1.25rem;">
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:#0369a1;letter-spacing:.04em;">Km-vergoeding</div>
        <div style="font-size:1.5rem;font-weight:700;color:#0369a1;">€ {{ number_format($totalKmAllow, 2, ',', '') }}</div>
    </div>
    @endif
</div>
@endif

{{-- Verwijder modal --}}
<div id="delete-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:1rem;padding:2rem;max-width:400px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <div style="font-size:1.5rem;margin-bottom:.75rem;">🗑</div>
        <h3 style="font-size:1rem;font-weight:700;margin-bottom:.5rem;color:#1e293b;">Urenverzoek verwijderen?</h3>
        <p style="font-size:.875rem;color:#64748b;margin-bottom:1.5rem;" id="delete-modal-text"></p>
        <div style="display:flex;gap:.75rem;justify-content:flex-end;">
            <button type="button" onclick="closeDeleteModal()"
                style="padding:.55rem 1.1rem;border:1px solid #e2e8f0;border-radius:.5rem;background:#fff;font-size:.875rem;font-weight:500;cursor:pointer;color:#374151;">
                Annuleren
            </button>
            <form id="delete-modal-form" method="POST" style="display:inline;">
                @csrf
                @method('DELETE')
                <button type="submit"
                    style="padding:.55rem 1.1rem;border:none;border-radius:.5rem;background:#dc2626;color:#fff;font-size:.875rem;font-weight:600;cursor:pointer;">
                    Definitief verwijderen
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function openDeleteModal(id, naam, boeking) {
    document.getElementById('delete-modal-text').textContent =
        'Je staat op het punt het urenverzoek van ' + naam + ' (boeking ' + boeking + ') te verwijderen. Dit kan niet ongedaan worden gemaakt.';
    document.getElementById('delete-modal-form').action = '/staff-uren/' + id;
    var modal = document.getElementById('delete-modal');
    modal.style.display = 'flex';
}
function closeDeleteModal() {
    document.getElementById('delete-modal').style.display = 'none';
}
document.getElementById('delete-modal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});
</script>

@endsection
