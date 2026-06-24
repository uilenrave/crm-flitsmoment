<?php

namespace App\Http\Controllers;

use App\Models\StripTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class StripTemplateController extends Controller
{
    public function index(): View
    {
        $templates = StripTemplate::orderBy('is_active', 'desc')
            ->orderBy('sort_order')
            ->orderBy('number')
            ->get();

        return view('strip-templates.index', compact('templates'));
    }

    public function create(): View
    {
        $nextNumber = (int) StripTemplate::max('number') + 1;
        $template = new StripTemplate(['number' => $nextNumber, 'is_active' => true]);

        return view('strip-templates.form', ['template' => $template]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request, null);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['image_path'] = $request->file('image')->store('strip-templates', 'public');

        StripTemplate::create($data);

        return redirect()->route('strip-templates.index')->with('success', '✅ Template toegevoegd.');
    }

    public function edit(StripTemplate $stripTemplate): View
    {
        return view('strip-templates.form', ['template' => $stripTemplate]);
    }

    public function update(Request $request, StripTemplate $stripTemplate): RedirectResponse
    {
        $data = $this->validatePayload($request, $stripTemplate);
        $data['is_active'] = $request->boolean('is_active', true);

        if ($request->hasFile('image')) {
            if ($stripTemplate->image_path) {
                Storage::disk('public')->delete($stripTemplate->image_path);
            }
            $data['image_path'] = $request->file('image')->store('strip-templates', 'public');
        }

        $stripTemplate->update($data);

        return redirect()->route('strip-templates.index')->with('success', '✅ Template bijgewerkt.');
    }

    public function destroy(StripTemplate $stripTemplate): RedirectResponse
    {
        if ($stripTemplate->image_path) {
            Storage::disk('public')->delete($stripTemplate->image_path);
        }
        $stripTemplate->delete();

        return redirect()->route('strip-templates.index')->with('success', '🗑 Template verwijderd.');
    }

    private function validatePayload(Request $request, ?StripTemplate $existing): array
    {
        $uniqueRule = 'unique:strip_templates,number,'
            . ($existing?->id ?? 'NULL')
            . ',id,account_id,'
            . (auth()->user()->account_id);

        return $request->validate([
            'number'     => ['required', 'integer', 'min:1', 'max:9999', $uniqueRule],
            'name'       => ['nullable', 'string', 'max:120'],
            'theme'      => ['required', 'in:bruiloft,bedrijfsfeest,verjaardag,kerst'],
            'format'     => ['required', 'in:strips_5x15,photo_10x15'],
            'image'      => [$existing ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'is_active'  => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
        ], [
            'number.unique' => 'Dit nummer is al in gebruik door een ander template.',
        ]);
    }
}
