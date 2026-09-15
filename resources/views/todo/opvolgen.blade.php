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

    <div id="lijst">
        @forelse ($leads as $lead)
            @include('todo._lead', ['lead' => $lead])
        @empty
            <p class="leeg">Geen leads die om opvolging vragen. Alles bij.</p>
        @endforelse
    </div>
@endsection

@push('scripts')
<script>
function leadUrl(id, pad) {
    return @json(route('todo.leads.notitie', ['lead' => '__ID__'])).replace('__ID__', id).replace(/notitie$/, pad);
}

function knopBevestig(knop, tekst) {
    const oud = knop.textContent;
    knop.textContent = tekst;
    knop.disabled = true;
    setTimeout(() => { knop.textContent = oud; knop.disabled = false; }, 1600);
}

function leadNotitie(knop, id) {
    const veld = document.getElementById('notitie-' + id);
    const tekst = veld.value.trim();
    if (!tekst) { veld.focus(); return; }

    verstuur(leadUrl(id, 'notitie'), 'POST', { note: tekst })
        .then(() => { veld.value = ''; knopBevestig(knop, 'Staat in het CRM ✓'); })
        .catch(() => alert('Bewaren lukte niet. Probeer het nog eens.'));
}

function leadContact(knop, id, type) {
    const veld = document.getElementById('notitie-' + id);
    const tekst = veld.value.trim();

    verstuur(leadUrl(id, 'contact'), 'POST', { contact_type: type, note: tekst || null })
        .then(() => { veld.value = ''; knopBevestig(knop, 'Gelogd ✓'); })
        .catch(() => alert('Loggen lukte niet. Probeer het nog eens.'));
}

function leadDatum(id, dagen, datum) {
    const data = dagen ? { dagen: dagen } : { follow_up_at: datum || null };

    verstuur(leadUrl(id, 'datum'), 'PATCH', data).then(zachtHerladen);
}
</script>
@endpush
