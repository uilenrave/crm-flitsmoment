<div class="cal-head">
    <button class="cal-prev" data-month="{{ $cal_prev }}" title="Vorige maand">‹</button>
    <span class="cal-label">{{ ucfirst($cal_label) }}</span>
    <button class="cal-next" data-month="{{ $cal_next }}" title="Volgende maand">›</button>
</div>
<div class="cal-grid">
    @foreach(['ma','di','wo','do','vr','za','zo'] as $wd)
        <div class="cal-wd">{{ $wd }}</div>
    @endforeach
    @foreach($cal_days as $d)
        <button type="button"
                class="cal-day {{ $d['in_month'] ? '' : 'cal-out' }} {{ $d['selected'] ? 'cal-sel' : '' }} {{ $d['today'] ? 'cal-today' : '' }} {{ $d['open'] ? 'cal-hasopen' : '' }}"
                data-date="{{ $d['date'] }}">
            <span class="cal-num">{{ $d['day'] }}</span>
            <span class="cal-badges">
                @if($d['shifts'])<span class="cal-b cal-shift">🚐{{ $d['shifts'] }}</span>@endif
                @if($d['open'])<span class="cal-b cal-open">⚠️{{ $d['open'] }}</span>@endif
            </span>
        </button>
    @endforeach
</div>
