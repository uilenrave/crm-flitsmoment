<?php

namespace App\Http\Controllers;

use App\Models\CanvaTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CanvaTemplateController extends Controller
{
    public function index(): View
    {
        $templates = CanvaTemplate::orderBy('is_active', 'desc')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('canva-templates.index', compact('templates'));
    }

    public function create(): View
    {
        $template = new CanvaTemplate(['is_active' => true, 'sort_order' => 0, 'photo_count' => 1]);
        return view('canva-templates.form', ['template' => $template]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request, null);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['image_path'] = $request->file('image')->store('canva-templates', 'public');

        CanvaTemplate::create($data);

        return redirect()->route('canva-templates.index')->with('success', '✅ Canva-template toegevoegd.');
    }

    public function edit(CanvaTemplate $canvaTemplate): View
    {
        return view('canva-templates.form', ['template' => $canvaTemplate]);
    }

    public function update(Request $request, CanvaTemplate $canvaTemplate): RedirectResponse
    {
        $data = $this->validatePayload($request, $canvaTemplate);
        $data['is_active'] = $request->boolean('is_active', true);

        if ($request->hasFile('image')) {
            if ($canvaTemplate->image_path) {
                Storage::disk('public')->delete($canvaTemplate->image_path);
            }
            $data['image_path'] = $request->file('image')->store('canva-templates', 'public');
        }

        $canvaTemplate->update($data);

        return redirect()->route('canva-templates.index')->with('success', '✅ Canva-template bijgewerkt.');
    }

    public function destroy(CanvaTemplate $canvaTemplate): RedirectResponse
    {
        if ($canvaTemplate->image_path) {
            Storage::disk('public')->delete($canvaTemplate->image_path);
        }
        $canvaTemplate->delete();

        return redirect()->route('canva-templates.index')->with('success', '🗑 Canva-template verwijderd.');
    }

    private function validatePayload(Request $request, ?CanvaTemplate $existing): array
    {
        return $request->validate([
            'title'       => ['required', 'string', 'max:200'],
            'canva_url'   => ['required', 'url', 'max:500'],
            'format'      => ['required', 'in:strips_5x15,photo_10x15'],
            'photo_count' => ['required', 'integer', 'min:1', 'max:5'],
            'image'       => [$existing ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'is_active'   => ['boolean'],
            'sort_order'  => ['nullable', 'integer'],
        ]);
    }
}
