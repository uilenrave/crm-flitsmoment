@extends('layouts.app')

@section('title', $briefing->exists ? 'Briefing bewerken' : 'Nieuwe briefing')

@section('content')

<div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.5rem;flex-wrap:wrap;">
    <a href="{{ route('briefings.index') }}" style="color:#64748b;text-decoration:none;font-size:.875rem;">← Briefings</a>
    <span style="color:#cbd5e1;">/</span>
    <h2 style="margin:0;font-size:1.1rem;font-weight:700;color:#1e293b;">
        {{ $briefing->exists ? ($briefing->effective_title) : 'Nieuwe briefing' }}
    </h2>
    @if($briefing->exists)
    <div style="margin-left:auto;display:flex;gap:.5rem;">
        <a href="{{ route('briefings.pdf', $briefing) }}?preview=1" target="_blank" class="btn btn-secondary">👁 Voorbeeld</a>
        <a href="{{ route('briefings.pdf', $briefing) }}" class="btn btn-primary">📄 Genereer PDF</a>
    </div>
    @endif
</div>

<form method="POST" action="{{ $briefing->exists ? route('briefings.update', $briefing) : route('briefings.store') }}">
    @csrf
    @if($briefing->exists) @method('PUT') @endif

    {{-- ── Briefing basics ── --}}
    <div class="card neu-card" style="margin-bottom:1.25rem;padding:1.25rem;">
        <div style="display:grid;grid-template-columns:1.4fr 1fr 1fr;gap:1rem;">
            <div class="form-group" style="margin:0;">
                <label class="form-label">Titel <span style="color:#94a3b8;font-weight:400;">(optioneel)</span></label>
                <input type="text" name="title" class="form-control" value="{{ old('title', $briefing->title) }}"
                       placeholder="bv. Briefing Max — week 22" maxlength="200">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Medewerker <span style="color:#ef4444;">*</span></label>
                <select name="staff_id" class="form-control" required>
                    <option value="">— Kies —</option>
                    @foreach($staff as $s)
                        <option value="{{ $s->id }}" @selected(old('staff_id', $briefing->staff_id) == $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Periode <span style="color:#ef4444;">*</span></label>
                <div style="display:flex;gap:.4rem;align-items:center;">
                    <input type="date" name="date_from" class="form-control" required value="{{ old('date_from', $briefing->date_from?->format('Y-m-d')) }}">
                    <span style="color:#94a3b8;">→</span>
                    <input type="date" name="date_to" class="form-control" required value="{{ old('date_to', $briefing->date_to?->format('Y-m-d')) }}">
                </div>
            </div>
        </div>
        <div style="margin-top:1rem;display:flex;gap:.65rem;">
            <button type="submit" class="btn btn-primary">{{ $briefing->exists ? '💾 Opslaan' : 'Aanmaken & ritten laden' }}</button>
            <a href="{{ route('briefings.index') }}" class="btn btn-secondary">Annuleren</a>
        </div>
    </div>

    {{-- ── Ritten + notities ── --}}
    @if($briefing->exists && isset($ritten))
        @php
            $roleIcons = ['delivery' => '🚚', 'pickup' => '↩', 'handover' => '🤝'];
            $roleLabels = ['delivery' => 'Bezorging', 'pickup' => 'Ophalen', 'handover' => 'Afgifte (To Go)'];
        @endphp

        @if($ritten->isEmpty())
        <div class="card neu-card" style="padding:1.75rem;text-align:center;">
            <p style="font-size:1.4rem;margin:0 0 .5rem;">📭</p>
            <p style="font-weight:600;margin:0 0 .35rem;">Geen ritten gevonden in deze periode</p>
            <p style="color:#64748b;font-size:.85rem;margin:0;">Pas de datum-range aan of plan {{ $briefing->staff->name }} in op een boeking.</p>
        </div>
        @else
        <div style="margin-bottom:1rem;font-size:.85rem;color:#64748b;">
            {{ $ritten->count() }} {{ $ritten->count() === 1 ? 'rit' : 'ritten' }} gevonden voor {{ $briefing->staff->name }} in deze periode.
            Voeg per rit een extra instructie toe, en sla dan op.
        </div>

        @foreach($ritten as $r)
            @php
                $b = $r['booking'];
                $role = $r['role'];
                $datum = $r['datum'];
                $key = $b->id . ':' . $role;
                $existingNote = $briefing->noteFor($b->id, $role);
                $adres = collect([$b->event_address, $b->event_postcode, $b->event_city])->filter()->implode(', ');
            @endphp
            <div class="card" style="padding:1rem 1.25rem;margin-bottom:.75rem;border-left:3px solid {{ $role === 'pickup' ? '#16a34a' : '#2563eb' }};">
                <div style="display:flex;gap:.875rem;align-items:flex-start;">
                    <div style="font-size:1.4rem;line-height:1;">{{ $roleIcons[$role] ?? '📦' }}</div>
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;margin-bottom:.25rem;">
                            <strong style="font-size:.95rem;">{{ $roleLabels[$role] ?? $role }}</strong>
                            <span style="color:#94a3b8;">·</span>
                            <a href="{{ route('bookings.show', $b) }}" target="_blank" style="color:#2563eb;text-decoration:none;font-weight:600;font-size:.85rem;">{{ $b->booking_number }}</a>
                            <span style="color:#94a3b8;">·</span>
                            <span style="font-size:.85rem;">{{ $b->customer_name }}</span>
                        </div>
                        <div style="font-size:.85rem;color:#1e293b;margin-bottom:.25rem;">
                            🕐 <strong>{{ $datum->translatedFormat('l j F') }}</strong> om <strong>{{ $datum->format('H:i') }}</strong>
                        </div>
                        @if($adres || $b->event_location)
                        <div style="font-size:.82rem;color:#64748b;">
                            📍 {{ $b->event_location ?: '' }}@if($b->event_location && $adres) — @endif{{ $adres }}
                        </div>
                        @endif
                    </div>
                </div>

                <div style="margin-top:.75rem;">
                    <label style="display:block;font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.3rem;">
                        ✍️ Extra instructies voor deze rit
                    </label>
                    <textarea name="notes[{{ $key }}]" rows="2"
                              style="width:100%;padding:.5rem .7rem;border:1px solid #e2e8f0;border-radius:.4rem;font-size:.875rem;font-family:inherit;resize:vertical;"
                              placeholder="Bijv. eerst even bellen, achterom lossen, contact ter plaatse: …">{{ old('notes.'.$key, $existingNote) }}</textarea>
                </div>
            </div>
        @endforeach

        <div style="position:sticky;bottom:.75rem;padding:.75rem 0;background:linear-gradient(to top, rgba(248,250,252,1) 50%, rgba(248,250,252,.3));display:flex;gap:.65rem;justify-content:flex-end;">
            <a href="{{ route('briefings.pdf', $briefing) }}?preview=1" target="_blank" class="btn btn-secondary">👁 Voorbeeld</a>
            <button type="submit" class="btn btn-primary">💾 Notities opslaan</button>
        </div>
        @endif
    @endif
</form>

@endsection
