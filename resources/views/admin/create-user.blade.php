@extends('layouts.app')
@section('title', 'Nieuwe gebruiker')

@section('content')
<div class="card" style="max-width:520px;">
    @if($errors->any())
        <div class="alert alert-danger" style="margin-bottom:1rem;">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.users.store') }}">
        @csrf

        @if($accounts->count() > 1)
        <div class="form-group">
            <label>Account *</label>
            <select name="account_id" required>
                @foreach($accounts as $account)
                <option value="{{ $account->id }}" @selected(old('account_id') == $account->id)>{{ $account->name }}</option>
                @endforeach
            </select>
        </div>
        @else
        <input type="hidden" name="account_id" value="{{ $accounts->first()->id }}">
        @endif

        <div class="form-grid">
            <div class="form-group">
                <label>Voornaam *</label>
                <input type="text" name="first_name" value="{{ old('first_name') }}" required>
            </div>
            <div class="form-group">
                <label>Achternaam *</label>
                <input type="text" name="last_name" value="{{ old('last_name') }}" required>
            </div>
        </div>

        <div class="form-group">
            <label>E-mailadres *</label>
            <input type="email" name="email" value="{{ old('email') }}" required>
        </div>

        <div class="form-group">
            <label>Rol *</label>
            <select name="role">
                <option value="employee" @selected(old('role') === 'employee')>Medewerker</option>
                <option value="manager"  @selected(old('role') === 'manager')>Manager</option>
                <option value="admin"    @selected(old('role') === 'admin')>Admin</option>
            </select>
        </div>

        <div class="form-group">
            <label>Wachtwoord *</label>
            <input type="password" name="password" autocomplete="new-password" required>
        </div>
        <div class="form-group">
            <label>Herhaal wachtwoord *</label>
            <input type="password" name="password_confirmation" autocomplete="new-password" required>
        </div>

        <div style="display:flex;gap:.75rem;margin-top:1.5rem;">
            <button type="submit" class="btn btn-primary">Aanmaken</button>
            <a href="{{ route('admin.users') }}" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>
@endsection
