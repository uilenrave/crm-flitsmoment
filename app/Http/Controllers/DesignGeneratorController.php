<?php

namespace App\Http\Controllers;

use App\Models\DesignPromptSetting;
use App\Services\ImageGeneration\ImageGenerationManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DesignGeneratorController extends Controller
{
    /** Toon het ontwerp-generator formulier (vooralsnog alleen: achtergrond) */
    public function index(): View
    {
        return view('design.index', $this->baseViewData() + [
            'results' => null,
            'input'   => '',
        ]);
    }

    /** Genereer een achtergrondafbeelding via Gemini */
    public function generate(Request $request): View
    {
        $data = $request->validate([
            'input'        => ['required', 'string', 'max:2000'],
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

        $template = DesignPromptSetting::currentPrompt('background');
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

        return view('design.index', $this->baseViewData() + [
            'results' => $results,
            'input'   => $data['input'],
        ]);
    }

    /** Sla de (vaste, herbruikbare) prompt-instelling voor een onderdeel op */
    public function updatePrompt(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key'    => ['required', 'string', 'in:background'],
            'prompt' => ['required', 'string', 'max:4000'],
        ]);

        DesignPromptSetting::updateOrCreate(
            ['key' => $data['key']],
            ['label' => DesignPromptSetting::label($data['key']), 'prompt' => $data['prompt']]
        );

        return response()->json(['ok' => true]);
    }

    private function baseViewData(): array
    {
        return [
            'promptKey'      => 'background',
            'promptLabel'    => DesignPromptSetting::label('background'),
            'promptTemplate' => DesignPromptSetting::currentPrompt('background'),
            'promptDefault'  => DesignPromptSetting::DEFAULTS['background']['prompt'],
        ];
    }
}
