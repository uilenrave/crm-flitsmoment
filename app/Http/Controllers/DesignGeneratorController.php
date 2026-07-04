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
    /** Toon het ontwerp-generator formulier (los, niet aan een boeking gekoppeld) */
    public function index(): View
    {
        $upcomingBookings = Booking::where('status', 'confirmed')
            ->whereDate('event_date', '>=', now()->toDateString())
            ->orderBy('event_date')
            ->get(['id', 'booking_number', 'customer_name', 'event_date']);

        return view('design.index', array_merge($this->baseViewData(), [
            'results'          => null,
            'input'            => '',
            'upcomingBookings' => $upcomingBookings,
        ]));
    }

    /** Genereer een achtergrondafbeelding via Gemini (standalone) */
    public function generate(Request $request): View
    {
        $data = app(DesignGenerationService::class)->validateGenerateRequest($request);
        $refPaths = app(DesignGenerationService::class)->storeReferenceFiles($request);
        $results = app(DesignGenerationService::class)->generateBackground($data['event_type'], $data['input'], $refPaths);

        return view('design.index', array_merge($this->baseViewData(), [
            'results'   => $results,
            'input'     => $data['input'],
            'eventType' => $data['event_type'],
        ]));
    }

    /** Stel een geüpload logo vrij als transparante PNG (standalone) */
    public function cutoutLogo(Request $request): JsonResponse
    {
        $data = app(DesignGenerationService::class)->validateLogoRequest($request);
        $stored = $request->file('logo')->store('design-generator/refs', 'local');

        return response()->json(app(DesignGenerationService::class)->cutoutLogo(Storage::disk('local')->path($stored)));
    }

    /** Pas een masker (+ optionele gekleurde svg-rand) toe op een gegenereerde achtergrond (standalone preview) */
    public function applyMask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'background_path' => ['required', 'string'],
            'mask_id'          => ['required', 'integer', 'exists:design_masks,id'],
            'border_color'     => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
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
                ->renderMaskPreview($data['background_path'], $mask, $data['border_color'] ?? null);

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

    /** Voeg een zwart-wit maskerafbeelding (+ optionele thumbnail/svg-rand) toe aan de herbruikbare bibliotheek */
    public function uploadMask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label'     => ['required', 'string', 'max:100'],
            'mask'      => ['required', 'image', 'max:8192'],
            'thumbnail' => ['nullable', 'image', 'max:8192'],
            'svg'       => ['nullable', 'file', 'mimes:svg', 'max:2048'],
        ], [], [
            'mask'      => 'maskerafbeelding',
            'thumbnail' => 'thumbnail-afbeelding',
            'svg'       => 'svg-bestand',
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

        $svgFilename = null;
        if ($request->hasFile('svg')) {
            $svgContent  = $this->sanitizeSvg(file_get_contents($request->file('svg')->getRealPath()));
            $svgFilename = 'design-generator/masks/' . Str::random(24) . '.svg';
            Storage::disk('public')->put($svgFilename, $svgContent);
        }

        $mask = DesignMask::create([
            'label'          => $data['label'],
            'path'           => $filename,
            'thumbnail_path' => $thumbFilename,
            'svg_path'       => $svgFilename,
        ]);

        return response()->json([
            'ok'           => true,
            'id'           => $mask->id,
            'label'        => $mask->label,
            'url'          => $mask->url,
            'thumbnailUrl' => $mask->thumbnail_url,
            'svgContent'   => $mask->svg_path ? Storage::disk('public')->get($mask->svg_path) : null,
        ]);
    }

    /** Verwijder een masker uit de bibliotheek */
    public function destroyMask(DesignMask $mask): JsonResponse
    {
        Storage::disk('public')->delete(array_filter([$mask->path, $mask->thumbnail_path, $mask->svg_path]));
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

    /** Genereer een achtergrond voor de boeking-gekoppelde sessie */
    public function bookingGenerate(Request $request, Booking $booking): View
    {
        $data = app(DesignGenerationService::class)->validateGenerateRequest($request);
        $refPaths = app(DesignGenerationService::class)->storeReferenceFiles($request);
        $results = app(DesignGenerationService::class)->generateBackground($data['event_type'], $data['input'], $refPaths);

        $session = $this->session($booking);
        $state   = $session->state ?? [];
        if ($results['ok']) {
            $state['backgroundPath'] = $results['path'];
        }

        $session->update([
            'event_type' => $data['event_type'],
            'input'      => $data['input'],
            'state'      => $state,
        ]);

        return view('design.booking', array_merge($this->baseViewData(), [
            'booking'      => $booking,
            'session'      => $session,
            'results'      => $results,
            'input'        => $data['input'],
            'eventType'    => $data['event_type'],
            'initialState' => $state,
        ]));
    }

    /** Stel een logo vrij voor de boeking-gekoppelde sessie (geen limiet — admin) */
    public function bookingCutoutLogo(Request $request, Booking $booking): JsonResponse
    {
        $data = app(DesignGenerationService::class)->validateLogoRequest($request);
        $stored = $request->file('logo')->store('design-generator/refs', 'local');

        return response()->json(app(DesignGenerationService::class)->cutoutLogo(Storage::disk('local')->path($stored)));
    }

    /** Verstuur het huidige ontwerp naar de klant (mockup + mail, zoals de bestaande handmatige upload) */
    public function bookingSendToCustomer(Booking $booking): RedirectResponse
    {
        $session = $this->session($booking);

        try {
            $binary = app(DesignRenderService::class)->render($session);
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
            $binary = app(DesignRenderService::class)->render($session);
        } catch (\Throwable $e) {
            return back()->with('error', 'Kan nog niet klaarzetten: ' . $e->getMessage());
        }

        $filename = 'production/' . $booking->booking_number . '_' . Str::random(8) . '.png';
        Storage::disk('public')->put($filename, $binary);

        $booking->update([
            'production_file_path' => $filename,
            'production_file_at'   => now(),
            'strip_status'         => 'ready',
        ]);

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

    /** Basisbeveiliging voor geüploade SVG's: verwijder scripts en on*-event-handlers vóórdat we ze opslaan/inline tonen */
    private function sanitizeSvg(string $svg): string
    {
        $svg = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $svg);
        $svg = preg_replace('/\son\w+\s*=\s*"[^"]*"/i', '', $svg);
        $svg = preg_replace("/\son\\w+\\s*=\\s*'[^']*'/i", '', $svg);

        return $svg;
    }

    private function baseViewData(): array
    {
        $promptsByType = [];
        foreach (DesignPromptSetting::EVENT_TYPES as $type => $typeLabel) {
            $promptsByType[$type] = DesignPromptSetting::currentPrompt('background', $type);
        }

        $masks = DesignMask::orderBy('label')->get()->map(fn (DesignMask $m) => [
            'id'           => $m->id,
            'label'        => $m->label,
            'url'          => $m->url,
            'thumbnailUrl' => $m->thumbnail_url,
            'svgContent'   => $m->svg_path ? Storage::disk('public')->get($m->svg_path) : null,
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
        ];
    }
}
