@extends('layouts.app')
@section('title', 'Leads')

@section('actions')
    <a href="{{ route('leads.create') }}" class="btn btn-primary">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Nieuwe lead
    </a>
@endsection

@section('content')

{{-- Tabs --}}
<div style="display:flex;gap:.25rem;margin-bottom:1.25rem;border-bottom:2px solid #e2e8f0;">
    <a href="{{ route('leads.index', array_merge(request()->except('tab','page'), ['tab'=>'actief'])) }}"
       style="padding:.6rem 1.25rem;font-size:.875rem;font-weight:500;text-decoration:none;border-bottom:2px solid {{ $tab === 'actief' ? '#fcd34d' : 'transparent' }};margin-bottom:-2px;color:{{ $tab === 'actief' ? '#fcd34d' : '#64748b' }};">
        Actieve leads
        <span style="background:#e2e8f0;color:#475569;border-radius:9999px;padding:.1rem .5rem;font-size:.75rem;margin-left:.4rem;">{{ $aantalActief }}</span>
    </a>
    <a href="{{ route('leads.index', array_merge(request()->except('tab','page'), ['tab'=>'archief'])) }}"
       style="padding:.6rem 1.25rem;font-size:.875rem;font-weight:500;text-decoration:none;border-bottom:2px solid {{ $tab === 'archief' ? '#fcd34d' : 'transparent' }};margin-bottom:-2px;color:{{ $tab === 'archief' ? '#fcd34d' : '#64748b' }};">
        Archief
        <span style="background:#e2e8f0;color:#475569;border-radius:9999px;padding:.1rem .5rem;font-size:.75rem;margin-left:.4rem;">{{ $aantalArchief }}</span>
    </a>
</div>

<div class="card">
    {{-- Filters --}}
    <form method="GET" style="display:flex;gap:.75rem;margin-bottom:1rem;flex-wrap:wrap;">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <input type="text" name="zoeken" value="{{ request('zoeken') }}" placeholder="Zoeken op naam, e-mail, nummer..." style="flex:1;min-width:200px;">
        @if($tab === 'actief')
        <select name="status" style="min-width:160px;">
            <option value="">Alle statussen</option>
            @foreach($statussen as $status)
                <option value="{{ $status->id }}" @selected(request('status') == $status->id)>{{ $status->display_name }}</option>
            @endforeach
        </select>
        @endif
        <button type="submit" class="btn btn-secondary">Zoeken</button>
        @if(request()->hasAny(['zoeken','status']))
            <a href="{{ route('leads.index', ['tab' => $tab]) }}" class="btn btn-secondary">Wis filters</a>
        @endif
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nummer</th>
                    <th>Naam</th>
                    <th>E-mail</th>
                    <th>Event datum</th>
                    <th>Status</th>
                    @if($tab === 'archief')
                    <th>Reden</th>
                    <th>Gearchiveerd</th>
                    @else
                    <th>Aangemaakt</th>
                    @endif
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($leads as $lead)
                <tr>
                    <td style="font-size:.8rem;color:#64748b;">{{ $lead->lead_number }}</td>
                    <td><strong>{{ $lead->name }}</strong></td>
                    <td>{{ $lead->email ?? '—' }}</td>
                    <td>{{ $lead->event_date?->format('d M Y') ?? '—' }}</td>
                    <td>
                        @if($lead->status)
                            <span class="badge" style="background:{{ $lead->status->color }}20;color:{{ $lead->status->color }};">
                                {{ $lead->status->display_name }}
                            </span>
                        @endif
                    </td>
                    @if($tab === 'archief')
                    <td>
                        @if($lead->archive_reason === 'won')
                            <span class="badge" style="background:#dcfce7;color:#16a34a;">Gewonnen</span>
                        @elseif($lead->archive_reason === 'lost')
                            <span class="badge" style="background:#fee2e2;color:#dc2626;">Afgewezen</span>
                        @else
                            —
                        @endif
                    </td>
                    <td style="font-size:.8rem;color:#64748b;">{{ $lead->archived_at?->format('d-m-Y') }}</td>
                    @else
                    <td style="font-size:.8rem;color:#64748b;">{{ $lead->created_at->format('d-m-Y') }}</td>
                    @endif
                    <td>
                        <a href="{{ route('leads.show', $lead) }}" class="btn btn-sm btn-secondary">Bekijk</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align:center;padding:2rem;color:#64748b;">
                        {{ $tab === 'archief' ? 'Geen gearchiveerde leads.' : 'Geen actieve leads.' }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $leads->links() }}
</div>
@endsection
