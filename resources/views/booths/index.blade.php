@extends('layouts.app')
@section('title', 'Booths')

@section('content')
@php $tz = 'Europe/Amsterdam'; @endphp

@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:1rem;">{{ session('success') }}</div>
@endif

@if(session('new_token'))
    <div class="card" style="margin-bottom:1rem;border:2px solid #d97706;">
        <strong>Bewaar dit token nu — het wordt maar één keer getoond.</strong>
        <p style="margin:.5rem 0;color:#64748b;font-size:.85rem;">Vul het in bij de installatie van de booth-app op deze machine.</p>
        <input type="text" readonly value="{{ session('new_token') }}" onclick="this.select()" style="width:100%;font-family:monospace;">
    </div>
@endif

@if($errors->any())
    <div class="alert" style="margin-bottom:1rem;background:#fee2e2;color:#991b1b;">{{ $errors->first() }}</div>
@endif

<div class="card" style="margin-bottom:1rem;">
    @if($rows->isEmpty())
        <p style="color:#64748b;margin:0;">Nog geen booths gekoppeld. Koppel hieronder de eerste.</p>
    @else
    <table>
        <thead>
            <tr>
                <th>Booth</th>
                <th>Versie</th>
                <th>Laatst gezien</th>
                <th>Zelftest</th>
                <th>Papier over</th>
                <th>Updaten</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        @foreach($rows as $row)
            @php
                $b      = $row['booth'];
                $status = is_array($b->last_status) ? $b->last_status : [];
                $failed = collect($status['selftest'] ?? [])->filter(fn ($ok) => ! $ok)->keys()->implode(', ');
            @endphp
            <tr>
                <td>
                    <strong>{{ $b->displayName() }}</strong>
                    <div style="font-size:.78rem;color:#64748b;">
                        {{ $channels[$b->channel()] ?? $b->channel() }}@if($b->pinned_version) · vast op {{ $b->pinned_version }}@endif
                        @if($b->unit_number === null) · <span style="color:#b45309;">niet gekoppeld aan een unit</span>@endif
                    </div>
                </td>
                <td>
                    {{ $b->app_version ?? '—' }}
                    @if($row['behind'])
                        <div><span class="badge" style="background:#fef3c7;color:#92400e;">achter · nieuwste {{ $latest[$b->channel()] ?? '?' }}</span></div>
                    @endif
                </td>
                <td>{{ $b->last_heartbeat_at ? $b->last_heartbeat_at->setTimezone($tz)->format('d-m H:i') : 'nooit' }}</td>
                <td>
                    @if($b->selftest_ok === true)
                        <span class="badge" style="background:#dcfce7;color:#16a34a;">In orde</span>
                    @elseif($b->selftest_ok === false)
                        <span class="badge" style="background:#fee2e2;color:#dc2626;">Gefaald{{ $failed ? ': ' . $failed : '' }}</span>
                    @else
                        —
                    @endif
                </td>
                <td>{{ $status['media_remaining'] ?? '—' }}</td>
                <td>
                    @if($row['policy']['allowed'])
                        <span class="badge" style="background:#dcfce7;color:#16a34a;">Mag</span>
                    @else
                        <span class="badge" style="background:#f1f5f9;color:#475569;" title="{{ $row['policy']['reason'] }}">Geblokkeerd</span>
                        <div style="font-size:.75rem;color:#64748b;">{{ $row['policy']['reason'] }}</div>
                    @endif
                </td>
                <td style="text-align:right;">
                    <button type="button" class="btn btn-sm btn-secondary" onclick="document.getElementById('booth-edit-{{ $b->id }}').toggleAttribute('hidden')">Instellen</button>
                </td>
            </tr>
            <tr id="booth-edit-{{ $b->id }}" hidden>
                <td colspan="7" style="background:#f8fafc;">
                    <form method="POST" action="{{ route('booths.update', $b->id) }}" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;align-items:end;">
                        @csrf
                        @method('PUT')
                        <div>
                            <label>Naam machine</label>
                            <input type="text" name="name" value="{{ $b->name }}" required maxlength="100">
                        </div>
                        <div>
                            <label>Photobooth (asset + unit)</label>
                            <select name="booth">
                                <option value="">— niet gekoppeld —</option>
                                @foreach($boothOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($value === $b->asset_id . ':' . $b->unit_number)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label>Updatekanaal</label>
                            <select name="update_channel">
                                @foreach($channels as $value => $label)
                                    <option value="{{ $value }}" @selected($value === $b->channel())>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label>Versie vastzetten</label>
                            <input type="text" name="pinned_version" value="{{ $b->pinned_version }}" placeholder="leeg = kanaal volgen" maxlength="30">
                        </div>
                        <div style="grid-column:1/-1;">
                            <label>Functies</label>
                            <div style="display:flex;flex-wrap:wrap;gap:1rem;">
                                @foreach($b->effectiveFeatures() as $key => $on)
                                    <label style="display:flex;align-items:center;gap:.4rem;font-weight:400;">
                                        <input type="checkbox" name="features[{{ $key }}]" value="1" @checked($on)>
                                        {{ $features[$key]['label'] ?? $key }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div style="grid-column:1/-1;display:flex;gap:.5rem;justify-content:space-between;">
                            <button type="submit" class="btn btn-primary">Opslaan</button>
                            <button type="submit" form="booth-revoke-{{ $b->id }}" class="btn btn-sm btn-danger"
                                    onclick="return confirm('Token van {{ addslashes($b->name) }} intrekken? De machine kan dan niet meer bij de CRM.')">Token intrekken</button>
                        </div>
                    </form>
                    <form id="booth-revoke-{{ $b->id }}" method="POST" action="{{ route('booths.revoke', $b->id) }}">@csrf</form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

<div class="card" style="margin-bottom:1rem;">
    <h3 style="margin:0 0 .75rem 0;font-size:1rem;">Booth koppelen</h3>
    <form method="POST" action="{{ route('booths.store') }}" style="display:flex;flex-wrap:wrap;gap:1rem;align-items:end;">
        @csrf
        <div>
            <label>Naam machine</label>
            <input type="text" name="name" placeholder="bijv. Booth 3" required maxlength="100">
        </div>
        <div>
            <label>Photobooth (asset + unit)</label>
            <select name="booth">
                <option value="">— later koppelen —</option>
                @foreach($boothOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Token aanmaken</button>
    </form>
</div>

<div class="card">
    <h3 style="margin:0 0 .75rem 0;font-size:1rem;">Laatste gebeurtenissen</h3>
    @if($events->isEmpty())
        <p style="color:#64748b;margin:0;">Nog geen gebeurtenissen gemeld.</p>
    @else
    <table>
        <tbody>
        @foreach($events as $e)
            <tr>
                <td style="white-space:nowrap;color:#64748b;">{{ $e->created_at->setTimezone($tz)->format('d-m H:i') }}</td>
                <td><strong>{{ $e->booth?->displayName() ?? 'Booth' }}</strong></td>
                <td>
                    <span class="badge" style="background:{{ $e->isOk() ? '#dcfce7;color:#16a34a' : '#fef3c7;color:#92400e' }};">{{ $e->label() }}</span>
                    @if($e->app_version) <span style="color:#64748b;">{{ $e->app_version }}</span>@endif
                </td>
                <td style="color:#64748b;">{{ $e->message }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>
@endsection
