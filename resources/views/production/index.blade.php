@extends('layouts.app')

@section('title', 'Productie-bestanden')

@section('content')
<style>
    .prod-link-row { display: flex; align-items: center; gap: .6rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
    .prod-link-input { flex: 1; min-width: 260px; padding: .55rem .7rem; border: 1px solid #e2e8f0; border-radius: .5rem; font-size: .82rem; background: #f8fafc; color: #64748b; }
    .prod-copy-btn { font-size: .8rem; font-weight: 700; color: #7c3aed; background: #fff; border: 1px solid #ddd6fe; border-radius: .5rem; padding: .5rem .9rem; cursor: pointer; }
    .prod-copy-btn:hover { background: #f5f3ff; }
    .prod-table { width: 100%; border-collapse: collapse; }
    .prod-table th { text-align: left; font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; color: #94a3b8; padding: .5rem .75rem; border-bottom: 1px solid #e2e8f0; }
    .prod-table td { padding: .6rem .75rem; border-bottom: 1px solid #f1f5f9; font-size: .85rem; vertical-align: middle; }
    .prod-status { font-size: .7rem; font-weight: 700; padding: .15rem .55rem; border-radius: 999px; }
    .prod-status-ready { background: #dcfce7; color: #15803d; }
    .prod-status-other { background: #f1f5f9; color: #64748b; }
    .prod-upload-form { display: flex; align-items: center; gap: .5rem; }
    .prod-upload-form input[type=file] { font-size: .78rem; max-width: 180px; }
    .prod-upload-btn { font-size: .78rem; font-weight: 700; color: #fff; background: #7c3aed; border: none; border-radius: .4rem; padding: .4rem .7rem; cursor: pointer; }
    .prod-download-link { font-size: .78rem; color: #7c3aed; font-weight: 600; }
    .prod-delete-btn { font-size: .78rem; color: #b91c1c; background: none; border: none; cursor: pointer; }
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem;">
    <h2 style="margin:0;font-size:1.25rem;font-weight:700;color:#1e293b;">📥 Productie-bestanden</h2>
    <span style="font-size:.8rem;color:#64748b;">Vervangt Google Drive voor de photobooths</span>
</div>

<div class="card neu-card" style="padding:1rem 1.25rem;margin-bottom:1.25rem;">
    <label style="display:block;font-size:.8rem;font-weight:700;color:#334155;margin-bottom:.4rem;">Publieke productielink (geen login nodig)</label>
    <div class="prod-link-row">
        <input id="prod-link-input" class="prod-link-input" type="text" readonly value="{{ route('production.board', $account->production_token) }}">
        <button type="button" class="prod-copy-btn" onclick="navigator.clipboard.writeText(document.getElementById('prod-link-input').value); this.textContent='✓ Gekopieerd';setTimeout(()=>this.textContent='📋 Kopiëren',1500);">📋 Kopiëren</button>
    </div>
</div>

<div class="card neu-card" style="padding:0;overflow:hidden;">
    <table class="prod-table">
        <thead>
            <tr>
                <th>Event</th>
                <th>Datum</th>
                <th>Status</th>
                <th>Productiebestand</th>
            </tr>
        </thead>
        <tbody>
            @forelse($bookings as $booking)
            <tr>
                <td>
                    <div style="font-weight:600;">{{ $booking->customer_name }}</div>
                    <div style="font-size:.75rem;color:#94a3b8;">{{ $booking->booking_number }}</div>
                </td>
                <td>{{ $booking->event_date?->format('d-m-Y') }}</td>
                <td>
                    <span class="prod-status {{ $booking->strip_status === 'ready' ? 'prod-status-ready' : 'prod-status-other' }}">
                        {{ \App\Models\Booking::stripStatusLabel($booking->strip_status) ?? '—' }}
                    </span>
                </td>
                <td>
                    <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;">
                        @if($booking->production_file_path)
                            <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($booking->production_file_path) }}" target="_blank" class="prod-download-link">⬇ Bekijk huidige PNG</a>
                            <form method="POST" action="{{ route('production.destroy', $booking) }}" onsubmit="return confirm('Productiebestand verwijderen?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="prod-delete-btn">✕ Verwijderen</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('production.upload', $booking) }}" enctype="multipart/form-data" class="prod-upload-form">
                            @csrf
                            <input type="file" name="production_file" accept="image/png" required>
                            <button type="submit" class="prod-upload-btn">{{ $booking->production_file_path ? 'Vervangen' : 'Uploaden' }}</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="4" style="text-align:center;color:#94a3b8;padding:2rem;">Geen aankomende events.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
