@extends('todo.layout')

@section('inhoud')
    <div class="kop">
        <div>
            <h1>{{ $titel }}</h1>
            <p>{{ $onderschrift }}</p>
        </div>
        <button class="menuknop" onclick="menuOpen()" aria-label="Menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>
    </div>

    @if ($weergave === 'zoeken')
        <div class="zoekbalk open" id="zoekbalk">
            <form method="GET" action="{{ route('todo.zoeken') }}">
                <input type="search" name="q" value="{{ $zoekterm ?? '' }}" placeholder="Zoek in alles…" autofocus>
            </form>
        </div>
    @endif

    <div id="lijst">
        @php $leeg = collect($secties)->every(fn ($s) => $s['items']->isEmpty()); @endphp

        @if ($leeg)
            <p class="leeg">{{ $leegTekst }}</p>
        @else
            @foreach ($secties as $sectie)
                @continue($sectie['items']->isEmpty())
                <div class="sectie">
                    @if ($sectie['titel'])
                        <div class="sectie-kop">{{ $sectie['titel'] }}</div>
                    @endif
                    @foreach ($sectie['items'] as $item)
                        @include('todo._item', ['item' => $item])
                    @endforeach
                </div>
            @endforeach
        @endif
    </div>
@endsection
