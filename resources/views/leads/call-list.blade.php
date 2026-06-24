@extends('layouts.app')

@section('title', 'Bellijst')

@push('styles')
<style>
    .cl-wrap { max-width: 560px; margin: 0 auto; }

    /* Zoekbalk */
    .cl-search {
        position: sticky; top: 0; z-index: 50;
        background: #f8f7f5; padding: .25rem 0 .75rem;
    }
    .cl-search input {
        width: 100%; padding: .8rem 1rem; font-size: 16px; /* 16px voorkomt iOS zoom */
        border: 1.5px solid #e2e8f0; border-radius: .85rem; background: #fff;
        box-shadow: -1px -1px 3px rgba(255,255,255,.8), 2px 2px 5px rgba(0,0,0,.06);
    }
    .cl-search input:focus { outline: none; border-color: #fcd34d; }

    /* Samenvatting chips */
    .cl-summary { display: flex; gap: .5rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .cl-sum-chip { font-size: .78rem; font-weight: 700; padding: .35rem .75rem; border-radius: 999px; }
    .cl-sum-chip.overdue { background: #fee2e2; color: #b91c1c; }
    .cl-sum-chip.today   { background: #fef3c7; color: #92400e; }
    .cl-sum-chip.planned { background: #dbeafe; color: #1d4ed8; }
    .cl-sum-chip.unplanned { background: #f1f5f9; color: #475569; }

    /* Groep headers */
    .cl-group-title {
        font-size: .76rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em;
        color: #475569; margin: 1.1rem 0 .5rem; display: flex; align-items: center; gap: .45rem;
    }
    .cl-group-title .count { background: #e2e8f0; color: #475569; font-size: .7rem; padding: .08rem .45rem; border-radius: 999px; }

    /* Lead kaart */
    .cl-card {
        background: #fff; border-radius: 1rem; margin-bottom: .6rem;
        box-shadow: -1px -1px 3px rgba(255,255,255,.8), 2px 2px 8px rgba(0,0,0,.07);
        overflow: hidden; border-left: 4px solid transparent;
    }
    .cl-card.is-overdue { border-left-color: #dc2626; }
    .cl-card.is-today   { border-left-color: #f59e0b; }
    .cl-card.is-planned { border-left-color: #3b82f6; }

    .cl-card-head { padding: .7rem .95rem .25rem; }
    .cl-name-row { display: flex; align-items: center; gap: .5rem; }
    .cl-name { font-size: .98rem; font-weight: 800; color: #0f172a; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .cl-status { font-size: .66rem; font-weight: 700; padding: .12rem .5rem; border-radius: 999px; flex-shrink: 0; }

    .cl-meta { padding: 0 .95rem; font-size: .8rem; color: #64748b; line-height: 1.5; }
    .cl-meta strong { color: #1e293b; }
    .cl-followup-pill {
        display: inline-flex; align-items: center; gap: .3rem;
        font-size: .72rem; font-weight: 700; padding: .15rem .55rem; border-radius: 999px; margin-top: .2rem;
    }
    .cl-followup-pill.overdue { background: #fee2e2; color: #b91c1c; }
    .cl-followup-pill.today   { background: #fef3c7; color: #92400e; }
    .cl-followup-pill.future  { background: #dbeafe; color: #1d4ed8; }

    .cl-lastnote {
        margin: .4rem .95rem 0; padding: .4rem .65rem; background: #fffbeb;
        border-left: 3px solid #fcd34d; border-radius: .4rem;
        font-size: .78rem; color: #78350f; line-height: 1.4;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    }

    /* Grote actieknoppen: bellen + whatsapp + mail */
    .cl-cta-row { display: flex; gap: .45rem; padding: .6rem .95rem .25rem; }
    .cl-cta {
        flex: 1; display: flex; align-items: center; justify-content: center; gap: .4rem;
        min-height: 42px; border-radius: .7rem; font-size: .88rem; font-weight: 800;
        text-decoration: none; border: none; cursor: pointer; transition: transform .1s;
    }
    .cl-cta:active { transform: scale(.97); }
    .cl-cta.bel { background: #1e293b; color: #fff; }
    .cl-cta.wa  { background: #25d366; color: #fff; }
    .cl-cta.mail{ background: #f1f5f9; color: #334155; flex: 0 0 46px; font-size: 1.05rem; }
    .cl-cta[aria-disabled="true"] { opacity: .35; pointer-events: none; }

    /* Follow-up snelkeuze */
    .cl-fu-row { display: flex; gap: .35rem; padding: .45rem .95rem 0; flex-wrap: wrap; align-items: center; }
    .cl-fu-label { font-size: .68rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .04em; width: 100%; }
    .cl-fu-chip {
        border: 1.5px solid #e2e8f0; background: #fff; color: #334155;
        font-size: .76rem; font-weight: 700; padding: .35rem .65rem; border-radius: 999px;
        cursor: pointer; min-height: 36px;
    }
    .cl-fu-chip:active { background: #fef3c7; border-color: #fcd34d; }
    .cl-fu-date {
        border: 1.5px solid #e2e8f0; border-radius: 999px; padding: .3rem .6rem;
        font-size: 16px; background: #fff; color: #334155; min-height: 36px; font-weight: 600;
    }

    /* Onderste rij: notitie / akkoord / afwijzen */
    .cl-bottom { display: flex; gap: .4rem; padding: .55rem .95rem .75rem; }
    .cl-btn-sub {
        flex: 1; display: flex; align-items: center; justify-content: center; gap: .3rem;
        min-height: 40px; border-radius: .6rem; font-size: .82rem; font-weight: 700;
        border: 1.5px solid #e2e8f0; background: #fff; color: #475569; cursor: pointer;
    }
    .cl-btn-sub:active { background: #f8fafc; }
    .cl-btn-sub.akkoord { border-color: #86efac; color: #15803d; background: #f0fdf4; }
    .cl-btn-sub.afwijzen { border-color: #fecaca; color: #b91c1c; background: #fef2f2; flex: 0 0 46px; }

    /* Notitie-uitklap */
    .cl-note-form { display: none; padding: 0 1.1rem .9rem; }
    .cl-note-form.open { display: block; }
    .cl-note-form textarea {
        width: 100%; padding: .7rem .85rem; font-size: 16px; border: 1.5px solid #e2e8f0;
        border-radius: .7rem; resize: vertical; min-height: 70px; font-family: inherit;
    }
    .cl-note-form textarea:focus { outline: none; border-color: #fcd34d; }
    .cl-note-save {
        margin-top: .5rem; width: 100%; min-height: 44px; border: none; border-radius: .7rem;
        background: linear-gradient(135deg, #fcd34d, #f59e0b); color: #78350f;
        font-size: .9rem; font-weight: 800; cursor: pointer;
    }

    .cl-empty { text-align: center; padding: 3rem 1rem; color: #94a3b8; }
    .cl-empty .big { font-size: 2.5rem; margin-bottom: .5rem; }
</style>
@endpush

@section('content')
@php
    $today = now()->startOfDay();
    $totalCount = $groups['overdue']->count() + $groups['today']->count() + $groups['planned']->count() + $groups['unplanned']->count();

    $groupMeta = [
        'overdue'   => ['title' => '🔥 Te laat — bel nu',      'class' => 'is-overdue'],
        'today'     => ['title' => '📞 Vandaag bellen',         'class' => 'is-today'],
        'planned'   => ['title' => '📅 Gepland',                'class' => 'is-planned'],
        'unplanned' => ['title' => '🆕 Geen belafspraak',       'class' => ''],
    ];
@endphp

<div class="cl-wrap">

    {{-- Zoeken --}}
    <div class="cl-search">
        <input type="search" id="cl-search-input" placeholder="🔍 Zoek op naam of plaats…" autocomplete="off">
    </div>

    {{-- Samenvatting --}}
    <div class="cl-summary">
        @if($groups['overdue']->count()) <span class="cl-sum-chip overdue">🔥 {{ $groups['overdue']->count() }} te laat</span> @endif
        @if($groups['today']->count())   <span class="cl-sum-chip today">📞 {{ $groups['today']->count() }} vandaag</span> @endif
        @if($groups['planned']->count()) <span class="cl-sum-chip planned">📅 {{ $groups['planned']->count() }} gepland</span> @endif
        @if($groups['unplanned']->count()) <span class="cl-sum-chip unplanned">🆕 {{ $groups['unplanned']->count() }} zonder afspraak</span> @endif
    </div>

    @if($totalCount === 0)
        <div class="cl-empty">
            <div class="big">🎉</div>
            <p style="font-weight:700;margin-bottom:.3rem;">Niets na te bellen!</p>
            <p style="font-size:.85rem;">Alle actieve leads zijn bijgewerkt.</p>
        </div>
    @endif

    @foreach($groupMeta as $key => $meta)
        @if($groups[$key]->isNotEmpty())
        <div class="cl-group" data-group="{{ $key }}">
            <div class="cl-group-title">{{ $meta['title'] }} <span class="count">{{ $groups[$key]->count() }}</span></div>

            @foreach($groups[$key] as $lead)
            @php
                // WhatsApp-nummer normaliseren
                $waNr = '';
                if ($lead->phone) {
                    $waNr = ltrim(preg_replace('/[^0-9+]/', '', $lead->phone), '+');
                    if (str_starts_with($waNr, '00')) $waNr = substr($waNr, 2);
                    if (str_starts_with($waNr, '0'))  $waNr = '31' . substr($waNr, 1);
                }
                $laatsteNotitie = $lead->activities->firstWhere('activity_type', 'note');
                $fuClass = $lead->follow_up_at
                    ? ($lead->follow_up_at->lt($today) ? 'overdue' : ($lead->follow_up_at->isSameDay($today) ? 'today' : 'future'))
                    : null;
            @endphp
            <div class="cl-card {{ $meta['class'] }}" data-search="{{ strtolower($lead->name . ' ' . $lead->event_city . ' ' . $lead->event_location) }}">

                {{-- Kop: naam + status --}}
                <div class="cl-card-head">
                    <div class="cl-name-row">
                        <a href="{{ route('leads.show', $lead) }}" class="cl-name" style="text-decoration:none;color:inherit;">{{ $lead->name }}</a>
                        @if($lead->status)
                        <span class="cl-status" style="background:{{ $lead->status->color }}20;color:{{ $lead->status->color }};">{{ $lead->status->display_name }}</span>
                        @endif
                    </div>
                </div>

                {{-- Belangrijkste gegevens --}}
                <div class="cl-meta">
                    @if($lead->event_date)
                        🎉 <strong>{{ $lead->event_date->isoFormat('dd D MMM YYYY') }}</strong>
                        @if($lead->eventType) · {{ $lead->eventType->name }} @endif
                        <br>
                    @endif
                    @if($lead->event_location || $lead->event_city)
                        📍 {{ collect([$lead->event_location, $lead->event_city])->filter()->implode(' · ') }}<br>
                    @endif
                    @if($lead->total_price > 0)
                        💰 <strong>€ {{ number_format($lead->total_price, 0, ',', '.') }}</strong>
                    @endif

                    @if($lead->follow_up_at)
                        <div>
                            <span class="cl-followup-pill {{ $fuClass }}">
                                ⏰ {{ $lead->follow_up_at->isSameDay($today) ? 'Vandaag' : $lead->follow_up_at->isoFormat('dd D MMM') }}
                            </span>
                        </div>
                    @endif
                </div>

                {{-- Laatste notitie --}}
                @if($laatsteNotitie)
                <div class="cl-lastnote">💬 {{ $laatsteNotitie->description }}</div>
                @endif

                {{-- Grote CTA's --}}
                <div class="cl-cta-row">
                    <a href="{{ $lead->phone ? 'tel:' . $lead->phone : '#' }}" class="cl-cta bel" @if(!$lead->phone) aria-disabled="true" @endif>📞 Bellen</a>
                    <a href="{{ $waNr ? 'https://wa.me/' . $waNr : '#' }}" target="_blank" rel="noopener" class="cl-cta wa" @if(!$waNr) aria-disabled="true" @endif>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="#fff"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.86 9.86 0 0 0 4.74 1.21h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.91-7.02A9.87 9.87 0 0 0 12.04 2zm0 18.15h-.01a8.23 8.23 0 0 1-4.19-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.18 8.18 0 0 1-1.26-4.38c0-4.54 3.7-8.23 8.24-8.23 2.2 0 4.27.86 5.82 2.42a8.19 8.19 0 0 1 2.41 5.83c0 4.54-3.7 8.22-8.22 8.22zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.16.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.12-1.04-.38-1.99-1.22-.74-.66-1.23-1.47-1.37-1.72-.14-.25-.02-.39.11-.51.11-.11.25-.29.37-.43.12-.14.16-.25.25-.41.08-.17.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.22.25-.86.84-.86 2.06s.88 2.39 1 2.55c.12.17 1.74 2.66 4.22 3.73.59.26 1.05.41 1.41.52.59.19 1.13.16 1.55.1.47-.07 1.47-.6 1.67-1.18.21-.58.21-1.07.14-1.17-.06-.11-.22-.17-.46-.29z"/></svg>
                        WhatsApp
                    </a>
                    <a href="{{ $lead->email ? 'mailto:' . $lead->email : '#' }}" class="cl-cta mail" @if(!$lead->email) aria-disabled="true" @endif title="Mail">✉️</a>
                </div>

                {{-- Follow-up snelkeuze --}}
                <div class="cl-fu-row">
                    <span class="cl-fu-label">⏰ Volgende belmoment</span>
                    <form method="POST" action="{{ route('leads.follow-up', $lead) }}" style="display:inline;">
                        @csrf @method('PATCH')
                        <button type="submit" name="follow_up_at" value="{{ now()->addDay()->toDateString() }}" class="cl-fu-chip">Morgen</button>
                    </form>
                    <form method="POST" action="{{ route('leads.follow-up', $lead) }}" style="display:inline;">
                        @csrf @method('PATCH')
                        <button type="submit" name="follow_up_at" value="{{ now()->addDays(3)->toDateString() }}" class="cl-fu-chip">+3 dgn</button>
                    </form>
                    <form method="POST" action="{{ route('leads.follow-up', $lead) }}" style="display:inline;">
                        @csrf @method('PATCH')
                        <button type="submit" name="follow_up_at" value="{{ now()->addWeek()->toDateString() }}" class="cl-fu-chip">+1 wk</button>
                    </form>
                    <form method="POST" action="{{ route('leads.follow-up', $lead) }}" style="display:inline;">
                        @csrf @method('PATCH')
                        <input type="date" name="follow_up_at" class="cl-fu-date"
                               value="{{ $lead->follow_up_at?->toDateString() }}"
                               onchange="this.form.submit()">
                    </form>
                </div>

                {{-- Notitie / Akkoord / Afwijzen --}}
                <div class="cl-bottom">
                    <button type="button" class="cl-btn-sub" onclick="clToggleNote(this)">💬 Notitie</button>
                    <form method="POST" action="{{ route('leads.akkoord', $lead) }}" style="flex:1;display:flex;">
                        @csrf
                        <button type="button" class="cl-btn-sub akkoord" style="flex:1;"
                                onclick="crmConfirm('{{ addslashes($lead->name) }} akkoord? Status wordt Gewonnen — omzetten naar boeking doe je via de computer.', () => this.closest('form').submit()); return false;">
                            ✅ Akkoord
                        </button>
                    </form>
                    <form method="POST" action="{{ route('leads.afwijzen', $lead) }}" style="display:flex;">
                        @csrf
                        <button type="button" class="cl-btn-sub afwijzen"
                                onclick="crmConfirm('{{ addslashes($lead->name) }} afwijzen en archiveren?', () => this.closest('form').submit()); return false;"
                                title="Afwijzen">✕</button>
                    </form>
                </div>

                {{-- Notitie-formulier (uitklap) --}}
                <form method="POST" action="{{ route('leads.add-note', $lead) }}" class="cl-note-form">
                    @csrf
                    <textarea name="note" required maxlength="2000" placeholder="bijv. Gebeld — voicemail ingesproken. Wil eerst overleggen met partner…"></textarea>
                    <button type="submit" class="cl-note-save">💾 Notitie opslaan</button>
                </form>

            </div>
            @endforeach
        </div>
        @endif
    @endforeach

</div>

<script>
function clToggleNote(btn) {
    const card = btn.closest('.cl-card');
    const form = card.querySelector('.cl-note-form');
    const isOpen = form.classList.toggle('open');
    if (isOpen) form.querySelector('textarea').focus();
}

// Client-side zoeken
document.getElementById('cl-search-input').addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('.cl-card').forEach(card => {
        card.style.display = !q || card.dataset.search.includes(q) ? '' : 'none';
    });
    // Verberg lege groepen
    document.querySelectorAll('.cl-group').forEach(group => {
        const visible = [...group.querySelectorAll('.cl-card')].some(c => c.style.display !== 'none');
        group.style.display = visible ? '' : 'none';
    });
});
</script>
@endsection
