@extends('layouts.app')

@section('title', $staffHours->status === 'pending' ? 'Uren beoordelen' : 'Uren bekijken')

@section('content')

<div style="max-width:640px;">

<div style="margin-bottom:1.25rem;">
    <a href="{{ route('staff-hours.index') }}" style="color:#64748b;font-size:.85rem;text-decoration:none;">← Terug naar uren overzicht</a>
</div>

{{-- Info card --}}
<div class="card" style="margin-bottom:1rem;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;margin-bottom:1.25rem;">
        <div>
            <h3 style="margin:0 0 .25rem;font-size:1.1rem;font-weight:700;">{{ $staffHours->staff->name }}</h3>
            <div style="font-size:.85rem;color:#64748b;">
                Boeking
                <a href="{{ route('bookings.show', $staffHours->booking_id) }}" style="color:#3b82f6;font-weight:600;text-decoration:none;">
                    {{ $staffHours->booking->booking_number ?? '—' }}
                </a>
                — {{ $staffHours->booking->customer_name ?? '' }}
            </div>
        </div>
        @if($staffHours->status === 'pending')
            <span style="background:#fef3c7;color:#92400e;border:1px solid #fcd34d;padding:.25rem .7rem;border-radius:999px;font-size:.75rem;font-weight:700;">⏳ Wacht op goedkeuring</span>
        @elseif($staffHours->status === 'approved')
            <span style="background:#dcfce7;color:#166534;border:1px solid #86efac;padding:.25rem .7rem;border-radius:999px;font-size:.75rem;font-weight:700;">✅ Goedgekeurd</span>
        @elseif($staffHours->status === 'combined')
            <span style="background:#f5f3ff;color:#6d28d9;border:1px solid #ddd6fe;padding:.25rem .7rem;border-radius:999px;font-size:.75rem;font-weight:700;">🔗 Combinatierit</span>
        @else
            <span style="background:#ede9fe;color:#5b21b6;border:1px solid #c4b5fd;padding:.25rem .7rem;border-radius:999px;font-size:.75rem;font-weight:700;">💸 Uitbetaald</span>
        @endif
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1.25rem;">
        <div style="background:#f8fafc;border-radius:.75rem;padding:.875rem 1rem;">
            <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:#64748b;letter-spacing:.04em;margin-bottom:.3rem;">Rol</div>
            @if($staffHours->role === 'delivery')
                <span style="font-size:.8rem;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;padding:.2rem .55rem;border-radius:999px;font-weight:700;">⬇ Bezorging</span>
            @elseif($staffHours->role === 'handover')
                <span style="font-size:.8rem;background:#fef3c7;color:#92400e;border:1px solid #fcd34d;padding:.2rem .55rem;border-radius:999px;font-weight:700;">🤝 Afgifte</span>
            @else
                <span style="font-size:.8rem;background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;padding:.2rem .55rem;border-radius:999px;font-weight:700;">⬆ Ophaling</span>
            @endif
        </div>
        <div style="background:#f8fafc;border-radius:.75rem;padding:.875rem 1rem;">
            <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:#64748b;letter-spacing:.04em;margin-bottom:.3rem;">Ingediend</div>
            <div style="font-size:1.25rem;font-weight:700;color:#1e293b;">{{ number_format($staffHours->hours, 2, ',', '') }} uur</div>
        </div>
        <div style="background:#f8fafc;border-radius:.75rem;padding:.875rem 1rem;">
            <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:#64748b;letter-spacing:.04em;margin-bottom:.3rem;">Ingediend op</div>
            <div style="font-size:.85rem;color:#374151;">{{ $staffHours->submitted_at?->format('d M Y') ?? '—' }}</div>
            <div style="font-size:.75rem;color:#64748b;">{{ $staffHours->submitted_at?->format('H:i') ?? '' }}</div>
        </div>
        @if($staffHours->km > 0)
        <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:.75rem;padding:.875rem 1rem;">
            <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:#0369a1;letter-spacing:.04em;margin-bottom:.3rem;">Privé km</div>
            <div style="font-size:1.25rem;font-weight:700;color:#0369a1;">{{ number_format($staffHours->km, 0, ',', '') }} km</div>
            <div style="font-size:.8rem;color:#0369a1;">€ {{ number_format($staffHours->km_allowance, 2, ',', '') }} vergoeding</div>
        </div>
        @endif
    </div>

    @if($staffHours->staff_note)
    <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:.75rem;padding:.875rem 1rem;margin-bottom:1.25rem;">
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:#92400e;margin-bottom:.35rem;">Opmerking medewerker</div>
        <div style="font-size:.875rem;color:#374151;white-space:pre-line;">{{ $staffHours->staff_note }}</div>
    </div>
    @endif

    @if($staffHours->approved_at)
    <div style="font-size:.8rem;color:#64748b;margin-bottom:.5rem;">
        Goedgekeurd op {{ $staffHours->approved_at->format('d M Y \o\m H:i') }}
        @if($staffHours->hours_approved !== null)
        — <strong>{{ number_format($staffHours->hours_approved, 2, ',', '') }} uur</strong> vastgesteld
        @endif
    </div>
    @endif
    @if($staffHours->paid_at)
    <div style="font-size:.8rem;color:#64748b;">
        Uitbetaald op {{ $staffHours->paid_at->format('d M Y \o\m H:i') }}
    </div>
    @endif
</div>

{{-- Combinatie-rit info --}}
@if($staffHours->status === 'combined')
<div class="card" style="margin-bottom:1rem;background:#f5f3ff;border:1px solid #ddd6fe;">
    <div style="display:flex;gap:.75rem;align-items:flex-start;">
        <span style="font-size:1.5rem;">🔗</span>
        <div style="flex:1;">
            <div style="font-weight:700;font-size:.95rem;color:#5b21b6;margin-bottom:.35rem;">Combinatierit</div>
            <div style="font-size:.875rem;color:#374151;line-height:1.55;">
                De medewerker heeft deze rit samen met een andere boeking gereden en de uren <strong>al ingediend</strong> bij die andere rit. Er hoeft hier niets goedgekeurd of uitbetaald te worden.
            </div>
            @if($staffHours->combinedWithBooking)
            <div style="margin-top:.65rem;">
                <a href="{{ route('bookings.show', $staffHours->combinedWithBooking) }}"
                   style="display:inline-flex;align-items:center;gap:.35rem;background:#fff;border:1px solid #c4b5fd;color:#6d28d9;padding:.4rem .8rem;border-radius:.4rem;text-decoration:none;font-size:.85rem;font-weight:600;">
                    ↗ Naar {{ $staffHours->combinedWithRoleLabel() ?: 'rit' }} van boeking {{ $staffHours->combinedWithBooking->booking_number }}
                    @if($staffHours->combinedWithBooking->customer_name) · {{ $staffHours->combinedWithBooking->customer_name }} @endif
                </a>
            </div>
            @endif
        </div>
    </div>
</div>
@endif

{{-- Goedkeuring formulier (alleen als pending of approved) --}}
@if(in_array($staffHours->status, ['pending', 'approved']))
<div class="card">
    <h4 style="margin:0 0 1rem;font-size:.95rem;font-weight:700;">
        {{ $staffHours->status === 'pending' ? '✅ Goedkeuren' : '✏️ Uren aanpassen' }}
    </h4>

    <form method="POST" action="{{ route('staff-hours.approve', $staffHours) }}">
        @csrf
        <div class="form-group">
            <label>Uren goedkeuren *</label>
            <input type="number" name="hours_approved" step="0.25" min="0" max="24"
                   value="{{ old('hours_approved', $staffHours->hours_approved ?? $staffHours->hours) }}"
                   style="max-width:160px;"
                   required>
            @error('hours_approved')
                <div class="error-msg">{{ $message }}</div>
            @enderror
            <div style="font-size:.75rem;color:#64748b;margin-top:.25rem;">
                Medewerker heeft {{ number_format($staffHours->hours, 2, ',', '') }} uur ingediend.
                Pas aan als nodig.
            </div>
        </div>
        <div class="form-group">
            <label>Interne notitie (optioneel)</label>
            <textarea name="admin_note" rows="3" placeholder="Bijv. reistijd niet meegeteld…">{{ old('admin_note', $staffHours->admin_note) }}</textarea>
            @error('admin_note')
                <div class="error-msg">{{ $message }}</div>
            @enderror
        </div>
        <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary">
                {{ $staffHours->status === 'pending' ? '✅ Goedkeuren' : '💾 Bijwerken' }}
            </button>
            <a href="{{ route('staff-hours.index') }}" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>
@endif

@if($staffHours->status === 'approved')
<div style="margin-top:1rem;display:flex;gap:.75rem;flex-wrap:wrap;align-items:center;">
    <form method="POST" action="{{ route('staff-hours.mark-paid', $staffHours) }}">
        @csrf
        <button type="submit" class="btn" style="background:#ede9fe;color:#5b21b6;border:none;cursor:pointer;border-radius:.75rem;"
                onclick="crmConfirm('Markeren als uitbetaald aan {{ $staffHours->staff->name }}?', () => this.closest('form').submit()); return false;">
            💸 Markeren als uitbetaald
        </button>
    </form>
    <form method="POST" action="{{ route('staff-hours.revert', $staffHours) }}">
        @csrf
        <button type="submit" class="btn btn-secondary btn-sm"
                onclick="crmConfirm('Goedkeuring terugdraaien naar \'wacht op goedkeuring\'?', () => this.closest('form').submit()); return false;">
            ↩ Goedkeuring terugdraaien
        </button>
    </form>
</div>
@endif

@if($staffHours->status === 'paid')
<div style="margin-top:1rem;">
    <form method="POST" action="{{ route('staff-hours.revert', $staffHours) }}">
        @csrf
        <button type="submit" class="btn btn-secondary btn-sm"
                onclick="crmConfirm('Uitbetaling terugdraaien naar \'goedgekeurd\'?', () => this.closest('form').submit()); return false;">
            ↩ Uitbetaling terugdraaien
        </button>
    </form>
</div>
@endif

{{-- Verwijderen --}}
<div style="margin-top:2rem;padding-top:1.25rem;border-top:1px solid #f1f5f9;">
    <button type="button" onclick="document.getElementById('delete-modal').style.display='flex'"
        style="background:none;border:1px solid #fca5a5;color:#dc2626;border-radius:.5rem;padding:.4rem .9rem;font-size:.8rem;font-weight:500;cursor:pointer;">
        🗑 Verzoek verwijderen
    </button>
</div>

{{-- Verwijder modal --}}
<div id="delete-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:1rem;padding:2rem;max-width:400px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <div style="font-size:1.5rem;margin-bottom:.75rem;">🗑</div>
        <h3 style="font-size:1rem;font-weight:700;margin-bottom:.5rem;color:#1e293b;">Urenverzoek verwijderen?</h3>
        <p style="font-size:.875rem;color:#64748b;margin-bottom:1.5rem;">
            Je verwijdert het verzoek van <strong>{{ $staffHours->staff->name }}</strong>
            voor boeking <strong>{{ $staffHours->booking->booking_number ?? '?' }}</strong>.
            Dit kan niet ongedaan worden gemaakt.
        </p>
        <div style="display:flex;gap:.75rem;justify-content:flex-end;">
            <button type="button" onclick="document.getElementById('delete-modal').style.display='none'"
                style="padding:.55rem 1.1rem;border:1px solid #e2e8f0;border-radius:.5rem;background:#fff;font-size:.875rem;font-weight:500;cursor:pointer;color:#374151;">
                Annuleren
            </button>
            <form method="POST" action="{{ route('staff-hours.destroy', $staffHours) }}">
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
document.getElementById('delete-modal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

</div>
@endsection
