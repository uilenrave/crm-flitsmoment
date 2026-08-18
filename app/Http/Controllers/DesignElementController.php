<?php

namespace App\Http\Controllers;

use App\Models\DesignElement;
use App\Models\DesignElementCategory;
use App\Services\DesignGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Beheer van de gedeelde elementenbibliotheek: goedkeuringswachtrij met vrijgestelde elementen,
 * en de goedgekeurde bibliotheek per categorie. Parallel aan DesignTemplateController; het overzicht
 * zelf wordt getoond als tab op de templatebibliotheek-pagina (DesignTemplateController::index).
 */
class DesignElementController extends Controller
{
    /** Keur een pending element goed en plaats 'm in een categorie (bestaand of nieuw). */
    public function approve(Request $request, DesignElement $designElement): RedirectResponse
    {
        $request->validate([
            'category_id'  => ['nullable', 'exists:design_element_categories,id'],
            'new_category' => ['nullable', 'string', 'max:120'],
            'label'        => ['nullable', 'string', 'max:160'],
        ]);

        $designElement->update([
            'status'      => 'approved',
            'category_id' => $this->resolveCategoryId($request),
            'label'       => $request->input('label') ?: $designElement->label,
            'approved_at' => now(),
        ]);

        return back()->with('success', '✅ Element goedgekeurd.');
    }

    /** Wijs een pending element af — verwijder rij + bestand. */
    public function reject(DesignElement $designElement): RedirectResponse
    {
        Storage::disk('public')->delete($designElement->image_path);
        $designElement->delete();

        return back()->with('success', '🗑 Element afgewezen.');
    }

    /** Verplaats een goedgekeurd element naar een andere categorie. */
    public function recategorize(Request $request, DesignElement $designElement): RedirectResponse
    {
        $request->validate([
            'category_id'  => ['nullable', 'exists:design_element_categories,id'],
            'new_category' => ['nullable', 'string', 'max:120'],
        ]);

        $designElement->update(['category_id' => $this->resolveCategoryId($request)]);

        return back()->with('success', '✅ Categorie bijgewerkt.');
    }

    /** Verwijder een goedgekeurd element uit de bibliotheek. */
    public function destroy(DesignElement $designElement): RedirectResponse
    {
        Storage::disk('public')->delete($designElement->image_path);
        $designElement->delete();

        return back()->with('success', '🗑 Element verwijderd.');
    }

    /** Admin uploadt zelf een element (direct goedgekeurd). Transparantie blijft behouden. */
    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'image'        => ['required', 'image', 'max:15360'],
            'category_id'  => ['nullable', 'exists:design_element_categories,id'],
            'new_category' => ['nullable', 'string', 'max:120'],
            'label'        => ['nullable', 'string', 'max:160'],
        ]);

        $path = app(DesignGenerationService::class)->storeElementUpload($request->file('image'));

        DesignElement::create([
            'category_id'       => $this->resolveCategoryId($request),
            'status'            => 'approved',
            'image_path'        => $path,
            'label'             => $request->input('label'),
            'source'            => 'admin_upload',
            'source_account_id' => auth()->user()?->account_id,
            'approved_at'       => now(),
        ]);

        return back()->with('success', '✅ Eigen element toegevoegd.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate(['label' => ['required', 'string', 'max:120']]);

        DesignElementCategory::create([
            'slug'       => DesignElementCategory::makeSlug($data['label']),
            'label'      => $data['label'],
            'sort_order' => (DesignElementCategory::max('sort_order') ?? 0) + 1,
        ]);

        return back()->with('success', '✅ Categorie toegevoegd.');
    }

    public function updateCategory(Request $request, DesignElementCategory $category): RedirectResponse
    {
        $data = $request->validate(['label' => ['required', 'string', 'max:120']]);
        $category->update(['label' => $data['label']]);

        return back()->with('success', '✅ Categorie hernoemd.');
    }

    /** Verwijder een categorie — de elementen erin worden 'zonder categorie' (nullOnDelete). */
    public function destroyCategory(DesignElementCategory $category): RedirectResponse
    {
        $category->delete();

        return back()->with('success', '🗑 Categorie verwijderd. Elementen staan nu zonder categorie.');
    }

    /** Bepaal de categorie: een nieuw ingevoerde naam wint van de dropdown. */
    private function resolveCategoryId(Request $request): ?int
    {
        $newCategory = trim((string) $request->input('new_category'));
        if ($newCategory !== '') {
            return DesignElementCategory::create([
                'slug'       => DesignElementCategory::makeSlug($newCategory),
                'label'      => $newCategory,
                'sort_order' => (DesignElementCategory::max('sort_order') ?? 0) + 1,
            ])->id;
        }

        return $request->input('category_id') ? (int) $request->input('category_id') : null;
    }
}
