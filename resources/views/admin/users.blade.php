@extends('layouts.app')
@section('title', 'Gebruikersbeheer')

@section('actions')
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Nieuwe gebruiker
    </a>
@endsection

@section('content')
<div class="card">
    @if(session('success'))
        <div class="alert alert-success" style="margin-bottom:1rem;">{{ session('success') }}</div>
    @endif

    @php
        $gegroepeerd = $users->groupBy('account_id');
        $toonAccount = auth()->user()->account->type === 'headquarters' && $gegroepeerd->count() > 1;
    @endphp

    @foreach($gegroepeerd as $accountId => $groep)
        @if($toonAccount)
        <h3 style="font-size:.8rem;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.75rem;{{ !$loop->first ? 'margin-top:2rem;' : '' }}">
            {{ $groep->first()->account->name }}
        </h3>
        @endif
        <table>
            <thead>
                <tr>
                    <th>Naam</th>
                    <th>E-mail</th>
                    <th>Rol</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($groep as $user)
                <tr>
                    <td><strong>{{ $user->first_name }} {{ $user->last_name }}</strong></td>
                    <td>{{ $user->email }}</td>
                    <td>
                        @php $rolKleur = ['admin'=>'#dbeafe;color:#1d4ed8','manager'=>'#fef3c7;color:#92400e','employee'=>'#f1f5f9;color:#475569'][$user->role] ?? '#f1f5f9;color:#475569'; @endphp
                        <span class="badge" style="background:{{ $rolKleur }}">{{ ucfirst($user->role) }}</span>
                    </td>
                    <td>
                        @if($user->status === 'active')
                            <span class="badge" style="background:#dcfce7;color:#16a34a;">Actief</span>
                        @else
                            <span class="badge" style="background:#fee2e2;color:#dc2626;">Inactief</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-secondary">Bewerken</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
</div>
@endsection
