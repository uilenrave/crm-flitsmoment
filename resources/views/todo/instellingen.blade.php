@extends('todo.layout')

@section('inhoud')
    <div class="kop">
        <div>
            <h1>Instellingen</h1>
            <p>wanneer het CRM je een taak mag geven</p>
        </div>
        <button class="menuknop" onclick="menuOpen()" aria-label="Menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>
    </div>

    <div id="lijst">
        @if (session('bewaard'))
            <div class="melding">Bewaard. De volgende sync rekent met deze waarden.</div>
        @endif

        <form method="POST" action="{{ route('todo.instellingen.opslaan') }}">
            @csrf
            <div class="detail-grid" style="max-width:520px">
                <div class="veld">
                    <label for="offer_followup_days">Offerte stil na … dagen</label>
                    <input type="number" id="offer_followup_days" name="offer_followup_days" min="1" max="60"
                           value="{{ old('offer_followup_days', $account->todoSetting('offer_followup_days')) }}">
                </div>
                <div class="veld">
                    <label for="gallery_days">Galerij … dagen na de klus</label>
                    <input type="number" id="gallery_days" name="gallery_days" min="0" max="60"
                           value="{{ old('gallery_days', $account->todoSetting('gallery_days')) }}">
                </div>
                <div class="veld">
                    <label for="shift_days">Open shift … dagen vooruit</label>
                    <input type="number" id="shift_days" name="shift_days" min="1" max="90"
                           value="{{ old('shift_days', $account->todoSetting('shift_days')) }}">
                </div>
            </div>

            <div class="detail-acties">
                <button type="submit" class="knopje">Bewaren</button>
            </div>
        </form>

        <p class="leeg" style="max-width:60ch">
            De sync draait elk uur. Een taak die jij wegvinkt komt nooit terug, ook al blijft de situatie in het CRM bestaan.
            Lost het CRM iets zelf op — galerij ingevuld, offerte geaccepteerd, medewerker gekoppeld — dan vinkt de app de taak voor je af.
        </p>
    </div>
@endsection
