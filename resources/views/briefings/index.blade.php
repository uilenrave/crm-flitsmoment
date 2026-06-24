@extends('layouts.app')

@section('title', 'Briefings')

@section('content')

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;flex-wrap:wrap;gap:.75rem;">
    <div>
        <h2 style="margin:0;font-size:1.25rem;font-weight:700;color:#1e293b;">📋 Briefings</h2>
        <p style="margin:.2rem 0 0;font-size:.85rem;color:#64748b;">Per medewerker een PDF met aankomende ritten en extra instructies.</p>
    </div>
    <a href="{{ route('briefings.create') }}" class="btn btn-primary">+ Nieuwe briefing</a>
</div>

@if($briefings->isEmpty())
<div class="card neu-card" style="text-align:center;padding:2.5rem 1.5rem;">
    <p style="font-size:1.5rem;margin-bottom:.5rem;">📭</p>
    <p style="font-weight:600;margin-bottom:.5rem;">Nog geen briefings</p>
    <p style="color:#64748b;font-size:.875rem;margin-bottom:1.25rem;">Maak een briefing aan voor een medewerker.</p>
    <a href="{{ route('briefings.create') }}" class="btn btn-primary">+ Eerste briefing</a>
</div>
@else
<div class="card" style="padding:0;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
        <thead>
            <tr style="background:#f8fafc;">
                <th style="padding:.65rem 1rem;text-align:left;font-size:.7rem;font-weight:700;text-transform:uppercase;color:#64748b;border-bottom:2px solid #e2e8f0;">Titel</th>
                <th style="padding:.65rem 1rem;text-align:left;font-size:.7rem;font-weight:700;text-transform:uppercase;color:#64748b;border-bottom:2px solid #e2e8f0;">Medewerker</th>
                <th style="padding:.65rem 1rem;text-align:left;font-size:.7rem;font-weight:700;text-transform:uppercase;color:#64748b;border-bottom:2px solid #e2e8f0;">Periode</th>
                <th style="padding:.65rem 1rem;text-align:left;font-size:.7rem;font-weight:700;text-transform:uppercase;color:#64748b;border-bottom:2px solid #e2e8f0;">Laatst gegenereerd</th>
                <th style="padding:.65rem 1rem;border-bottom:2px solid #e2e8f0;"></th>
            </tr>
        </thead>
        <tbody>
            @foreach($briefings as $b)
            <tr style="border-bottom:1px solid #f1f5f9;">
                <td style="padding:.7rem 1rem;font-weight:600;color:#1e293b;">{{ $b->effective_title }}</td>
                <td style="padding:.7rem 1rem;">{{ $b->staff->name ?? '—' }}</td>
                <td style="padding:.7rem 1rem;color:#64748b;font-size:.85rem;">{{ $b->date_from->format('d M') }} – {{ $b->date_to->format('d M Y') }}</td>
                <td style="padding:.7rem 1rem;color:#64748b;font-size:.85rem;">{{ $b->generated_at?->diffForHumans() ?? '— nog niet —' }}</td>
                <td style="padding:.7rem 1rem;text-align:right;">
                    <div style="display:inline-flex;gap:.35rem;">
                        <a href="{{ route('briefings.edit', $b) }}" class="btn btn-secondary btn-sm">Bewerken</a>
                        <a href="{{ route('briefings.pdf', $b) }}" class="btn btn-primary btn-sm">📄 PDF</a>
                        <form method="POST" action="{{ route('briefings.destroy', $b) }}" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="button" onclick="crmConfirm('Briefing verwijderen?', () => this.closest('form').submit()); return false;"
                                    style="background:none;border:1px solid #fca5a5;color:#dc2626;border-radius:.4rem;padding:.3rem .55rem;cursor:pointer;font-size:.8rem;">🗑</button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

@endsection
