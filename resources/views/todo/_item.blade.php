@php
    $laat   = $item->due_status === 'overdue';
    $nu     = $item->due_status === 'today';
    $bronUrl = $item->sourceUrl();
@endphp

<div class="item {{ $item->is_completed ? 'af' : '' }}" data-id="{{ $item->id }}"
     @if($item->is_completed) data-blijf="1" @endif>
    <button class="vink {{ $item->is_completed ? 'aan' : '' }}" onclick="vinkAf(this)"
            aria-label="{{ $item->is_completed ? 'Weer openzetten' : 'Afvinken' }}"></button>

    <div class="tekst" onclick="klapUit(this)">
        <div class="titel">
            {{ $item->title }}
            @if ($item->flagged) <span class="vlag" title="Gevlagd">●</span> @endif
        </div>

        @php
            $onderdelen = [];
            if ($item->price_label) $onderdelen[] = '<span class="prijs">' . e($item->price_label) . '</span>';
            if ($item->vendor)      $onderdelen[] = e($item->vendor);
            if ($item->kind)        $onderdelen[] = e(\App\Services\TodoQuickParser::kindLabel($item->kind));
            if ($item->subtasks->count()) {
                $af = $item->subtasks->whereNotNull('completed_at')->count();
                $onderdelen[] = e($af . '/' . $item->subtasks->count() . ' subtaken');
            }
            if ($item->notes) $onderdelen[] = e(\Illuminate\Support\Str::limit(strtok($item->notes, "\n"), 70));
        @endphp

        @if ($onderdelen || $bronUrl || $item->url)
            <div class="onder">
                @if ($bronUrl)
                    <a class="bron" href="{{ $bronUrl }}" target="_blank" rel="noopener"
                       onclick="event.stopPropagation()">{{ $item->sourceLabel() }}</a>
                @endif
                @if ($item->url)
                    <a class="bron" href="{{ $item->url }}" target="_blank" rel="noopener"
                       onclick="event.stopPropagation()">link</a>
                @endif
                {!! implode(' · ', $onderdelen) !!}
            </div>
        @endif
    </div>

    <div class="wanneer {{ $laat ? 'laat' : ($nu ? 'nu' : '') }}">
        @if ($item->is_completed)
            {{ $item->completed_at?->translatedFormat('j M') }}
        @elseif ($laat)
            @php $dagenTeLaat = (int) $item->due_date->startOfDay()->diffInDays(now()->startOfDay()); @endphp
            {{ $dagenTeLaat > 1 ? $dagenTeLaat . ' dagen te laat' : 'te laat' }}
        @elseif ($nu)
            vandaag
        @elseif ($item->due_date)
            {{ $item->due_date->translatedFormat('j M') }}
        @endif
    </div>
</div>

<div class="detail" data-id="{{ $item->id }}">
    <textarea name="notes" placeholder="Notitie…" onchange="bewaarVeld(this)">{{ $item->notes }}</textarea>

    <div class="detail-grid">
        <div class="veld">
            <label for="datum-{{ $item->id }}">Datum</label>
            <input type="date" id="datum-{{ $item->id }}" name="due_date"
                   value="{{ $item->due_date?->toDateString() }}" onchange="bewaarVeld(this)">
        </div>

        @if ($item->type === \App\Models\Task::TYPE_KOPEN)
            <div class="veld">
                <label for="prijs-{{ $item->id }}">Prijs</label>
                <input type="text" id="prijs-{{ $item->id }}" name="price" inputmode="decimal"
                       value="{{ $item->price_cents !== null ? number_format($item->price_cents / 100, 2, ',', '') : '' }}"
                       placeholder="89,00" onchange="bewaarVeld(this)">
            </div>
            <div class="veld">
                <label for="winkel-{{ $item->id }}">Winkel</label>
                <input type="text" id="winkel-{{ $item->id }}" name="vendor"
                       value="{{ $item->vendor }}" placeholder="Conrad" onchange="bewaarVeld(this)">
            </div>
        @endif

        @if ($item->type === \App\Models\Task::TYPE_LEREN)
            <div class="veld">
                <label for="soort-{{ $item->id }}">Soort</label>
                <select id="soort-{{ $item->id }}" name="kind" onchange="bewaarVeld(this)">
                    <option value="">Overig</option>
                    @foreach (['cursus' => 'Cursus', 'boek' => 'Boek', 'video' => 'Video', 'artikel' => 'Artikel', 'podcast' => 'Podcast'] as $waarde => $label)
                        <option value="{{ $waarde }}" @selected($item->kind === $waarde)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @if (in_array($item->type, [\App\Models\Task::TYPE_KOPEN, \App\Models\Task::TYPE_LEREN], true))
            <div class="veld">
                <label for="url-{{ $item->id }}">Link</label>
                <input type="url" id="url-{{ $item->id }}" name="url" value="{{ $item->url }}"
                       placeholder="https://…" onchange="bewaarVeld(this)">
            </div>
        @endif

        <div class="veld">
            <label for="lijst-{{ $item->id }}">Lijst</label>
            <select id="lijst-{{ $item->id }}" name="task_list_id" onchange="bewaarVeld(this)">
                @foreach ($lijsten as $lijst)
                    <option value="{{ $lijst->id }}" @selected($item->task_list_id === $lijst->id)>{{ $lijst->title }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="detail-acties">
        <button type="button" class="knopje" onclick="morgen(this, 1)">Morgen</button>
        <button type="button" class="knopje" onclick="morgen(this, 7)">Volgende week</button>
        <button type="button" class="knopje" onclick="vlagOm(this)">{{ $item->flagged ? 'Vlag weg' : 'Vlag' }}</button>
        <button type="button" class="knopje weghalen" onclick="haalWeg(this)">Weghalen</button>
    </div>

    @if ($bronUrl)
        <a class="bron-link" href="{{ $bronUrl }}" target="_blank" rel="noopener">Bekijk de {{ $item->sourceLabel() }} in het CRM →</a>
    @endif
</div>
