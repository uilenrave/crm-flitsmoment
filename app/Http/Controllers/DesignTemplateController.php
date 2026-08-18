<?php

namespace App\Http\Controllers;

use App\Models\DesignElement;
use App\Models\DesignElementCategory;
use App\Models\DesignTemplate;
use App\Models\DesignTemplateCategory;
use App\Services\DesignGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Beheer van de gedeelde fotostrip-templatebibliotheek: een goedkeuringswachtrij met alle
 * gegenereerde achtergronden, en de goedgekeurde bibliotheek per categorie.
 */
class DesignTemplateController extends Controller
{
    public function index(): View
    {
        $pending = DesignTemplate::with('sourceBooking:id,booking_number')
            ->where('status', 'pending')
            ->latest('id')
            ->get();

        $categories = DesignTemplateCategory::withCount(['templates as approved_count' => function ($q) {
            $q->where('status', 'approved');
        }])->orderBy('sort_order')->orderBy('label')->get();

        $approved = DesignTemplate::where('status', 'approved')
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->groupBy('category_id');

        // Elementenbibliotheek (parallel systeem) — als tweede tab op dezelfde pagina.
        $pendingElements = DesignElement::with('sourceBooking:id,booking_number')
            ->where('status', 'pending')
            ->latest('id')
            ->get();

        $elementCategories = DesignElementCategory::withCount(['elements as approved_count' => function ($q) {
            $q->where('status', 'approved');
        }])->orderBy('sort_order')->orderBy('label')->get();

        $approvedElements = DesignElement::where('status', 'approved')
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->groupBy('category_id');

        return view('design-templates.index', compact(
            'pending', 'categories', 'approved',
            'pendingElements', 'elementCategories', 'approvedElements',
        ));
    }

    /** Keur een pending template goed en plaats 'm in een categorie (bestaand of nieuw). */
    public function approve(Request $request, DesignTemplate $designTemplate): RedirectResponse
    {
        $request->validate([
            'category_id'  => ['nullable', 'exists:design_template_categories,id'],
            'new_category' => ['nullable', 'string', 'max:120'],
            'label'        => ['nullable', 'string', 'max:160'],
        ]);

        $categoryId = $this->resolveCategoryId($request);

        $designTemplate->update([
            'status'      => 'approved',
            'category_id' => $categoryId,
            'label'       => $request->input('label') ?: $designTemplate->label,
            'approved_at' => now(),
        ]);

        return back()->with('success', '✅ Template goedgekeurd.');
    }

    /** Wijs een pending template af — verwijder rij + bestand. */
    public function reject(DesignTemplate $designTemplate): RedirectResponse
    {
        Storage::disk('public')->delete($designTemplate->image_path);
        $designTemplate->delete();

        return back()->with('success', '🗑 Template afgewezen.');
    }

    /** Verplaats een goedgekeurde template naar een andere categorie. */
    public function recategorize(Request $request, DesignTemplate $designTemplate): RedirectResponse
    {
        $request->validate([
            'category_id'  => ['nullable', 'exists:design_template_categories,id'],
            'new_category' => ['nullable', 'string', 'max:120'],
        ]);

        $designTemplate->update(['category_id' => $this->resolveCategoryId($request)]);

        return back()->with('success', '✅ Categorie bijgewerkt.');
    }

    /** Verwijder een goedgekeurde template uit de bibliotheek. */
    public function destroy(DesignTemplate $designTemplate): RedirectResponse
    {
        Storage::disk('public')->delete($designTemplate->image_path);
        $designTemplate->delete();

        return back()->with('success', '🗑 Template verwijderd.');
    }

    /** Admin uploadt zelf een template (direct goedgekeurd). */
    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'image'        => ['required', 'image', 'max:15360'],
            'category_id'  => ['nullable', 'exists:design_template_categories,id'],
            'new_category' => ['nullable', 'string', 'max:120'],
            'label'        => ['nullable', 'string', 'max:160'],
        ]);

        $path = app(DesignGenerationService::class)->storeTemplateUpload($request->file('image'));

        DesignTemplate::create([
            'category_id'       => $this->resolveCategoryId($request),
            'status'            => 'approved',
            'image_path'        => $path,
            'label'             => $request->input('label'),
            'source'            => 'admin_upload',
            'source_account_id' => auth()->user()?->account_id,
            'approved_at'       => now(),
        ]);

        return back()->with('success', '✅ Eigen template toegevoegd.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate(['label' => ['required', 'string', 'max:120']]);

        DesignTemplateCategory::create([
            'slug'       => DesignTemplateCategory::makeSlug($data['label']),
            'label'      => $data['label'],
            'sort_order' => (DesignTemplateCategory::max('sort_order') ?? 0) + 1,
        ]);

        return back()->with('success', '✅ Categorie toegevoegd.');
    }

    public function updateCategory(Request $request, DesignTemplateCategory $category): RedirectResponse
    {
        $data = $request->validate(['label' => ['required', 'string', 'max:120']]);
        $category->update(['label' => $data['label']]);

        return back()->with('success', '✅ Categorie hernoemd.');
    }

    /** Verwijder een categorie — de templates erin worden 'zonder categorie' (nullOnDelete). */
    public function destroyCategory(DesignTemplateCategory $category): RedirectResponse
    {
        $category->delete();

        return back()->with('success', '🗑 Categorie verwijderd. Templates staan nu zonder categorie.');
    }

    /** Bepaal de categorie: een nieuw ingevoerde naam wint van de dropdown. */
    private function resolveCategoryId(Request $request): ?int
    {
        $newCategory = trim((string) $request->input('new_category'));
        if ($newCategory !== '') {
            return DesignTemplateCategory::create([
                'slug'       => DesignTemplateCategory::makeSlug($newCategory),
                'label'      => $newCategory,
                'sort_order' => (DesignTemplateCategory::max('sort_order') ?? 0) + 1,
            ])->id;
        }

        return $request->input('category_id') ? (int) $request->input('category_id') : null;
    }
}
