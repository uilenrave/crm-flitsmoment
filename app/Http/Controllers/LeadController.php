<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\EventType;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadSource;
use App\Models\LeadStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->get('tab', 'actief'); // 'actief' of 'archief'

        $query = Lead::with(['status', 'assignedTo'])->latest();

        if ($tab === 'archief') {
            $query->whereNotNull('archived_at');
        } else {
            $query->whereNull('archived_at');
        }

        if ($request->filled('status')) {
            $query->where('status_id', $request->status);
        }
        if ($request->filled('zoeken')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->zoeken}%")
                  ->orWhere('email', 'like', "%{$request->zoeken}%")
                  ->orWhere('lead_number', 'like', "%{$request->zoeken}%");
            });
        }

        $leads     = $query->paginate(20)->withQueryString();
        $statussen = LeadStatus::where('is_active', true)->orderBy('sort_order')->get();

        // Tellingen voor tabs
        $aantalActief  = Lead::whereNull('archived_at')->count();
        $aantalArchief = Lead::whereNotNull('archived_at')->count();

        return view('leads.index', compact('leads', 'statussen', 'tab', 'aantalActief', 'aantalArchief'));
    }

    public function create(): View
    {
        $statussen = LeadStatus::where('is_active', true)->orderBy('sort_order')->get();
        $bronnen   = LeadSource::where('is_active', true)->get();
        $typen     = EventType::where('is_active', true)->get();
        $assets    = Asset::where('is_active', true)->orderBy('category')->orderBy('name')->get();

        return view('leads.create', compact('statussen', 'bronnen', 'typen', 'assets'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:150'],
            'email'           => ['nullable', 'email', 'max:255'],
            'phone'           => ['nullable', 'string', 'max:50'],
            'event_date'      => ['nullable', 'date'],
            'event_location'  => ['nullable', 'string'],
            'event_address'   => ['nullable', 'string', 'max:255'],
            'event_postcode'  => ['nullable', 'string', 'max:20'],
            'event_city'      => ['nullable', 'string', 'max:100'],
            'notes'           => ['nullable', 'string'],
            'status_id'       => ['required', 'exists:lead_statuses,id'],
            'source_id'       => ['nullable', 'exists:lead_sources,id'],
            'event_type_id'   => ['nullable', 'exists:event_types,id'],
            'assets'             => ['nullable', 'array'],
            'assets.*.asset_id'  => ['required', 'exists:assets,id'],
            'assets.*.quantity'  => ['nullable', 'integer', 'min:1'],
            'assets.*.price'     => ['nullable', 'numeric', 'min:0'],
        ]);

        $assetsInput = $request->input('assets', []);
        $data['total_price'] = collect($assetsInput)
            ->filter(fn($i) => !empty($i['selected']))
            ->sum(fn($i) => (float)($i['price'] ?? 0) * max(1, (int)($i['quantity'] ?? 1)));

        $lead = Lead::create($data);

        // Koppel assets (indicatief, niet gereserveerd)
        $this->syncLeadAssets($lead, $assetsInput);

        LeadActivity::create([
            'lead_id'       => $lead->id,
            'user_id'       => auth()->id(),
            'activity_type' => 'note',
            'title'         => 'Lead aangemaakt',
        ]);

        return redirect()->route('leads.show', $lead)->with('success', 'Lead aangemaakt.');
    }

    public function show(Lead $lead): View
    {
        $lead->load(['status', 'source', 'eventType', 'assignedTo', 'activities.user', 'assets', 'offers']);
        $statussen = LeadStatus::where('is_active', true)->orderBy('sort_order')->get();

        return view('leads.show', compact('lead', 'statussen'));
    }

    public function edit(Lead $lead): View
    {
        $statussen     = LeadStatus::where('is_active', true)->orderBy('sort_order')->get();
        $bronnen       = LeadSource::where('is_active', true)->get();
        $typen         = EventType::where('is_active', true)->get();
        $assets        = Asset::where('is_active', true)->orderBy('category')->orderBy('name')->get();
        $selectedItems = $lead->assets->keyBy('id'); // keyed by asset_id

        return view('leads.edit', compact('lead', 'statussen', 'bronnen', 'typen', 'assets', 'selectedItems'));
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:150'],
            'email'           => ['nullable', 'email', 'max:255'],
            'phone'           => ['nullable', 'string', 'max:50'],
            'event_date'      => ['nullable', 'date'],
            'event_location'  => ['nullable', 'string'],
            'event_address'   => ['nullable', 'string', 'max:255'],
            'event_postcode'  => ['nullable', 'string', 'max:20'],
            'event_city'      => ['nullable', 'string', 'max:100'],
            'notes'           => ['nullable', 'string'],
            'status_id'       => ['required', 'exists:lead_statuses,id'],
            'source_id'       => ['nullable', 'exists:lead_sources,id'],
            'event_type_id'   => ['nullable', 'exists:event_types,id'],
            'assets'             => ['nullable', 'array'],
            'assets.*.asset_id'  => ['required', 'exists:assets,id'],
            'assets.*.quantity'  => ['nullable', 'integer', 'min:1'],
            'assets.*.price'     => ['nullable', 'numeric', 'min:0'],
        ]);

        $assetsInput = $request->input('assets', []);
        $data['total_price'] = collect($assetsInput)
            ->filter(fn($i) => !empty($i['selected']))
            ->sum(fn($i) => (float)($i['price'] ?? 0) * max(1, (int)($i['quantity'] ?? 1)));

        $oudStatusId = $lead->status_id;
        $lead->update($data);

        // Sync assets (indicatief)
        $this->syncLeadAssets($lead, $assetsInput);

        if ($oudStatusId !== $lead->status_id) {
            LeadActivity::create([
                'lead_id'       => $lead->id,
                'user_id'       => auth()->id(),
                'activity_type' => 'status_change',
                'title'         => 'Status gewijzigd',
                'old_status'    => (string) $oudStatusId,
                'new_status'    => (string) $lead->status_id,
            ]);
        }

        return redirect()->route('leads.show', $lead)->with('success', 'Lead bijgewerkt.');
    }

    private function syncLeadAssets(Lead $lead, array $assetsInput): void
    {
        $syncData = [];
        foreach ($assetsInput as $item) {
            if (empty($item['asset_id'])) continue;
            if (empty($item['selected'])) continue;
            $syncData[$item['asset_id']] = [
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'price'    => (float) ($item['price'] ?? 0),
            ];
        }
        $lead->assets()->sync($syncData);
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        abort_if($lead->account_id !== session('account_id'), 403);
        $lead->delete();
        return redirect()->route('leads.index')->with('success', 'Lead verwijderd.');
    }

    public function addNote(Request $request, Lead $lead): RedirectResponse
    {
        $request->validate(['note' => ['required', 'string', 'max:2000']]);

        LeadActivity::create([
            'lead_id'       => $lead->id,
            'user_id'       => auth()->id(),
            'activity_type' => 'note',
            'title'         => 'Notitie',
            'description'   => $request->note,
        ]);

        return back()->with('success', 'Notitie toegevoegd.');
    }

    public function afwijzen(Request $request, Lead $lead): RedirectResponse
    {
        if ($lead->isArchived()) {
            return back()->with('error', 'Deze lead is al gearchiveerd.');
        }

        // Zet status op 'lost' als die bestaat
        $lostStatus = LeadStatus::where('name', 'lost')->first();
        if ($lostStatus) {
            $lead->status_id = $lostStatus->id;
        }

        $lead->archiveer('lost');

        LeadActivity::create([
            'lead_id'       => $lead->id,
            'user_id'       => auth()->id(),
            'activity_type' => 'status_change',
            'title'         => 'Lead afgewezen',
            'description'   => $request->input('reden'),
            'new_status'    => 'lost',
        ]);

        return redirect()->route('leads.index')->with('success', 'Lead afgewezen en gearchiveerd.');
    }
}
