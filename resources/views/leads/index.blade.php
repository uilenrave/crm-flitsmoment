@extends('layouts.app')
@section('title', 'Leads')

@section('actions')
    <a href="{{ route('leads.create') }}" class="btn btn-primary">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Nieuwe lead
    </a>
@endsection

@push('styles')
<style>
.kans-select { padding: .25rem .5rem; border: 1px solid #e2e8f0; border-radius: .375rem; font-size: .78rem; font-weight: 600; background: #fff; color: #94a3b8; cursor: pointer; transition: opacity .15s; }
.kans-select:focus { outline: none; box-shadow: 0 0 0 2px rgba(124,58,237,.12); }
.kans-select.saving { opacity: .5; pointer-events: none; }
.fu-badge { cursor: pointer; }
.fu-badge:hover { opacity: .8; }

/* Laatste-contact chip onder de naam */
.lead-lastcontact { margin-top: .2rem; font-size: .72rem; color: #64748b; }
.lead-lastcontact:empty { display: none; }
.lead-ico-btn.ico-contact { color: #0369a1; }

/* Contact-log modal */
.lc-overlay { display: none; position: fixed; inset: 0; background: rgba(15,23,42,.5); z-index: 1000; align-items: flex-start; justify-content: center; padding: 5vh 1rem; }
.lc-overlay.open { display: flex; }
.lc-modal { background: #fff; border-radius: .9rem; width: 100%; max-width: 460px; box-shadow: 0 20px 50px rgba(0,0,0,.25); overflow: hidden; max-height: 88vh; display: flex; flex-direction: column; }
.lc-head { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; border-bottom: 1px solid #f1f5f9; }
.lc-head h3 { margin: 0; font-size: 1rem; font-weight: 700; color: #1e293b; }
.lc-close { background: none; border: none; font-size: 1.4rem; line-height: 1; color: #94a3b8; cursor: pointer; }
.lc-body { padding: 1.1rem 1.25rem; overflow-y: auto; }
.lc-quick { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: .7rem; }
.lc-qbtn { flex: 1 1 auto; min-width: 120px; padding: .55rem .6rem; border: 1px solid #e2e8f0; border-radius: .55rem; background: #f8fafc; font-size: .82rem; font-weight: 600; color: #334155; cursor: pointer; }
.lc-qbtn:hover { background: #eef2f7; border-color: #cbd5e1; }
.lc-qbtn:disabled { opacity: .5; cursor: default; }
.lc-note { width: 100%; box-sizing: border-box; border: 1px solid #e2e8f0; border-radius: .55rem; padding: .55rem .7rem; font-size: .85rem; resize: vertical; min-height: 52px; margin-bottom: 1rem; font-family: inherit; }
.lc-hint { font-size: .72rem; color: #94a3b8; margin: 0 0 .5rem; }
.lc-history { display: flex; flex-direction: column; gap: .5rem; border-top: 1px solid #f1f5f9; padding-top: .8rem; }
.lc-item { display: flex; gap: .6rem; font-size: .82rem; }
.lc-item .lc-ico { flex: 0 0 auto; }
.lc-item .lc-main { min-width: 0; }
.lc-item .lc-label { font-weight: 700; color: #1e293b; }
.lc-item .lc-meta { color: #94a3b8; font-size: .72rem; }
.lc-item .lc-note { color: #475569; white-space: pre-wrap; word-break: break-word; }
.lc-empty { color: #94a3b8; font-size: .82rem; text-align: center; padding: .6rem; }
.fu-form { display: none; align-items: center; gap: .3rem; flex-wrap: nowrap; }
.fu-form input[type=date] {
    padding: .25rem .4rem;
    font-size: .78rem;
    border: 1px solid #d1d5db;
    border-radius: .375rem;
    background: #fff;
    font-family: inherit;
    width: 130px;
}
.fu-form input[type=date]:focus { outline: none; border-color: #2563eb; }
.fu-save { background: #fcd34d; border: none; border-radius: .3rem; padding: .2rem .5rem; font-size: .75rem; font-weight: 700; cursor: pointer; line-height: 1.4; }
.fu-cancel { background: #e2e8f0; border: none; border-radius: .3rem; padding: .2rem .45rem; font-size: .75rem; cursor: pointer; line-height: 1.4; color: #64748b; }

/* Quick action icon buttons */
.lead-actions { display: inline-flex; align-items: center; gap: .3rem; flex-wrap: nowrap; justify-content: flex-end; }
.lead-actions form { display: inline; margin: 0; }
.lead-ico-btn {
    width: 1.9rem; height: 1.9rem;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: .4rem; border: 1px solid transparent;
    cursor: pointer; background: #fff; padding: 0;
    transition: transform .08s ease, box-shadow .15s ease;
    text-decoration: none;
}
.lead-ico-btn:hover { transform: translateY(-1px); box-shadow: 0 2px 5px rgba(0,0,0,.08); }
.lead-ico-btn svg { width: 1rem; height: 1rem; }
.lead-ico-btn.ico-akkoord  { border-color: #86efac; color: #16a34a; }
.lead-ico-btn.ico-akkoord:hover  { background: #f0fdf4; }
.lead-ico-btn.ico-afwijzen { border-color: #fca5a5; color: #dc2626; }
.lead-ico-btn.ico-afwijzen:hover { background: #fef2f2; }
.lead-ico-btn.ico-delete   { border-color: #cbd5e1; color: #64748b; }
.lead-ico-btn.ico-delete:hover   { background: #f1f5f9; color: #dc2626; border-color: #fca5a5; }
.lead-ico-btn.ico-view     { border-color: #e2e8f0; color: #475569; }
.lead-ico-btn.ico-view:hover     { background: #f8fafc; color: #1e293b; }
</style>
@endpush

@section('content')

{{-- Tabs --}}
<div class="page-tabs" style="display:flex;gap:.25rem;margin-bottom:1.25rem;border-bottom:2px solid #e2e8f0;overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none;">
    <a href="{{ route('leads.index', array_merge(request()->except('tab','page'), ['tab'=>'actief'])) }}"
       style="padding:.6rem 1.25rem;font-size:.875rem;font-weight:500;text-decoration:none;border-bottom:2px solid {{ $tab === 'actief' ? '#fcd34d' : 'transparent' }};margin-bottom:-2px;color:{{ $tab === 'actief' ? '#fcd34d' : '#64748b' }};">
        Actieve leads
        <span style="background:#e2e8f0;color:#475569;border-radius:9999px;padding:.1rem .5rem;font-size:.75rem;margin-left:.4rem;">{{ $aantalActief }}</span>
    </a>
    <a href="{{ route('leads.index', array_merge(request()->except('tab','page'), ['tab'=>'archief'])) }}"
       style="padding:.6rem 1.25rem;font-size:.875rem;font-weight:500;text-decoration:none;border-bottom:2px solid {{ $tab === 'archief' ? '#fcd34d' : 'transparent' }};margin-bottom:-2px;color:{{ $tab === 'archief' ? '#fcd34d' : '#64748b' }};">
        Archief
        <span style="background:#e2e8f0;color:#475569;border-radius:9999px;padding:.1rem .5rem;font-size:.75rem;margin-left:.4rem;">{{ $aantalArchief }}</span>
    </a>
</div>

<div class="card">
    {{-- Filters --}}
    <form method="GET" style="display:flex;gap:.75rem;margin-bottom:1rem;flex-wrap:wrap;">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <input type="text" name="zoeken" value="{{ request('zoeken') }}" placeholder="Zoeken op naam, e-mail, nummer..." style="flex:1;min-width:200px;">
        @if($tab === 'actief')
        <select name="status" style="min-width:160px;">
            <option value="">Alle statussen</option>
            @foreach($statussen as $status)
                <option value="{{ $status->id }}" @selected(request('status') == $status->id)>{{ $status->display_name }}</option>
            @endforeach
        </select>
        @endif
        <button type="submit" class="btn btn-secondary">Zoeken</button>
        @if(request()->hasAny(['zoeken','status']))
            <a href="{{ route('leads.index', ['tab' => $tab]) }}" class="btn btn-secondary">Wis filters</a>
        @endif
    </form>

    @php
        // Icoon + label per contactmoment-type (spiegelt LeadController::CONTACT_META)
        $contactMeta = [
            'call_no_answer' => ['icon' => '📞', 'label' => 'Geen gehoor'],
            'call_answered'  => ['icon' => '📞', 'label' => 'Gesproken'],
            'email'          => ['icon' => '✉️', 'label' => 'Gemaild'],
            'contact_other'  => ['icon' => '💬', 'label' => 'Contact'],
        ];
    @endphp

    @php
        // Klikbare sorteerkop: pijltje toont actieve kolom + richting, klik wisselt richting
        $sortCol = request('sort', 'event_date');
        $sortDir = request('dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $sortLink = function ($col, $label) use ($sortCol, $sortDir) {
            $active  = $sortCol === $col;
            $nextDir = ($active && $sortDir === 'asc') ? 'desc' : 'asc';
            $arrow   = $active ? ($sortDir === 'asc' ? '▲' : '▼') : '↕';
            $url     = request()->fullUrlWithQuery(['sort' => $col, 'dir' => $nextDir, 'page' => 1]);
            $opacity = $active ? '.85' : '.3';
            return '<a href="'.e($url).'" style="color:inherit;text-decoration:none;white-space:nowrap;cursor:pointer;">'
                 . e($label) . ' <span style="font-size:.78em;opacity:'.$opacity.';">'.$arrow.'</span></a>';
        };

        // Conversiekans: waarde => label + kleuren
        $kansOpties = [
            1 => ['label' => 'Laag',      'kleur' => '#dc2626', 'bg' => '#fef2f2'],
            2 => ['label' => 'Gemiddeld', 'kleur' => '#d97706', 'bg' => '#fffbeb'],
            3 => ['label' => 'Hoog',      'kleur' => '#16a34a', 'bg' => '#f0fdf4'],
        ];
    @endphp
    <div class="table-wrap">
        <table class="table-card-mobile">
            <thead>
                <tr>
                    <th>{!! $sortLink('lead_number', 'Nummer') !!}</th>
                    <th>{!! $sortLink('name', 'Naam') !!}</th>
                    <th>{!! $sortLink('email', 'E-mail') !!}</th>
                    <th>{!! $sortLink('event_date', 'Event datum') !!}</th>
                    <th>Status</th>
                    <th>{!! $sortLink('conversion_chance', 'Kans') !!}</th>
                    @if($tab === 'archief')
                    <th>Reden</th>
                    <th>{!! $sortLink('archived_at', 'Gearchiveerd') !!}</th>
                    @else
                    <th>{!! $sortLink('follow_up_at', 'Opvolging') !!}</th>
                    <th>{!! $sortLink('created_at', 'Aangemaakt') !!}</th>
                    @endif
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($leads as $lead)
                <tr>
                    <td data-label="Nummer" style="font-size:.8rem;color:#64748b;">{{ $lead->lead_number }}</td>
                    <td data-label="Naam">
                        <strong>{{ $lead->name }}</strong>
                        @if($lead->product_label)
                            <br><span class="badge" style="background:#ede9fe;color:#7c3aed;">{{ $lead->product_label }}</span>
                        @endif
                        <div id="lastcontact-{{ $lead->id }}" class="lead-lastcontact">
                            @if($lead->latestContact)
                                @php($m = $contactMeta[$lead->latestContact->activity_type] ?? ['icon' => '💬', 'label' => 'Contact'])
                                <span title="Laatste contact: {{ $lead->latestContact->created_at->format('d-m-Y H:i') }}">{{ $m['icon'] }} {{ $m['label'] }} · {{ $lead->latestContact->created_at->diffForHumans(['short' => true]) }}</span>
                            @endif
                        </div>
                    </td>
                    <td data-label="E-mail">{{ $lead->email ?? '—' }}</td>
                    <td data-label="Eventdatum">{{ $lead->event_date?->format('d M Y') ?? '—' }}</td>
                    <td data-label="Status">
                        @if($lead->status)
                            <span class="badge" style="background:{{ $lead->status->color }}20;color:{{ $lead->status->color }};">
                                {{ $lead->status->display_name }}
                            </span>
                        @endif
                    </td>
                    <td data-label="Kans">
                        <select class="kans-select" data-url="{{ route('leads.kans', $lead) }}" onchange="kansChange(this)">
                            <option value="">—</option>
                            @foreach($kansOpties as $val => $opt)
                                <option value="{{ $val }}" @selected($lead->conversion_chance == $val)>{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                    </td>
                    @if($tab === 'archief')
                    <td data-label="Reden">
                        @if($lead->archive_reason === 'won')
                            <span class="badge" style="background:#dcfce7;color:#16a34a;">Gewonnen</span>
                        @elseif($lead->archive_reason === 'lost')
                            <span class="badge" style="background:#fee2e2;color:#dc2626;">Afgewezen</span>
                        @else
                            —
                        @endif
                    </td>
                    <td data-label="Gearchiveerd" style="font-size:.8rem;color:#64748b;">{{ $lead->archived_at?->format('d-m-Y') }}</td>
                    @else
                    {{-- Opvolging inline edit --}}
                    <td data-label="Opvolging">
                        <div id="fu-cell-{{ $lead->id }}">
                            {{-- Badge (klik om te bewerken) --}}
                            <span class="fu-badge" onclick="fuEdit({{ $lead->id }})">
                                @if($lead->follow_up_at)
                                    @if($lead->follow_up_at->isPast() && !$lead->follow_up_at->isToday())
                                        <span style="font-size:.72rem;background:#fee2e2;color:#dc2626;padding:.15rem .45rem;border-radius:999px;font-weight:700;white-space:nowrap;">📅 {{ $lead->follow_up_at->format('d M') }} <span style="opacity:.7;">achterstallig</span></span>
                                    @elseif($lead->follow_up_at->isToday())
                                        <span style="font-size:.72rem;background:#fef3c7;color:#d97706;padding:.15rem .45rem;border-radius:999px;font-weight:700;white-space:nowrap;">📅 vandaag</span>
                                    @else
                                        <span style="font-size:.72rem;background:#f0f9ff;color:#0369a1;padding:.15rem .45rem;border-radius:999px;font-weight:600;white-space:nowrap;">📅 {{ $lead->follow_up_at->format('d M') }}</span>
                                    @endif
                                @else
                                    <span style="font-size:.75rem;color:#94a3b8;">+ instellen</span>
                                @endif
                            </span>
                            {{-- Inline edit form --}}
                            <form class="fu-form" id="fu-form-{{ $lead->id }}"
                                  method="POST" action="{{ route('leads.follow-up', $lead) }}">
                                @csrf
                                @method('PATCH')
                                <input type="date" name="follow_up_at"
                                       value="{{ $lead->follow_up_at?->format('Y-m-d') }}"
                                       onkeydown="if(event.key==='Escape')fuCancel({{ $lead->id }})">
                                <button type="submit" class="fu-save">✓</button>
                                <button type="button" class="fu-cancel" onclick="fuCancel({{ $lead->id }})">✕</button>
                            </form>
                        </div>
                    </td>
                    <td data-label="Aangemaakt" style="font-size:.8rem;color:#64748b;">{{ $lead->created_at->format('d-m-Y') }}</td>
                    @endif
                    <td class="no-label">
                        <div class="lead-actions">
                            {{-- Contact loggen (bellen/mailen) vanuit het overzicht --}}
                            <button type="button" class="lead-ico-btn ico-contact" title="Contact loggen"
                                    onclick="leadContactOpen(this)"
                                    data-name="{{ addslashes($lead->name) }}"
                                    data-list-url="{{ route('leads.contact-log', $lead) }}"
                                    data-log-url="{{ route('leads.log-contact', $lead) }}"
                                    data-chip="lastcontact-{{ $lead->id }}">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            </button>
                            @if($tab !== 'archief')
                                {{-- Akkoord (gewonnen) --}}
                                <form method="POST" action="{{ route('leads.akkoord', $lead) }}">
                                    @csrf
                                    <button type="button" class="lead-ico-btn ico-akkoord" title="Geaccepteerd"
                                            onclick="crmConfirm('Lead &quot;{{ addslashes($lead->name) }}&quot; markeren als geaccepteerd?', () => this.closest('form').submit())">
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                </form>
                                {{-- Afwijzen --}}
                                <form method="POST" action="{{ route('leads.afwijzen', $lead) }}">
                                    @csrf
                                    <button type="button" class="lead-ico-btn ico-afwijzen" title="Afwijzen"
                                            onclick="crmConfirm('Lead &quot;{{ addslashes($lead->name) }}&quot; afwijzen?', () => this.closest('form').submit())">
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </form>
                            @endif
                            {{-- Verwijderen --}}
                            <form method="POST" action="{{ route('leads.destroy', $lead) }}">
                                @csrf
                                @method('DELETE')
                                <button type="button" class="lead-ico-btn ico-delete" title="Verwijderen"
                                        onclick="crmConfirm('Lead &quot;{{ addslashes($lead->name) }}&quot; permanent verwijderen?', () => this.closest('form').submit())">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
                                </button>
                            </form>
                            {{-- Bekijken --}}
                            <a href="{{ route('leads.show', $lead) }}" class="lead-ico-btn ico-view" title="Bekijk">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" style="text-align:center;padding:2rem;color:#64748b;">
                        {{ $tab === 'archief' ? 'Geen gearchiveerde leads.' : 'Geen actieve leads.' }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $leads->links() }}
</div>

{{-- Contact-log modal (herbruikt voor elke lead) --}}
<div id="lc-overlay" class="lc-overlay" onclick="if(event.target===this)leadContactClose()">
    <div class="lc-modal">
        <div class="lc-head">
            <h3>📞 Contact — <span id="lc-name"></span></h3>
            <button type="button" class="lc-close" onclick="leadContactClose()">&times;</button>
        </div>
        <div class="lc-body">
            <p class="lc-hint">Kies wat er gebeurde. Een notitie (optioneel) helpt je de volgende keer inhaken.</p>
            <textarea id="lc-note" class="lc-note" placeholder="Notitie — bijv. 'gebeld, spreekt vrijdag met partner, terugbellen maandag'"></textarea>
            <div class="lc-quick">
                <button type="button" class="lc-qbtn" data-type="call_no_answer" onclick="leadContactLog('call_no_answer')">📞 Geen gehoor</button>
                <button type="button" class="lc-qbtn" data-type="call_answered" onclick="leadContactLog('call_answered')">📞 Gesproken</button>
                <button type="button" class="lc-qbtn" data-type="email" onclick="leadContactLog('email')">✉️ Gemaild</button>
                <button type="button" class="lc-qbtn" data-type="contact_other" onclick="leadContactLog('contact_other')">💬 Overig</button>
            </div>
            <div id="lc-history" class="lc-history"></div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// ── Contactmoment loggen + geschiedenis, vanuit het overzicht ──
const LC_CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
let lcLead = null; // { listUrl, logUrl, chip }

function leadContactOpen(btn) {
    lcLead = { listUrl: btn.dataset.listUrl, logUrl: btn.dataset.logUrl, chip: btn.dataset.chip };
    document.getElementById('lc-name').textContent = btn.dataset.name;
    document.getElementById('lc-note').value = '';
    const hist = document.getElementById('lc-history');
    hist.innerHTML = '<div class="lc-empty">Laden…</div>';
    document.getElementById('lc-overlay').classList.add('open');
    fetch(lcLead.listUrl, { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(d => lcRenderHistory(d.items || []))
        .catch(() => { hist.innerHTML = '<div class="lc-empty">Kon geschiedenis niet laden.</div>'; });
}

function leadContactClose() {
    document.getElementById('lc-overlay').classList.remove('open');
    lcLead = null;
}

function lcRenderHistory(items) {
    const hist = document.getElementById('lc-history');
    hist.innerHTML = items.length ? items.map(lcItemHtml).join('') : '<div class="lc-empty">Nog geen contactmomenten gelogd.</div>';
}

function lcItemHtml(a) {
    const note = a.note ? '<div class="lc-note">' + lcEsc(a.note) + '</div>' : '';
    const who = a.user ? ' · ' + lcEsc(a.user) : '';
    return '<div class="lc-item"><div class="lc-ico">' + a.icon + '</div><div class="lc-main">'
        + '<div class="lc-label">' + lcEsc(a.label) + '</div>'
        + '<div class="lc-meta" title="' + lcEsc(a.exact) + '">' + lcEsc(a.ago) + who + '</div>'
        + note + '</div></div>';
}

function leadContactLog(type) {
    if (!lcLead) return;
    const note = document.getElementById('lc-note').value.trim();
    const btns = document.querySelectorAll('.lc-qbtn');
    btns.forEach(b => b.disabled = true);
    const fd = new FormData();
    fd.append('contact_type', type);
    if (note) fd.append('note', note);
    fetch(lcLead.logUrl, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': LC_CSRF, 'Accept': 'application/json' },
        body: fd,
    })
    .then(r => r.json())
    .then(d => {
        btns.forEach(b => b.disabled = false);
        if (!d.ok) return;
        const a = d.activity;
        document.getElementById('lc-note').value = '';
        const hist = document.getElementById('lc-history');
        if (hist.querySelector('.lc-empty')) hist.innerHTML = '';
        hist.insertAdjacentHTML('afterbegin', lcItemHtml(a));
        const chip = lcLead.chip && document.getElementById(lcLead.chip);
        if (chip) chip.innerHTML = '<span title="Laatste contact: ' + lcEsc(a.exact) + '">' + a.icon + ' ' + lcEsc(a.short) + ' · zojuist</span>';
    })
    .catch(() => btns.forEach(b => b.disabled = false));
}

function lcEsc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }
document.addEventListener('keydown', e => { if (e.key === 'Escape' && document.getElementById('lc-overlay').classList.contains('open')) leadContactClose(); });

function fuEdit(id) {
    document.querySelector('#fu-cell-' + id + ' .fu-badge').style.display = 'none';
    var form = document.getElementById('fu-form-' + id);
    form.style.display = 'flex';
    form.querySelector('input[type=date]').focus();
}
function fuCancel(id) {
    document.querySelector('#fu-cell-' + id + ' .fu-badge').style.display = '';
    document.getElementById('fu-form-' + id).style.display = 'none';
}

// ── Conversiekans: AJAX-dropdown vanuit het overzicht ──
const KANS_CSRF  = '{{ csrf_token() }}';
const KANS_KLEUR = {
    '1': { c: '#dc2626', b: '#fef2f2' },
    '2': { c: '#d97706', b: '#fffbeb' },
    '3': { c: '#16a34a', b: '#f0fdf4' },
};
function kansStyle(sel) {
    const k = KANS_KLEUR[sel.value];
    if (k) {
        sel.style.color = k.c; sel.style.background = k.b;
        sel.style.borderColor = k.c + '55'; sel.style.fontWeight = '700';
    } else {
        sel.style.color = '#94a3b8'; sel.style.background = '#fff';
        sel.style.borderColor = '#e2e8f0'; sel.style.fontWeight = '600';
    }
}
function kansChange(sel) {
    sel.classList.add('saving');
    fetch(sel.dataset.url, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': KANS_CSRF },
        body: JSON.stringify({ conversion_chance: sel.value || null }),
    })
    .then(r => r.json())
    .then(() => { sel.classList.remove('saving'); kansStyle(sel); })
    .catch(() => { sel.classList.remove('saving'); alert('Opslaan mislukt, probeer opnieuw.'); });
}
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.kans-select').forEach(kansStyle);
});
</script>
@endpush

@endsection
