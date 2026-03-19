<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EBoekhoudenService
{
    private string $cacheKey = 'eboekhouden_session_token';
    private int $tokenRefreshBuffer = 300; // 5 minutes before expiry
    private ?string $apiKey = null;

    /** Stel een account-specifieke API key in */
    public function withApiKey(string $key): static
    {
        $this->apiKey = $key;
        // Per-account cache key zodat tokens niet door elkaar lopen
        $this->cacheKey = 'eboekhouden_session_token_' . md5($key);
        return $this;
    }

    private function resolveApiKey(): string
    {
        return $this->apiKey ?: config('services.eboekhouden.api_key', env('EBOEKHOUDEN_API_KEY', ''));
    }

    /**
     * Get or create session token
     */
    private function getSessionToken(): string
    {
        // Controleer of we al een geldig token in cache hebben
        $cached = Cache::get($this->cacheKey);
        if ($cached && is_array($cached) && isset($cached['token'])) {
            // Check if token is still valid (with buffer)
            if (time() < ($cached['expiresAt'] - $this->tokenRefreshBuffer)) {
                Log::debug('e-boekhouden: using cached session token');
                return $cached['token'];
            }
        }

        // Token is niet beschikbaar of verlopen, maak nieuwe session
        Log::info('e-boekhouden: creating new session');
        $response = Http::post(
            config('services.eboekhouden.api_url') . '/v1/session',
            [
                'accessToken' => $this->resolveApiKey(),
                'source' => config('services.eboekhouden.app_code'),
            ]
        );

        if ($response->status() === 401) {
            // Token wellicht verlopen uit cache — cache wissen en opnieuw proberen
            Cache::forget($this->cacheKey);
        }

        if (!$response->successful()) {
            Log::error('e-boekhouden: failed to create session', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to create e-boekhouden session: ' . $response->body());
        }

        $data = $response->json();
        $token = $data['token'] ?? null;
        $expiresIn = $data['expiresIn'] ?? 3600;

        if (!$token) {
            throw new \Exception('e-boekhouden session did not return a token');
        }

        // Cache the token with expiration time
        $expiresAt = time() + $expiresIn;
        Cache::put($this->cacheKey, [
            'token' => $token,
            'expiresAt' => $expiresAt,
        ], $expiresIn);

        Log::info('e-boekhouden: new session created', ['expiresIn' => $expiresIn]);
        return $token;
    }

    /**
     * Haal de PDF-URL op van een e-boekhouden factuur
     */
    public function getInvoicePdfUrl(string $invoiceId): ?string
    {
        try {
            $token = $this->getSessionToken();
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->get(config('services.eboekhouden.api_url') . '/v1/invoice/' . $invoiceId);

            if (!$response->successful()) {
                Log::error('e-boekhouden: getInvoicePdfUrl failed', [
                    'invoiceId' => $invoiceId,
                    'status'    => $response->status(),
                ]);
                return null;
            }

            return $response->json('urlPdfFile') ?? null;
        } catch (\Exception $e) {
            Log::error('e-boekhouden: getInvoicePdfUrl exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Maak factuur aan in e-boekhouden
     */
    public function createInvoice(Booking $booking): array
    {
        try {
            // Load relations
            $booking->loadMissing(['account', 'items']);

            // Controleer of API key is ingesteld
            if (!$this->resolveApiKey()) {
                throw new \Exception('e-boekhouden API key niet geconfigureerd');
            }

            // Controleer of boeking al een invoice heeft
            if ($booking->eboekhouden_invoice_id) {
                return [
                    'success' => false,
                    'error' => "Factuur bestaat al: {$booking->eboekhouden_invoice_id}",
                ];
            }

            // Zorg dat klant in e-boekhouden bestaat (check/create)
            $relationId = $this->ensureRelation($booking);

            // Bouw factuur payload
            $payload = $this->buildInvoicePayload($booking, $relationId);

            // POST naar e-boekhouden API
            $token = $this->getSessionToken();
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->post(
                config('services.eboekhouden.api_url') . '/v1/invoice',
                $payload
            );

            // Handle response
            if (!$response->successful()) {
                $body = $response->body();
                $status = $response->status();

                Log::error('e-boekhouden API error', [
                    'status' => $status,
                    'body' => $body,
                    'booking_id' => $booking->id,
                    'payload' => $payload,
                ]);

                // Check if it's "invoice number in use" error - this is a race condition
                if ($status === 400 && str_contains($body, 'Invoice number is in use')) {
                    Log::warning('e-boekhouden invoice number race condition detected, retrying...', [
                        'booking_id' => $booking->id,
                        'invoice_number' => $payload['invoiceNumber'],
                    ]);

                    // Try with next number
                    $currentNum = intval(preg_replace('/[^0-9]/', '', $payload['invoiceNumber']));
                    $nextNum = $currentNum + 1;
                    $newInvoiceNumber = 'F' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);

                    $payload['invoiceNumber'] = $newInvoiceNumber;
                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type' => 'application/json',
                    ])->post(
                        config('services.eboekhouden.api_url') . '/v1/invoice',
                        $payload
                    );

                    if (!$response->successful()) {
                        throw new \Exception("API error ({$response->status()}): {$response->body()} (after retry with {$newInvoiceNumber})");
                    }

                    Log::info('e-boekhouden retry successful', [
                        'booking_id' => $booking->id,
                        'new_invoice_number' => $newInvoiceNumber,
                    ]);
                } else {
                    // User-friendly error messages
                    if ($status === 404) {
                        throw new \Exception("404 error: Relation ID of template ID niet gevonden. Controleer je e-boekhouden account: Beheer > Relaties en templates.");
                    } elseif ($status === 401 || $status === 403) {
                        throw new \Exception("Authentificatie fout: API key is ongeldig of verlopen.");
                    }

                    throw new \Exception("API error ({$status}): {$body}");
                }
            }

            $data = $response->json();

            Log::debug('e-boekhouden invoice response', [
                'status_code' => $response->status(),
                'response_data' => $data,
                'payload_sent' => $payload,
            ]);

            // API geeft 'id' terug (niet 'invoiceId')
            $invoiceId     = $data['id'] ?? null;
            $invoiceNumber = $data['invoiceNumber'] ?? $payload['invoiceNumber'] ?? null;

            // Sla invoice referentie op
            $booking->update([
                'eboekhouden_invoice_id'     => $invoiceId,
                'eboekhouden_invoice_number' => $invoiceNumber,
                'eboekhouden_status'         => 'created',
                'eboekhouden_synced_at'      => now(),
            ]);

            Log::info('e-boekhouden factuur aangemaakt', [
                'booking_id'     => $booking->id,
                'invoice_id'     => $invoiceId,
                'invoice_number' => $invoiceNumber,
                'booking_number' => $booking->booking_number,
                'account_id'     => $booking->account_id,
                'relation_id'    => $relationId,
            ]);

            return [
                'success'        => true,
                'invoice_id'     => $invoiceId,
                'invoice_number' => $invoiceNumber,
                'status'         => 'created',
            ];

        } catch (\Exception $e) {
            Log::error('EBoekhoudenService fout', [
                'error' => $e->getMessage(),
                'booking_id' => $booking->id ?? null,
                'booking_number' => $booking->booking_number ?? null,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get next invoice number (F00228 → F00229)
     */
    private function getNextInvoiceNumber(): string
    {
        try {
            $token = $this->getSessionToken();

            // Fetch invoices with pagination - e-boekhouden may return max 100 per request
            $allInvoices = [];
            $offset = 0;
            $pageSize = 100;
            $maxPages = 10; // Safety limit to prevent infinite loops
            $pagesFetched = 0;

            while ($pagesFetched < $maxPages) {
                Log::debug('e-boekhouden fetching invoices', [
                    'offset' => $offset,
                    'page' => $pagesFetched + 1,
                ]);

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                ])->get(
                    config('services.eboekhouden.api_url') . '/v1/invoice',
                    [
                        'offset' => $offset,
                        'limit' => $pageSize,
                    ]
                );

                if (!$response->successful()) {
                    Log::error('e-boekhouden failed to fetch invoices', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'offset' => $offset,
                    ]);
                    break;
                }

                $responseData = $response->json();
                $invoices = $responseData['items'] ?? $responseData['data'] ?? [];

                if (!is_array($invoices) || empty($invoices)) {
                    Log::debug('e-boekhouden no more invoices to fetch', [
                        'offset' => $offset,
                        'page' => $pagesFetched + 1,
                    ]);
                    break;
                }

                $allInvoices = array_merge($allInvoices, $invoices);

                // Check if we got a full page - if not, we've reached the end
                if (count($invoices) < $pageSize) {
                    Log::debug('e-boekhouden reached end of invoices', [
                        'items_in_last_page' => count($invoices),
                        'total_invoices' => count($allInvoices),
                    ]);
                    break;
                }

                $offset += $pageSize;
                $pagesFetched++;
            }

            Log::info('e-boekhouden total invoices fetched', [
                'count' => count($allInvoices),
                'pages' => $pagesFetched,
            ]);

            if (!empty($allInvoices)) {
                // Sort by invoice number - extract ONLY the numeric part to find the highest
                usort($allInvoices, function($a, $b) {
                    $numA = intval(preg_replace('/[^0-9]/', '', $a['invoiceNumber'] ?? ''));
                    $numB = intval(preg_replace('/[^0-9]/', '', $b['invoiceNumber'] ?? ''));
                    return $numB - $numA;
                });

                $lastInvoice = $allInvoices[0];
                $lastNumber = $lastInvoice['invoiceNumber'] ?? '';

                // Extract ONLY the digits, ignore any prefix (F, FM-F, etc)
                $lastDigits = preg_replace('/[^0-9]/', '', $lastNumber);
                $lastNumericValue = intval($lastDigits);

                Log::info('e-boekhouden latest invoice found', [
                    'full_number' => $lastNumber,
                    'numeric_value' => $lastNumericValue,
                    'total_invoices_searched' => count($allInvoices),
                ]);

                if (!empty($lastDigits)) {
                    // Calculate next number
                    $nextNumber = $lastNumericValue + 1;
                    $paddingLength = strlen($lastDigits); // Use the padding of the last invoice
                    $formattedNumber = 'FM-F' . str_pad($nextNumber, $paddingLength, '0', STR_PAD_LEFT);

                    Log::info('e-boekhouden next invoice number', [
                        'last_full' => $lastNumber,
                        'last_numeric' => $lastNumericValue,
                        'next' => $formattedNumber,
                        'padding_length' => $paddingLength,
                    ]);

                    return $formattedNumber;
                } else {
                    Log::warning('e-boekhouden could not extract numeric value from invoice number', [
                        'number' => $lastNumber,
                    ]);
                }
            } else {
                Log::warning('e-boekhouden no invoices found after fetching all pages');
            }
        } catch (\Exception $e) {
            Log::error('e-boekhouden exception in getNextInvoiceNumber', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        // Fallback: use FM-F00229 as default if we can't determine
        Log::warning('e-boekhouden using fallback invoice number FM-F00229');
        return 'FM-F00229';
    }

    /**
     * Zorg dat klant als relatie bestaat in e-boekhouden
     * Check eerst op mailadres, dan op naam, maak aan als nodig
     */
    private function ensureRelation(Booking $booking): int
    {
        // Als we al een relation ID hebben opgeslagen, gebruik die
        if ($booking->eboekhouden_relation_id) {
            Log::info("e-boekhouden relatie reeds opgeslagen", ['relation_id' => $booking->eboekhouden_relation_id]);
            return $booking->eboekhouden_relation_id;
        }

        // Try to find existing relation by email address first
        if ($booking->customer_email) {
            $existingRelation = $this->findRelationByEmail($booking->customer_email);
            if ($existingRelation) {
                Log::info("e-boekhouden relatie gevonden op mailadres", [
                    'relation_id' => $existingRelation['id'],
                    'email' => $booking->customer_email,
                    'name' => $existingRelation['name'] ?? null,
                ]);
                $booking->update(['eboekhouden_relation_id' => $existingRelation['id']]);
                return $existingRelation['id'];
            }
        }

        // Try to find existing relation by name
        $existingRelation = $this->findRelationByName($booking->customer_name);
        if ($existingRelation) {
            Log::info("e-boekhouden relatie gevonden op naam", [
                'relation_id' => $existingRelation['id'],
                'name' => $booking->customer_name,
            ]);
            $booking->update(['eboekhouden_relation_id' => $existingRelation['id']]);
            return $existingRelation['id'];
        }

        // Relatie bestaat niet, maak aan
        Log::info("e-boekhouden relatie aanmaken voor: {$booking->customer_name}", [
            'customer_type' => $booking->customer_type,
            'email' => $booking->customer_email ?? 'geen email',
        ]);
        $newRelation = $this->createRelation($booking);

        if (!$newRelation || !isset($newRelation['id'])) {
            Log::error("createRelation returned invalid response", [
                'booking_id' => $booking->id,
                'customer_name' => $booking->customer_name,
                'customer_type' => $booking->customer_type,
                'response' => $newRelation,
            ]);
            throw new \Exception("Kon klant niet aanmaken in e-boekhouden. Controleer de logs voor details.");
        }

        $booking->update(['eboekhouden_relation_id' => $newRelation['id']]);
        return $newRelation['id'];
    }

    /**
     * Zoek relatie in e-boekhouden op mailadres
     */
    private function findRelationByEmail(?string $email): ?array
    {
        if (!$email) {
            return null;
        }

        try {
            $token = $this->getSessionToken();
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->get(
                config('services.eboekhouden.api_url') . '/v1/relation'
            );

            if ($response->successful()) {
                $responseData = $response->json();
                // e-boekhouden uses 'items' key for relation list
                $relations = $responseData['items'] ?? $responseData['data'] ?? [];

                // Search for matching relation by email address
                foreach ($relations as $relation) {
                    $relationEmail = $relation['emailAddress'] ?? null;
                    if ($relationEmail && strcasecmp($relationEmail, $email) === 0) {
                        Log::debug('e-boekhouden relation match found by email', [
                            'email' => $email,
                            'relation_id' => $relation['id'] ?? null,
                        ]);
                        return [
                            'id' => $relation['id'] ?? null,
                            'name' => $relation['companyName'] ?? $relation['name'] ?? null,
                        ];
                    }
                }

                Log::debug('e-boekhouden no relation found for email', ['email' => $email]);
            }
        } catch (\Exception $e) {
            Log::warning("Fout bij zoeken e-boekhouden relatie op email: {$e->getMessage()}");
        }

        return null;
    }

    /**
     * Zoek relatie in e-boekhouden op naam
     */
    private function findRelationByName(?string $name): ?array
    {
        if (!$name) {
            return null;
        }

        try {
            $token = $this->getSessionToken();
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->get(
                config('services.eboekhouden.api_url') . '/v1/relation'
            );

            if ($response->successful()) {
                $responseData = $response->json();
                // e-boekhouden uses 'items' key for relation list
                $relations = $responseData['items'] ?? $responseData['data'] ?? [];

                // Search for matching relation by name
                foreach ($relations as $relation) {
                    if (strcasecmp($relation['companyName'] ?? $relation['name'] ?? '', $name) === 0) {
                        Log::debug('e-boekhouden relation match found by name', [
                            'name' => $name,
                            'relation_id' => $relation['id'] ?? null,
                        ]);
                        return [
                            'id' => $relation['id'] ?? null,
                            'name' => $relation['companyName'] ?? $relation['name'] ?? null,
                        ];
                    }
                }

                Log::debug('e-boekhouden no relation found for name', ['name' => $name]);
            }
        } catch (\Exception $e) {
            Log::warning("Fout bij zoeken e-boekhouden relatie op naam: {$e->getMessage()}");
        }

        return null;
    }

    /**
     * Maak nieuwe relatie aan in e-boekhouden
     */
    private function createRelation(Booking $booking): ?array
    {
        // Type bepalen op basis van customer_type
        // P = Particulier/Person, B = Zakelijk/Business
        $isZakelijk = $booking->customer_type === 'zakelijk';
        $type = $isZakelijk ? 'B' : 'P';

        // Voor type B: name = bedrijfsnaam (verplicht veld), contact = contactpersoon
        // Voor type P: name = klantnaam
        // companyName bestaat NIET in de REST API — dit gaat via 'name'
        // code weglaten → e-boekhouden auto-genereert (vermijdt REL_049)
        $payload = [
            'type'    => $type,
            'name'    => $isZakelijk ? ($booking->company_name ?: $booking->customer_name) : $booking->customer_name,
            'country' => 'NL',
        ];

        // Contactpersoon meesturen bij zakelijk
        if ($isZakelijk && $booking->customer_name) {
            $payload['contact'] = $booking->customer_name;
        }

        // Adresvelden alleen meesturen als niet leeg (lege strings geven validatiefouten)
        if ($isZakelijk) {
            if (!empty(trim($booking->event_address ?? ''))) {
                $payload['address'] = $booking->event_address;
            }
            if (!empty(trim($booking->event_postcode ?? ''))) {
                $payload['postalCode'] = $booking->event_postcode;
            }
            if (!empty(trim($booking->event_city ?? ''))) {
                $payload['city'] = $booking->event_city;
            }
        }

        // Email en telefoon voor beide types (optioneel)
        if ($booking->customer_email) {
            $payload['emailAddress'] = $booking->customer_email;
        }
        if ($booking->customer_phone) {
            $payload['phoneNumber'] = $booking->customer_phone;
        }

        try {
            Log::debug("e-boekhouden createRelation payload", [
                'payload' => $payload,
                'type' => $type,
            ]);

            $token = $this->getSessionToken();
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->post(
                config('services.eboekhouden.api_url') . '/v1/relation',
                $payload
            );

            if ($response->successful()) {
                $data = $response->json();
                Log::info("e-boekhouden relatie aangemaakt", [
                    'relation_id' => $data['id'] ?? null,
                    'name' => $booking->customer_name,
                    'type' => $type,
                    'customer_type' => $booking->customer_type,
                    'response' => $data,
                ]);
                return [
                    'id' => $data['id'] ?? null,
                    'name' => $data['name'] ?? null,
                ];
            }

            // API returned an error
            $responseBody = $response->body();
            $responseData = $response->json();

            // REL_049: code bestaat al → probeer relatie te vinden op e-mail of naam
            if ($response->status() === 400 && ($responseData['code'] ?? '') === 'REL_049') {
                Log::warning("e-boekhouden code bestaat al, zoek relatie op naam/email", [
                    'booking_id' => $booking->id,
                    'code' => $payload['code'],
                ]);

                if ($booking->customer_email) {
                    $found = $this->findRelationByEmail($booking->customer_email);
                    if ($found) return $found;
                }
                $found = $this->findRelationByName($booking->customer_name);
                if ($found) return $found;

                // Laatste redmiddel: nieuwe code met microtime om collision te voorkomen
                Log::warning("e-boekhouden relatie niet gevonden, opnieuw proberen met unieke code", [
                    'booking_id' => $booking->id,
                ]);
                $payload['code'] = 'BK-' . strtoupper(substr(str_replace(' ', '', $booking->customer_name), 0, 3))
                    . '-' . $booking->id . '-' . substr((string) (microtime(true) * 1000), -6);

                $retryResponse = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                ])->post(config('services.eboekhouden.api_url') . '/v1/relation', $payload);

                if ($retryResponse->successful()) {
                    $data = $retryResponse->json();
                    Log::info("e-boekhouden relatie aangemaakt met fallback code", ['relation_id' => $data['id'] ?? null]);
                    return ['id' => $data['id'] ?? null, 'name' => $data['name'] ?? null];
                }
            }

            Log::error("e-boekhouden API error bij aanmaken relatie", [
                'status' => $response->status(),
                'body' => $responseBody,
                'booking_id' => $booking->id,
                'customer_name' => $booking->customer_name,
                'customer_type' => $booking->customer_type,
                'type' => $type,
                'payload' => $payload,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error("createRelation exception: {$e->getMessage()}", [
                'booking_id' => $booking->id,
                'customer_name' => $booking->customer_name,
                'customer_type' => $booking->customer_type,
                'exception' => (string) $e,
            ]);
            return null;
        }
    }

    /**
     * Bepaal de juiste ledgerId op basis van itemtype
     * Photobooths: 43301467
     * Extras (achtergronden, props, etc): 43301513
     */
    private function getLedgerIdForItem(string $itemName): int
    {
        $lowerName = strtolower($itemName);

        // Keywords voor extra's/upsells
        $extraKeywords = ['achtergrond', 'prop', 'extra', 'backdrop', 'accessoire', 'opdruk'];

        foreach ($extraKeywords as $keyword) {
            if (str_contains($lowerName, $keyword)) {
                Log::debug('e-boekhouden item identified as extra', [
                    'name' => $itemName,
                    'keyword' => $keyword,
                    'ledgerId' => 43301513,
                ]);
                return 43301513; // Extras/Upsells
            }
        }

        // Default: Photobooth verhuur
        Log::debug('e-boekhouden item identified as photobooth', [
            'name' => $itemName,
            'ledgerId' => 43301467,
        ]);
        return 43301467;
    }

    /**
     * Bouw e-boekhouden factuur payload
     */
    private function buildInvoicePayload(Booking $booking, int $relationId): array
    {
        $booking->loadMissing(['account', 'items', 'payments']);

        // Bereken betaaldatum van meest recente betaling
        $latestPayment = $booking->payments()
            ->where('status', 'paid')
            ->latest('paid_at')
            ->first();

        $invoiceDate = $booking->confirmed_at ?? now();
        $dueDate = $invoiceDate->copy()->addDays(30);

        // Get next invoice number (F00228 → F00229)
        $invoiceNumber = $this->getNextInvoiceNumber();

        return [
            'relationId'    => $relationId,
            'templateId'    => 1325175, // Flitsmoment template
            'date'          => $invoiceDate->toDateString(),
            'termOfPayment' => 30,
            'invoiceNumber' => $invoiceNumber,
            'reference'     => "Boeking {$booking->booking_number}",
            'inExVat'       => 'IN', // Prijzen zijn incl. BTW

            // Regel items
            'items' => $booking->items->map(fn($item) => [
                'description'  => $item->name_snapshot,
                'pricePerUnit' => (float) $item->price_snapshot,
                'quantity'     => (int) $item->quantity,
                'vatCode'      => 'HOOG_VERK_21', // 21% standaard (Netherlands)
                'ledgerId'     => $this->getLedgerIdForItem($item->name_snapshot),
            ])->toArray(),
        ];
    }

    /**
     * Haal de betaalstatus op van een factuur
     * Geeft de volledige factuurdata terug inclusief status
     */
    public function getInvoiceStatus(string $invoiceId): ?array
    {
        try {
            $token = $this->getSessionToken();
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->get(config('services.eboekhouden.api_url') . '/v1/invoice/' . $invoiceId);

            if (!$response->successful()) {
                Log::error('e-boekhouden: getInvoiceStatus failed', [
                    'invoiceId' => $invoiceId,
                    'status'    => $response->status(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('e-boekhouden: getInvoiceStatus exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Zoek een factuur op factuurnummer (bijv. FM-F00228)
     * Geeft de factuurdata terug inclusief ID en status
     */
    public function findInvoiceByNumber(string $invoiceNumber): ?array
    {
        try {
            $token = $this->getSessionToken();

            // Probeer eerst direct te filteren via query parameter
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->get(config('services.eboekhouden.api_url') . '/v1/invoice', [
                'invoiceNumber' => $invoiceNumber,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $items = $data['items'] ?? $data['data'] ?? [];

                // Als de API filtering ondersteunt, eerste match teruggeven
                foreach ($items as $invoice) {
                    if (($invoice['invoiceNumber'] ?? '') === $invoiceNumber) {
                        return $invoice;
                    }
                }

                // Als er maar één resultaat is (en geen exacte match gevonden), gebruik die
                if (count($items) === 1) {
                    return $items[0];
                }
            }

            Log::warning('e-boekhouden: findInvoiceByNumber no direct match, searching all invoices', [
                'invoiceNumber' => $invoiceNumber,
            ]);

            // Fallback: doorzoek alle facturen (paginering)
            $offset = 0;
            $pageSize = 100;

            while (true) {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                ])->get(config('services.eboekhouden.api_url') . '/v1/invoice', [
                    'offset' => $offset,
                    'limit'  => $pageSize,
                ]);

                if (!$response->successful()) break;

                $items = $response->json('items') ?? $response->json('data') ?? [];
                if (empty($items)) break;

                foreach ($items as $invoice) {
                    if (($invoice['invoiceNumber'] ?? '') === $invoiceNumber) {
                        Log::info('e-boekhouden: findInvoiceByNumber found', [
                            'invoiceNumber' => $invoiceNumber,
                            'id'            => $invoice['id'] ?? null,
                        ]);
                        return $invoice;
                    }
                }

                if (count($items) < $pageSize) break;
                $offset += $pageSize;
            }

            Log::warning('e-boekhouden: findInvoiceByNumber not found', ['invoiceNumber' => $invoiceNumber]);
            return null;

        } catch (\Exception $e) {
            Log::error('e-boekhouden: findInvoiceByNumber exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Haal alle beschikbare grootboekrekeningen op
     * Handig om te zien welke ID je voor extras/upsell wilt gebruiken
     */
    public function getAllLedgerAccounts(): array
    {
        try {
            $token = $this->getSessionToken();
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->get(
                config('services.eboekhouden.api_url') . '/v1/ledger'
            );

            if ($response->successful()) {
                $responseData = $response->json();
                $ledgers = $responseData['items'] ?? $responseData['data'] ?? [];

                Log::info('e-boekhouden ledger accounts fetched', [
                    'count' => count($ledgers),
                ]);

                return $ledgers;
            }

            Log::error('e-boekhouden failed to fetch ledger accounts', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('e-boekhouden exception fetching ledger accounts', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
