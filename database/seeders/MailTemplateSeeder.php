<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Scopes\AccountScope;
use App\Models\MailTemplate;
use Illuminate\Database\Seeder;

class MailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = $this->defaultTemplates();

        foreach (Account::all() as $account) {
            foreach ($templates as $tpl) {
                MailTemplate::withoutGlobalScope(AccountScope::class)
                    ->updateOrCreate(
                        ['account_id' => $account->id, 'key' => $tpl['key']],
                        array_merge($tpl, ['account_id' => $account->id])
                    );
            }
        }
    }

    /** Alleen de opgegeven template-keys (voor gerichte seeders die bestaande, mogelijk aangepaste templates niet mogen overschrijven). */
    public function only(array $keys): array
    {
        return array_values(array_filter($this->defaultTemplates(), fn (array $t) => in_array($t['key'], $keys, true)));
    }

    private function defaultTemplates(): array
    {
        return [

            // ─── Klantmails ───────────────────────────────────────

            [
                'key'            => 'customer_booking_confirmation',
                'name'           => 'Boekingsbevestiging',
                'description'    => 'Verstuurd zodra een nieuwe boeking wordt aangemaakt.',
                'recipient_type' => 'customer',
                'subject'        => 'Uw boeking is bevestigd — {{boeking_nummer}}',
                'body'           => $this->wrap('Boekingsbevestiging', '
<p>Hallo <strong>{{klant_voornaam}}</strong>,</p>
<p>Hartelijk dank voor uw boeking bij <strong>{{bedrijf_naam}}</strong>! We zijn blij dat u voor ons heeft gekozen.</p>

<table width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;border-collapse:collapse;">
  <tr style="background:#f8fafc;">
    <td style="padding:10px 14px;font-weight:600;border:1px solid #e2e8f0;width:40%;">Boekingsnummer</td>
    <td style="padding:10px 14px;border:1px solid #e2e8f0;">{{boeking_nummer}}</td>
  </tr>
  <tr>
    <td style="padding:10px 14px;font-weight:600;border:1px solid #e2e8f0;">Evenementdatum</td>
    <td style="padding:10px 14px;border:1px solid #e2e8f0;">{{event_datum}}</td>
  </tr>
  <tr style="background:#f8fafc;">
    <td style="padding:10px 14px;font-weight:600;border:1px solid #e2e8f0;">Locatie</td>
    <td style="padding:10px 14px;border:1px solid #e2e8f0;">{{event_locatie}}<br><small style="color:#64748b;">{{event_adres}}</small></td>
  </tr>
  <tr>
    <td style="padding:10px 14px;font-weight:600;border:1px solid #e2e8f0;">Totaalbedrag</td>
    <td style="padding:10px 14px;border:1px solid #e2e8f0;">{{totaal_prijs}}</td>
  </tr>
</table>

<p>Via de onderstaande knop kunt u uw boeking bekijken en betalen:</p>
<p style="text-align:center;margin:28px 0;">
  <a href="{{portal_link}}" style="background:#2563eb;color:#ffffff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:700;font-size:16px;display:inline-block;">Mijn boeking bekijken</a>
</p>

<p>Heeft u vragen? Neem gerust contact op.</p>
<p>Met vriendelijke groet,<br><strong>{{bedrijf_naam}}</strong></p>
                '),
            ],

            [
                'key'            => 'customer_gallery_ready',
                'name'           => 'Fotogalerij gereed',
                'description'    => "Verstuurd als de galerij-link aan de boeking wordt toegevoegd.",
                'recipient_type' => 'customer',
                'subject'        => 'Uw foto\'s van {{event_datum}} zijn klaar! 🎉',
                'body'           => $this->wrap("Uw foto's zijn klaar!", '
<p>Hallo <strong>{{klant_voornaam}}</strong>,</p>
<p>Wat een mooie dag was het! We hebben alle foto\'s van uw evenement op <strong>{{event_datum}}</strong> voor u klaarstaan.</p>

<p style="text-align:center;margin:28px 0;">
  <a href="{{gallery_link}}" style="background:#16a34a;color:#ffffff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:700;font-size:16px;display:inline-block;">📸 Bekijk de foto\'s</a>
</p>

<p>Geniet ervan en vergeet niet om de mooiste momenten te delen!</p>
<p>Met vriendelijke groet,<br><strong>{{bedrijf_naam}}</strong></p>
                '),
            ],

            [
                'key'            => 'customer_strip_design_added',
                'name'           => 'Fotostrip ontwerp ter beoordeling',
                'description'    => 'Verstuurd als het fotostrip ontwerp klaar is voor de klant.',
                'recipient_type' => 'customer',
                'subject'        => 'Uw fotostrip ontwerp staat klaar — {{boeking_nummer}}',
                'body'           => $this->wrap('Fotostrip ontwerp beoordelen', '
<p>Hallo <strong>{{klant_voornaam}}</strong>,</p>
<p>Goed nieuws! Het ontwerp voor uw fotostrip is klaar. We zijn benieuwd wat u ervan vindt.</p>

<p style="text-align:center;margin:28px 0;">
  <a href="{{portal_link}}" style="background:#2563eb;color:#ffffff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:700;font-size:16px;display:inline-block;">🎨 Bekijk het ontwerp</a>
</p>

<p>U kunt het ontwerp bekijken en direct <strong>goedkeuren</strong> of <strong>feedback</strong> geven via uw persoonlijke boekingspagina.</p>
<p>We horen graag van u!</p>
<p>Met vriendelijke groet,<br><strong>{{bedrijf_naam}}</strong></p>
                '),
            ],

            [
                'key'            => 'customer_strip_accepted',
                'name'           => 'Fotostrip geaccepteerd (klant bevestiging)',
                'description'    => "Verstuurd naar de klant als bevestiging dat het ontwerp goedgekeurd is.",
                'recipient_type' => 'customer',
                'subject'        => 'Ontwerp bevestigd — bedankt! ✓',
                'body'           => $this->wrap('Ontwerp goedgekeurd ✓', '
<p>Hallo <strong>{{klant_voornaam}}</strong>,</p>
<p>Top! We hebben uw goedkeuring ontvangen voor het fotostrip ontwerp van boeking <strong>{{boeking_nummer}}</strong>.</p>
<p>We gaan dit ontwerp inzetten op <strong>{{event_datum}}</strong>. Uw gasten zullen er zeker van genieten!</p>
<p>Heeft u nog vragen voor het evenement? Neem gerust contact op.</p>
<p>Tot dan,<br><strong>{{bedrijf_naam}}</strong></p>
                '),
            ],

            [
                'key'            => 'customer_photo_upsell',
                'name'           => 'Upsell gedrukte foto\'s (1 week na event)',
                'description'    => "Verstuurd 7 dagen na het evenement als upsell voor gedrukte foto's.",
                'recipient_type' => 'customer',
                'subject'        => 'Uw foto\'s van {{event_datum}} — ook thuis aan de muur? 🖼️',
                'body'           => $this->wrap("Nog eens genieten van uw foto's", '
<p>Hallo <strong>{{klant_voornaam}}</strong>,</p>
<p>Wat fijn dat we er bij mochten zijn op <strong>{{event_datum}}</strong>! We hopen dat iedereen heeft genoten van de photobooth.</p>
<p>Wist u dat u de favoriete foto\'s ook als <strong>mooie afdruk</strong> kunt bestellen? Zo heeft u een prachtige herinnering aan uw evenement, voor altijd aan de muur.</p>
<p>Neem contact op voor de mogelijkheden:</p>
<p style="text-align:center;margin:28px 0;">
  <a href="{{portal_link}}" style="background:#2563eb;color:#ffffff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:700;font-size:16px;display:inline-block;">Meer informatie</a>
</p>
<p>Met vriendelijke groet,<br><strong>{{bedrijf_naam}}</strong></p>
                '),
            ],

            // ─── Adminmails ────────────────────────────────────────

            [
                'key'            => 'admin_new_booking',
                'name'           => 'Nieuwe boeking (admin)',
                'description'    => 'Verstuurd naar jou als er een nieuwe boeking is aangemaakt.',
                'recipient_type' => 'admin',
                'subject'        => '🆕 Nieuwe boeking: {{boeking_nummer}} — {{klant_naam}}',
                'body'           => $this->wrap('Nieuwe boeking ontvangen', '
<p>Er is een nieuwe boeking aangemaakt:</p>
<table width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;border-collapse:collapse;">
  <tr style="background:#f8fafc;"><td style="padding:8px 12px;font-weight:600;border:1px solid #e2e8f0;width:40%;">Boekingsnummer</td><td style="padding:8px 12px;border:1px solid #e2e8f0;">{{boeking_nummer}}</td></tr>
  <tr><td style="padding:8px 12px;font-weight:600;border:1px solid #e2e8f0;">Klant</td><td style="padding:8px 12px;border:1px solid #e2e8f0;">{{klant_naam}}</td></tr>
  <tr style="background:#f8fafc;"><td style="padding:8px 12px;font-weight:600;border:1px solid #e2e8f0;">E-mail</td><td style="padding:8px 12px;border:1px solid #e2e8f0;">{{klant_email}}</td></tr>
  <tr><td style="padding:8px 12px;font-weight:600;border:1px solid #e2e8f0;">Event datum</td><td style="padding:8px 12px;border:1px solid #e2e8f0;">{{event_datum}}</td></tr>
  <tr style="background:#f8fafc;"><td style="padding:8px 12px;font-weight:600;border:1px solid #e2e8f0;">Locatie</td><td style="padding:8px 12px;border:1px solid #e2e8f0;">{{event_locatie}}</td></tr>
  <tr><td style="padding:8px 12px;font-weight:600;border:1px solid #e2e8f0;">Totaal</td><td style="padding:8px 12px;border:1px solid #e2e8f0;">{{totaal_prijs}}</td></tr>
</table>
<p><a href="{{portal_link}}">Portaal bekijken</a></p>
                '),
            ],

            [
                'key'            => 'admin_new_payment',
                'name'           => 'Nieuwe betaling (admin)',
                'description'    => 'Verstuurd als er een betaling is ontvangen via Mollie.',
                'recipient_type' => 'admin',
                'subject'        => '💳 Betaling ontvangen — {{boeking_nummer}} ({{klant_naam}})',
                'body'           => $this->wrap('Betaling ontvangen', '
<p>Er is een betaling ontvangen:</p>
<table width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;border-collapse:collapse;">
  <tr style="background:#f8fafc;"><td style="padding:8px 12px;font-weight:600;border:1px solid #e2e8f0;width:40%;">Boeking</td><td style="padding:8px 12px;border:1px solid #e2e8f0;">{{boeking_nummer}} — {{klant_naam}}</td></tr>
  <tr><td style="padding:8px 12px;font-weight:600;border:1px solid #e2e8f0;">Openstaand</td><td style="padding:8px 12px;border:1px solid #e2e8f0;">{{openstaand_bedrag}}</td></tr>
</table>
                '),
            ],

            [
                'key'            => 'admin_strip_accepted',
                'name'           => 'Fotostrip geaccepteerd (admin)',
                'description'    => 'Verstuurd als de klant het fotostrip ontwerp goedkeurt.',
                'recipient_type' => 'admin',
                'subject'        => '✅ Ontwerp geaccepteerd — {{klant_naam}} ({{boeking_nummer}})',
                'body'           => $this->wrap('Ontwerp goedgekeurd door klant', '
<p><strong>{{klant_naam}}</strong> heeft het fotostrip ontwerp goedgekeurd voor boeking <strong>{{boeking_nummer}}</strong>.</p>
<p>Het evenement is op <strong>{{event_datum}}</strong> bij <strong>{{event_locatie}}</strong>.</p>
<p>Het ontwerp is klaar voor gebruik!</p>
                '),
            ],

            [
                'key'            => 'admin_event_finished',
                'name'           => 'Event afgelopen — foto\'s versturen (admin)',
                'description'    => 'Verstuurd de dag na het evenement als herinnering om digitale foto\'s te sturen.',
                'recipient_type' => 'admin',
                'subject'        => '📸 Event gisteren afgelopen — digitale foto\'s versturen ({{klant_naam}})',
                'body'           => $this->wrap("Actie vereist: digitale foto's versturen", '
<p>Het evenement van <strong>{{klant_naam}}</strong> (boeking <strong>{{boeking_nummer}}</strong>) is gisteren afgelopen.</p>
<p>Vergeet niet de digitale foto\'s door te sturen naar: <strong>{{klant_email}}</strong></p>
<p>Locatie was: {{event_locatie}}, {{event_adres}}</p>
<p>Stuur ook de gallerij-link in het CRM zodat de klant een automatische mail krijgt.</p>
                '),
            ],

            [
                'key'            => 'admin_strip_reminder',
                'name'           => 'Fotostrip herinnering 7 dagen voor event (admin)',
                'description'    => 'Verstuurd 7 dagen voor het event als er nog geen fotostrip ontwerp is.',
                'recipient_type' => 'admin',
                'subject'        => '⚠️ 7 dagen voor event — nog geen fotostrip! ({{klant_naam}})',
                'body'           => $this->wrap('Fotostrip ontwerp nog niet gereed', '
<p>Let op: het evenement van <strong>{{klant_naam}}</strong> (boeking <strong>{{boeking_nummer}}</strong>) is over 7 dagen op <strong>{{event_datum}}</strong>.</p>
<p>Er is nog <strong>geen fotostrip ontwerp</strong> toegevoegd aan de boeking.</p>
<p>Locatie: <strong>{{event_locatie}}</strong>, {{event_adres}}</p>
<p>Vergeet niet het ontwerp te maken en ter beoordeling aan de klant te sturen!</p>
                '),
            ],

            [
                'key'            => 'admin_strip_input_received',
                'name'           => 'Fotostrip input aangeleverd (admin)',
                'description'    => 'Verstuurd als de klant input heeft aangeleverd voor het fotostrip ontwerp.',
                'recipient_type' => 'admin',
                'subject'        => '🎨 Input ontvangen van {{klant_naam}} — fotostrip {{boeking_nummer}}',
                'body'           => $this->wrap('Fotostrip input aangeleverd', '
<p><strong>{{klant_naam}}</strong> heeft input aangeleverd voor het fotostrip ontwerp van boeking <strong>{{boeking_nummer}}</strong>.</p>
<table width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;border-collapse:collapse;">
  <tr style="background:#f8fafc;"><td style="padding:8px 12px;font-weight:600;border:1px solid #e2e8f0;width:40%;">Event datum</td><td style="padding:8px 12px;border:1px solid #e2e8f0;">{{event_datum}}</td></tr>
  <tr><td style="padding:8px 12px;font-weight:600;border:1px solid #e2e8f0;">Locatie</td><td style="padding:8px 12px;border:1px solid #e2e8f0;">{{event_locatie}}</td></tr>
</table>
<p>Bekijk de aangeleverde input (omschrijving en/of bestanden) in het CRM en begin met ontwerpen.</p>
<p><a href="{{portal_link}}">Boeking bekijken in CRM</a></p>
                '),
            ],

            [
                'key'            => 'admin_strip_comment',
                'name'           => 'Klant geeft feedback op ontwerp (admin)',
                'description'    => 'Verstuurd als de klant opmerkingen heeft over het fotostrip ontwerp.',
                'recipient_type' => 'admin',
                'subject'        => '💬 Feedback van {{klant_naam}} op fotostrip ontwerp',
                'body'           => $this->wrap('Feedback ontvangen op fotostrip ontwerp', '
<p><strong>{{klant_naam}}</strong> heeft feedback gegeven op het fotostrip ontwerp van boeking <strong>{{boeking_nummer}}</strong>:</p>
<blockquote style="border-left:4px solid #2563eb;padding:12px 16px;margin:16px 0;background:#eff6ff;border-radius:0 8px 8px 0;font-style:italic;">
  {{opmerkingen_klant}}
</blockquote>
<p>Event datum: <strong>{{event_datum}}</strong> bij <strong>{{event_locatie}}</strong></p>
<p>Pas het ontwerp aan en stuur het opnieuw ter beoordeling.</p>
                '),
            ],

            [
                'key'            => 'admin_strip_method_self',
                'name'           => 'Klant gaat zelf ontwerpen (admin)',
                'description'    => 'Verstuurd zodra de klant kiest om zelf de fotostrip te ontwerpen in Canva of Photoshop.',
                'recipient_type' => 'admin',
                'subject'        => '🎨 Klant ontwerpt zelf — {{boeking_nummer}} ({{klant_naam}})',
                'body'           => $this->wrap('Klant gaat zelf ontwerpen', '
<p><strong>{{klant_naam}}</strong> heeft gekozen om de fotostrip <strong>zelf te ontwerpen</strong> voor boeking <strong>{{boeking_nummer}}</strong>.</p>
<table width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;border-collapse:collapse;">
  <tr style="background:#f8fafc;"><td style="padding:8px 12px;font-weight:600;border:1px solid #e2e8f0;width:40%;">Event datum</td><td style="padding:8px 12px;border:1px solid #e2e8f0;">{{event_datum}}</td></tr>
  <tr><td style="padding:8px 12px;font-weight:600;border:1px solid #e2e8f0;">Locatie</td><td style="padding:8px 12px;border:1px solid #e2e8f0;">{{event_locatie}}</td></tr>
</table>
<p>De klant gaat het ontwerp mailen naar <strong>ontwerp@flitsmoment.nl</strong> met onderwerp <strong>Fotostrip {{boeking_nummer}}</strong>.</p>
<p>Zodra het binnen is, upload je het in het CRM zodat de klant het ter goedkeuring krijgt.</p>
<p><a href="{{portal_link}}">Boeking bekijken in CRM</a></p>
                '),
            ],

            [
                // VEROUDERD: sinds de portaal-vereenvoudiging (2 keuzepaden) is er geen aparte
                // template-galerij meer, dus deze mail heeft geen verzendpad meer. Regel blijft
                // staan zodat bestaande mail-instellingen niet verdwijnen.
                'key'            => 'admin_strip_method_template',
                'name'           => 'Klant koos template (admin)',
                'description'    => 'Verstuurd zodra de klant een template uit de galerij heeft gekozen.',
                'recipient_type' => 'admin',
                'subject'        => '📋 Template gekozen — {{boeking_nummer}} ({{klant_naam}})',
                'body'           => $this->wrap('Klant heeft een template gekozen', '
<p><strong>{{klant_naam}}</strong> heeft een template gekozen voor boeking <strong>{{boeking_nummer}}</strong>.</p>
<table width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;border-collapse:collapse;">
  <tr style="background:#f8fafc;"><td style="padding:8px 12px;font-weight:600;border:1px solid #e2e8f0;width:40%;">Event datum</td><td style="padding:8px 12px;border:1px solid #e2e8f0;">{{event_datum}}</td></tr>
  <tr><td style="padding:8px 12px;font-weight:600;border:1px solid #e2e8f0;">Locatie</td><td style="padding:8px 12px;border:1px solid #e2e8f0;">{{event_locatie}}</td></tr>
</table>
<p>We wachten nu op de tekst die de klant op de strip wil zien. Je krijgt een nieuwe mail zodra dat binnen is.</p>
<p><a href="{{portal_link}}">Boeking bekijken in CRM</a></p>
                '),
            ],

            [
                'key'            => 'customer_strip_choice_reminder',
                'name'           => 'Fotostrip keuze herinnering (klant)',
                'description'    => 'Verstuurd 14 dagen voor event als klant nog geen ontwerpmethode heeft gekozen.',
                'recipient_type' => 'customer',
                'subject'        => '🎨 Vergeet je niet je fotostrip-ontwerp te kiezen?',
                'body'           => $this->wrap('Kies snel je fotostrip-ontwerp', '
<p>Hallo <strong>{{klant_voornaam}}</strong>,</p>
<p>Je event op <strong>{{event_datum}}</strong> komt eraan! We zien dat je nog geen ontwerpmethode hebt gekozen voor je fotostrip.</p>
<p>Je hebt drie opties:</p>
<ol style="padding-left:1.25rem;">
  <li><strong>Zelf ontwerpen</strong> in Canva of Photoshop met onze templates</li>
  <li><strong>Kies uit een template</strong> uit onze galerij en wij vullen je tekst in</li>
  <li><strong>Wij ontwerpen het voor je</strong> op basis van jouw wensen</li>
</ol>
<p style="text-align:center;margin:28px 0;">
  <a href="{{portal_link}}" style="background:#fcd34d;color:#78350f;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:700;font-size:16px;display:inline-block;">Kies je ontwerpmethode</a>
</p>
<p>Met vriendelijke groet,<br><strong>{{bedrijf_naam}}</strong></p>
                '),
            ],

            [
                'key'            => 'admin_intake_completed',
                'name'           => 'Intake compleet (admin)',
                'description'    => 'Verstuurd als de klant de intake volledig heeft ingevuld.',
                'recipient_type' => 'admin',
                'subject'        => '📋 Intake ingevuld — {{klant_naam}} ({{boeking_nummer}})',
                'body'           => $this->wrap('Intake ingevuld door klant', '
<p><strong>{{klant_naam}}</strong> heeft de intake ingevuld voor boeking <strong>{{boeking_nummer}}</strong>.</p>
<table width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;border-collapse:collapse;">
  <tr style="background:#f8fafc;"><td style="padding:8px 12px;font-weight:600;border:1px solid #e2e8f0;width:40%;">Event datum</td><td style="padding:8px 12px;border:1px solid #e2e8f0;">{{event_datum}}</td></tr>
  <tr><td style="padding:8px 12px;font-weight:600;border:1px solid #e2e8f0;">Locatie</td><td style="padding:8px 12px;border:1px solid #e2e8f0;">{{event_locatie}}</td></tr>
  <tr style="background:#f8fafc;"><td style="padding:8px 12px;font-weight:600;border:1px solid #e2e8f0;">Printformaat</td><td style="padding:8px 12px;border:1px solid #e2e8f0;">{{strip_formaat}}</td></tr>
</table>
<p>Bekijk de volledige antwoorden in het CRM om de voorbereidingen te starten.</p>
<p><a href="{{portal_link}}">Portaal bekijken</a></p>
                '),
            ],

            [
                'key'            => 'customer_intake_confirmed',
                'name'           => 'Intake bevestiging (klant)',
                'description'    => 'Verstuurd naar de klant als bevestiging dat de intake is ontvangen.',
                'recipient_type' => 'customer',
                'subject'        => 'Bedankt voor het invullen! — {{boeking_nummer}}',
                'body'           => $this->wrap('Intake ontvangen', '
<p>Hallo <strong>{{klant_voornaam}}</strong>,</p>
<p>Bedankt voor het invullen van de intake voor uw boeking op <strong>{{event_datum}}</strong>!</p>
<p>We hebben alle informatie ontvangen en gaan ermee aan de slag. U hoort van ons zodra het fotostrip ontwerp klaar is voor beoordeling.</p>
<p>Uw boeking kunt u altijd bekijken via onderstaande knop:</p>
<p style="text-align:center;margin:28px 0;">
  <a href="{{portal_link}}" style="background:#2563eb;color:#ffffff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:700;font-size:16px;display:inline-block;">Mijn boeking bekijken</a>
</p>
<p>Met vriendelijke groet,<br><strong>{{bedrijf_naam}}</strong></p>
                '),
            ],

            [
                'key'            => 'customer_review_request',
                'name'           => 'Review verzoek (24u na event)',
                'description'    => 'Verstuurd 24 uur na de starttijd van het event om een Google review te vragen.',
                'recipient_type' => 'customer',
                'subject'        => 'Hoe was je ervaring met {{bedrijf_naam}}? ⭐',
                'body'           => $this->wrap('Wat fijn dat je er bij was! 🎉', '
<p>Hallo <strong>{{klant_voornaam}}</strong>,</p>
<p>Wat een mooi evenement was het gisteren! We hopen dat jij en je gasten volop hebben genoten van de photobooth.</p>
<p>We zijn een klein bedrijf en goede reviews helpen ons enorm om beter gevonden te worden in Google. Zou jij ons willen helpen door een korte review achter te laten?</p>
<p>Het kost maar 2 minuutjes en het betekent echt veel voor ons! 🙏</p>

<p style="text-align:center;margin:32px 0;">
  <a href="https://g.page/r/JOUW_GOOGLE_REVIEW_LINK/review" style="background:#f59e0b;color:#ffffff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:700;font-size:16px;display:inline-block;">⭐ Schrijf een Google review</a>
</p>

<p style="font-size:13px;color:#64748b;">Heb je zelf nog vragen of opmerkingen over je ervaring? Stuur ons gerust een berichtje terug.</p>
<p>Alvast heel erg bedankt!</p>
<p>Met vriendelijke groet,<br><strong>{{bedrijf_naam}}</strong></p>
                '),
            ],

            // ─── Herinneringen 48 uur van tevoren ───────────────────

            [
                'key'            => 'customer_to_go_reminder',
                'name'           => 'To Go herinnering (48u vooraf)',
                'description'    => 'Verstuurd 48 uur vóór het ophaalmoment van een To Go boeking — met ophaal- en retourtijd en de nadruk dat het exacte tijdvensters zijn.',
                'recipient_type' => 'customer',
                'subject'        => '⏰ Herinnering: ophalen & terugbrengen photobooth — {{boeking_nummer}}',
                'body'           => $this->wrap('Bijna zover! Je photobooth ophalen 📦', '
<p>Hallo <strong>{{klant_voornaam}}</strong>,</p>
<p>Over 2 dagen is het zover! Een korte maar belangrijke herinnering over het <strong>ophalen en terugbrengen</strong> van je photobooth (boeking <strong>{{boeking_nummer}}</strong>).</p>

<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:16px 20px;margin:18px 0;">
  <p style="margin:0 0 6px;font-weight:700;color:#c2410c;">📦 Ophalen bij ons</p>
  <p style="margin:0;font-size:18px;font-weight:700;">{{ophaal_window}}</p>
</div>
<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:16px 20px;margin:18px 0;">
  <p style="margin:0 0 6px;font-weight:700;color:#c2410c;">🔄 Terugbrengen bij ons</p>
  <p style="margin:0;font-size:18px;font-weight:700;">{{retour_window}}</p>
</div>

<p style="background:#fef2f2;border-left:4px solid #ef4444;padding:12px 16px;border-radius:6px;color:#991b1b;">
  <strong>Let op — dit is een exact tijdstip, geen &laquo;vanaf&raquo;-tijd.</strong><br>
  We plannen elk ophaal- en retourmoment strak in en zijn specifiek in dat halfuur aanwezig — staat er <strong>10:00</strong>, dan zijn we er van <strong>10:00 tot 10:30</strong>. Daarbuiten zijn we er vaak niet, dus kom alsjeblieft binnen dit tijdvenster langs. Zo voorkomen we dat je voor een dichte deur staat. 🙏
</p>

<p><strong>Controleer even of alles klopt.</strong> Neem in je portaal nog één keer je gegevens, de ophaal- en retourtijden en het adres door, zodat we zeker weten dat alles goed staat voor de grote dag.</p>

<p style="background:#eff6ff;border-left:4px solid #3b82f6;padding:12px 16px;border-radius:6px;color:#1e3a8a;">
  <strong>☔ De photobooth mag in principe niet buiten staan.</strong><br>
  De apparatuur is niet weer- en vochtbestendig. Zorg dus voor een <strong>droge, overdekte plek binnen</strong>. Twijfel je over de opstelplek? Laat het ons even weten, dan denken we mee.
</p>
<p style="text-align:center;margin:26px 0;">
  <a href="{{portal_link}}" style="background:#2563eb;color:#ffffff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:700;font-size:16px;display:inline-block;">Mijn boeking bekijken</a>
</p>

<p>Tot snel en veel plezier met de photobooth!</p>
<p>Met vriendelijke groet,<br><strong>{{bedrijf_naam}}</strong></p>
                '),
            ],

            [
                'key'            => 'customer_full_service_reminder',
                'name'           => 'Full Service herinnering (48u vooraf)',
                'description'    => 'Verstuurd 48 uur vóór de bezorging van een Full Service boeking — met de bezorg- en ophaaltijd van de photobooth.',
                'recipient_type' => 'customer',
                'subject'        => '⏰ Herinnering: bezorging & ophalen photobooth — {{boeking_nummer}}',
                'body'           => $this->wrap('Bijna zover! Wij komen langs 🚚', '
<p>Hallo <strong>{{klant_voornaam}}</strong>,</p>
<p>Over 2 dagen komen we langs met de photobooth voor je evenement (boeking <strong>{{boeking_nummer}}</strong>). Een korte herinnering met de <strong>bezorg- en ophaaltijden</strong>.</p>

<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px 20px;margin:18px 0;">
  <p style="margin:0 0 6px;font-weight:700;color:#1d4ed8;">🚚 Wij bezorgen de photobooth</p>
  <p style="margin:0;font-size:18px;font-weight:700;">{{bezorg_window}}</p>
</div>
<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px 20px;margin:18px 0;">
  <p style="margin:0 0 6px;font-weight:700;color:#1d4ed8;">📦 Wij halen de photobooth op</p>
  <p style="margin:0;font-size:18px;font-weight:700;">{{ophaal_fs_window}}</p>
</div>

<p><strong>Controleer even of alles klopt.</strong> Neem in je portaal nog één keer je gegevens, de bezorg- en ophaaltijden en de locatie door, zodat we zeker weten dat alles goed staat voor je evenement.</p>

<p style="background:#eff6ff;border-left:4px solid #3b82f6;padding:12px 16px;border-radius:6px;color:#1e3a8a;">
  <strong>☔ De photobooth mag in principe niet buiten staan.</strong><br>
  De apparatuur is niet weer- en vochtbestendig. Zorg dus voor een <strong>droge, overdekte plek binnen</strong> waar wij de photobooth kunnen neerzetten. Twijfel je over de opstelplek? Laat het ons even weten, dan denken we mee.
</p>
<p style="text-align:center;margin:26px 0;">
  <a href="{{portal_link}}" style="background:#2563eb;color:#ffffff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:700;font-size:16px;display:inline-block;">Mijn boeking bekijken</a>
</p>

<p>Tot snel!</p>
<p>Met vriendelijke groet,<br><strong>{{bedrijf_naam}}</strong></p>
                '),
            ],

            // ─── Bezorg/ophaalmoment aangepast ──────────────────────

            [
                'key'            => 'customer_times_updated_to_go',
                'name'           => 'Ophaal/retour aangepast (To Go)',
                'description'    => 'Verstuurd zodra het ophaal- en retourmoment van een To Go boeking is aangepast (na goedkeuring van een wijziging of handmatig vanuit het CRM).',
                'recipient_type' => 'customer',
                'subject'        => 'We hebben je ophaal- en retourmoment aangepast — {{boeking_nummer}}',
                'body'           => $this->wrap('Je ophaal- en retourmoment is aangepast 📦', '
<p>Hallo <strong>{{klant_voornaam}}</strong>,</p>
<p>Goed nieuws — we hebben het ophaal- en retourmoment van je photobooth (boeking <strong>{{boeking_nummer}}</strong>) aangepast. Dit zijn de nieuwe tijden:</p>

<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:16px 20px;margin:18px 0;">
  <p style="margin:0 0 6px;font-weight:700;color:#c2410c;">📦 Ophalen bij ons</p>
  <p style="margin:0;font-size:18px;font-weight:700;">{{ophaal_window}}</p>
</div>
<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:16px 20px;margin:18px 0;">
  <p style="margin:0 0 6px;font-weight:700;color:#c2410c;">🔄 Terugbrengen bij ons</p>
  <p style="margin:0;font-size:18px;font-weight:700;">{{retour_window}}</p>
</div>

<p style="background:#fef2f2;border-left:4px solid #ef4444;padding:12px 16px;border-radius:6px;color:#991b1b;">
  <strong>Let op — dit is een exact tijdstip, geen &laquo;vanaf&raquo;-tijd.</strong><br>
  We zijn specifiek in dat halfuur aanwezig — staat er <strong>10:00</strong>, dan zijn we er van <strong>10:00 tot 10:30</strong>. Kom dus alsjeblieft binnen dit tijdvenster langs op <strong>Ravenswade 132, 3439 LD Nieuwegein</strong>.
</p>

<p style="text-align:center;margin:26px 0;">
  <a href="{{portal_link}}" style="background:#2563eb;color:#ffffff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:700;font-size:16px;display:inline-block;">Mijn boeking bekijken</a>
</p>

<p>Klopt er iets niet? Laat het ons gerust weten.</p>
<p>Met vriendelijke groet,<br><strong>{{bedrijf_naam}}</strong></p>
                '),
            ],

            [
                'key'            => 'customer_times_updated_full_service',
                'name'           => 'Bezorg/ophaal aangepast (Full Service)',
                'description'    => 'Verstuurd zodra het bezorg- en ophaalmoment van een Full Service boeking is aangepast (na goedkeuring van een wijziging of handmatig vanuit het CRM).',
                'recipient_type' => 'customer',
                'subject'        => 'We hebben je bezorg- en ophaalmoment aangepast — {{boeking_nummer}}',
                'body'           => $this->wrap('Je bezorg- en ophaalmoment is aangepast 🚚', '
<p>Hallo <strong>{{klant_voornaam}}</strong>,</p>
<p>Goed nieuws — we hebben het bezorg- en ophaalmoment van je photobooth (boeking <strong>{{boeking_nummer}}</strong>) aangepast. Dit zijn de nieuwe tijden:</p>

<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px 20px;margin:18px 0;">
  <p style="margin:0 0 6px;font-weight:700;color:#1d4ed8;">🚚 Wij bezorgen de photobooth</p>
  <p style="margin:0;font-size:18px;font-weight:700;">{{bezorg_window}}</p>
</div>
<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px 20px;margin:18px 0;">
  <p style="margin:0 0 6px;font-weight:700;color:#1d4ed8;">📦 Wij halen de photobooth op</p>
  <p style="margin:0;font-size:18px;font-weight:700;">{{ophaal_fs_window}}</p>
</div>

<p style="text-align:center;margin:26px 0;">
  <a href="{{portal_link}}" style="background:#2563eb;color:#ffffff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:700;font-size:16px;display:inline-block;">Mijn boeking bekijken</a>
</p>

<p>Klopt er iets niet? Laat het ons gerust weten.</p>
<p>Met vriendelijke groet,<br><strong>{{bedrijf_naam}}</strong></p>
                '),
            ],

            // ─── Offerte mails ──────────────────────────────────────

            [
                'key'            => 'offer_sent',
                'name'           => 'Offerte verstuurd (klant)',
                'description'    => 'Verstuurd als een offerte naar de klant wordt gestuurd.',
                'recipient_type' => 'customer',
                'subject'        => 'Uw offerte staat klaar — {{offerte_nummer}}',
                'body'           => $this->wrap('Uw offerte staat klaar', '
<p>Hallo <strong>{{klant_voornaam}}</strong>,</p>
<p>Bedankt voor uw interesse in een photobooth voor uw evenement op <strong>{{event_datum}}</strong>!</p>
<p>We hebben een offerte op maat voor u samengesteld. Bekijk alle details en accepteer de offerte direct via onderstaande knop:</p>

<table width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;border-collapse:collapse;">
  <tr style="background:#f8fafc;">
    <td style="padding:10px 14px;font-weight:600;border:1px solid #e2e8f0;width:40%;">Offertenummer</td>
    <td style="padding:10px 14px;border:1px solid #e2e8f0;">{{offerte_nummer}}</td>
  </tr>
  <tr>
    <td style="padding:10px 14px;font-weight:600;border:1px solid #e2e8f0;">Evenement</td>
    <td style="padding:10px 14px;border:1px solid #e2e8f0;">{{event_datum}}</td>
  </tr>
  <tr style="background:#f8fafc;">
    <td style="padding:10px 14px;font-weight:600;border:1px solid #e2e8f0;">Locatie</td>
    <td style="padding:10px 14px;border:1px solid #e2e8f0;">{{event_locatie}}</td>
  </tr>
  <tr>
    <td style="padding:10px 14px;font-weight:600;border:1px solid #e2e8f0;">Totaalbedrag</td>
    <td style="padding:10px 14px;border:1px solid #e2e8f0;">{{offerte_totaal}}</td>
  </tr>
</table>

<p style="text-align:center;margin:28px 0;">
  <a href="{{offerte_link}}" style="background:#f59e0b;color:#ffffff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:700;font-size:16px;display:inline-block;">Bekijk uw offerte</a>
</p>

<p>Heeft u vragen? Neem gerust contact op!</p>
<p>Met vriendelijke groet,<br><strong>{{bedrijf_naam}}</strong></p>
                '),
            ],

            [
                'key'            => 'offer_accepted_customer',
                'name'           => 'Offerte geaccepteerd (klant)',
                'description'    => 'Verstuurd naar de klant als bevestiging dat de offerte is geaccepteerd.',
                'recipient_type' => 'customer',
                'subject'        => 'Uw boeking is bevestigd! 🎉 — {{offerte_nummer}}',
                'body'           => $this->wrap('Boeking bevestigd!', '
<p>Hallo <strong>{{klant_voornaam}}</strong>,</p>
<p>Geweldig nieuws! U heeft de offerte geaccepteerd en uw boeking is nu definitief bevestigd.</p>

<table width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;border-collapse:collapse;">
  <tr style="background:#f8fafc;">
    <td style="padding:10px 14px;font-weight:600;border:1px solid #e2e8f0;width:40%;">Evenement</td>
    <td style="padding:10px 14px;border:1px solid #e2e8f0;">{{event_datum}}</td>
  </tr>
  <tr>
    <td style="padding:10px 14px;font-weight:600;border:1px solid #e2e8f0;">Locatie</td>
    <td style="padding:10px 14px;border:1px solid #e2e8f0;">{{event_locatie}}</td>
  </tr>
  <tr style="background:#f8fafc;">
    <td style="padding:10px 14px;font-weight:600;border:1px solid #e2e8f0;">Totaal</td>
    <td style="padding:10px 14px;border:1px solid #e2e8f0;">{{offerte_totaal}}</td>
  </tr>
</table>

<p><strong>Wat is de volgende stap?</strong></p>
<p>Via uw persoonlijke boekingspagina kunt u een korte intake invullen zodat wij alles perfect kunnen voorbereiden voor uw evenement.</p>

<p>Heeft u vragen? We staan altijd voor u klaar!</p>
<p>Met vriendelijke groet,<br><strong>{{bedrijf_naam}}</strong></p>
                '),
            ],

            [
                'key'            => 'offer_accepted_admin',
                'name'           => 'Offerte geaccepteerd (admin)',
                'description'    => 'Verstuurd naar jou als een klant de offerte accepteert.',
                'recipient_type' => 'admin',
                'subject'        => '✅ Offerte geaccepteerd — {{klant_naam}} ({{offerte_nummer}})',
                'body'           => $this->wrap('Offerte geaccepteerd', '
<p><strong>{{klant_naam}}</strong> heeft offerte <strong>{{offerte_nummer}}</strong> geaccepteerd!</p>

<table width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;border-collapse:collapse;">
  <tr style="background:#f8fafc;"><td style="padding:8px 12px;font-weight:600;border:1px solid #e2e8f0;width:40%;">Klant</td><td style="padding:8px 12px;border:1px solid #e2e8f0;">{{klant_naam}}</td></tr>
  <tr><td style="padding:8px 12px;font-weight:600;border:1px solid #e2e8f0;">E-mail</td><td style="padding:8px 12px;border:1px solid #e2e8f0;">{{klant_email}}</td></tr>
  <tr style="background:#f8fafc;"><td style="padding:8px 12px;font-weight:600;border:1px solid #e2e8f0;">Event datum</td><td style="padding:8px 12px;border:1px solid #e2e8f0;">{{event_datum}}</td></tr>
  <tr><td style="padding:8px 12px;font-weight:600;border:1px solid #e2e8f0;">Locatie</td><td style="padding:8px 12px;border:1px solid #e2e8f0;">{{event_locatie}}</td></tr>
  <tr style="background:#f8fafc;"><td style="padding:8px 12px;font-weight:600;border:1px solid #e2e8f0;">Totaal</td><td style="padding:8px 12px;border:1px solid #e2e8f0;">{{offerte_totaal}}</td></tr>
</table>

<p>De boeking is automatisch aangemaakt. De klant kan nu de intake invullen.</p>
                '),
            ],

        ];
    }

    private function wrap(string $title, string $content): string
    {
        return '<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
  body { margin:0; padding:0; background:#f1f5f9; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; color:#1e293b; }
  .wrapper { max-width:600px; margin:0 auto; padding:32px 16px; }
  .card { background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.1); }
  .header { background:#2563eb; padding:28px 32px; }
  .header h1 { margin:0; color:#fff; font-size:20px; font-weight:700; }
  .body { padding:32px; font-size:15px; line-height:1.7; }
  .body p { margin:0 0 16px; }
  .footer { padding:20px 32px; font-size:12px; color:#94a3b8; border-top:1px solid #f1f5f9; text-align:center; }
  a { color:#2563eb; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="card">
    <div class="header"><h1>' . htmlspecialchars($title) . '</h1></div>
    <div class="body">' . $content . '</div>
    <div class="footer">{{bedrijf_naam}} &mdash; Dit bericht is automatisch verstuurd.</div>
  </div>
</div>
</body>
</html>';
    }
}
