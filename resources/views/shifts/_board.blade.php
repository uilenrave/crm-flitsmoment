<div class="sb-grid">
    <div class="sb-col sb-unplanned">
        <div class="sb-col-head">Nog in te plannen <span class="sb-count">{{ $unplanned->count() }}</span></div>
        <div class="droplist" id="unplanned-list" data-role="unplanned">
            @forelse($unplanned as $m)
                @php
                    $b = $m['booking']; $meta = $m['meta'];
                    // Wie staat er (via de oude planning) al op deze rit?
                    $assigned = [];
                    if ($meta['legacy'] === 'pickup') {
                        if ($b->pickupStaff)  $assigned[] = $b->pickupStaff->name;
                        if ($b->pickupStaff2) $assigned[] = $b->pickupStaff2->name;
                    } elseif ($meta['legacy'] === 'delivery') {
                        if ($b->deliveryStaff)  $assigned[] = $b->deliveryStaff->name;
                        if ($b->deliveryStaff2) $assigned[] = $b->deliveryStaff2->name;
                    }
                    $times = [];
                    if ($b->event_start_time) {
                        $times[] = '🎉 ' . \Carbon\Carbon::parse($b->event_start_time)->format('H:i')
                            . ($b->event_end_time ? '–' . \Carbon\Carbon::parse($b->event_end_time)->format('H:i') : '');
                    }
                    if ($b->booking_type === 'full_service') {
                        if ($b->delivery_at) $times[] = '🚚 ' . $b->delivery_at->format('H:i');
                        if ($b->pickup_at)   $times[] = '🧹 ' . $b->pickup_at->format('H:i');
                    } else {
                        if ($b->customer_pickup_at) $times[] = '📦 ' . $b->customer_pickup_at->format('H:i');
                        if ($b->customer_return_at) $times[] = '↩️ ' . $b->customer_return_at->format('H:i');
                    }
                @endphp
                <div class="klus-card" data-booking-id="{{ $b->id }}" data-action="{{ $m['action'] }}">
                    <div class="kc-head">{{ $meta['icon'] }} {{ $meta['label'] }} <span class="kc-req">🕒 {{ $m['required_at']->format('H:i') }}</span></div>
                    <div class="kc-cust">{{ $b->customer_name }}</div>
                    <div class="kc-meta">📍 {{ $meta['location'] === 'depot' ? 'Depot' : ($b->event_city ?: $b->event_location) }}</div>
                    @if($assigned)<div class="kc-staff">👤 {{ implode(' + ', $assigned) }}</div>@endif
                    @if($times)<div class="kc-times">{{ implode(' · ', $times) }}</div>@endif
                </div>
            @empty
                <div class="droplist-empty">Alles ingepland ✨</div>
            @endforelse
        </div>
    </div>

    <div class="sb-col sb-shifts">
        <div class="sb-col-head">
            Shifts <span class="sb-count">{{ $shifts->count() }}</span>
            <button class="btn-new-shift" data-date="{{ $date }}">+ Nieuwe shift</button>
        </div>
        <div class="laden-palette">
            <div class="klus-card laden-template" data-action="laden" data-template="1">
                <div class="kc-head">📥 Laden <span class="kc-req">15 min · Depot</span></div>
                <div class="kc-meta">Sleep naar een shift ↦</div>
            </div>
        </div>
        <div id="shifts-list">
            @forelse($shifts as $shift)
                @include('shifts._shift_card', ['shift' => $shift, 'staffMembers' => $staffMembers, 'mapsKey' => $mapsKey ?? null])
            @empty
                <div class="droplist-empty">Nog geen shifts vandaag. Maak er een aan en sleep klussen erin.</div>
            @endforelse
        </div>
    </div>
</div>
