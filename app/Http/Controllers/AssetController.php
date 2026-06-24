<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssetController extends Controller
{
    public function index(): View
    {
        $assets = Asset::orderBy('category')->orderBy('name')->paginate(50);

        return view('assets.index', compact('assets'));
    }

    public function create(): View
    {
        return view('assets.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'category'    => ['required', 'in:photobooth,background,prop_box,extra'],
            'description' => ['nullable', 'string'],
            'price'       => ['required', 'numeric', 'min:0'],
            'stock'        => ['required', 'integer', 'min:0'],
            'ignore_stock' => ['nullable', 'boolean'],
            'is_active'    => ['nullable', 'boolean'],
        ]);

        $data['is_active']    = $request->boolean('is_active', true);
        $data['ignore_stock'] = $request->boolean('ignore_stock', false);

        Asset::create($data);

        return redirect()->route('assets.index')->with('success', 'Product aangemaakt.');
    }

    public function show(Asset $asset): RedirectResponse
    {
        return redirect()->route('assets.edit', $asset);
    }

    public function edit(Asset $asset): View
    {
        return view('assets.edit', compact('asset'));
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'category'    => ['required', 'in:photobooth,background,prop_box,extra'],
            'description' => ['nullable', 'string'],
            'price'       => ['required', 'numeric', 'min:0'],
            'stock'        => ['required', 'integer', 'min:0'],
            'ignore_stock' => ['nullable', 'boolean'],
            'is_active'    => ['nullable', 'boolean'],
        ]);

        $data['is_active']    = $request->boolean('is_active', false);
        $data['ignore_stock'] = $request->boolean('ignore_stock', false);

        $asset->update($data);

        return redirect()->route('assets.index')->with('success', 'Product bijgewerkt.');
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        abort_if($asset->account_id !== auth()->user()->account_id, 403);
        $asset->delete();

        return redirect()->route('assets.index')->with('success', 'Product verwijderd.');
    }
}
