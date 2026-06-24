<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Briefing;
use App\Models\Staff;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class BriefingController extends Controller
{
    public function index(): View
    {
        $briefings = Briefing::with('staff')->latest('date_from')->get();
        return view('briefings.index', compact('briefings'));
    }

    public function create(): View
    {
        $staff = Staff::where('is_active', true)->orderBy('name')->get();
        $briefing = new Briefing([
            'date_from' => now()->startOfWeek()->toDateString(),
            'date_to'   => now()->startOfWeek()->addDays(6)->toDateString(),
        ]);
        return view('briefings.form', compact('briefing', 'staff'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);
        $briefing = Briefing::create($data);
        return redirect()->route('briefings.edit', $briefing)->with('success', 'Briefing aangemaakt. Vul nu de extra notities in.');
    }

    public function edit(Briefing $briefing): View
    {
        $staff = Staff::where('is_active', true)->orderBy('name')->get();
        $ritten = $this->buildRitten($briefing);
        return view('briefings.form', compact('briefing', 'staff', 'ritten'));
    }

    public function update(Request $request, Briefing $briefing): RedirectResponse
    {
        $data = $this->validatePayload($request);

        // Notities mergen vanuit per-rit textareas: notes[bookingId:role] = "tekst"
        $notes = collect($request->input('notes', []))
            ->filter(fn($v) => trim((string) $v) !== '')
            ->all();

        $briefing->update(array_merge($data, ['notes' => $notes]));

        return redirect()->route('briefings.edit', $briefing)->with('success', '✅ Briefing bijgewerkt.');
    }

    public function destroy(Briefing $briefing): RedirectResponse
    {
        $briefing->delete();
        return redirect()->route('briefings.index')->with('success', '🗑 Briefing verwijderd.');
    }

    /** Genereer en download/preview de PDF */
    public function pdf(Briefing $briefing, Request $request): Response
    {
        $ritten          = $this->buildRitten($briefing);
        $byDay           = $this->groupByDay($ritten);
        $byUnit          = $this->groupByUnit($ritten);

        $briefing->update(['generated_at' => now()]);

        $pdf = Pdf::loadView('briefings.pdf', [
            'briefing' => $briefing,
            'byDay'    => $byDay,
            'byUnit'   => $byUnit,
        ])->setPaper('a4', 'portrait');

        $filename = 'briefing-' . str()->slug($briefing->staff->name ?? 'medewerker') . '-' . $briefing->date_from->format('Y-m-d') . '.pdf';

        if ($request->boolean('preview')) {
            return $pdf->stream($filename);
        }
        return $pdf->download($filename);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'staff_id'  => ['required', 'exists:staff,id'],
            'title'     => ['nullable', 'string', 'max:200'],
            'date_from' => ['required', 'date'],
            'date_to'   => ['required', 'date', 'after_or_equal:date_from'],
        ]);
    }

    /** Verzamel alle ritten van de staff binnen de date range, met items + adres */
    private function buildRitten(Briefing $briefing): \Illuminate\Support\Collection
    {
        $staffId = $briefing->staff_id;
        $from    = $briefing->date_from->startOfDay();
        $to      = $briefing->date_to->endOfDay();

        $bookings = Booking::with(['items.asset'])
            ->where(function ($q) use ($staffId) {
                $q->where('delivery_staff_id', $staffId)->orWhere('pickup_staff_id', $staffId);
            })
            ->whereIn('status', ['confirmed', 'completed'])
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('event_date', [$from->toDateString(), $to->toDateString()])
                  ->orWhereBetween('delivery_at', [$from, $to])
                  ->orWhereBetween('pickup_at', [$from, $to])
                  ->orWhereBetween('customer_pickup_at', [$from, $to])
                  ->orWhereBetween('customer_return_at', [$from, $to]);
            })
            ->get();

        $ritten = collect();

        foreach ($bookings as $b) {
            // Delivery / handover rit (vanuit medewerker-perspectief)
            if ($b->delivery_staff_id === $staffId) {
                $isToGo = $b->booking_type === 'to_go';
                $datum  = $isToGo
                    ? ($b->customer_pickup_at ?? $b->event_date)
                    : ($b->delivery_at ?? $b->event_date);
                $datum  = $datum instanceof Carbon ? $datum : Carbon::parse($datum);
                if ($datum->between($from, $to)) {
                    $ritten->push([
                        'booking' => $b,
                        'role'    => $isToGo ? 'handover' : 'delivery',
                        'datum'   => $datum,
                    ]);
                }
            }
            // Pickup rit
            if ($b->pickup_staff_id === $staffId) {
                $isToGo = $b->booking_type === 'to_go';
                $datum  = $isToGo
                    ? ($b->customer_return_at ?? $b->event_date)
                    : ($b->pickup_at ?? $b->event_date);
                $datum  = $datum instanceof Carbon ? $datum : Carbon::parse($datum);
                if ($datum->between($from, $to)) {
                    $ritten->push([
                        'booking' => $b,
                        'role'    => 'pickup',
                        'datum'   => $datum,
                    ]);
                }
            }
        }

        return $ritten->sortBy('datum')->values();
    }

    /** Groepeer ritten per dag */
    private function groupByDay(\Illuminate\Support\Collection $ritten): array
    {
        return $ritten->groupBy(fn($r) => $r['datum']->format('Y-m-d'))
            ->map(fn($items, $day) => [
                'date'   => Carbon::parse($day),
                'ritten' => $items->values(),
            ])
            ->values()
            ->toArray();
    }

    /** Groepeer ritten per photobooth-unit — toon waar elke unit heen gaat */
    private function groupByUnit(\Illuminate\Support\Collection $ritten): array
    {
        $units = [];
        foreach ($ritten as $r) {
            $b = $r['booking'];
            foreach ($b->items as $item) {
                if (! $item->asset || $item->asset->category !== 'photobooth') continue;
                $key = $item->asset->id . '-' . ($item->unit_number ?? 0);
                if (! isset($units[$key])) {
                    $units[$key] = [
                        'asset_name'  => $item->asset->name,
                        'unit_number' => $item->unit_number,
                        'movements'   => collect(),
                    ];
                }
                $units[$key]['movements']->push($r);
            }
        }

        foreach ($units as $k => $u) {
            $units[$k]['movements'] = $u['movements']->sortBy('datum')->values();
        }

        // Sorteer units op asset_name + unit_number
        ksort($units);
        return array_values($units);
    }
}
