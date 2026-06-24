@php
    // Bepaal de relevante datum/tijd van DEZE rit
    $relevantDate = match($role) {
        'pickup'   => $booking->pickup_at ?? $booking->customer_return_at ?? $booking->event_date,
        default    => $booking->delivery_at ?? $booking->customer_pickup_at ?? $booking->event_date,
    };
    $relevantDate = $relevantDate instanceof \Carbon\Carbon ? $relevantDate : \Carbon\Carbon::parse($relevantDate);

    // Filter ritten: niet zichzelf + binnen ±36 uur
    $candidates = ($allStaffRitten ?? collect())
        ->filter(function ($rit) use ($booking, $role, $relevantDate) {
            // Niet de huidige rit zelf
            if ($rit['booking']->id === $booking->id && $rit['role'] === $role) return false;
            $d = $rit['datum'] instanceof \Carbon\Carbon ? $rit['datum'] : \Carbon\Carbon::parse($rit['datum']);
            return abs($d->diffInHours($relevantDate)) <= 36;
        })
        ->sortBy('datum')
        ->values();

    $roleIcons = [
        'delivery' => '🚚',
        'pickup'   => '↩',
        'handover' => '🤝',
    ];
    $roleLabels = [
        'delivery' => 'Bezorging',
        'pickup'   => 'Ophalen',
        'handover' => 'Afgifte',
    ];
@endphp

@if($candidates->isNotEmpty())
<div class="combineer-wrap">
    <button type="button" class="combineer-link" onclick="toggleCombineerForm(this)">
        🔗 Was dit een combinatierit?
    </button>
    <form method="POST" action="{{ route('staff.portal.hours.combine', $staff->public_token) }}" class="combineer-form">
        @csrf
        <input type="hidden" name="booking_id" value="{{ $booking->id }}">
        <input type="hidden" name="role" value="{{ $role }}">
        <div style="font-size:.78rem;color:#5b21b6;margin-bottom:.4rem;font-weight:600;">
            Bij welke rit heb je je uren al ingediend?
        </div>
        <select name="combined_with" required onchange="this.form.querySelector('.combineer-btn').disabled = !this.value;">
            <option value="">— Kies een rit —</option>
            @foreach($candidates as $rit)
                @php
                    $other = $rit['booking'];
                    $otherRole = $rit['role'];
                    $d = $rit['datum'] instanceof \Carbon\Carbon ? $rit['datum'] : \Carbon\Carbon::parse($rit['datum']);
                @endphp
                <option value="{{ $other->id }}:{{ $otherRole }}">
                    {{ $other->booking_number }} · {{ $roleIcons[$otherRole] ?? '' }} {{ $roleLabels[$otherRole] ?? $otherRole }} · {{ $d->translatedFormat('d M H:i') }} · {{ $other->customer_name }}
                </option>
            @endforeach
        </select>
        <div class="combineer-actions">
            <button type="button" class="combineer-cancel" onclick="toggleCombineerForm(this)">Annuleren</button>
            <button type="submit" class="combineer-btn" disabled>🔗 Markeren als combinatie</button>
        </div>
    </form>
</div>
@endif
