<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\EventType;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadStatus;
use App\Scopes\AccountScope;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Ontvang een lead van een extern formulier (bijv. Elementor).
     * URL: POST /webhook/lead/{token}
     */
    public function lead(Request $request, string $token): Response
    {
        $account = $this->accountByToken($token);
        if (! $account) {
            return response('Unauthorized', 401);
        }

        // Normaliseer alle keys naar lowercase (Elementor stuurt bijv. "Voornaam")
        $data = $this->parsePayload($request);

        Log::info('Webhook lead ontvangen', ['account' => $account->id, 'data' => $data]);

        // ── Naam ─────────────────────────────────────────────────────────────
        $firstName = $data['voornaam'] ?? $data['first_name'] ?? $data['firstname'] ?? null;
        $lastName  = $data['achternaam'] ?? $data['last_name'] ?? $data['lastname'] ?? null;
        $fullName  = trim("$firstName $lastName") ?: null;
        $name      = $fullName ?: $data['name'] ?? $data['naam'] ?? null;

        // ── Contactgegevens ───────────────────────────────────────────────────
        // "E mail" → genormaliseerd naar "e_mail"; ook gewone "email" ondersteunen
        $email = $data['email'] ?? $data['e_mail'] ?? $data['e-mail'] ?? null;
        $phone = $data['telefoonnummer'] ?? $data['phone'] ?? $data['telefoon'] ?? $data['tel'] ?? null;

        if (! $name && ! $email) {
            return response('Bad Request: geen naam of e-mail gevonden', 400);
        }

        // ── Datum ─────────────────────────────────────────────────────────────
        // Elementor stuurt "Boekingsdatum" → genormaliseerd naar "boekingsdatum"
        $rawDate = $data['boekingsdatum'] ?? $data['datum'] ?? $data['event_date'] ?? $data['date'] ?? null;
        $date    = $this->parseDate($rawDate);

        // ── Tijden ────────────────────────────────────────────────────────────
        // Elementor stuurt "Vanaf" en "Tot"; "Tijd" is een auto-timestamp → negeren
        $timeFrom = $data['vanaf'] ?? $data['tijd_van'] ?? $data['start_time'] ?? null;
        $timeTill = $data['tot']   ?? $data['tijd_tot'] ?? $data['end_time']   ?? null;

        // ── Adres → geocode via Google ────────────────────────────────────────
        $rawAddress = $data['adres'] ?? $data['address'] ?? null;
        $rawCity    = $data['plaats'] ?? $data['city'] ?? null;
        $geocoded   = $this->geocode($rawAddress, $rawCity);

        // ── Booking type (Full Service / To Go) ───────────────────────────────
        $rawHuren    = $data['wat_wil_je_huren'] ?? $data['watwiljehuren'] ?? $data['huren'] ?? $data['product'] ?? null;
        $bookingType = null;
        if ($rawHuren) {
            $lc = strtolower($rawHuren);
            if (str_contains($lc, 'to go') || str_contains($lc, 'to_go') || str_contains($lc, 'togo')) {
                $bookingType = 'to_go';
            } elseif (str_contains($lc, 'full service') || str_contains($lc, 'full_service')) {
                $bookingType = 'full_service';
            }
        }

        // ── Type event (matcht op naam in event_types tabel) ──────────────────
        $rawType   = $data['type_event'] ?? $data['evenement'] ?? $data['event_type'] ?? null;
        $eventType = $rawType ? EventType::withoutGlobalScope(AccountScope::class)
            ->where('account_id', $account->id)
            ->whereRaw('LOWER(name) = ?', [strtolower($rawType)])
            ->first() : null;

        // ── Bron + status ─────────────────────────────────────────────────────
        $source = LeadSource::withoutGlobalScope(AccountScope::class)
            ->where('account_id', $account->id)->whereRaw('LOWER(name) = ?', ['website'])->first();
        $status = LeadStatus::withoutGlobalScope(AccountScope::class)
            ->where('account_id', $account->id)->whereRaw('LOWER(name) = ?', ['nieuw'])->first();

        Lead::withoutGlobalScope(AccountScope::class)->create([
            'account_id'       => $account->id,
            'name'             => $name ?: ($email ?? 'Onbekend'),
            'email'            => $email,
            'phone'            => $phone,
            'event_date'       => $date,
            'event_start_time' => $timeFrom ? $this->normalizeTime($timeFrom) : null,
            'event_end_time'   => $timeTill ? $this->normalizeTime($timeTill) : null,
            'event_address'    => $geocoded['address'] ?? $rawAddress,
            'event_postcode'   => $geocoded['postcode'] ?? null,
            'event_city'       => $geocoded['city'] ?? $rawCity,
            'event_location'   => $geocoded['formatted'] ?? implode(', ', array_filter([$rawAddress, $rawCity])) ?: null,
            'notes'            => $this->buildNotes($data),
            'status_id'        => $status?->id,
            'source_id'        => $source?->id,
            'event_type_id'    => $eventType?->id,
            'booking_type'     => $bookingType,
        ]);

        return response('OK', 200);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    private function accountByToken(string $token): ?Account
    {
        foreach (Account::withoutGlobalScope(AccountScope::class)->where('status', 'active')->get() as $account) {
            if (hash_equals(hash_hmac('sha256', (string) $account->id, config('app.key')), $token)) {
                return $account;
            }
        }
        return null;
    }

    /**
     * Verwerk payload naar een plat array met lowercase keys.
     * Ondersteunt:
     *  - Gewone form-encoded POST
     *  - Elementor geneste fields-array: [['id'=>'name','value'=>'...'], ...]
     */
    private function parsePayload(Request $request): array
    {
        $raw = $request->all();

        // Elementor geneste format: {"fields":[{"id":"voornaam","value":"Jesse"},...]}
        if (isset($raw['fields']) && is_array($raw['fields'])) {
            $flat = [];
            foreach ($raw['fields'] as $field) {
                if (isset($field['id'], $field['value'])) {
                    $flat[strtolower($field['id'])] = $field['value'];
                } elseif (isset($field['title'], $field['value'])) {
                    $flat[strtolower($field['title'])] = $field['value'];
                }
            }
            if ($flat) {
                $raw = array_merge($raw, $flat);
            }
        }

        // Normaliseer alle keys naar lowercase + verwijder speciale tekens (?!)
        $normalized = [];
        foreach ($raw as $k => $v) {
            $key = strtolower(preg_replace('/[^a-z0-9_]/i', '', str_replace([' ', '-'], '_', $k)));
            if ($key !== '' && ! is_array($v)) {
                $normalized[$key] = trim((string) $v);
            }
        }

        return $normalized;
    }

    /** Datum parsen: probeert dd-mm-yyyy (NL), yyyy-mm-dd, en fallback via Carbon */
    private function parseDate(?string $raw): ?string
    {
        if (! $raw) return null;
        try {
            // Nederlands formaat dd-mm-yyyy of dd/mm/yyyy
            if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $raw, $m)) {
                return \Carbon\Carbon::createFromFormat('d-m-Y', "{$m[1]}-{$m[2]}-{$m[3]}")->format('Y-m-d');
            }
            return \Carbon\Carbon::parse($raw)->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }

    /** Tijd normaliseren naar HH:MM */
    private function normalizeTime(string $raw): string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})/', $raw, $m)) {
            return str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2];
        }
        return $raw;
    }

    /** Geocode adres + stad via Google Maps Geocoding API */
    private function geocode(?string $address, ?string $city): ?array
    {
        $apiKey = config('services.maps_key');
        if (! $apiKey || (! $address && ! $city)) return null;

        $query = implode(', ', array_filter([$address, $city, 'Nederland']));

        try {
            $response = Http::timeout(3)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $query,
                'key'     => $apiKey,
                'language' => 'nl',
                'region'  => 'nl',
            ]);

            $result = $response->json('results.0');
            if (! $result) return null;

            $components = collect($result['address_components'] ?? []);
            $get = function (string $type) use ($components): ?string {
                $match = $components->first(fn($c) => in_array($type, $c['types']));
                return $match ? ($match['long_name'] ?? null) : null;
            };

            return [
                'formatted' => $result['formatted_address'] ?? null,
                'address'   => trim(($get('route') ?? '') . ' ' . ($get('street_number') ?? '')),
                'postcode'  => $get('postal_code'),
                'city'      => $get('locality') ?? $get('administrative_area_level_2') ?? $city,
            ];
        } catch (\Exception $e) {
            Log::warning('Geocoding mislukt', ['query' => $query, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Zet overige velden (extras, wensen, product) in een notitieblok.
     * Alles wat al als apart veld is opgeslagen wordt overgeslagen.
     */
    private function buildNotes(array $data): ?string
    {
        $skip = [
            // Naam
            'voornaam','achternaam','firstname','lastname','first_name','last_name','name','naam',
            // Email (ook "E mail" → genormaliseerd naar "e_mail")
            'email','e_mail','email_address',
            // Telefoon
            'telefoonnummer','phone','telefoon','tel','phone_number',
            // Datum ("Boekingsdatum" → "boekingsdatum")
            'boekingsdatum','datum','event_date','date','eventdate',
            // Adres + Stad
            'adres','address','event_address','plaats','city','event_city','location','locatie',
            // Tijden ("Vanaf"→"vanaf", "Tot"→"tot"; "Tijd" negeren want dat is Elementor auto-timestamp)
            'vanaf','tot','tijd','tijdvan','tijdtot','tijd_van','tijd_tot','start_time','end_time',
            // Type event
            'type_event','evenement','event_type',
            // Booking type
            'wat_wil_je_huren','watwiljehuren','huren','product',
            // Wensen/bericht
            'wensen','wat_zijn_je_wensen','message','bericht','notes','opmerkingen','omschrijving',
            // Elementor metadata
            'fields','formid','form_id','formname','form_name','pageid','page_id',
            'pageurl','page_url','paginaurl','pagina_url',
            'pagetitle','referrer','postid','post_id','status',
            'useragent','user_agent','externeip','externe_ip',
            'aangedrovendoor','aangedreven_door',
        ];

        $labels = [
            'wat_wil_je_huren'          => 'Huren',
            'watwiljehuren'             => 'Huren',
            'product'                   => 'Product',
            'achtergrond'               => 'Extra: Achtergrond',
            'kist_met_party_attributen' => 'Extra: Kist met party attributen',
            'kistwithpartyattributen'   => 'Extra: Kist met party attributen',
            'rode_loper'                => 'Extra: Rode loper',
            'rodeloper'                 => 'Extra: Rode loper',
            'audio_gastenboek'          => 'Extra: Audio gastenboek',
            'audiogastenboek'           => 'Extra: Audio gastenboek',
        ];

        // Wensen bovenaan
        $wensen = $data['wensen'] ?? $data['wat_zijn_je_wensen'] ?? $data['message'] ?? $data['bericht'] ?? null;
        $lines  = $wensen ? [$wensen] : [];

        foreach ($data as $k => $v) {
            if (in_array($k, $skip, true) || $v === '' || $v === null) continue;
            $label  = $labels[$k] ?? ucfirst(str_replace('_', ' ', $k));
            $lines[] = $label . ': ' . $v;
        }

        return $lines ? implode("\n", $lines) : null;
    }
}
