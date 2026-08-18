@extends('layouts.app')

@section('title', 'Ontwerp-generator')

@section('content')
<div style="max-width:1100px;margin:0 auto;">
    <div style="margin-bottom:1.5rem;">
        <h2 style="margin:0;font-size:1.35rem;font-weight:800;color:#1e293b;">✨ Ontwerp-generator</h2>
        <p style="font-size:.85rem;color:#64748b;margin:.35rem 0 0;">Kies de boeking waarvoor je een fotostrip-ontwerp gaat maken.</p>
    </div>

    @if($bookings->isEmpty())
        <div style="padding:2.5rem 1.5rem;text-align:center;background:#fff;border:1px solid #e2e8f0;border-radius:.9rem;color:#64748b;">
            <div style="font-size:2rem;margin-bottom:.5rem;">📭</div>
            <p style="margin:0;font-weight:600;color:#334155;">Geen aankomende bevestigde boekingen</p>
            <p style="margin:.35rem 0 0;font-size:.85rem;">Zodra er een bevestigde boeking met een toekomstige eventdatum is, verschijnt die hier.</p>
        </div>
    @else
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem;">
            @foreach($bookings as $b)
                @php($c = \App\Models\Booking::stripStatusColor($b->strip_status))
                <a href="{{ route('design.booking', $b) }}"
                   style="display:flex;flex-direction:column;gap:.6rem;padding:1.1rem 1.2rem;background:#fff;border:1px solid #e2e8f0;border-radius:.9rem;text-decoration:none;transition:box-shadow .15s,border-color .15s;"
                   onmouseover="this.style.boxShadow='0 4px 14px rgba(15,23,42,.08)';this.style.borderColor='#c7d2fe';"
                   onmouseout="this.style.boxShadow='none';this.style.borderColor='#e2e8f0';">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;">
                        <div>
                            <div style="font-weight:700;color:#1e293b;font-size:.98rem;">{{ $b->customer_name }}</div>
                            <div style="font-size:.78rem;color:#94a3b8;">{{ $b->booking_number }}</div>
                        </div>
                        @if($b->strip_status)
                            <span style="flex:0 0 auto;font-size:.7rem;font-weight:700;padding:.2rem .5rem;border-radius:999px;background:{{ $c['bg'] }};color:{{ $c['text'] }};white-space:nowrap;">
                                {{ \App\Models\Booking::stripStatusLabel($b->strip_status) }}
                            </span>
                        @endif
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:.82rem;color:#475569;">📅 {{ $b->event_date?->format('d-m-Y') }}</span>
                        <span style="font-size:.82rem;font-weight:700;color:#7c3aed;">Ontwerpen →</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
