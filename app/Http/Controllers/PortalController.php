<?php

namespace App\Http\Controllers;

use App\Mail\ReturnTimeRequestedNotification;
use App\Models\Booking;
use App\Models\DesignMask;
use App\Models\DesignPromptSetting;
use App\Models\DesignSession;
use App\Models\Payment;
use App\Models\StripTemplate;
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

        // Actieve templates voor dit account (voor de galerij van Optie 2)
        $stripTemplates = StripTemplate::withoutGlobalScope(AccountScope::class)
            ->where('account_id', $booking->account_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('number')
            ->get();

        // Canva-templates voor het Zelf-ontwerpen / Canva sub-pad
        $canvaTemplates = CanvaTemplate::withoutGlobalScope(AccountScope::class)
            ->where('account_id', $booking->account_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('portal.booking', compact('booking', 'stripTemplates', 'canvaTemplates'));
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

    /** Display the strip design to customer in portal */
    public function stripDesignView(string $token): View
    {
        $booking = Booking::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)
            ->with('account')
            ->firstOrFail();

        return view('portal.strip-design', compact('booking'));
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

        if (! empty($state['backgroundPath']) && Storage::disk('public')->exists($state['backgroundPath'])) {
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
            'justGenerated' => false,
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

    /** Bepaal de achtergrond — AI-generatie is geblokkeerd na DesignSession::MAX_BACKGROUND_GENERATIONS pogingen; upload/kleur tellen niet mee */
    public function designToolGenerate(Request $request, string $token): View
    {
        $booking = $this->designToolBooking($token);
        abort_if(! $booking, 404);

        $session = $this->designSession($booking);
        $service = app(DesignGenerationService::class);
        $method  = $request->input('background_method', 'ai');

        if ($method === 'ai' && $session->backgroundLimitReached()) {
            return view('portal.design-tool', array_merge($this->designToolViewData($booking, $session), [
                'booking'       => $booking,
                'results'       => ['ok' => false, 'limit' => true],
                'input'         => $request->input('input', $session->input ?? ''),
                'eventType'     => $request->input('event_type', $session->event_type),
                'initialState'  => $session->state ?? [],
                'justGenerated' => false,
            ]));
        }

        $data  = $service->validateGenerateRequest($request);
        $input = $session->input ?? '';

        if ($method === 'upload') {
            $results = $service->uploadBackground($request->file('background_upload'));
        } elseif ($method === 'color') {
            $results = $service->colorBackground($data['background_color']);
        } else {
            $refPaths = $service->storeReferenceFiles($request);
            $results  = $service->generateBackground($data['event_type'], $data['input'], $refPaths, $booking->account_id);
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

        return view('portal.design-tool', array_merge($this->designToolViewData($booking, $session->fresh()), [
            'booking'       => $booking,
            'results'       => $results,
            'input'         => $input,
            'eventType'     => $data['event_type'],
            'initialState'  => $state,
            'justGenerated' => true,
        ]));
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

        $result = $service->cutoutLogo(Storage::disk('local')->path($stored));
        $session->increment('logo_cutouts');

        return response()->json($result);
    }

    /** Pas een masker (+ optionele gekleurde svg-rand) toe — zelfde logica als de admin-tool, expliciet account-gescoped */
    public function designToolApplyMask(Request $request, string $token): JsonResponse
    {
        $booking = $this->designToolBooking($token);
        if (! $booking) {
            return response()->json(['ok' => false, 'error' => 'Boeking niet gevonden.'], 404);
        }

        $data = $request->validate([
            'background_path' => ['required', 'string'],
            'mask_id'          => ['required', 'integer'],
            'border_color'     => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
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
                ->renderMaskPreview($data['background_path'], $mask, $data['border_color'] ?? null);

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
            'session'        => $session,
            'urls'           => [
                'generate'         => route('portal.design-tool.generate', $token),
                'logoCutout'       => route('portal.design-tool.logo', $token),
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

    /** Stap 1: klant kiest een hoofdmethode. Sub-keuzes volgen in vervolgstappen. */
    public function setStripMethod(Request $request, string $token): RedirectResponse
    {
        $booking = Booking::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)
            ->with('account')
            ->firstOrFail();

        if (in_array($booking->strip_status, ['accepted', 'ready'])) {
            return back()->with('error', 'Het ontwerp is al goedgekeurd — wijzigen kan niet meer.');
        }

        $validated = $request->validate([
            'method' => ['required', 'in:self,template,custom'],
        ]);

        $booking->update([
            'strip_design_method' => $validated['method'],
            'strip_self_tool'     => null,
            'strip_template_id'   => null,
            'strip_status'        => null,
            'strip_intake_data'   => null,
        ]);

        // Voor 'custom': mail wordt verstuurd zodra klant brief indient (bestaande admin_strip_input_received).
        // Voor 'template': mail wordt verstuurd zodra klant template kiest.
        // Voor 'self': mail wordt verstuurd zodra klant tool kiest (Canva/Photoshop).

        return redirect()->route('portal.show', $token);
    }

    /**
     * Stap 1 (nieuw): klant kiest direct één van de 3 paden — photoshop/canva (self met tool
     * in één stap) of ai (eigen wizard-pagina). "Door ons laten ontwerpen" is geen hoofdoptie
     * meer, maar blijft bereikbaar via het hulp-formulier onder elk pad (submitStripInput).
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
            'path' => ['required', 'in:photoshop,canva,ai'],
        ]);

        if ($validated['path'] === 'ai') {
            $booking->update([
                'strip_design_method' => 'ai',
                'strip_self_tool'     => null,
                'strip_template_id'   => null,
                'strip_status'        => null,
            ]);

            return redirect()->route('portal.design-tool', $token);
        }

        $booking->update([
            'strip_design_method' => 'self',
            'strip_self_tool'     => $validated['path'],
            'strip_template_id'   => null,
            'strip_status'        => 'awaiting_customer_design',
        ]);

        if ($booking->account->email) {
            app(MailService::class)->send('admin_strip_method_self', $booking, $booking->account->email);
        }

        return redirect()->route('portal.show', $token)
            ->with('success', '✅ Genoteerd! Stuur je afgewerkte ontwerp naar ontwerp@flitsmoment.nl.');
    }

    /** Stap 2a (Self): klant kiest tool — pas hier wordt admin op de hoogte gebracht. */
    public function setStripSelfTool(Request $request, string $token): RedirectResponse
    {
        $booking = Booking::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)
            ->with('account')
            ->firstOrFail();

        if ($booking->strip_design_method !== 'self') {
            return back()->with('error', 'Eerst een ontwerpmethode kiezen.');
        }
        if (in_array($booking->strip_status, ['accepted', 'ready'])) {
            return back()->with('error', 'Het ontwerp is al goedgekeurd — wijzigen kan niet meer.');
        }

        $validated = $request->validate([
            'tool' => ['required', 'in:canva,photoshop'],
        ]);

        $booking->update([
            'strip_self_tool' => $validated['tool'],
            'strip_status'    => 'awaiting_customer_design',
        ]);

        if ($booking->account->email) {
            app(MailService::class)->send('admin_strip_method_self', $booking, $booking->account->email);
        }

        return redirect()->route('portal.show', $token)
            ->with('success', '✅ Genoteerd! Stuur je afgewerkte ontwerp naar ontwerp@flitsmoment.nl.');
    }

    /** Klant kiest een template uit de galerij. */
    public function selectStripTemplate(Request $request, string $token): RedirectResponse
    {
        $booking = Booking::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)
            ->with('account')
            ->firstOrFail();

        if (in_array($booking->strip_status, ['accepted', 'ready'])) {
            return back()->with('error', 'Het ontwerp is al goedgekeurd — wijzigen kan niet meer.');
        }

        $request->validate([
            'template_id' => ['required', 'integer', 'exists:strip_templates,id'],
        ]);

        // Verifieer dat het template van hetzelfde account én actief is
        $template = StripTemplate::withoutGlobalScope(AccountScope::class)
            ->where('id', $request->template_id)
            ->where('account_id', $booking->account_id)
            ->where('is_active', true)
            ->firstOrFail();

        $booking->update([
            'strip_design_method' => 'template',
            'strip_self_tool'     => null,
            'strip_template_id'   => $template->id,
            'strip_status'        => 'waiting_input',
        ]);

        // Notify admin
        if ($booking->account->email) {
            app(MailService::class)->send('admin_strip_method_template', $booking, $booking->account->email);
        }

        return redirect()->route('portal.show', $token)
            ->with('success', "✅ Template #{$template->number} gekozen — lever nu de tekst aan die op de strip moet komen.");
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
            ->with('success', 'Je keuze is gereset. Kies opnieuw uit de 3 opties.');
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

    /** Customer submits feedback on strip design */
    public function submitStripFeedback(Request $request, string $token): RedirectResponse
    {
        $booking = Booking::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)
            ->with('account')
            ->firstOrFail();

        $request->validate([
            'feedback' => ['required', 'string', 'max:2000']
        ]);

        // Auto-increment strip_version
        $newVersion = ($booking->strip_version ?? 1) + 1;
        $now = now();

        // Voeg toe aan commentaarhistorie
        $comments = $booking->strip_comments ?? [];
        $comments[] = [
            'text'       => $request->feedback,
            'design_url' => $booking->strip_design_url,
            'created_at' => $now->toIso8601String(),
        ];

        $booking->update([
            'strip_notes'       => $request->feedback,
            'strip_feedback'    => $request->feedback,
            'strip_feedback_at' => $now,
            'strip_version'     => $newVersion,
            'strip_comments'    => $comments,
            'strip_status'      => 'designing',
        ]);

        // Stuur direct de admin mail
        $mail = app(MailService::class);
        if ($booking->account->email) {
            $mail->send('admin_strip_comment', $booking, $booking->account->email);
        }

        return redirect()->route('portal.strip-design', $token)
            ->with('success', '✅ Bedankt voor je feedback! We passen het ontwerp aan.');
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
