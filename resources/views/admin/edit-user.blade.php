@extends('layouts.app')
@section('title', 'Gebruiker bewerken')

@section('content')
<div class="card" style="max-width:520px;">
    @if($errors->any())
        <div class="alert alert-danger" style="margin-bottom:1rem;">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.users.update', $user) }}">
        @csrf @method('PUT')

        <div class="form-grid">
            <div class="form-group">
                <label>Voornaam *</label>
                <input type="text" name="first_name" value="{{ old('first_name', $user->first_name) }}" required>
            </div>
            <div class="form-group">
                <label>Achternaam *</label>
                <input type="text" name="last_name" value="{{ old('last_name', $user->last_name) }}" required>
            </div>
        </div>

        <div class="form-group">
            <label>E-mailadres *</label>
            <input type="email" name="email" value="{{ old('email', $user->email) }}" required>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label>Rol *</label>
                <select name="role">
                    <option value="admin"    @selected(old('role', $user->role) === 'admin')>Admin</option>
                    <option value="manager"  @selected(old('role', $user->role) === 'manager')>Manager</option>
                    <option value="employee" @selected(old('role', $user->role) === 'employee')>Medewerker</option>
                </select>
            </div>
            <div class="form-group">
                <label>Status *</label>
                <select name="status">
                    <option value="active"   @selected(old('status', $user->status) === 'active')>Actief</option>
                    <option value="inactive" @selected(old('status', $user->status) === 'inactive')>Inactief</option>
                </select>
            </div>
        </div>

        <hr style="margin:1.25rem 0;border:none;border-top:1px solid #e2e8f0;">
        <p style="font-size:.8rem;color:#64748b;margin-bottom:.75rem;">Laat leeg om het wachtwoord ongewijzigd te laten.</p>

        <div class="form-group">
            <label>Nieuw wachtwoord</label>
            <input type="password" name="password" autocomplete="new-password">
        </div>
        <div class="form-group">
            <label>Herhaal wachtwoord</label>
            <input type="password" name="password_confirmation" autocomplete="new-password">
        </div>

        <div style="display:flex;gap:.75rem;margin-top:1.5rem;">
            <button type="submit" class="btn btn-primary">Opslaan</button>
            <a href="{{ route('admin.users') }}" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>
@endsection
