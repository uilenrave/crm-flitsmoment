{{-- Hulp-link + formulier, getoond onder elk van de 3 ontwerp-opties zodra er gekozen is.
     Hergebruikt het bestaande briefing-formulier (submitStripInput) — geen apart pad nodig. --}}
<div class="strip-email-callout" style="background:linear-gradient(135deg,#ede9fe,#ddd6fe);border-color:#c4b5fd;">
    <div class="strip-email-callout-title" style="color:#5b21b6;">🙋 Hulp nodig?</div>
    <div class="strip-email-callout-text" style="color:#5b21b6;">
        Geen probleem — vraag hier gratis hulp van onze vormgeving en we maken een ontwerp op maat.
    </div>
    <div style="margin-top:.75rem;">
        <a href="#strip-help-form" class="strip-btn strip-btn-secondary" id="strip-help-toggle"
           onclick="document.getElementById('strip-help-form').style.display='block';document.getElementById('strip-help-toggle').style.display='none';return false;">
            Vraag hulp aan →
        </a>
    </div>
</div>

<div id="strip-help-form" class="strip-form-block" style="display:none;margin-top:1rem;">
    <form method="POST" action="{{ route('portal.strip-input', $booking->public_token) }}" enctype="multipart/form-data">
        @csrf
        <p class="strip-form-help">Beschrijf je wensen en upload eventueel je logo, uitnodiging of huisstijl-bestanden — wij nemen het ontwerp dan volledig van je over.</p>
        <label>Wensen & tekst</label>
        <textarea name="strip_input_text" rows="4" maxlength="3000" placeholder="Bijv. naam bruidspaar, datum, kleuren van het thema, gewenste tekst..."></textarea>
        <label>Bestanden (optioneel, max 5)</label>
        <input type="file" name="strip_input_files[]" multiple accept="image/jpeg,image/png,image/gif,image/webp,application/pdf">
        <div class="strip-actions">
            <button type="submit" class="strip-btn strip-btn-primary strip-btn-full">📨 Versturen</button>
        </div>
    </form>
</div>
