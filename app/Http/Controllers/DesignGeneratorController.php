<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\DesignMask;
use App\Models\DesignPromptSetting;
use App\Models\DesignSession;
use App\Services\DesignGenerationService;
use App\Services\DesignRenderService;
use App\Services\StripDesignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DesignGeneratorController extends Controller
{
    /**
     * Ontwerpen kan alléén gekoppeld aan een boeking. De generator-ingang toont daarom direct een
     * overzicht van boekingen om uit te kiezen (geen los/algemeen ontwerp meer).
     */
    public function index(): View
    {
        return view('design.index', [
            'bookings' => $this->pickableBookings(),
        ]);
    }

    /** Boekingen om een ontwerp voor te maken: aankomende bevestigde boekingen, eerstvolgende eerst. */
    private function pickableBookings()
    {
        return Booking::where('status', 'confirmed')
            ->whereDate('event_date', '>=', now()->toDateString())
            ->orderBy('event_date')
            ->get(['id', 'booking_number', 'customer_name', 'event_date', 'strip_status', 'production_file_path']);
    }

    /** Pas een masker toe op een gegenereerde achtergrond (standalone preview) */
    public function applyMask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'background_path' => ['required', 'string'],
            'mask_id'          => ['required', 'integer', 'exists:design_masks,id'],
        ]);

        if (
            ! str_starts_with($data['background_path'], 'design-generator/out/')
            || ! Storage::disk('public')->exists($data['background_path'])
        ) {
            return response()->json(['ok' => false, 'error' => 'Achtergrond niet gevonden.']);
        }

        $mask = DesignMask::findOrFail($data['mask_id']);

        try {
            $binary = app(DesignRenderService::class)
                ->renderMaskPreview($data['background_path'], $mask);

            $filename = 'design-generator/out/' . Str::random(24) . '-masked.png';
            Storage::disk('public')->put($filename, $binary);

            return response()->json([
                'ok'  => true,
                'url' => Storage::disk('public')->url($filename),
            ]);
        } catch (\Throwable $e) {
            Log::error('Masker toepassen mislukt: ' . $e->getMessage());

            return response()->json([
                'ok'    => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Voeg een zwart-wit maskerafbeelding (+ optionele thumbnail) toe aan de herbruikbare bibliotheek */
    public function uploadMask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label'          => ['required', 'string', 'max:100'],
            'mask'           => ['required', 'image', 'max:8192'],
            'thumbnail'      => ['nullable', 'image', 'max:8192'],
            'preview_photos' => ['nullable', 'image', 'max:8192'],
        ], [], [
            'mask'           => 'maskerafbeelding',
            'thumbnail'      => 'thumbnail-afbeelding',
            'preview_photos' => 'voorbeeldfoto\'s-afbeelding',
        ]);

        $maskImage = new \Imagick($request->file('mask')->getRealPath());
        $maskImage->setImageFormat('png');

        $filename = 'design-generator/masks/' . Str::random(24) . '.png';
        Storage::disk('public')->put($filename, $maskImage->getImageBlob());

        $thumbFilename = null;
        if ($request->hasFile('thumbnail')) {
            $thumbFilename = 'design-generator/masks/' . Str::random(24) . '-thumb.'
                . $request->file('thumbnail')->extension();
            Storage::disk('public')->put($thumbFilename, file_get_contents($request->file('thumbnail')->getRealPath()));
        }

        $previewPhotosFilename = null;
        if ($request->hasFile('preview_photos')) {
            $previewPhotosFilename = 'design-generator/masks/' . Str::random(24) . '-preview.'
                . $request->file('preview_photos')->extension();
            Storage::disk('public')->put($previewPhotosFilename, file_get_contents($request->file('preview_photos')->getRealPath()));
        }

        $mask = DesignMask::create([
            'label'               => $data['label'],
            'path'                => $filename,
            'thumbnail_path'      => $thumbFilename,
            'preview_photos_path' => $previewPhotosFilename,
        ]);

        return response()->json([
            'ok'               => true,
            'id'               => $mask->id,
            'label'            => $mask->label,
            'url'              => $mask->url,
            'thumbnailUrl'     => $mask->thumbnail_url,
            'previewPhotosUrl' => $mask->preview_photos_url,
        ]);
    }

    /** Verwijder een masker uit de bibliotheek */
    public function destroyMask(DesignMask $mask): JsonResponse
    {
        Storage::disk('public')->delete(array_filter([
            $mask->path, $mask->thumbnail_path, $mask->svg_path, $mask->preview_photos_path,
        ]));
        $mask->delete();

        return response()->json(['ok' => true]);
    }

    /** Sla de (vaste, herbruikbare) prompt-instelling voor een onderdeel + event-type op */
    public function updatePrompt(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key'        => ['required', 'string', 'in:background'],
            'event_type' => ['required', Rule::in(array_keys(DesignPromptSetting::EVENT_TYPES))],
            'prompt'     => ['required', 'string', 'max:4000'],
        ]);

        DesignPromptSetting::updateOrCreate(
            ['key' => $data['key'], 'event_type' => $data['event_type']],
            ['label' => DesignPromptSetting::label($data['key']), 'prompt' => $data['prompt']]
        );

        return response()->json(['ok' => true]);
    }

    // ──────────────────────────────────────────────────────────────
    // Boeking-gekoppelde editor (admin)
    // ──────────────────────────────────────────────────────────────

    /** Toon/hervat de ontwerp-sessie van een specifieke boeking */
    public function bookingIndex(Booking $booking): View
    {
        $session = $this->session($booking);
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

        return view('design.booking', array_merge($this->baseViewData(), [
            'booking'      => $booking,
            'session'      => $session,
            'results'      => $results,
            'input'        => $session->input ?? '',
            'eventType'    => $session->event_type ?? array_key_first(DesignPromptSetting::EVENT_TYPES),
            'initialState' => $state,
        ]));
    }

    /** Autosave: sla de huidige stand van de ontwerp-sessie op */
    public function bookingSaveState(Request $request, Booking $booking): JsonResponse
    {
        $data = $request->validate([
            'event_type' => ['nullable', 'string'],
            'input'      => ['nullable', 'string', 'max:2000'],
            'state'      => ['nullable', 'array'],
        ]);

        $session = $this->session($booking);
        $session->update([
            'event_type' => $data['event_type'] ?? $session->event_type,
            'input'      => $data['input'] ?? $session->input,
            'state'      => array_merge($session->state ?? [], $data['state'] ?? []),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Bepaal de achtergrond (AI-generatie, eigen upload of een effen kleur) voor de boeking-gekoppelde sessie.
     * Redirect na afloop (Post/Redirect/Get) zodat een pagina-herlaad de POST niet opnieuw uitvoert —
     * anders genereerde een reload op deze url stilletjes een nieuwe AI-achtergrond i.p.v. de opgeslagen te tonen.
     */
    public function bookingGenerate(Request $request, Booking $booking): RedirectResponse|JsonResponse
    {
        $service = app(DesignGenerationService::class);
        $data = $service->validateGenerateRequest($request);
        $method = $data['background_method'] ?? 'ai';
        $input = '';

        if ($method === 'upload') {
            $results = $service->uploadBackground($request->file('background_upload'));
        } elseif ($method === 'color') {
            $results = $service->colorBackground($data['background_color']);
        } elseif ($method === 'template') {
            $results = $service->templateBackground((int) $data['template_id']);
        } else {
            $refPaths = $service->storeReferenceFiles($request);
            $results = $service->generateBackground($data['event_type'], $data['input'], $refPaths, null, $booking->id, 'admin_generator');
            $input = $data['input'];
        }

        $session = $this->session($booking);
        $state   = $session->state ?? [];
        if ($results['ok']) {
            $state['backgroundPath'] = $results['path'];
        }

        $session->update([
            'event_type' => $data['event_type'],
            'input'      => $input,
            'state'      => $state,
        ]);

        // Regeneratie via AJAX (er staat al een achtergrond) — geen redirect, puur het resultaat,
        // zodat de client de afbeelding in-place kan verversen zonder de pagina te herladen.
        if ($request->expectsJson()) {
            return response()->json($results);
        }

        if (! $results['ok']) {
            return redirect()->route('design.booking', $booking)->with('error', $results['error']);
        }

        return redirect()->route('design.booking', $booking);
    }

    /** Stel een logo vrij voor de boeking-gekoppelde sessie (geen limiet — admin) */
    public function bookingCutoutLogo(Request $request, Booking $booking): JsonResponse
    {
        $data = app(DesignGenerationService::class)->validateLogoRequest($request);
        $stored = $request->file('logo')->store('design-generator/refs', 'local');

        return response()->json(app(DesignGenerationService::class)->cutoutLogo(
            Storage::disk('local')->path($stored),
            $booking->account_id,
            $request->input('description'),
            'admin_generator',
            $booking->id,
        ));
    }

    /** Plaats een goedgekeurd bibliotheek-element als laag (boeking-gekoppelde sessie — admin) */
    public function bookingUseElement(Request $request, Booking $booking): JsonResponse
    {
        $request->validate(['element_id' => ['required', 'integer']]);

        return response()->json(app(DesignGenerationService::class)->elementFromLibrary((int) $request->input('element_id')));
    }

    /** Gebruik een geüpload logo direct, zonder AI-vrijstelling (boeking-gekoppelde sessie — admin) */
    public function bookingUploadLogo(Request $request, Booking $booking): JsonResponse
    {
        app(DesignGenerationService::class)->validateLogoRequest($request);

        return response()->json(app(DesignGenerationService::class)->uploadLogoDirect($request->file('logo')));
    }

    /** Verstuur het huidige ontwerp naar de klant (mockup + mail, zoals de bestaande handmatige upload) */
    public function bookingSendToCustomer(Booking $booking): RedirectResponse
    {
        $session = $this->session($booking);

        try {
            // includePreviewPhotos: de klant moet altijd de voorbeeldfoto's in de vensters zien
            // (ongeacht de "Preview modus"-stand in de editor) — anders ogen de foto-vensters
            // leeg/transparant in de presentatie. Het echte productiebestand (bookingSetProduction)
            // rendert apart, met genuine transparantie, dus dit raakt de productie-flow niet.
            $binary = app(DesignRenderService::class)->render($session, includePreviewPhotos: true);
        } catch (\Throwable $e) {
            return back()->with('error', 'Kan nog niet versturen: ' . $e->getMessage());
        }

        $filename = 'strip-designs/ai_' . Str::random(20) . '.png';
        Storage::disk('public')->put($filename, $binary);

        $stripDesignService = app(StripDesignService::class);
        $mockPath = $stripDesignService->applyMockupIfImage($filename, 'image/png');
        $url      = Storage::disk('public')->url($mockPath);

        $stripDesignService->attachDesign($booking, $url, 'ai-ontwerp.jpg');

        return redirect()->route('design.booking', $booking)
            ->with('success', "Ontwerp verstuurd naar {$booking->customer_name}.");
    }

    /** Zet de huidige ontwerp-render klaar als productiebestand (voor de photobooth-download) */
    public function bookingSetProduction(Booking $booking): RedirectResponse
    {
        $session = $this->session($booking);

        try {
            app(DesignRenderService::class)->storeProductionFile($session, $booking);
        } catch (\Throwable $e) {
            return back()->with('error', 'Kan nog niet klaarzetten: ' . $e->getMessage());
        }

        return redirect()->route('design.booking', $booking)
            ->with('success', 'PNG klaargezet voor productie.');
    }

    private function session(Booking $booking): DesignSession
    {
        return DesignSession::firstOrCreate(['booking_id' => $booking->id]);
    }

    // ──────────────────────────────────────────────────────────────
    // Gedeelde kernlogica
    // ──────────────────────────────────────────────────────────────

    private function baseViewData(): array
    {
        $promptsByType = [];
        foreach (DesignPromptSetting::EVENT_TYPES as $type => $typeLabel) {
            $promptsByType[$type] = DesignPromptSetting::currentPrompt('background', $type);
        }

        $masks = DesignMask::orderBy('label')->get()->map(fn (DesignMask $m) => [
            'id'               => $m->id,
            'label'            => $m->label,
            'url'              => $m->url,
            'thumbnailUrl'     => $m->thumbnail_url,
            'previewPhotosUrl' => $m->preview_photos_url,
        ])->values();

        return [
            'promptKey'      => 'background',
            'promptLabel'    => DesignPromptSetting::label('background'),
            'promptDefault'  => DesignPromptSetting::DEFAULTS['background']['prompt'],
            'promptsByType'  => $promptsByType,
            'eventTypes'     => DesignPromptSetting::EVENT_TYPES,
            'eventType'      => array_key_first(DesignPromptSetting::EVENT_TYPES),
            'logoEventTypes' => DesignPromptSetting::LOGO_EVENT_TYPES,
            'masks'          => $masks,
            'googleFonts'    => \App\Services\GoogleFontRegistry::labels(),
            'templateCategories' => app(DesignGenerationService::class)->templateCategories(),
            'elementCategories'  => app(DesignGenerationService::class)->elementCategories(),
        ];
    }
}
