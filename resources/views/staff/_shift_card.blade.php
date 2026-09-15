@php
    use App\Models\ShiftJob;
    $rcJobs = collect($shift->route_cache['jobs'] ?? [])->keyBy('id');
    $typeMap = ['opbouwen' => 'delivery', 'afbouwen' => 'pickup', 'afgeven' => 'handover', 'aannemen' => 'pickup', 'laden' => null];

    // Wie doet deze shift nog meer (naast de ingelogde medewerker)?
    $other = null;
    if ($shift->staff_id === $staff->id && $shift->staff2) $other = $shift->staff2->name;
    elseif ($shift->staff_id_2 === $staff->id && $shift->staff) $other = $shift->staff->name;

    $startTxt = $shift->start_at ? $shift->start_at->format('H:i') : null;
    $endTxt   = $shift->end_at ? $shift->end_at->format('H:i') : null;

    $entry    = ($shiftHours[$shift->id] ?? null);
    $isLocked = $entry && in_array($entry->status, ['approved', 'paid']);
@endphp
<div class="card shift-portal-card">
    <div class="card-header shift-portal-header">
        <div>
            <span class="shift-portal-badge">🚐 Shift</span>
            @if($other)<span style="font-size:.78rem;color:#0e7490;margin-left:.4rem;">samen met {{ $other }}</span>@endif
            <div class="shift-portal-date">{{ $shift->shift_date->translatedFormat('l j F Y') }}</div>
        </div>
        @if($startTxt)
        <div style="text-align:right;">
            <div class="shift-portal-time">{{ $startTxt }}@if($endTxt) – {{ $endTxt }}@endif</div>
            <div style="font-size:.7rem;color:#0e7490;">{{ $shift->jobs->count() }} {{ $shift->jobs->count() === 1 ? 'klus' : 'klussen' }}</div>
        </div>
        @endif
    </div>
    <div class="card-body">
        @forelse($shift->jobs as $i => $job)
            @php
                $meta = ShiftJob::ACTIONS[$job->action] ?? ShiftJob::ACTIONS['opbouwen'];
                $r    = $rcJobs->get($job->id);
                $tijd = ! empty($r['arrive_at']) ? \Carbon\Carbon::parse($r['arrive_at'])->format('H:i') : ($job->requiredAt()?->format('H:i'));
                $type = $typeMap[$job->action] ?? null;
            @endphp
            <div class="klus-block">
                <span class="klus-step">{{ $i + 1 }}</span>
                <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:.4rem;">
                    <span class="role-badge {{ $job->action === 'afbouwen' || $job->action === 'aannemen' ? 'badge-pickup' : 'badge-delivery' }}">{{ $meta['icon'] }} {{ $meta['label'] }}</span>
                    @if($tijd)<span class="time-highlight" style="font-size:1rem;">{{ $tijd }}</span>@endif
                    @if(($r['wait_seconds'] ?? 0) > 0)<span style="font-size:.72rem;color:#92400e;">⏳ wacht {{ round($r['wait_seconds'] / 60) }}m</span>@endif
                    @if($r['late'] ?? false)<span style="font-size:.72rem;color:#b91c1c;font-weight:700;">⚠️ krap</span>@endif
                </div>
                @if($job->booking)
                    @include('staff._booking_details', ['b' => $job->booking, 'type' => $type])
                @else
                    <div style="font-size:.85rem;color:#64748b;">📥 Laden op het depot ({{ $job->duration_minutes }} min)</div>
                @endif
            </div>
        @empty
            <div style="font-size:.85rem;color:#94a3b8;">Deze shift heeft nog geen klussen.</div>
        @endforelse

        {{-- Uren voor de hele shift --}}
        <div class="uren-section">
            <div class="uren-title">⏱ Gewerkte uren (hele shift)</div>
            @if($isLocked)
                @if($entry->status === 'paid')
                <div class="uren-status-paid">💸 <strong>{{ number_format($entry->effective_hours, 2, ',', '') }} uur uitbetaald</strong></div>
                @else
                <div class="uren-status-approved">✅ <strong>{{ number_format($entry->effective_hours, 2, ',', '') }} uur goedgekeurd</strong></div>
                @endif
            @else
                @if($entry && $entry->status === 'pending')
                <div class="uren-status-pending" style="margin-bottom:.625rem;">
                    ⏳ <span>{{ number_format($entry->hours, 2, ',', '') }} uur @if($entry->km > 0)· {{ number_format($entry->km, 0) }} km @endif ingediend — wacht op goedkeuring</span>
                </div>
                @endif
                <form method="POST" action="{{ route('staff.portal.shift-hours.submit', $staff->public_token) }}">
                    @csrf
                    <input type="hidden" name="shift_id" value="{{ $shift->id }}">
                    <div class="uren-form">
                        <div>
                            <div style="font-size:.7rem;color:#64748b;margin-bottom:.2rem;">Uren</div>
                            <input type="number" name="hours" step="0.25" min="0.25" max="24" class="uren-input"
                                   value="{{ $entry ? number_format($entry->hours, 2, '.', '') : '' }}" placeholder="2.50" required>
                        </div>
                        <div>
                            <div style="font-size:.7rem;color:#64748b;margin-bottom:.2rem;">Privé km</div>
                            <input type="number" name="km" step="1" min="0" max="9999" class="uren-input"
                                   value="{{ $entry && $entry->km ? number_format($entry->km, 0, '.', '') : '' }}" placeholder="0" oninput="updateKmPreview(this)">
                        </div>
                        <button type="submit" class="uren-btn">{{ $entry ? 'Bijwerken' : 'Indienen' }}</button>
                    </div>
                    <div class="km-preview" style="display:none;font-size:.75rem;color:#64748b;margin-top:.4rem;">= €<span class="km-amount">0,00</span> km-vergoeding</div>
                </form>
            @endif
        </div>
    </div>
</div>
