@extends('layouts.app')
@section('title', 'Producten & voorraad')

@section('actions')
    <a href="{{ route('assets.create') }}" class="btn btn-primary">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Nieuw product
    </a>
@endsection

@section('content')
<div class="card">
    @if(session('success'))
        <div class="alert alert-success" style="margin-bottom:1rem;">{{ session('success') }}</div>
    @endif

    @php
        $catLabels = ['photobooth'=>'Photobooth','background'=>'Achtergronden','prop_box'=>'Attributenkisten','extra'=>"Extra's"];
        $gegroepeerd = $assets->getCollection()->groupBy('category');
    @endphp

    @forelse($gegroepeerd as $cat => $items)
        <div style="margin-bottom:2rem;">
            <h3 style="font-size:.8rem;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.75rem;">
                {{ $catLabels[$cat] ?? $cat }}
            </h3>
            <div class="table-wrap">
                <table style="table-layout:fixed;width:100%;">
                    <colgroup>
                        <col style="width:25%;">
                        <col style="width:10%;">
                        <col style="width:9%;">
                        <col style="width:36%;">
                        <col style="width:10%;">
                        <col style="width:10%;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Naam</th>
                            <th>Prijs</th>
                            <th style="text-align:center;">Voorraad</th>
                            <th>Beschrijving</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $asset)
                        <tr>
                            <td><strong title="{{ $asset->name }}">{{ $asset->name }}</strong></td>
                            <td>€ {{ number_format($asset->price, 2, ',', '.') }}</td>
                            <td style="text-align:center;">
                                <span style="font-weight:600;{{ $asset->stock === 0 ? 'color:#dc2626;' : ($asset->stock <= 2 ? 'color:#d97706;' : 'color:#16a34a;') }}">
                                    {{ $asset->stock }}
                                </span>
                            </td>
                            <td style="color:#64748b;" title="{{ $asset->description }}">{{ $asset->description }}</td>
                            <td>
                                @if($asset->is_active)
                                    <span class="badge" style="background:#dcfce7;color:#16a34a;">Actief</span>
                                @else
                                    <span class="badge" style="background:#f1f5f9;color:#64748b;">Inactief</span>
                                @endif
                            </td>
                            <td style="white-space:nowrap;">
                                <a href="{{ route('assets.edit', $asset) }}" class="btn btn-sm btn-secondary">Bewerken</a>
                                <form method="POST" action="{{ route('assets.destroy', $asset) }}" style="display:inline;" onsubmit="return confirm('Zeker weten?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm" style="background:#fee2e2;color:#dc2626;">Verwijder</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <p style="color:#64748b;text-align:center;padding:2rem;">
            Nog geen producten. <a href="{{ route('assets.create') }}">Voeg er een toe →</a>
        </p>
    @endforelse

    {{ $assets->links() }}
</div>
@endsection
