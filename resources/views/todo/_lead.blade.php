@php
    $dagen    = $lead->dagenVerlopen();
    $telaat   = $lead->follow_up_at->isBefore(now()->startOfDay());
    $whatsapp = $lead->whatsappNummer();
@endphp

<div class="item lead" data-id="{{ $lead->id }}">
    <div class="tekst" onclick="klapUit(this)">
        <div class="titel">
            {{ $lead->name }}
            <span class="leadnr">{{ $lead->lead_number }}</span>
        </div>
        <div class="onder">
            @if ($lead->status?->name)
                <span>{{ $lead->status->name }}</span>
            @endif
            @if ($lead->event_date)
                <span>{{ $lead->event_date->translatedFormat('j M Y') }}{{ $lead->event_city ? ', ' . $lead->event_city : '' }}</span>
            @endif
            @if ($lead->phone)
                <span>{{ $lead->phone }}</span>
            @endif
        </div>
    </div>

    <div class="contactknoppen">
        @if ($lead->phone)
            <a class="rond bellen" href="tel:{{ preg_replace('/\s/', '', $lead->phone) }}"
               title="Bellen" onclick="event.stopPropagation()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
                </svg>
            </a>
        @endif
        @if ($whatsapp)
            <a class="rond whatsapp" href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener"
               title="WhatsApp sturen" onclick="event.stopPropagation()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884a9.82 9.82 0 0 1 6.988 2.896 9.83 9.83 0 0 1 2.893 6.994c-.003 5.45-4.437 9.886-9.885 9.886m8.413-18.297A11.82 11.82 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.82 11.82 0 0 0 20.465 3.49"/>
                </svg>
            </a>
        @endif
    </div>

    <div class="wanneer {{ $telaat ? 'laat' : 'nu' }}">
        @if (! $telaat)
            vandaag
        @elseif ($dagen === 1)
            1 dag te laat
        @else
            {{ $dagen }} dagen te laat
        @endif
    </div>
</div>

<div class="detail" data-id="{{ $lead->id }}">
    @if ($lead->activities->isNotEmpty())
        <div class="tijdlijn">
            @foreach ($lead->activities as $activiteit)
                <div class="tl-regel">
                    <span class="tl-datum">{{ $activiteit->created_at->translatedFormat('j M') }}</span>
                    <span><b>{{ $activiteit->title }}</b>{{ $activiteit->description ? ' — ' . \Illuminate\Support\Str::limit($activiteit->description, 90) : '' }}</span>
                </div>
            @endforeach
        </div>
    @endif

    <textarea id="notitie-{{ $lead->id }}" placeholder="Notitie — komt in het CRM bij deze lead te staan…"></textarea>
    <div class="detail-acties">
        <button type="button" class="knopje" onclick="leadNotitie(this, {{ $lead->id }})">Notitie bewaren</button>
        <button type="button" class="knopje" onclick="leadContact(this, {{ $lead->id }}, 'call_answered')">Gesproken</button>
        <button type="button" class="knopje" onclick="leadContact(this, {{ $lead->id }}, 'call_no_answer')">Geen gehoor</button>
    </div>

    <div class="detail-grid" style="margin-top:12px">
        <div class="veld">
            <label for="opvolg-{{ $lead->id }}">Volgende opvolging</label>
            <input type="date" id="opvolg-{{ $lead->id }}" value="{{ $lead->follow_up_at?->toDateString() }}"
                   onchange="leadDatum({{ $lead->id }}, null, this.value)">
        </div>
    </div>

    <div class="detail-acties">
        <button type="button" class="knopje" onclick="leadDatum({{ $lead->id }}, 1)">Morgen</button>
        <button type="button" class="knopje" onclick="leadDatum({{ $lead->id }}, 3)">Over 3 dagen</button>
        <button type="button" class="knopje" onclick="leadDatum({{ $lead->id }}, 7)">Volgende week</button>
        <button type="button" class="knopje weghalen" onclick="leadDatum({{ $lead->id }}, null, '')">Geen opvolging meer</button>
    </div>

    <a class="bron-link" href="{{ rtrim(config('app.url'), '/') }}/leads/{{ $lead->id }}" target="_blank" rel="noopener">
        Open de lead in het CRM →
    </a>
</div>
