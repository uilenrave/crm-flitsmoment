<?php

namespace App\Services;

use App\Models\AiUsageEvent;
use App\Models\DesignElement;
use App\Models\DesignElementCategory;
use App\Models\DesignPromptSetting;
use App\Models\DesignTemplate;
use App\Models\DesignTemplateCategory;
use App\Services\ImageGeneration\ImageGenerationManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Imagick;

/**
 * Kernlogica voor het genereren van achtergronden en het vrijstellen van logo's —
 * gedeeld tussen de admin-generator (DesignGeneratorController) en de klant-wizard
 * in het portaal (PortalController), zodat beide dezelfde AI-aanroepen/opslag/validatie gebruiken.
 */
class DesignGenerationService
{
    private const CANVAS_WIDTH = 600;
    private const CANVAS_HEIGHT = 1800;

    public function validateGenerateRequest(Request $request): array
    {
        $method = $request->input('background_method', 'ai');

        return $request->validate([
            'background_method' => ['nullable', Rule::in(['ai', 'upload', 'color', 'template'])],
            'event_type'        => ['required', Rule::in(array_keys(DesignPromptSetting::EVENT_TYPES))],
            'input'             => [Rule::requiredIf($method === 'ai'), 'nullable', 'string', 'max:2000'],
            'references'        => ['nullable', 'array', 'max:6'],
            'references.*'      => ['image', 'max:8192'], // 8 MB
            'background_upload' => [Rule::requiredIf($method === 'upload'), 'nullable', 'image', 'max:15360'], // 15 MB
            'background_color'  => [Rule::requiredIf($method === 'color'), 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'template_id'       => [Rule::requiredIf($method === 'template'), 'nullable', 'integer', Rule::exists('design_templates', 'id')->where('status', 'approved')],
        ], [], [
            'references.*'      => 'referentieafbeelding',
            'background_upload' => 'afbeelding',
            'background_color'  => 'kleur',
            'template_id'       => 'template',
        ]);
    }

    public function validateLogoRequest(Request $request): array
    {
        return $request->validate([
            'logo' => ['required', 'image', 'max:8192'],
        ], [], ['logo' => 'logo-afbeelding']);
    }

    public function storeReferenceFiles(Request $request): array
    {
        $refPaths = [];
        foreach ($request->file('references', []) as $file) {
            $stored = $file->store('design-generator/refs', 'local');
            $refPaths[] = Storage::disk('local')->path($stored);
        }

        return $refPaths;
    }

    /**
     * Genereer + cover-crop een achtergrondafbeelding via Gemini. Retourneert het standaard results-array.
     * $accountId: verplicht vanuit een niet-auth-context (portaal/token) — zie DesignPromptSetting::currentPrompt().
     */
    public function generateBackground(
        string $eventType,
        string $input,
        array $refPaths,
        ?int $accountId = null,
        ?int $sourceBookingId = null,
        string $source = 'admin_generator'
    ): array {
        $template = DesignPromptSetting::currentPrompt('background', $eventType, $accountId);
        $prompt = str_contains($template, '{beschrijving}')
            ? str_replace('{beschrijving}', $input, $template)
            : $template . "\n\n" . $input;

        $started = microtime(true);
        try {
            $manager = app(ImageGenerationManager::class);
            $image   = $manager->driver('gemini')->generate($prompt, $refPaths, null);
            $binary  = $this->coverCropToCanvas($image->binary, self::CANVAS_WIDTH, self::CANVAS_HEIGHT);

            $filename = 'design-generator/out/' . Str::random(24) . '.jpg';
            Storage::disk('public')->put($filename, $binary);

            // Elke geslaagde AI-generatie belandt als 'pending' in de templatebibliotheek-wachtrij,
            // zodat de admin 'm later kan goedkeuren i.p.v. dat credits eenmalig verloren gaan.
            $this->captureCandidateTemplate($binary, $source, $accountId, $sourceBookingId);

            // Verbruik vastleggen per account (Gemini-achtergrond) voor het instellingen-overzicht.
            AiUsageEvent::record($accountId ?? auth()->user()?->account_id, AiUsageEvent::PROVIDER_GEMINI, 'background');

            return [
                'ok'      => true,
                'url'     => Storage::disk('public')->url($filename),
                'path'    => $filename,
                'seconds' => round(microtime(true) - $started, 1),
            ];
        } catch (\Throwable $e) {
            Log::error('Achtergrond-generatie mislukt: ' . $e->getMessage());

            return [
                'ok'    => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Schrijf een eigen kopie van een gegenereerde achtergrond naar design-templates/ en maak een
     * pending DesignTemplate aan. Eigen kopie (los van de sessie-werkkopie in out/) zodat de kandidaat
     * niet verdwijnt als de sessie wordt opgeschoond. Faalt stil: een capture-fout mag nooit de
     * generatie zelf breken.
     */
    private function captureCandidateTemplate(string $binary, string $source, ?int $accountId, ?int $sourceBookingId): void
    {
        try {
            $path = 'design-templates/' . Str::random(24) . '.jpg';
            Storage::disk('public')->put($path, $binary);

            DesignTemplate::create([
                'category_id'       => null,
                'status'            => 'pending',
                'image_path'        => $path,
                'source'            => $source,
                'source_account_id' => $accountId ?? auth()->user()?->account_id,
                'source_booking_id' => $sourceBookingId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Kandidaat-template vastleggen mislukt: ' . $e->getMessage());
        }
    }

    /**
     * Sla een door de admin geüploade template op: cover-crop naar het canvasformaat (zodat hij
     * dezelfde 2:6-verhouding heeft als gegenereerde templates) en schrijf naar design-templates/.
     * Retourneert het opgeslagen pad.
     */
    public function storeTemplateUpload(\Illuminate\Http\UploadedFile $file): string
    {
        $binary = $this->coverCropToCanvas(file_get_contents($file->getRealPath()), self::CANVAS_WIDTH, self::CANVAS_HEIGHT);
        $path = 'design-templates/' . Str::random(24) . '.jpg';
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    /** Gebruik een geüploade afbeelding rechtstreeks als achtergrond (cover-crop naar canvasformaat). */
    public function uploadBackground(\Illuminate\Http\UploadedFile $file): array
    {
        $started = microtime(true);
        try {
            $binary = $this->coverCropToCanvas(file_get_contents($file->getRealPath()), self::CANVAS_WIDTH, self::CANVAS_HEIGHT);

            $filename = 'design-generator/out/' . Str::random(24) . '.jpg';
            Storage::disk('public')->put($filename, $binary);

            return [
                'ok'      => true,
                'url'     => Storage::disk('public')->url($filename),
                'path'    => $filename,
                'seconds' => round(microtime(true) - $started, 1),
            ];
        } catch (\Throwable $e) {
            Log::error('Achtergrond-upload mislukt: ' . $e->getMessage());

            return [
                'ok'    => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Categorieën + goedgekeurde templates uit de database (gedeelde bibliotheek). Alleen categorieën
     * met minstens één goedgekeurde template worden getoond. Returnvorm: [{slug,label,items:[{id,url,label}]}].
     */
    public function templateCategories(): array
    {
        $cats = DesignTemplateCategory::with(['templates' => function ($q) {
            $q->where('status', 'approved')->orderBy('sort_order')->orderBy('id');
        }])->orderBy('sort_order')->orderBy('label')->get();

        $categories = [];
        foreach ($cats as $cat) {
            if ($cat->templates->isEmpty()) {
                continue;
            }

            $categories[] = [
                'slug'  => $cat->slug,
                'label' => $cat->label,
                'items' => $cat->templates->map(fn (DesignTemplate $t) => [
                    'id'    => $t->id,
                    'url'   => $t->url,
                    'label' => $t->label ?? '',
                ])->values()->all(),
            ];
        }

        return $categories;
    }

    /** Gebruik een goedgekeurde template als achtergrond (cover-crop is al gedaan toen de template werd gemaakt). */
    public function templateBackground(int $templateId): array
    {
        $template = DesignTemplate::where('id', $templateId)->where('status', 'approved')->first();

        if (! $template || ! Storage::disk('public')->exists($template->image_path)) {
            return ['ok' => false, 'error' => 'Template niet gevonden.'];
        }

        try {
            $binary = Storage::disk('public')->get($template->image_path);

            $filename = 'design-generator/out/' . Str::random(24) . '.jpg';
            Storage::disk('public')->put($filename, $binary);

            $template->increment('usage_count');

            return [
                'ok'      => true,
                'url'     => Storage::disk('public')->url($filename),
                'path'    => $filename,
                'seconds' => 0,
            ];
        } catch (\Throwable $e) {
            Log::error('Template-achtergrond mislukt: ' . $e->getMessage());

            return [
                'ok'    => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /** Genereer een effen kleurvlak op canvasformaat als achtergrond. */
    public function colorBackground(string $hexColor): array
    {
        try {
            $image = new Imagick();
            $image->newImage(self::CANVAS_WIDTH, self::CANVAS_HEIGHT, new \ImagickPixel($hexColor));
            $image->setImageFormat('jpg');

            $filename = 'design-generator/out/' . Str::random(24) . '.jpg';
            Storage::disk('public')->put($filename, $image->getImageBlob());

            return [
                'ok'      => true,
                'url'     => Storage::disk('public')->url($filename),
                'path'    => $filename,
                'seconds' => 0,
            ];
        } catch (\Throwable $e) {
            Log::error('Kleur-achtergrond mislukt: ' . $e->getMessage());

            return [
                'ok'    => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Elementenbibliotheek (vrijgestelde elementen) — parallel aan de templatebibliotheek
    // ──────────────────────────────────────────────────────────────

    /** Leg een vrijgesteld element vast als 'pending' kandidaat (eigen kopie, transparantie behouden). */
    private function captureCandidateElement(string $binary, string $extension, string $source, ?int $accountId, ?int $sourceBookingId): void
    {
        try {
            $path = 'design-elements/' . Str::random(24) . '.' . ($extension ?: 'png');
            Storage::disk('public')->put($path, $binary);

            DesignElement::create([
                'category_id'       => null,
                'status'            => 'pending',
                'image_path'        => $path,
                'source'            => $source,
                'source_account_id' => $accountId ?? auth()->user()?->account_id,
                'source_booking_id' => $sourceBookingId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Kandidaat-element vastleggen mislukt: ' . $e->getMessage());
        }
    }

    /** Sla een door de admin geüpload element op zoals het is (transparantie behouden, geen crop). */
    public function storeElementUpload(\Illuminate\Http\UploadedFile $file): string
    {
        $ext  = strtolower($file->getClientOriginalExtension()) ?: 'png';
        $path = 'design-elements/' . Str::random(24) . '.' . $ext;
        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        return $path;
    }

    /**
     * Categorieën + goedgekeurde elementen uit de database (gedeelde bibliotheek). Alleen categorieën
     * met minstens één goedgekeurd element. Returnvorm: [{slug,label,items:[{id,url,label}]}].
     */
    public function elementCategories(): array
    {
        $cats = DesignElementCategory::with(['elements' => function ($q) {
            $q->where('status', 'approved')->orderBy('sort_order')->orderBy('id');
        }])->orderBy('sort_order')->orderBy('label')->get();

        $categories = [];
        foreach ($cats as $cat) {
            if ($cat->elements->isEmpty()) {
                continue;
            }

            $categories[] = [
                'slug'  => $cat->slug,
                'label' => $cat->label,
                'items' => $cat->elements->map(fn (DesignElement $e) => [
                    'id'    => $e->id,
                    'url'   => $e->url,
                    'label' => $e->label ?? '',
                ])->values()->all(),
            ];
        }

        return $categories;
    }

    /**
     * Gebruik een goedgekeurd element uit de bibliotheek: kopieer naar een eigen werkbestand (zodat het
     * ontwerp niet breekt als het bibliotheek-element later wordt verwijderd) en tel het gebruik.
     */
    public function elementFromLibrary(int $elementId): array
    {
        $element = DesignElement::where('id', $elementId)->where('status', 'approved')->first();

        if (! $element || ! Storage::disk('public')->exists($element->image_path)) {
            return ['ok' => false, 'error' => 'Element niet gevonden.'];
        }

        try {
            $ext    = pathinfo($element->image_path, PATHINFO_EXTENSION) ?: 'png';
            $binary = Storage::disk('public')->get($element->image_path);

            $filename = 'design-generator/logos/' . Str::random(24) . '.' . $ext;
            Storage::disk('public')->put($filename, $binary);

            $element->increment('usage_count');

            return [
                'ok'   => true,
                'url'  => Storage::disk('public')->url($filename),
                'path' => $filename,
            ];
        } catch (\Throwable $e) {
            Log::error('Bibliotheek-element gebruiken mislukt: ' . $e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Gebruik een geüpload logo direct, zonder AI-vrijstelling (bijv. als het al een transparante achtergrond heeft of de eigen achtergrond mag behouden). */
    public function uploadLogoDirect(\Illuminate\Http\UploadedFile $file): array
    {
        try {
            $filename = 'design-generator/logos/' . Str::random(24) . '.' . $file->getClientOriginalExtension();
            Storage::disk('public')->put($filename, file_get_contents($file->getRealPath()));

            return [
                'ok'   => true,
                'url'  => Storage::disk('public')->url($filename),
                'path' => $filename,
            ];
        } catch (\Throwable $e) {
            Log::error('Logo direct toevoegen mislukt: ' . $e->getMessage());

            return [
                'ok'    => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Stel een element (logo, illustratie, …) vrij als transparante PNG (via GPT/OpenAI — Gemini kan
     * geen echte transparantie). $description: optionele omschrijving van WÁT vrijgesteld moet worden,
     * zodat de AI het hele bedoelde element pakt i.p.v. iets binnenin te selecteren.
     * $accountId: het account dat de vrijstelling veroorzaakt, voor het verbruiksoverzicht.
     */
    public function cutoutLogo(
        string $localPath,
        ?int $accountId = null,
        ?string $description = null,
        string $source = 'admin_generator',
        ?int $sourceBookingId = null
    ): array {
        $subject = trim((string) $description);
        $subject = $subject !== '' ? mb_substr($subject, 0, 200) : 'the main subject';

        $prompt = "Cut out the {$subject} from this image as ONE single, whole element. "
            . 'Remove the entire background completely so it becomes fully transparent (alpha channel = 0). '
            . "Keep the {$subject} exactly as-is and complete — do not redraw, recolor, restyle, crop, or add effects, "
            . 'and do not select only a portion of it. No shadow, no border, no added background color. '
            . 'The output must have a genuinely transparent background.';

        try {
            $manager = app(ImageGenerationManager::class);
            $image   = $manager->driver('openai')->generate($prompt, [$localPath], null);

            $filename = 'design-generator/logos/' . Str::random(24) . '.' . $image->extension();
            Storage::disk('public')->put($filename, $image->binary);

            // Verbruik vastleggen per account (GPT/OpenAI logo-vrijstelling) voor het instellingen-overzicht.
            AiUsageEvent::record($accountId ?? auth()->user()?->account_id, AiUsageEvent::PROVIDER_OPENAI, 'logo_cutout');

            // Elk vrijgesteld element belandt als 'pending' in de elementenbibliotheek-wachtrij,
            // zodat het na goedkeuring herbruikt kan worden i.p.v. dat de credit eenmalig verloren gaat.
            $this->captureCandidateElement($image->binary, $image->extension(), $source, $accountId, $sourceBookingId);

            return [
                'ok'   => true,
                'url'  => Storage::disk('public')->url($filename),
                'path' => $filename,
            ];
        } catch (\Throwable $e) {
            Log::error('Logo vrijstellen mislukt: ' . $e->getMessage());

            return [
                'ok'    => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Forceer de gegenereerde achtergrond op het vaste canvas (zoals CSS background-size: cover):
     * schalen tot beide dimensies gedekt zijn, daarna vanuit het midden bijsnijden op maat.
     * Gemini's aspect ratio klopt niet altijd exact — dit garandeert altijd 600×1800px.
     */
    private function coverCropToCanvas(string $binary, int $targetWidth, int $targetHeight): string
    {
        $image = new Imagick();
        $image->readImageBlob($binary);
        $image = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);

        $srcWidth  = $image->getImageWidth();
        $srcHeight = $image->getImageHeight();
        $scale     = max($targetWidth / $srcWidth, $targetHeight / $srcHeight);

        $image->resizeImage(
            (int) ceil($srcWidth * $scale),
            (int) ceil($srcHeight * $scale),
            Imagick::FILTER_LANCZOS,
            1
        );

        $cropX = (int) round(($image->getImageWidth() - $targetWidth) / 2);
        $cropY = (int) round(($image->getImageHeight() - $targetHeight) / 2);
        $image->cropImage($targetWidth, $targetHeight, max(0, $cropX), max(0, $cropY));
        $image->setImagePage(0, 0, 0, 0);

        $image->setImageFormat('jpg');
        $image->setImageCompressionQuality(92);

        return $image->getImageBlob();
    }
}
