@extends('layouts.app')

@section('title', 'Medewerkers')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem;">
    <h2 style="margin:0;font-size:1.25rem;font-weight:700;color:#1e293b;">👥 Medewerkers</h2>
    <a href="{{ route('staff.create') }}" class="btn btn-primary">+ Toevoegen</a>
</div>

@if(session('success'))
<div class="alert alert-success" style="margin-bottom:1rem;">{{ session('success') }}</div>
@endif

@if($staff->isEmpty())
<div class="card neu-card" style="text-align:center;padding:3rem 1.5rem;">
    <p style="font-size:2rem;margin-bottom:.75rem;">👤</p>
    <p style="font-weight:600;color:#1e293b;margin-bottom:.5rem;">Nog geen medewerkers</p>
    <p style="color:#64748b;font-size:.9rem;margin-bottom:1.25rem;">Voeg medewerkers toe die je kunt koppelen aan bezorgingen en ophaalmomenten.</p>
    <a href="{{ route('staff.create') }}" class="btn btn-primary" style="display:inline-flex;">+ Medewerker toevoegen</a>
</div>
@else
<div class="card neu-card" style="padding:0;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
        <thead>
            <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                <th style="padding:.75rem 1rem;text-align:left;font-weight:600;color:#374151;">Naam</th>
                <th style="padding:.75rem 1rem;text-align:left;font-weight:600;color:#374151;">Telefoon</th>
                <th style="padding:.75rem 1rem;text-align:left;font-weight:600;color:#374151;">E-mail</th>
                <th style="padding:.75rem 1rem;text-align:left;font-weight:600;color:#374151;">Status</th>
                <th style="padding:.75rem 1rem;text-align:right;font-weight:600;color:#374151;"></th>
            </tr>
        </thead>
        <tbody>
            @foreach($staff as $person)
            <tr style="border-bottom:1px solid #f1f5f9;{{ !$person->is_active ? 'opacity:.55;' : '' }}">
                <td style="padding:.75rem 1rem;font-weight:600;color:#1e293b;">{{ $person->name }}</td>
                <td style="padding:.75rem 1rem;color:#374151;">
                    @if($person->phone)
                        <a href="tel:{{ $person->phone }}" style="color:inherit;text-decoration:none;">{{ $person->phone }}</a>
                    @else
                        <span style="color:#94a3b8;">—</span>
                    @endif
                </td>
                <td style="padding:.75rem 1rem;color:#374151;">
                    @if($person->email)
                        <a href="mailto:{{ $person->email }}" style="color:#3b82f6;text-decoration:none;">{{ $person->email }}</a>
                    @else
                        <span style="color:#94a3b8;">—</span>
                    @endif
                </td>
                <td style="padding:.75rem 1rem;">
                    @if($person->is_active)
                        <span style="display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .6rem;background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;border-radius:999px;font-size:.75rem;font-weight:600;">● Actief</span>
                    @else
                        <span style="display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .6rem;background:#f1f5f9;color:#94a3b8;border:1px solid #e2e8f0;border-radius:999px;font-size:.75rem;font-weight:600;">● Inactief</span>
                    @endif
                </td>
                <td style="padding:.75rem 1rem;text-align:right;white-space:nowrap;">
                    @if($person->public_token)
                    <a href="{{ route('staff.portal', $person->public_token) }}" target="_blank"
                       class="btn btn-sm btn-secondary" title="Planningslink openen">🔗 Link</a>
                    <button type="button" class="btn btn-sm btn-secondary"
                            onclick="copyLink('{{ route('staff.portal', $person->public_token) }}', this)"
                            title="Link kopiëren">📋 Kopieer</button>
                    @endif
                    <a href="{{ route('staff.edit', $person) }}" class="btn btn-sm btn-secondary">Bewerken</a>
                    <form method="POST" action="{{ route('staff.destroy', $person) }}" style="display:inline;"
                          data-confirm="Medewerker verwijderen?">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm" style="background:#fee2e2;color:#dc2626;border:1px solid #fecaca;">Verwijder</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@push('scripts')
<script>
function copyLink(url, btn) {
    navigator.clipboard.writeText(url).then(() => {
        const orig = btn.textContent;
        btn.textContent = '✅ Gekopieerd!';
        setTimeout(() => btn.textContent = orig, 2000);
    });
}
</script>
@endpush
@endsection
