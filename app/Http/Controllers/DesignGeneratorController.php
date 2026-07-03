<?php

namespace App\Http\Controllers;

use App\Models\DesignPromptSetting;
use App\Services\ImageGeneration\ImageGenerationManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DesignGeneratorController extends Controller
{
    /** Toon het ontwerp-generator formulier (vooralsnog alleen: achtergrond) */
    public function index(): View
    {
        return view('design.index', array_merge($this->baseViewData(), [
            'results' => null,
            'input'   => '',
        ]));
    }

    /** Genereer een achtergrondafbeelding via Gemini */
    public function generate(Request $request): View
    {
        $data = $request->validate([
            'input'        => ['required', 'string', 'max:2000'],
            'event_type'   => ['required', Rule::in(array_keys(DesignPromptSetting::EVENT_TYPES))],
            'references'   => ['nullable', 'array', 'max:6'],
            'references.*' => ['image', 'max:8192'], // 8 MB — alleen voor referentieafbeeldingen hier
        ], [], [
            'references.*' => 'referentieafbeelding',
        ]);

        $refPaths = [];
        foreach ($request->file('references', []) as $file) {
            $stored = $file->store('design-generator/refs', 'local');
            $refPaths[] = Storage::disk('local')->path($stored);
        }

        $template = DesignPromptSetting::currentPrompt('background', $data['event_type']);
        $prompt = str_contains($template, '{beschrijving}')
            ? str_replace('{beschrijving}', $data['input'], $template)
            : $template . "\n\n" . $data['input'];

        $started = microtime(true);
        try {
            $manager = app(ImageGenerationManager::class);
            $image   = $manager->driver('gemini')->generate($prompt, $refPaths, null);

            $filename = 'design-generator/out/' . Str::random(24) . '.' . $image->extension();
            Storage::disk('public')->put($filename, $image->binary);

            $results = [
                'ok'      => true,
                'url'     => Storage::disk('public')->url($filename),
                'seconds' => round(microtime(true) - $started, 1),
            ];
        } catch (\Throwable $e) {
            Log::error('Achtergrond-generatie mislukt: ' . $e->getMessage());
            $results = [
                'ok'    => false,
                'error' => $e->getMessage(),
            ];
        }

        return view('design.index', array_merge($this->baseViewData(), [
            'results'   => $results,
            'input'     => $data['input'],
            'eventType' => $data['event_type'],
        ]));
    }

    /** Stel een geüpload logo vrij als transparante PNG (via GPT/OpenAI — Gemini kan geen echte transparantie) */
    public function cutoutLogo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'logo' => ['required', 'image', 'max:8192'],
        ], [], ['logo' => 'logo-afbeelding']);

        $stored = $request->file('logo')->store('design-generator/refs', 'local');
        $path   = Storage::disk('local')->path($stored);

        $prompt = 'Cut out only the logo from this image. Remove the background completely so it becomes '
            . 'fully transparent (alpha channel = 0), keep the logo itself unchanged — do not redraw, '
            . 'recolor, restyle, or add effects. No shadow, no border, no added background color. '
            . 'The output must have a genuinely transparent background.';

        try {
            $manager = app(ImageGenerationManager::class);
            $image   = $manager->driver('openai')->generate($prompt, [$path], null);

            $filename = 'design-generator/logos/' . Str::random(24) . '.' . $image->extension();
            Storage::disk('public')->put($filename, $image->binary);

            return response()->json([
                'ok'  => true,
                'url' => Storage::disk('public')->url($filename),
            ]);
        } catch (\Throwable $e) {
            Log::error('Logo vrijstellen mislukt: ' . $e->getMessage());

            return response()->json([
                'ok'    => false,
                'error' => $e->getMessage(),
            ]);
        }
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

    private function baseViewData(): array
    {
        $promptsByType = [];
        foreach (DesignPromptSetting::EVENT_TYPES as $type => $typeLabel) {
            $promptsByType[$type] = DesignPromptSetting::currentPrompt('background', $type);
        }

        return [
            'promptKey'      => 'background',
            'promptLabel'    => DesignPromptSetting::label('background'),
            'promptDefault'  => DesignPromptSetting::DEFAULTS['background']['prompt'],
            'promptsByType'  => $promptsByType,
            'eventTypes'     => DesignPromptSetting::EVENT_TYPES,
            'eventType'      => array_key_first(DesignPromptSetting::EVENT_TYPES),
            'logoEventTypes' => DesignPromptSetting::LOGO_EVENT_TYPES,
        ];
    }
}
