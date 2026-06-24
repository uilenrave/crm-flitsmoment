<?php

namespace App\Services;

use App\Mail\DynamicMail;
use App\Models\Booking;
use App\Models\MailLog;
use App\Models\MailTemplate;
use App\Models\Offer;
use App\Scopes\AccountScope;
use Illuminate\Support\Facades\Mail;

class MailService
{
    /**
     * Stuur een mail op basis van een template key (voor boekingen).
     * Werkt ook buiten de auth-context (scheduled tasks, webhooks).
     */
    public function send(string $templateKey, Booking $booking, string $toEmail): bool
    {
        if (! $toEmail) return false;

        $template = MailTemplate::withoutGlobalScope(AccountScope::class)
            ->where('account_id', $booking->account_id)
            ->where('key', $templateKey)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            \Log::info("Mail template '{$templateKey}' niet gevonden of inactief voor account {$booking->account_id}.");
            return false;
        }

        $subject = $this->replaceVars($template->subject, $booking);
        $body    = $this->replaceVars($template->body, $booking);

        try {
            Mail::to($toEmail)->send(new DynamicMail($subject, $body));

            MailLog::withoutGlobalScope(AccountScope::class)->create([
                'account_id'      => $booking->account_id,
                'booking_id'      => $booking->id,
                'template_key'    => $templateKey,
                'recipient_email' => $toEmail,
                'subject'         => $subject,
                'status'          => 'sent',
            ]);

            return true;

        } catch (\Exception $e) {
            \Log::error("MailService fout [{$templateKey}]: " . $e->getMessage());

            MailLog::withoutGlobalScope(AccountScope::class)->create([
                'account_id'      => $booking->account_id,
                'booking_id'      => $booking->id,
                'template_key'    => $templateKey,
                'recipient_email' => $toEmail,
                'subject'         => $subject,
                'status'          => 'failed',
                'error_message'   => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Stuur een mail op basis van een template key (voor offertes).
     */
    public function sendForOffer(string $templateKey, Offer $offer, string $toEmail): bool
    {
        if (! $toEmail) return false;

        $template = MailTemplate::withoutGlobalScope(AccountScope::class)
            ->where('account_id', $offer->account_id)
            ->where('key', $templateKey)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            \Log::info("Mail template '{$templateKey}' niet gevonden of inactief voor account {$offer->account_id}.");
            return false;
        }

        $subject = $this->replaceOfferVars($template->subject, $offer);
        $body    = $this->replaceOfferVars($template->body, $offer);

        try {
            Mail::to($toEmail)->send(new DynamicMail($subject, $body));

            MailLog::withoutGlobalScope(AccountScope::class)->create([
                'account_id'      => $offer->account_id,
                'offer_id'        => $offer->id,
                'template_key'    => $templateKey,
                'recipient_email' => $toEmail,
                'subject'         => $subject,
                'status'          => 'sent',
            ]);

            return true;

        } catch (\Exception $e) {
            \Log::error("MailService fout [{$templateKey}]: " . $e->getMessage());

            MailLog::withoutGlobalScope(AccountScope::class)->create([
                'account_id'      => $offer->account_id,
                'offer_id'        => $offer->id,
                'template_key'    => $templateKey,
                'recipient_email' => $toEmail,
                'subject'         => $subject,
                'status'          => 'failed',
                'error_message'   => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Stuur een eenvoudige notificatiemail zonder template (bijv. tegenvoorstel To Go).
     */
    public function sendRaw(string $toEmail, string $subject, string $htmlBody): void
    {
        if (! $toEmail) return;
        try {
            Mail::html($htmlBody, function ($msg) use ($toEmail, $subject) {
                $msg->to($toEmail)->subject($subject);
            });
        } catch (\Exception $e) {
            \Log::error("MailService sendRaw fout: " . $e->getMessage());
        }
    }

    private function replaceVars(string $text, Booking $booking): string
    {
        $booking->loadMissing('account');

        // Verwijder {{#toon_prijs}}...{{/toon_prijs}} blokken als prijzen verborgen zijn,
        // anders alleen de tags zelf weghalen en de inhoud tonen.
        if ($booking->hide_prices) {
            $text = preg_replace('/\{\{#toon_prijs\}\}.*?\{\{\/toon_prijs\}\}/s', '', $text);
        } else {
            $text = str_replace(['{{#toon_prijs}}', '{{/toon_prijs}}'], '', $text);
        }

        $adres = collect([
            $booking->event_address,
            $booking->event_postcode,
            $booking->event_city,
        ])->filter()->implode(', ');

        $vars = [
            '{{klant_naam}}'          => $booking->customer_name ?? '',
            '{{klant_voornaam}}'      => explode(' ', $booking->customer_name ?? '')[0],
            '{{klant_email}}'         => $booking->customer_email ?? '',
            '{{boeking_nummer}}'      => $booking->booking_number ?? '',
            '{{event_datum}}'         => $booking->event_date?->translatedFormat('l j F Y') ?? '',
            '{{event_locatie}}'       => $booking->event_location ?? '',
            '{{event_adres}}'         => $adres,
            '{{totaal_prijs}}'        => $booking->hide_prices ? '' : '€ ' . number_format((float) $booking->total_price, 2, ',', '.'),
            '{{openstaand_bedrag}}'   => $booking->hide_prices ? '' : '€ ' . number_format((float) $booking->amount_due, 2, ',', '.'),
            '{{portal_link}}'         => $booking->public_token ? route('portal.show', $booking->public_token) : '',
            '{{gallery_link}}'        => $booking->gallery_token
                                             ? route('gallery.show', $booking->gallery_token)
                                             : ($booking->gallery_url ?? ''),
            '{{ontwerp_link}}'        => $booking->strip_design_url ?? '',
            '{{opmerkingen_klant}}'   => $booking->strip_notes ?? '',
            '{{bedrijf_naam}}'        => $booking->account->name ?? config('app.name'),
            '{{strip_formaat}}'       => $booking->strip_format ?? '—',
            '{{crm_boeking_link}}'    => route('bookings.show', $booking->id),
            '{{to_go_tijden_sectie}}' => $this->buildToGoTijdenSectie($booking),
        ];

        return str_replace(array_keys($vars), array_values($vars), $text);
    }

    private function buildToGoTijdenSectie(Booking $booking): string
    {
        if ($booking->booking_type !== 'to_go') return '';
        $s3 = $booking->intake_data['step_3'] ?? [];
        $pickupTime = $s3['pickup_time'] ?? null;
        $returnTime = $s3['return_time'] ?? null;
        $pickupDate = $s3['pickup_date'] ?? null;
        $returnDate = $s3['return_date'] ?? null;
        if (! $pickupTime || ! $returnTime) return '';

        $pickupLabel = $pickupDate
            ? \Carbon\Carbon::parse($pickupDate)->translatedFormat('l j F') . ' om ' . $pickupTime
            : $pickupTime;
        $returnLabel = $returnDate
            ? \Carbon\Carbon::parse($returnDate)->translatedFormat('l j F') . ' om ' . $returnTime
            : $returnTime;

        $needsApproval = ($pickupTime !== '10:00' || $returnTime !== '10:00');
        $badge = $needsApproval
            ? '<span style="background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;padding:2px 8px;border-radius:12px;font-size:12px;font-weight:600;margin-left:8px;">⏳ Goedkeuring vereist</span>'
            : '<span style="background:#dcfce7;color:#15803d;border:1px solid #bbf7d0;padding:2px 8px;border-radius:12px;font-size:12px;font-weight:600;margin-left:8px;">✅ Automatisch goedgekeurd</span>';

        return '
  <tr style="background:#fff7ed;"><td style="padding:8px 12px;font-weight:600;border:1px solid #fed7aa;">📦 Ophalen bij ons</td>
    <td style="padding:8px 12px;border:1px solid #fed7aa;">' . $pickupLabel . '</td></tr>
  <tr style="background:#fff7ed;"><td style="padding:8px 12px;font-weight:600;border:1px solid #fed7aa;">🔄 Terugbrengen</td>
    <td style="padding:8px 12px;border:1px solid #fed7aa;">' . $returnLabel . $badge . '</td></tr>';
    }

    private function replaceOfferVars(string $text, Offer $offer): string
    {
        $offer->loadMissing(['lead', 'account']);

        $lead = $offer->lead;

        $adres = collect([
            $lead?->event_address,
            $lead?->event_postcode,
            $lead?->event_city,
        ])->filter()->implode(', ');

        $vars = [
            '{{klant_naam}}'       => $lead?->name ?? '',
            '{{klant_voornaam}}'   => explode(' ', $lead?->name ?? '')[0],
            '{{klant_email}}'      => $lead?->email ?? '',
            '{{offerte_nummer}}'   => $offer->offer_number ?? '',
            '{{offerte_titel}}'    => $offer->title ?? '',
            '{{event_datum}}'      => $lead?->event_date?->translatedFormat('l j F Y') ?? '',
            '{{event_locatie}}'    => $lead?->event_location ?? '',
            '{{event_adres}}'      => $adres,
            '{{offerte_totaal}}'   => $offer->formatted_total,
            '{{offerte_link}}'     => route('offer.show', $offer->public_token),
            '{{bedrijf_naam}}'     => $offer->account->name ?? config('app.name'),
        ];

        return str_replace(array_keys($vars), array_values($vars), $text);
    }
}
