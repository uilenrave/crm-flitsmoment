<?php

namespace App\Http\Controllers;

use App\Mail\ReturnTimeRequestedNotification;
use App\Models\Booking;
use App\Models\DesignMask;
use App\Models\DesignPromptSetting;
use App\Models\DesignSession;
use App\Models\Payment;
use App\Models\CanvaTemplate;
use App\Scopes\AccountScope;
use App\Services\DesignGenerationService;
use App\Services\DesignRenderService;
use App\Services\EBoekhoudenService;
use App\Services\MailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mollie\Api\MollieApiClient;

class PortalController extends Controller
{
    /** Publieke portalpagina voor klant */
    public function show(string $token): View
    {
        $booking = Booking::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)
            ->with(['items.asset', 'payments', 'account', 'deliveryStaff', 'pickupStaff', 'stripTemplate'])
            ->firstOrFail();

        // Canva-templates voor het template-pad (Canva-galerij op de template-hub)
        $canvaTemplates = CanvaTemplate::withoutGlobalScope(AccountScope::class)
            ->where('account_id', $booking->account_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('portal.booking', compact('booking', 'canvaTemplates'));
    }

    /** Klant downloadt factuur PDF via e-boekhouden */
    public function factuurDownload(string $token): RedirectResponse
    {
        $booking = Booking::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)
            ->with('account')
            ->firstOrFail();

        if (!$booking->eboekhouden_invoice_id) {
            abort(404, 'Geen factuur beschikbaar.');
        }

        $service = app(EBoekhoudenService::class)
            ->withApiKey($booking->account->getEBoekhoudenApiKey());

        $pdfUrl = $service->getInvoicePdfUrl($booking->eboekhouden_invoice_id);

        if (!$pdfUrl) {
            abort(503, 'Factuur PDF tijdelijk niet beschikbaar. Probeer het later opnieuw.');
        }

        return redirect()->away($pdfUrl);
    }

    /**
     * Legacy-route: het ontwerp + de beoordeling staan nu inline op de portalpagina zelf.
     * Redirect oude links/bladwijzers naar het ontwerp-onderdeel op de hoofdpagina.
     */
    public function stripDesignView(string $token): RedirectResponse
    {
        return redirect(route('portal.show', $token) . '#fotostrip-ontwerp');
    }

    // ──────────────────────────────────────────────────────────────
    // AI-ontwerp-wizard (klant, stap voor stap door de onderdelen heen)
    // ──────────────────────────────────────────────────────────────

    /** Toon de AI-ontwerp-wizard voor deze boeking */
    public function designTool(string $token): View|RedirectResponse
    {
        $booking = Booking::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)
            ->with('account')
            ->firstOrFail();

        if ($booking->strip_design_method !== 'ai') {
            return redirect()->route('portal.show', $token);
        }
        if (in_array($booking->strip_status, ['accepted', 'ready'])) {
            return redirect()->route('portal.show', $token)
                ->with('error', 'Het ontwerp is al goedgekeurd — wijzigen kan niet meer.');
        }

        $session = $this->designSession($booking);
        $state   = $session->state ?? [];
        $results = null;

        if (session('limitReached')) {
            $results = ['ok' => false, 'limit' => true];
        } elseif (! empty($state['backgroundPath']) && Storage::disk('public')->exists($state['backgroundPath'])) {
            $results = [
                'ok'      => true,
                'url'     => Storage::disk('public')->url($state['backgroundPath']),
                'path'    => $state['backgroundPath'],
                'seconds' => 0,
            ];
        }

        return view('portal.design-tool', array_merge($this->designToolViewData($booking, $session), [
            'booking'      => $booking,
            'results'      => $results,
            'input'        => $session->input ?? '',
            'eventType'    => $session->event_type ?? array_key_first(DesignPromptSetting::EVENT_TYPES),
            'initialState' => $state,
            'justGenerated' => session('justGenerated', false),
        ]));
    }

    /** Autosave: sla de huidige stand van de ontwerp-sessie op */
    public function designToolSaveState(Request $request, string $token): JsonResponse
    {
        $booking = $this->designToolBooking($token);
        if (! $booking) {
            return response()->json(['ok' => false], 404);
        }

        $data = $request->validate([
            'event_type' => ['nullable', 'string'],
            'input'      => ['nullable', 'string', 'max:2000'],
            'state'      => ['nullable', 'array'],
        ]);

        $session = $this->designSession($booking);
        $session->update([
            'event_type' => $data['event_type'] ?? $session->event_type,
            'input'      => $data['input'] ?? $session->input,
            'state'      => array_merge($session->state ?? [], $data['state'] ?? []),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Bepaal de achtergrond — AI-generatie is geblokkeerd na DesignSession::MAX_BACKGROUND_GENERATIONS pogingen; upload/kleur tellen niet mee.
     * Redirect na afloop (Post/Redirect/Get) zodat een pagina-herlaad de POST niet opnieuw uitvoert —
     * anders genereerde een reload op deze url stilletjes een nieuwe AI-achtergrond i.p.v. de opgeslagen te tonen.
     */
    public function designToolGenerate(Request $request, string $token): RedirectResponse|JsonResponse
    {
        $booking = $this->designToolBooking($token);
        abort_if(! $booking, 404);

        $session = $this->designSession($booking);
        $service = app(DesignGenerationService::class);
        $method  = $request->input('background_method', 'ai');

        if ($method === 'ai' && $session->backgroundLimitReached()) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'limit' => true]);
            }

            return redirect()->route('portal.design-tool', $token)->with('limitReached', true);
        }

        $data  = $service->validateGenerateRequest($request);
        $input = $session->input ?? '';

        if ($method === 'upload') {
            $results = $service->uploadBackground($request->file('background_upload'));
        } elseif ($method === 'color') {
            $results = $service->colorBackground($data['background_color']);
        } elseif ($method === 'template') {
            $results = $service->templateBackground((int) $data['template_id']);
        } else {
            $refPaths = $service->storeReferenceFiles($request);
            $results  = $service->generateBackground($data['event_type'], $data['input'], $refPaths, $booking->account_id, $booking->id, 'customer_portal');
            $input    = $data['input'];
        }

        $state = $session->state ?? [];
        if ($results['ok']) {
            $state['backgroundPath'] = $results['path'];
        }

        $session->update([
            'event_type'             => $data['event_type'],
            'input'                  => $input,
            'state'                  => $state,
            'background_generations' => $method === 'ai' ? ($session->background_generations ?? 0) + 1 : ($session->background_generations ?? 0),
        ]);

        // Regeneratie via AJAX (er staat al een achtergrond) — geen redirect, puur het resultaat,
        // zodat de client de afbeelding in-place kan verversen zonder de pagina te herladen.
        if ($request->expectsJson()) {
            return response()->json($results);
        }

        if (! $results['ok']) {
            return redirect()->route('portal.design-tool', $token)->with('error', $results['error']);
        }

        return redirect()->route('portal.design-tool', $token)->with('justGenerated', true);
    }

    /** Stel een logo vrij — geblokkeerd na DesignSession::MAX_LOGO_CUTOUTS pogingen */
    public function designToolCutoutLogo(Request $request, string $token): JsonResponse
    {
        $booking = $this->designToolBooking($token);
        if (! $booking) {
            return response()->json(['ok' => false, 'error' => 'Boeking niet gevonden.'], 404);
        }

        $session = $this->designSession($booking);
        if ($session->logoLimitReached()) {
            return response()->json(['ok' => false, 'limit' => true]);
        }

        $service = app(DesignGenerationService::class);
        $service->validateLogoRequest($request);
        $stored = $request->file('logo')->store('design-generator/refs', 'local');

        $result = $service->cutoutLogo(Storage::disk('local')->path($stored), $booking->account_id, $request->input('description'), 'customer_portal', $booking->id);
        $session->increment('logo_cutouts');

        return response()->json($result);
    }

    /** Gebruik een geüpload logo direct, zonder AI-vrijstelling — telt niet mee voor de vrijstel-limiet */
    public function designToolUploadLogo(Request $request, string $token): JsonResponse
    {
        $booking = $this->designToolBooking($token);
        if (! $booking) {
            return response()->json(['ok' => false, 'error' => 'Boeking niet gevonden.'], 404);
        }

        $service = app(DesignGenerationService::class);
        $service->validateLogoRequest($request);

        return response()->json($service->uploadLogoDirect($request->file('logo')));
    }

    /** Plaats een goedgekeurd bibliotheek-element als laag — geen AI-call, telt niet mee voor de vrijstel-limiet */
    public function designToolUseElement(Request $request, string $token): JsonResponse
    {
        $booking = $this->designToolBooking($token);
        if (! $booking) {
            return response()->json(['ok' => false, 'error' => 'Boeking niet gevonden.'], 404);
        }

        $request->validate(['element_id' => ['required', 'integer']]);

        return response()->json(app(DesignGenerationService::class)->elementFromLibrary((int) $request->input('element_id')));
    }

    /** Pas een masker toe — zelfde logica als de admin-tool, expliciet account-gescoped */
    public function designToolApplyMask(Request $request, string $token): JsonResponse
    {
        $booking = $this->designToolBooking($token);
        if (! $booking) {
            return response()->json(['ok' => false, 'error' => 'Boeking niet gevonden.'], 404);
        }

        $data = $request->validate([
            'background_path' => ['required', 'string'],
            'mask_id'          => ['required', 'integer'],
        ]);

        if (
            ! str_starts_with($data['background_path'], 'design-generator/out/')
            || ! Storage::disk('public')->exists($data['background_path'])
        ) {
            return response()->json(['ok' => false, 'error' => 'Achtergrond niet gevonden.']);
        }

        $mask = DesignMask::withoutGlobalScope(AccountScope::class)
            ->where('account_id', $booking->account_id)
            ->where('id', $data['mask_id'])
            ->first();

        if (! $mask) {
            return response()->json(['ok' => false, 'error' => 'Masker niet gevonden.']);
        }

        try {
            $binary = app(DesignRenderService::class)
                ->renderMaskPreview($data['background_path'], $mask);

            $filename = 'design-generator/out/' . Str::random(24) . '-masked.png';
            Storage::disk('public')->put($filename, $binary);

            return response()->json(['ok' => true, 'url' => Storage::disk('public')->url($filename)]);
        } catch (\Throwable $e) {
            Log::error('Masker toepassen (portaal) mislukt: ' . $e->getMessage());

            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** Klant geeft aan dat het ontwerp af is: mailt de admin met een link, zet strip_status op accepted */
    public function designToolFinish(string $token): JsonResponse
    {
        $booking = $this->designToolBooking($token);
        if (! $booking) {
            return response()->json(['ok' => false], 404);
        }

        $session = $this->designSession($booking);
        if (empty($session->state['backgroundPath'] ?? null)) {
            return response()->json(['ok' => false, 'error' => 'Er is nog geen achtergrond gegenereerd.']);
        }

        $session->update(['finished_at' => now()]);
        $booking->update(['strip_status' => 'accepted']);

        // AI-ontwerp geaccepteerd → automatisch het productie-PNG (met masker) klaarzetten.
        app(DesignRenderService::class)->publishBookingToProduction($booking);

        if ($booking->account->email) {
            $link = route('design.booking', $booking);
            app(MailService::class)->sendRaw(
                $booking->account->email,
                "Klant heeft zelf een ontwerp gemaakt — {$booking->booking_number}",
                "<p>{$booking->customer_name} heeft in de AI-ontwerp-tool aangegeven dat het ontwerp klaar is.</p>"
                . "<p><a href=\"{$link}\">Bekijk en pas het ontwerp aan →</a></p>"
            );
        }

        return response()->json(['ok' => true]);
    }

    /** Boeking ophalen + guarden op method='ai' + niet-vergrendeld. Null = actie niet toegestaan. */
    private function designToolBooking(string $token): ?Booking
    {
        $booking = Booking::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)
            ->with('account')
            ->first();

        if (! $booking || $booking->strip_design_method !== 'ai') {
            return null;
        }
        if (in_array($booking->strip_status, ['accepted', 'ready'])) {
            return null;
        }

        return $booking;
    }

    private function designSession(Booking $booking): DesignSession
    {
        return DesignSession::withoutGlobalScope(AccountScope::class)
            ->firstOrCreate(['booking_id' => $booking->id], ['account_id' => $booking->account_id]);
    }

    private function designToolViewData(Booking $booking, DesignSession $session): array
    {
        $promptsByType = [];
        foreach (DesignPromptSetting::EVENT_TYPES as $type => $typeLabel) {
            $promptsByType[$type] = DesignPromptSetting::currentPrompt('background', $type, $booking->account_id);
        }

        $token = $booking->public_token;

        return [
            'dgMode'         => 'portal',
            'promptKey'      => 'background',
            'promptLabel'    => DesignPromptSetting::label('background'),
            'promptDefault'  => DesignPromptSetting::DEFAULTS['background']['prompt'],
            'promptsByType'  => $promptsByType,
            'eventTypes'     => DesignPromptSetting::EVENT_TYPES,
            'logoEventTypes' => DesignPromptSetting::LOGO_EVENT_TYPES,
            'masks'          => DesignMask::listForAccount($booking->account_id),
            'googleFonts'    => \App\Services\GoogleFontRegistry::labels(),
            'templateCategories' => app(DesignGenerationService::class)->templateCategories(),
            'elementCategories'  => app(DesignGenerationService::class)->elementCategories(),
            'session'        => $session,
            'urls'           => [
                'generate'         => route('portal.design-tool.generate', $token),
                'logoCutout'       => route('portal.design-tool.logo', $token),
                'logoUpload'       => route('portal.design-tool.logo.upload', $token),
                'elementUse'       => route('portal.design-tool.element', $token),
                'promptUpdate'     => null,
                'masksUpload'      => null,
                'masksApply'       => route('portal.design-tool.mask', $token),
                'masksDestroyBase' => null,
                'saveState'        => route('portal.design-tool.state', $token),
                'finish'           => route('portal.design-tool.finish', $token),
                'helpLink'         => route('portal.show', $token),
            ],
            'limits' => [
                'backgroundMax'   => DesignSession::MAX_BACKGROUND_GENERATIONS,
                'logoMax'         => DesignSession::MAX_LOGO_CUTOUTS,
                'backgroundCount' => $session->background_generations,
                'logoCount'       => $session->logo_cutouts,
            ],
        ];
    }

    /**
     * Stap 1: klant kiest één van de 2 paden — 'template' (kant-en-klare Canva/Photoshop-
     * templates, klant maakt zelf) of 'ai' (eigen wizard-pagina). "Door ons laten ontwerpen"
     * is geen hoofdoptie meer maar blijft bereikbaar via het hulp-formulier onder elk pad
     * (submitStripInput). De legacy waarden 'photoshop'/'canva' worden nog stil geaccepteerd
     * (klant met een oude portalpagina open in een tab) en gedragen zich als 'template'.
     */
    public function chooseDesignPath(Request $request, string $token): RedirectResponse
    {
        $booking = Booking::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)
            ->with('account')
            ->firstOrFail();

        if (in_array($booking->strip_status, ['accepted', 'ready'])) {
            return back()->with('error', 'Het ontwerp is al goedgekeurd — wijzigen kan niet meer.');
        }

        $validated = $request->validate([
            'path' => ['required', 'in:template,ai,photoshop,canva'],
        ]);

        if ($validated['path'] === 'ai') {
            $booking->update([
                'strip_design_method' => 'ai',
                'strip_self_tool'     => null,
                'strip_template_id'   => null,
                'strip_status'        => 'customer_self_designing',
            ]);

            return redirect()->route('portal.design-tool', $token);
        }

        // template / photoshop / canva → klant ontwerpt zelf met een template (geen tool-keuze meer).
        $booking->update([
            'strip_design_method' => 'self',
            'strip_self_tool'     => null,
            'strip_template_id'   => null,
            'strip_status'        => 'customer_self_designing',
        ]);

        if ($booking->account->email) {
            app(MailService::class)->send('admin_strip_method_self', $booking, $booking->account->email);
        }

        return redirect()->route('portal.show', $token)
            ->with('success', '✅ Genoteerd! Stuur je afgewerkte ontwerp naar ontwerp@flitsmoment.nl.');
    }

    /** Klant wil terug naar het keuzemenu. Alleen toegestaan als status ∉ {accepted, ready}. */
    public function resetStripChoice(string $token): RedirectResponse
    {
        $booking = Booking::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)
            ->firstOrFail();

        if (in_array($booking->strip_status, ['accepted', 'ready'])) {
            return back()->with('error', 'Het ontwerp is al goedgekeurd — wijzigen kan niet meer.');
        }

        $booking->update([
            'strip_design_method' => null,
            'strip_self_tool'     => null,
            'strip_template_id'   => null,
            'strip_status'        => null,
            'strip_intake_data'   => null,
        ]);

        return redirect()->route('portal.show', $token)
            ->with('success', 'Je keuze is gereset. Kies opnieuw uit de opties.');
    }

    /** Klant levert input aan voor het fotostrip ontwerp */
    public function submitStripInput(Request $request, string $token): RedirectResponse
    {
        $booking = Booking::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)
            ->with('account')
            ->firstOrFail();

        $request->validate([
            'strip_input_text'   => ['nullable', 'string', 'max:3000'],
            'strip_input_files'  => ['nullable', 'array', 'max:5'],
            'strip_input_files.*' => ['file', 'mimes:jpg,jpeg,png,gif,webp,pdf', 'max:10240'],
        ], [
            'strip_input_text.required' => 'Vul minimaal een omschrijving in.',
        ]);

        if (! $request->filled('strip_input_text') && ! $request->hasFile('strip_input_files')) {
            return back()->with('error', 'Vul een omschrijving in of voeg een bestand toe.');
        }

        // Bestaande bestanden behouden bij her-inzending
        $uploadedFiles = $booking->strip_intake_data['files'] ?? [];

        // Nieuwe bestanden uploaden en toevoegen
        if ($request->hasFile('strip_input_files')) {
            foreach ($request->file('strip_input_files') as $file) {
                if (count($uploadedFiles) >= 5) break;
                $path = $file->store('strip-intake', 'public');
                $uploadedFiles[] = [
                    'url'      => Storage::disk('public')->url($path),
                    'filename' => $file->getClientOriginalName(),
                ];
            }
        }

        $intakeData = [
            'text'         => $request->strip_input_text ?? '',
            'files'        => $uploadedFiles,
            'submitted_at' => now()->toIso8601String(),
        ];

        // Bij Optie 3 (custom) wordt deze route direct gebruikt zonder dat strip_design_method al gezet is.
        // Bij Optie 2 (template) is method al 'template' en wordt alleen tekst aangeleverd.
        $update = [
            'strip_intake_data' => $intakeData,
            'strip_status'      => 'designing',
        ];
        if (! $booking->strip_design_method) {
            $update['strip_design_method'] = 'custom';
        }

        $booking->update($update);

        // Mail naar admin (bestaande template — body kan template-info tonen via {{boeking.strip_template_id}})
        $mail = app(MailService::class);
        if ($booking->account->email) {
            $mail->send('admin_strip_input_received', $booking, $booking->account->email);
        }

        return redirect()->route('portal.show', $token)
            ->with('success', '✅ Bedankt! We gaan aan de slag met jouw fotostrip ontwerp.');
    }

    /** Klant beoordeelt het fotostrip ontwerp (accepteren of feedback) */
    public function stripReview(Request $request, string $token): RedirectResponse
    {
        $booking = Booking::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)
            ->with('account')
            ->firstOrFail();

        $action = $request->input('action');
        $mail   = app(MailService::class);

        if ($action === 'accept') {
            $booking->update(['strip_status' => 'accepted']);

            // AI-ontwerp geaccepteerd → automatisch het productie-PNG (met masker) klaarzetten.
            app(DesignRenderService::class)->publishBookingToProduction($booking);

            if ($booking->customer_email) {
                $mail->send('customer_strip_accepted', $booking, $booking->customer_email);
            }
            if ($booking->account->email) {
                $mail->send('admin_strip_accepted', $booking, $booking->account->email);
            }

            return redirect()->route('portal.show', $token)
                ->with('success', '✅ Bedankt! U heeft het ontwerp goedgekeurd. We gaan ermee aan de slag!');
        }

        if ($action === 'comment') {
            $request->validate(['strip_notes' => ['required', 'string', 'max:2000']]);

            $now = now();
            $comments = $booking->strip_comments ?? [];
            $comments[] = [
                'text'       => $request->strip_notes,
                'design_url' => $booking->strip_design_url,
                'created_at' => $now->toIso8601String(),
            ];

            $booking->update([
                'strip_notes'       => $request->strip_notes,
                'strip_feedback'    => $request->strip_notes,
                'strip_feedback_at' => $now,
                'strip_comments'    => $comments,
                'strip_status'      => 'designing',
            ]);

            if ($booking->account->email) {
                $mail->send('admin_strip_comment', $booking, $booking->account->email);
            }

            return redirect()->route('portal.show', $token)
                ->with('success', '💬 Bedankt voor je feedback! We passen het ontwerp aan en sturen het opnieuw.');
        }

        return redirect()->route('portal.show', $token);
    }

    /** Klant vraagt een ander terugbrengtijdstip aan */
    public function requestReturnTime(Request $request, string $token): RedirectResponse
    {
        $booking = Booking::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)
            ->with('account')
            ->firstOrFail();

        // Alleen To Go boekingen met een vastgesteld terugbrengmoment
        if ($booking->booking_type !== 'to_go' || ! $booking->customer_return_at) {
            return redirect()->route('portal.show', $token)->with('error', 'Niet beschikbaar voor deze boeking.');
        }

        // Bouw de toegestane tijdsloten (08:00 t/m 15:00 per 30 min)
        $slots = [];
        for ($h = 8; $h <= 15; $h++) {
            $slots[] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
            if ($h < 15) {
                $slots[] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':30';
            }
        }

        $request->validate([
            'return_time' => ['required', 'string', 'in:' . implode(',', $slots)],
        ], [
            'return_time.in' => 'Kies een geldig tijdstip uit de lijst.',
        ]);

        $requestedTime = $request->return_time;

        // 10:00 is de standaard voorkeur — direct accepteren
        if ($requestedTime === '10:00') {
            $newReturnAt = $booking->customer_return_at->setTimeFromTimeString('10:00');
            $booking->update([
                'customer_return_at'      => $newReturnAt,
                'proposed_return_time'    => null,
                'proposed_return_status'  => 'approved',
            ]);

            return redirect()->route('portal.show', $token)
                ->with('success', '✅ Terugbrengtijdstip bevestigd: ' . $newReturnAt->format('H:i') . ' uur.');
        }

        // Ander tijdstip — verzoek opslaan en mail sturen naar admin
        $booking->update([
            'proposed_return_time'   => $requestedTime,
            'proposed_return_status' => 'pending',
        ]);

        if ($booking->account->email) {
            Mail::to($booking->account->email)->send(new ReturnTimeRequestedNotification($booking));
        }

        return redirect()->route('portal.show', $token)
            ->with('success', '⏰ Je verzoek voor ' . $requestedTime . ' uur is ontvangen. We bevestigen dit zo snel mogelijk.');
    }

    /** Contactpersoon op locatie opslaan (Full Service). */
    public function saveLocationContact(Request $request, string $token): RedirectResponse
    {
        $booking = Booking::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)->with('account')->firstOrFail();

        $types = ['ceremoniemeester', 'eventmanager', 'eventlocatie', 'wij_zelf', 'anders'];
        $data = $request->validate([
            'location_contact_type'  => ['required', 'in:' . implode(',', $types)],
            'location_contact_phone' => ['required', 'string', 'max:40'],
            'location_contact_email' => ['nullable', 'email', 'max:150'],
        ], [
            'location_contact_phone.required' => 'Vul een telefoonnummer in voor de contactpersoon.',
        ]);

        $booking->update([
            'location_contact_type'  => $data['location_contact_type'],
            'location_contact_phone' => $data['location_contact_phone'],
            'location_contact_email' => $data['location_contact_email'] ?? null,
        ]);

        // Korte notificatie naar de admin zodat je de info binnen hebt.
        if ($booking->account->email) {
            $labels = [
                'ceremoniemeester' => 'Ceremoniemeester', 'eventmanager' => 'Eventmanager',
                'eventlocatie' => 'Eventlocatie', 'wij_zelf' => 'Wij zelf (klant)', 'anders' => 'Anders',
            ];
            $rol   = $labels[$data['location_contact_type']] ?? $data['location_contact_type'];
            $extra = $data['location_contact_email'] ? ' · ' . e($data['location_contact_email']) : '';
            $crm   = route('bookings.show', $booking->id);
            app(MailService::class)->sendRaw(
                $booking->account->email,
                "📇 Contactpersoon op locatie — {$booking->customer_name} ({$booking->booking_number})",
                "<p><strong>" . e($booking->customer_name) . "</strong> heeft de contactpersoon op locatie ingevuld voor boeking <strong>{$booking->booking_number}</strong>:</p>"
                . "<p style='font-size:15px;'><strong>" . e($rol) . "</strong><br>📞 " . e($data['location_contact_phone']) . $extra . "</p>"
                . "<p><a href='{$crm}' style='display:inline-block;background:#2563eb;color:#fff;padding:.5rem 1rem;border-radius:.4rem;text-decoration:none;font-weight:600;'>Openen in CRM →</a></p>"
            );
        }

        return redirect()->route('portal.show', $token)->with('success', '✅ Contactpersoon op locatie opgeslagen.');
    }

    /** Klant vraagt een wijziging aan van het bezorg/ophaalmoment (concrete nieuwe tijd). */
    public function requestTimeChange(Request $request, string $token): RedirectResponse
    {
        $booking = Booking::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)->with('account')->firstOrFail();

        $keys = $booking->booking_type === 'to_go'
            ? ['customer_pickup_at', 'customer_return_at']
            : ['delivery_at', 'pickup_at'];

        $data = $request->validate(array_merge(
            array_fill_keys($keys, ['nullable', 'date']),
            ['note' => ['nullable', 'string', 'max:1000']],
        ));

        $change = [];
        foreach ($keys as $k) {
            if (! empty($data[$k])) {
                $change[$k] = \Carbon\Carbon::parse($data[$k])->format('Y-m-d H:i:s');
            }
        }
        if (empty($change)) {
            return redirect()->route('portal.show', $token)->with('error', 'Kies minstens één nieuw tijdstip.');
        }
        $change['note']         = $data['note'] ?? null;
        $change['requested_at'] = now()->toIso8601String();

        $changeToken = \Illuminate\Support\Str::random(48);
        $booking->update(['pending_change' => $change, 'pending_change_token' => $changeToken]);

        if ($booking->account->email) {
            $this->sendTimeChangeRequestMail($booking, $change, $changeToken);
        }

        return redirect()->route('portal.show', $token)
            ->with('success', '⏰ Je wijzigingsverzoek is verstuurd. We bevestigen het zo snel mogelijk.');
    }

    /** Admin-mail bij een wijzigingsverzoek: huidig → voorgesteld, 1-klik goedkeuren, andere momenten die dag. */
    private function sendTimeChangeRequestMail(Booking $booking, array $change, string $changeToken): void
    {
        $isToGo = $booking->booking_type === 'to_go';
        $map = $isToGo
            ? ['customer_pickup_at' => '📦 Ophalen (klant)', 'customer_return_at' => '🔄 Terugbrengen']
            : ['delivery_at' => '🚚 Bezorgen', 'pickup_at' => '📦 Ophalen (door ons)'];

        $rows = '';
        $dates = [];
        foreach ($map as $col => $label) {
            if (empty($change[$col])) continue;
            $proposedAt = \Carbon\Carbon::parse($change[$col]);
            $dates[] = $proposedAt->format('Y-m-d');
            $current  = $booking->$col ? $booking->$col->translatedFormat('l j F H:i') : '—';
            $proposed = $proposedAt->translatedFormat('l j F H:i');
            $rows .= "<tr><td style='padding:.3rem 1rem .3rem 0;color:#6b7280;'>{$label}</td>"
                   . "<td style='padding:.3rem 0;color:#9ca3af;text-decoration:line-through;'>{$current}</td>"
                   . "<td style='padding:.3rem 0 .3rem 1rem;font-weight:700;color:#ea580c;'>&rarr; {$proposed}</td></tr>";
        }

        $noteHtml = ! empty($change['note'])
            ? "<blockquote style='background:#f9fafb;border-left:4px solid #f59e0b;padding:.6rem 1rem;margin:1rem 0;'>" . nl2br(e($change['note'])) . "</blockquote>"
            : '';

        $approveUrl = route('booking.change.approve', $changeToken);
        $othersHtml = $this->otherMomentsHtml($booking, array_values(array_unique($dates)));

        $subject = "⏰ Wijzigingsverzoek bezorg/ophaal — {$booking->customer_name} ({$booking->booking_number})";
        $body = "
            <p><strong>" . e($booking->customer_name) . "</strong> vraagt een wijziging aan voor boeking <strong>{$booking->booking_number}</strong>:</p>
            <table style='border-collapse:collapse;margin:1rem 0;font-size:15px;'>{$rows}</table>
            {$noteHtml}
            <p style='margin:1.25rem 0;'><a href='{$approveUrl}' style='display:inline-block;background:#16a34a;color:#fff;padding:.7rem 1.4rem;border-radius:.5rem;text-decoration:none;font-weight:700;font-size:16px;'>✅ Goedkeuren &amp; toepassen</a></p>
            {$othersHtml}
            <p style='font-size:13px;color:#9ca3af;'>Klik je op goedkeuren, dan passen we meteen de nieuwe tijden toe en krijgt de klant automatisch een bevestigingsmail.</p>
        ";

        app(MailService::class)->sendRaw($booking->account->email, $subject, $body);
    }

    /** HTML-lijst met andere bezorg/ophaalmomenten van dit account op de opgegeven dag(en). */
    private function otherMomentsHtml(Booking $booking, array $dates): string
    {
        if (empty($dates)) return '';

        $others = Booking::withoutGlobalScope(AccountScope::class)
            ->where('account_id', $booking->account_id)
            ->where('id', '!=', $booking->id)
            ->whereIn('status', ['confirmed', 'completed'])
            ->where(function ($q) use ($dates) {
                foreach (['delivery_at', 'pickup_at', 'customer_pickup_at', 'customer_return_at'] as $col) {
                    foreach ($dates as $d) {
                        $q->orWhereDate($col, $d);
                    }
                }
            })
            ->get(['booking_number', 'customer_name', 'booking_type', 'delivery_at', 'pickup_at', 'customer_pickup_at', 'customer_return_at']);

        $items = [];
        foreach ($others as $o) {
            $moments = $o->booking_type === 'to_go'
                ? ['📦 ophalen' => $o->customer_pickup_at, '🔄 retour' => $o->customer_return_at]
                : ['🚚 bezorgen' => $o->delivery_at, '📦 ophalen' => $o->pickup_at];
            foreach ($moments as $lbl => $dt) {
                if (! $dt || $dt->format('H:i') === '00:00') continue;               // 00:00 = datum-placeholder
                if (! in_array($dt->format('Y-m-d'), $dates, true)) continue;
                $items[] = ['at' => $dt, 'html' => $dt->translatedFormat('D j M H:i') . " — {$lbl} · " . e($o->customer_name) . " ({$o->booking_number})"];
            }
        }

        if (empty($items)) {
            return "<p style='font-size:13px;color:#9ca3af;margin-top:1.25rem;'>📅 Geen andere bezorg/ophaalmomenten gepland op deze dag(en).</p>";
        }

        usort($items, fn ($a, $b) => $a['at'] <=> $b['at']);
        $list = implode('', array_map(fn ($i) => "<li style='margin:.2rem 0;'>{$i['html']}</li>", $items));

        return "<p style='font-weight:600;margin-top:1.25rem;'>📅 Andere momenten op deze dag(en):</p><ul style='font-size:14px;color:#374151;padding-left:1.1rem;'>{$list}</ul>";
    }

    /** Start een Mollie betaling */
    public function startPayment(Request $request, string $token): RedirectResponse
    {
        $booking = Booking::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)
            ->with('account')
            ->firstOrFail();

        if (! $booking->account->mollie_enabled) {
            return redirect()->route('portal.show', $token)
                ->with('info', 'Online betalen is niet beschikbaar voor deze boeking.');
        }

        $bedrag = $booking->amount_due;

        if ($bedrag <= 0) {
            return redirect()->route('portal.show', $token)
                ->with('info', 'Er is geen openstaand bedrag.');
        }

        try {
            $mollie = new MollieApiClient();
            $mollie->setApiKey($booking->account->getMollieKey());

            $paymentData = [
                'method'      => 'ideal',
                'amount'      => [
                    'currency' => 'EUR',
                    'value'    => number_format($bedrag, 2, '.', ''),
                ],
                'description' => "Boeking {$booking->booking_number} — {$booking->customer_name}",
                'redirectUrl' => route('portal.payment-return', $token),
                'metadata'    => ['booking_id' => $booking->id],
            ];

            if (! app()->environment('local')) {
                $paymentData['webhookUrl'] = route('portal.payment-webhook', $token);
            }

            $payment = $mollie->payments->create($paymentData);

            Payment::create([
                'account_id'          => $booking->account_id,
                'booking_id'          => $booking->id,
                'provider'            => 'mollie',
                'provider_payment_id' => $payment->id,
                'amount'              => $bedrag,
                'currency'            => 'EUR',
                'description'         => "Boeking {$booking->booking_number}",
                'status'              => 'pending',
            ]);

            return redirect($payment->getCheckoutUrl());

        } catch (\Exception $e) {
            return back()->with('error', 'Betaling kon niet worden gestart: ' . $e->getMessage());
        }
    }

    /** Klant keert terug na Mollie betaling — sync status direct */
    public function paymentReturn(string $token): RedirectResponse
    {
        $booking = Booking::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)
            ->with('account')
            ->firstOrFail();

        $payment = Payment::withoutGlobalScope(AccountScope::class)
            ->where('booking_id', $booking->id)
            ->whereIn('status', ['pending', 'created'])
            ->latest()
            ->first();

        if ($payment) {
            try {
                $mollie = new MollieApiClient();
                $mollie->setApiKey($booking->account->getMollieKey());
                $molliePayment = $mollie->payments->get($payment->provider_payment_id);

                $payment->update([
                    'status'      => $molliePayment->status,
                    'paid_at'     => $molliePayment->status === 'paid' ? now() : null,
                    'raw_payload' => (array) $molliePayment,
                ]);

                if ($molliePayment->status === 'paid') {
                    $totaalBetaald = Payment::withoutGlobalScope(AccountScope::class)
                        ->where('booking_id', $booking->id)
                        ->where('status', 'paid')
                        ->sum('amount');

                    $nieuwStatus = $totaalBetaald >= $booking->total_price ? 'paid' : 'partial';
                    $booking->update(['payment_status' => $nieuwStatus]);

                    // Admin mail: nieuwe betaling
                    if ($booking->account->email) {
                        app(MailService::class)->send('admin_new_payment', $booking, $booking->account->email);
                    }

                    return redirect()->route('portal.show', $token)
                        ->with('success', 'Betaling ontvangen! Bedankt.');
                }
            } catch (\Exception $e) {
                Log::error('Mollie status sync fout: ' . $e->getMessage());
            }
        }

        return redirect()->route('portal.show', $token)
            ->with('success', 'Bedankt! Uw betaling wordt verwerkt.');
    }

    /** Mollie webhook — betaalstatus updaten */
    public function paymentWebhook(Request $request, string $token): \Illuminate\Http\Response
    {
        $booking = Booking::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)
            ->with('account')
            ->firstOrFail();

        $mollieId = $request->input('id');
        if (! $mollieId) return response('ok');

        try {
            $mollie = new MollieApiClient();
            $mollie->setApiKey($booking->account->getMollieKey());
            $molliePayment = $mollie->payments->get($mollieId);

            $payment = Payment::withoutGlobalScope(AccountScope::class)
                ->where('provider_payment_id', $mollieId)->first();
            if (! $payment) return response('ok');

            $payment->update([
                'status'      => $molliePayment->status,
                'paid_at'     => $molliePayment->status === 'paid' ? now() : null,
                'raw_payload' => (array) $molliePayment,
            ]);

            if ($molliePayment->status === 'paid') {
                $totaalBetaald = Payment::withoutGlobalScope(AccountScope::class)
                    ->where('booking_id', $booking->id)
                    ->where('status', 'paid')
                    ->sum('amount');

                $nieuwStatus = $totaalBetaald >= $booking->total_price ? 'paid' : 'partial';
                $booking->update(['payment_status' => $nieuwStatus]);

                // Admin mail: nieuwe betaling
                if ($booking->account->email) {
                    app(MailService::class)->send('admin_new_payment', $booking, $booking->account->email);
                }
            }

        } catch (\Exception $e) {
            Log::error('Mollie webhook fout: ' . $e->getMessage());
        }

        return response('ok');
    }
}
