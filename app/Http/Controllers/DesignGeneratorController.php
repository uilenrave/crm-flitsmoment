<?php

namespace App\Http\Controllers;

use App\Services\ImageGeneration\ImageGenerationManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DesignGeneratorController extends Controller
{
    /** Toon het ontwerp-generator formulier */
    public function index(): View
    {
        return view('design.index', ['results' => null, 'prompt' => '']);
    }

    /** Genereer een fotostrip-ontwerp via AI */
    public function generate(Request $request): View
    {
        $data = $request->validate([
            'prompt'       => ['required', 'string', 'max:4000'],
            'provider'     => ['required', 'in:gemini,openai,both'],
            'references'   => ['nullable', 'array', 'max:6'],
            'references.*' => ['image', 'max:2048'], // 2 MB serverlimiet
            'mask'         => ['nullable', 'image', 'max:2048'],
        ], [], [
            'references.*' => 'referentieafbeelding',
        ]);

        // Referentieafbeeldingen (flyer/huisstijl/logo) tijdelijk opslaan → absolute paden
        $refPaths = [];
        foreach ($request->file('references', []) as $file) {
            $stored = $file->store('design-generator/refs', 'local');
            $refPaths[] = Storage::disk('local')->path($stored);
        }

        $maskPath = null;
        if ($request->hasFile('mask')) {
            $stored   = $request->file('mask')->store('design-generator/refs', 'local');
            $maskPath = Storage::disk('local')->path($stored);
        }

        $providers = $data['provider'] === 'both' ? ['gemini', 'openai'] : [$data['provider']];
        $manager   = app(ImageGenerationManager::class);

        $results = [];
        foreach ($providers as $prov) {
            $started = microtime(true);
            try {
                $image    = $manager->driver($prov)->generate($data['prompt'], $refPaths, $maskPath);
                $filename = 'design-generator/out/' . Str::random(24) . '.' . $image->extension();
                Storage::disk('public')->put($filename, $image->binary);

                $results[] = [
                    'provider' => $prov,
                    'ok'       => true,
                    'url'      => Storage::disk('public')->url($filename),
                    'seconds'  => round(microtime(true) - $started, 1),
                ];
            } catch (\Throwable $e) {
                Log::error("Ontwerp-generatie ({$prov}) mislukt: " . $e->getMessage());
                $results[] = [
                    'provider' => $prov,
                    'ok'       => false,
                    'error'    => $e->getMessage(),
                ];
            }
        }

        return view('design.index', [
            'results' => $results,
            'prompt'  => $data['prompt'],
        ]);
    }
}
