@php
    use Illuminate\Support\Carbon;
    $rc     = $shift->route_cache ?? [];
    $rcJobs = collect($rc['jobs'] ?? [])->keyBy('id');
    $fmt    = fn ($iso) => $iso ? Carbon::parse($iso)->format('H:i') : '—';

    // Stops voor de kaart-route (start → klussen → optionele eindlocatie)
    $stops = collect([$shift->startLocation()]);
    foreach ($shift->jobs as $job) {
        $job->setRelation('shift', $shift);
        $stops->push($job->locationAddress());
    }
    if ($shift->end_address) {
        $stops->push($shift->end_address);
    }
    $stops = $stops->filter()->values();

    $total = $rc['total_minutes'] ?? null;
@endphp
<div class="shift-card" data-shift-id="{{ $shift->id }}" data-stops='@json($stops)'>
    <div class="shift-head">
        <select class="shift-staff">
            <option value="">— Medewerker —</option>
            @foreach($staffMembers as $p)
                <option value="{{ $p->id }}" @selected($shift->staff_id == $p->id)>{{ $p->name }}</option>
            @endforeach
        </select>
        <input type="time" class="shift-start {{ $shift->start_manual ? '' : 'is-auto' }}" value="{{ $shift->start_at ? $shift->start_at->format('H:i') : '' }}" title="Starttijd">
        <button class="shift-start-auto" title="{{ $shift->start_manual ? 'Terug naar automatische starttijd' : 'Starttijd wordt automatisch berekend' }}">{{ $shift->start_manual ? '↺ auto' : '⏱ auto' }}</button>
        <button class="shift-del" title="Shift verwijderen">✕</button>
    </div>
    <div class="shift-locs">
        <input type="text" class="shift-startaddr" placeholder="Startlocatie (depot)" value="{{ $shift->start_address }}">
        <input type="text" class="shift-endaddr" placeholder="Eindlocatie (optioneel)" value="{{ $shift->end_address }}">
    </div>
    <div style="margin-bottom:.6rem;">
        <select class="shift-staff2">
            <option value="">+ 2e persoon (optioneel)</option>
            @foreach($staffMembers as $p)
                <option value="{{ $p->id }}" @selected($shift->staff_id_2 == $p->id)>{{ $p->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="shift-joblist droplist" data-shift-id="{{ $shift->id }}" data-role="shift">
        @forelse($shift->jobs as $job)
            @php $r = $rcJobs->get($job->id); @endphp
            <div class="job-row {{ ($r['late'] ?? false) ? 'is-late' : '' }}"
                 data-job-id="{{ $job->id }}" data-booking-id="{{ $job->booking_id }}" data-action="{{ $job->action }}">
                <div class="jr-travel">🚗 {{ $r ? round(($r['travel_seconds'] ?? 0) / 60) : 0 }}m · {{ $r['travel_km'] ?? 0 }}km{{ ($r['wait_seconds'] ?? 0) > 0 ? ' · ⏳ wacht ' . round($r['wait_seconds'] / 60) . 'm' : '' }}</div>
                <div class="jr-main">
                    <span class="jr-time">{{ $r ? $fmt($r['arrive_at']) : '—' }}–{{ $r ? $fmt($r['depart_at']) : '—' }}</span>
                    <span class="jr-label">{{ $job->icon() }} {{ $job->label() }}</span>
                    @if($job->booking)<span class="jr-cust">{{ $job->booking->customer_name }}</span>@endif
                    <span class="jr-loc">📍 {{ $job->locationLabel() }}</span>
                </div>
                <div class="jr-side">
                    <input type="number" class="jr-dur" value="{{ $job->duration_minutes }}" min="0" step="5" title="Werktijd (min)"><span class="jr-min">min</span>
                    <button class="jr-remove" title="Uit shift halen">✕</button>
                </div>
                @if($r['late'] ?? false)
                    <div class="jr-warn">⚠️ Aankomst {{ $fmt($r['arrive_at']) }} — nodig vóór {{ $fmt($r['required_at']) }}</div>
                @endif
            </div>
        @empty
            <div class="droplist-empty">Sleep hier klussen naartoe</div>
        @endforelse
    </div>

    <div class="shift-foot">
        @if($rc['return_leg'] ?? null)
            <div class="sf-return">↩️ Terug naar eindlocatie: {{ round(($rc['return_leg']['travel_seconds'] ?? 0) / 60) }}m · {{ $rc['return_leg']['travel_km'] ?? 0 }}km</div>
        @endif
        <div class="sf-total">
            <strong>{{ $shift->start_at ? $shift->start_at->format('H:i') : '—' }} → {{ isset($rc['end_at']) ? $fmt($rc['end_at']) : '—' }}</strong>
            @if(! is_null($total))
                · totaal {{ intdiv($total, 60) }}u {{ $total % 60 }}m
            @endif
            @if(isset($rc['travel_ok']) && ! $rc['travel_ok'])
                <span class="sf-warn">· reistijd niet berekend</span>
            @endif
            @if($rc['note'] ?? null)
                <span class="sf-note">· {{ $rc['note'] }}</span>
            @endif
        </div>
        <div class="sf-actions">
            <button class="btn-recompute" title="Herbereken route via Google">↻ Herbereken</button>
            @if(!empty($mapsKey ?? null) && $stops->count() >= 2)
                <button class="btn-map" title="Toon route op kaart">🗺 Kaart</button>
            @endif
        </div>
    </div>
</div>
