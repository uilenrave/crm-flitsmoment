<?php

namespace App\Http\Controllers;

use App\Helpers\CalendarHelper;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadStatus;
use App\Services\MailService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function index(Request $request): View
    {
        $query = Booking::latest('event_date');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('zoeken')) {
            $query->where(function ($q) use ($request) {
                $q->where('customer_name', 'like', "%{$request->zoeken}%")
                  ->orWhere('customer_email', 'like', "%{$request->zoeken}%")
                  ->orWhere('booking_number', 'like', "%{$request->zoeken}%");
            });
        }

        $bookings = $query->with('account')->paginate(20)->withQueryString();

        return view('bookings.index', compact('bookings'));
    }

    public function create(Request $request): View
    {
        $lead   = $request->filled('lead_id') ? Lead::with('assets')->findOrFail($request->lead_id) : null;
        $assets = Asset::where('is_active', true)->orderBy('category')->orderBy('name')->get();

        // Pre-selecteer assets vanuit lead (inclusief opgeslagen prijs)
        $leadAssets = $lead ? $lead->assets->keyBy('id') : collect();

        return view('bookings.create', compact('lead', 'assets', 'leadAssets'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'lead_id'              => ['nullable', 'exists:leads,id'],
            'customer_name'        => ['required', 'string', 'max:150'],
            'customer_email'       => ['nullable', 'email', 'max:255'],
            'customer_phone'       => ['nullable', 'string', 'max:50'],
            'customer_type'        => ['required', 'in:particulier,zakelijk'],
            'company_name'         => ['nullable', 'string', 'max:255', 'required_if:customer_type,zakelijk'],
            'payment_method'       => ['nullable', 'in:ideal,bij_levering'],
            'booking_type'         => ['required', 'in:full_service,to_go'],
            'event_date'           => ['required', 'date'],
            'event_end_date'       => ['nullable', 'date', 'after_or_equal:event_date'],
            'is_multi_day'         => ['nullable', 'boolean'],
            'event_start_time'     => ['nullable', 'date_format:H:i'],
            'event_end_time'       => ['nullable', 'date_format:H:i'],
            'delivery_at'          => ['nullable', 'date'],
            'pickup_at'            => ['nullable', 'date'],
            'customer_pickup_at'   => ['nullable', 'date'],
            'customer_return_at'   => ['nullable', 'date'],
            'event_location'       => ['nullable', 'string', 'max:255'],
            'event_address'        => ['nullable', 'string', 'max:255'],
            'event_postcode'       => ['nullable', 'string', 'max:20'],
            'event_city'           => ['nullable', 'string', 'max:100'],
            'event_notes'          => ['nullable', 'string'],
            'strip_status'         => ['required', 'in:waiting_input,designing,review,accepted,ready'],
            'total_price'          => ['nullable', 'numeric', 'min:0'],
            'assets'               => ['nullable', 'array'],
            'assets.*.selected'    => ['nullable', 'in:1'],
            'assets.*.asset_id'    => ['nullable', 'exists:assets,id'],
            'assets.*.quantity'    => ['nullable', 'integer', 'min:1'],
            'assets.*.price'       => ['nullable', 'numeric', 'min:0'],
        ]);

        // Zakelijk betaalt altijd via iDEAL, nooit bij levering
        if (($data['customer_type'] ?? 'particulier') === 'zakelijk') {
            $data['payment_method'] = 'ideal';
        } elseif (empty($data['payment_method'])) {
            $data['payment_method'] = 'ideal';
        }

        $data['confirmed_at'] = now();

        $assetsInput = $request->input('assets', []);
        unset($data['assets']);

        // Beschikbaarheidscheck voor photobooths
        $availErrors = $this->checkPhotboothAvailability(
            $assetsInput,
            $data['event_date'],
            $data['event_end_date'] ?? null,
        );
        if (! empty($availErrors)) {
            return back()->withInput()->withErrors(['availability' => implode(' ', $availErrors)]);
        }

        // Totaalprijs: photobooths prijs × aantal units; overigen prijs × qty
        $photobboothIds = Asset::where('category', 'photobooth')->pluck('id')->toArray();
        $data['total_price'] = collect($assetsInput)->map(function ($i, $assetId) use ($photobboothIds) {
            if (in_array((int) $assetId, $photobboothIds)) {
                $units = array_filter(array_map('intval', $i['units'] ?? []));
                return (float) ($i['price'] ?? 0) * count($units);
            }
            if (empty($i['selected'])) return 0;
            return (float) ($i['price'] ?? 0) * max(1, (int) ($i['quantity'] ?? 1));
        })->sum();

        $booking = Booking::create($data);
        $this->syncBookingItems($booking, $assetsInput);

        // Archiveer de gekoppelde lead als 'gewonnen'
        if (! empty($data['lead_id'])) {
            $lead = Lead::find($data['lead_id']);
            if ($lead && ! $lead->isArchived()) {
                $wonStatus = LeadStatus::where('name', 'won')->first();
                if ($wonStatus) {
                    $lead->status_id = $wonStatus->id;
                }
                $lead->archiveer('won');

                LeadActivity::create([
                    'lead_id'       => $lead->id,
                    'user_id'       => auth()->id(),
                    'activity_type' => 'status_change',
                    'title'         => 'Omgezet naar boeking',
                    'description'   => "Boeking {$booking->booking_number} aangemaakt.",
                    'new_status'    => 'won',
                ]);
            }
        }

        // ── Mail triggers ──
        $booking->load('account');
        $mail = app(MailService::class);

        if ($booking->customer_email) {
            $mail->send('customer_booking_confirmation', $booking, $booking->customer_email);
        }
        if ($booking->account->email) {
            $mail->send('admin_new_booking', $booking, $booking->account->email);
        }

        return redirect()->route('bookings.show', $booking)->with('success', 'Boeking aangemaakt.');
    }

    public function show(Booking $booking): View
    {
        if (! $booking->public_token) {
            $booking->update(['public_token' => \Illuminate\Support\Str::random(48)]);
        }

        $booking->load(['lead', 'payments', 'items', 'account']);

        return view('bookings.show', compact('booking'));
    }

    public function edit(Booking $booking): View
    {
        $booking->load('items');
        $assets        = Asset::where('is_active', true)->orderBy('category')->orderBy('name')->get();
        $selectedItems = $booking->items->keyBy('asset_id');
        $itemsByAsset  = $booking->items->groupBy('asset_id');

        // Bereken welke units al geboekt zijn door andere boekingen voor deze datumperiode
        $bookedUnitsByAsset = [];
        $eventDate    = $booking->event_date->toDateString();
        $eventEndDate = ($booking->event_end_date ?? $booking->event_date)->toDateString();

        foreach ($assets->where('category', 'photobooth') as $pb) {
            $overlapScope = function ($q) use ($eventDate, $eventEndDate, $booking) {
                $q->whereIn('status', ['confirmed', 'completed'])
                  ->where('id', '!=', $booking->id)
                  ->where('event_date', '<=', $eventEndDate)
                  ->whereRaw("COALESCE(event_end_date, event_date) >= ?", [$eventDate]);
            };

            // Units specifiek geboekt (met unit_number)
            $specificUnits = BookingItem::where('asset_id', $pb->id)
                ->whereNotNull('unit_number')
                ->whereHas('booking', $overlapScope)
                ->pluck('unit_number')
                ->toArray();

            // Legacy boekingen (zonder unit_number) — bezetten de eerste N vrije units
            $legacyQty = (int) BookingItem::where('asset_id', $pb->id)
                ->whereNull('unit_number')
                ->whereHas('booking', $overlapScope)
                ->sum('quantity');

            $bookedUnits = array_values(array_unique($specificUnits));
            for ($u = 1; $u <= $pb->stock && $legacyQty > 0; $u++) {
                if (! in_array($u, $bookedUnits)) {
                    $bookedUnits[] = $u;
                    $legacyQty--;
                }
            }

            $bookedUnitsByAsset[$pb->id] = $bookedUnits;
        }

        return view('bookings.edit', compact('booking', 'assets', 'selectedItems', 'itemsByAsset', 'bookedUnitsByAsset'));
    }

    public function update(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate([
            'customer_name'        => ['required', 'string', 'max:150'],
            'customer_email'       => ['nullable', 'email', 'max:255'],
            'customer_phone'       => ['nullable', 'string', 'max:50'],
            'customer_type'        => ['required', 'in:particulier,zakelijk'],
            'company_name'         => ['nullable', 'string', 'max:255', 'required_if:customer_type,zakelijk'],
            'payment_method'       => ['nullable', 'in:ideal,bij_levering'],
            'booking_type'         => ['required', 'in:full_service,to_go'],
            'event_date'           => ['required', 'date'],
            'event_end_date'       => ['nullable', 'date', 'after_or_equal:event_date'],
            'is_multi_day'         => ['nullable', 'boolean'],
            'event_start_time'     => ['nullable', 'date_format:H:i'],
            'event_end_time'       => ['nullable', 'date_format:H:i'],
            'delivery_at'          => ['nullable', 'date'],
            'pickup_at'            => ['nullable', 'date'],
            'customer_pickup_at'   => ['nullable', 'date'],
            'customer_return_at'   => ['nullable', 'date'],
            'event_location'       => ['nullable', 'string', 'max:255'],
            'event_address'        => ['nullable', 'string', 'max:255'],
            'event_postcode'       => ['nullable', 'string', 'max:20'],
            'event_city'           => ['nullable', 'string', 'max:100'],
            'event_notes'          => ['nullable', 'string'],
            'strip_status'         => ['required', 'in:waiting_input,designing,review,accepted,ready'],
            'gallery_url'          => ['nullable', 'url', 'max:500'],
            'total_price'          => ['nullable', 'numeric', 'min:0'],
            'status'               => ['required', 'in:confirmed,cancelled,completed,no_show'],
            'payment_status'       => ['required', 'in:unpaid,partial,paid,cancelled,refunded'],
            'assets'               => ['nullable', 'array'],
            'assets.*.selected'    => ['nullable', 'in:1'],
            'assets.*.asset_id'    => ['nullable', 'exists:assets,id'],
            'assets.*.quantity'    => ['nullable', 'integer', 'min:1'],
            'assets.*.price'       => ['nullable', 'numeric', 'min:0'],
            'strip_design_file'    => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,pdf', 'max:20480'],
        ]);

        // Zakelijk betaalt altijd via iDEAL, nooit bij levering
        if (($data['customer_type'] ?? 'particulier') === 'zakelijk') {
            $data['payment_method'] = 'ideal';
        } elseif (empty($data['payment_method'])) {
            $data['payment_method'] = 'ideal';
        }

        // ── Fotostrip ontwerp upload ──
        if ($request->hasFile('strip_design_file')) {
            // Verwijder oud bestand als het een lokaal bestand is
            if ($booking->strip_design_url && str_contains($booking->strip_design_url, '/storage/strip-designs/')) {
                $oldPath = str_replace(asset('storage') . '/', '', $booking->strip_design_url);
                Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('strip_design_file')->store('strip-designs', 'public');
            $data['strip_design_url'] = Storage::disk('public')->url($path);
        }

        $assetsInput = $request->input('assets', []);
        unset($data['assets'], $data['strip_design_file']);

        // Beschikbaarheidscheck voor photobooths (excl. huidige boeking)
        $availErrors = $this->checkPhotboothAvailability(
            $assetsInput,
            $data['event_date'],
            $data['event_end_date'] ?? null,
            $booking->id,
        );
        if (! empty($availErrors)) {
            return back()->withInput()->withErrors(['availability' => implode(' ', $availErrors)]);
        }

        // Totaalprijs: photobooths prijs × aantal units; overigen prijs × qty
        $photobboothIds = Asset::where('category', 'photobooth')->pluck('id')->toArray();
        $data['total_price'] = collect($assetsInput)->map(function ($i, $assetId) use ($photobboothIds) {
            if (in_array((int) $assetId, $photobboothIds)) {
                $units = array_filter(array_map('intval', $i['units'] ?? []));
                return (float) ($i['price'] ?? 0) * count($units);
            }
            if (empty($i['selected'])) return 0;
            return (float) ($i['price'] ?? 0) * max(1, (int) ($i['quantity'] ?? 1));
        })->sum();

        // ── Bewaar oud state voor change detection ──
        $oudGalleryUrl    = $booking->gallery_url;
        $oudDesignUrl     = $booking->strip_design_url;
        $oudStripStatus   = $booking->strip_status;

        $booking->update($data);
        $booking->items()->delete();
        $this->syncBookingItems($booking, $assetsInput);

        // ── Mail triggers ──
        $booking->load('account');
        $mail = app(MailService::class);

        // Gallerij link nieuw toegevoegd → mail klant + status op afgerond
        if (empty($oudGalleryUrl) && ! empty($booking->gallery_url)) {
            if ($booking->customer_email) {
                $mail->send('customer_gallery_ready', $booking, $booking->customer_email);
            }
            if ($booking->status !== 'completed') {
                $booking->update(['status' => 'completed']);
            }
        }

        // Fotostrip ontwerp nieuw of aangepast → mail klant + wis opmerkingen
        if ($booking->strip_design_url && $booking->strip_design_url !== $oudDesignUrl) {
            $booking->update([
                'strip_status'     => 'review',
                'strip_notes'      => null,
                'strip_feedback'   => null,
                'strip_feedback_at'=> null,
            ]);
            if ($booking->customer_email) {
                $mail->send('customer_strip_design_added', $booking, $booking->customer_email);
            }
        }

        // Strip status veranderd naar 'accepted' → mails
        if ($oudStripStatus !== 'accepted' && $booking->strip_status === 'accepted') {
            if ($booking->customer_email) {
                $mail->send('customer_strip_accepted', $booking, $booking->customer_email);
            }
            if ($booking->account->email) {
                $mail->send('admin_strip_accepted', $booking, $booking->account->email);
            }
        }

        return redirect()->route('bookings.show', $booking)->with('success', 'Boeking bijgewerkt.');
    }

    /** Follow-up: boekingen waarvan event voorbij is maar nog geen galerij link hebben */
    public function followUp(): View
    {
        $bookings = Booking::with('account')
            ->where('event_date', '<', now()->toDateString())
            ->whereIn('status', ['confirmed', 'completed'])
            ->where(function ($q) {
                $q->whereNull('gallery_url')->orWhere('gallery_url', '');
            })
            ->orderByDesc('event_date')
            ->get();

        return view('bookings.follow-up', compact('bookings'));
    }

    /** Ajax: sla galerij URL op en stuur mail */
    public function saveGallery(Request $request, Booking $booking): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'gallery_url' => ['required', 'url', 'max:500'],
        ]);

        $booking->update([
            'gallery_url' => $request->gallery_url,
            'status'      => 'completed',
        ]);

        $mailSent = false;
        if ($booking->customer_email) {
            app(MailService::class)->send('customer_gallery_ready', $booking, $booking->customer_email);
            $mailSent = true;
        }

        return response()->json([
            'success'   => true,
            'mail_sent' => $mailSent,
            'message'   => $mailSent ? 'Galerij opgeslagen en mail verstuurd!' : 'Galerij opgeslagen (geen e-mailadres bekend).',
        ]);
    }

    public function calendar(Request $request): View
    {
        // Haal maand/jaar uit request of gebruik huidige
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);
        $view = $request->input('view', 'month'); // month|week

        // Controleer geldigheid van maand/jaar
        if ($month < 1 || $month > 12) {
            $month = now()->month;
        }
        if ($year < 2000 || $year > 2100) {
            $year = now()->year;
        }

        // Haal boekingen op voor deze maand
        $bookings = Booking::whereYear('event_date', $year)
            ->whereMonth('event_date', $month)
            ->where('status', '!=', 'cancelled')
            ->orderBy('event_date')
            ->get();

        // Genereer alle events voor deze maand
        $allEvents = CalendarHelper::eventsForMonth($year, $month, $bookings);

        // Zet alle events per dag in een array voor gemakkelijke template access
        $eventsByDay = [];
        $daysInMonth = CalendarHelper::daysInMonth($year, $month);
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $eventsByDay[$dateStr] = CalendarHelper::eventsForDay($dateStr, $allEvents);
        }

        // Navigatie
        $previousMonth = CalendarHelper::previousMonth($year, $month);
        $nextMonth = CalendarHelper::nextMonth($year, $month);

        return view('bookings.calendar', compact(
            'bookings',
            'year',
            'month',
            'view',
            'eventsByDay',
            'previousMonth',
            'nextMonth',
            'allEvents'
        ));
    }

    public function createInvoice(Booking $booking): RedirectResponse
    {
        $booking->loadMissing('account');

        if (! $booking->account->eboekhouden_enabled) {
            return redirect()->route('bookings.show', $booking)
                ->with('error', 'e-Boekhouden koppeling is uitgeschakeld voor dit account.');
        }

        $service = app(\App\Services\EBoekhoudenService::class)
            ->withApiKey($booking->account->getEBoekhoudenApiKey());
        $result = $service->createInvoice($booking);

        if ($result['success']) {
            $message = "✅ Factuur aangemaakt in e-boekhouden";
            if ($result['invoice_id']) {
                $message .= " (ID: {$result['invoice_id']})";
            }
            return redirect()->route('bookings.show', $booking)->with('success', $message);
        } else {
            return redirect()->route('bookings.show', $booking)
                ->with('error', "Fout bij factuur aanmaken: {$result['error']}");
        }
    }

    /** Strip status bijwerken via AJAX */
    public function updateStripStatus(Request $request, Booking $booking): JsonResponse
    {
        $request->validate([
            'strip_status' => ['required', 'in:waiting_input,designing,review,accepted,ready'],
        ]);

        $booking->update(['strip_status' => $request->strip_status]);

        $labels = [
            'waiting_input' => 'Input aanleveren',
            'designing'     => 'Ontwerpen',
            'review'        => 'Wachten op goedkeuring',
            'accepted'      => 'Goedgekeurd',
            'ready'         => 'Ontwerp staat klaar',
        ];

        return response()->json([
            'success' => true,
            'label'   => $labels[$request->strip_status] ?? $request->strip_status,
        ]);
    }

    /** Snel ontwerp uploaden vanuit het overzicht */
    public function uploadStripDesign(Request $request, Booking $booking): RedirectResponse
    {
        $request->validate([
            'strip_design_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,pdf', 'max:20480'],
            'strip_design_url'  => ['nullable', 'url'],
        ]);

        $oudDesignUrl = $booking->strip_design_url;
        $nieuweUrl    = null;
        $filename     = null;

        if ($request->hasFile('strip_design_file')) {
            $file      = $request->file('strip_design_file');
            $filename  = $file->getClientOriginalName();
            $path      = $file->store('strip-designs', 'public');
            $nieuweUrl = Storage::disk('public')->url($path);
        } elseif ($request->filled('strip_design_url')) {
            $nieuweUrl = $request->input('strip_design_url');
            $filename  = basename(parse_url($nieuweUrl, PHP_URL_PATH)) ?: 'link';
        }

        if (! $nieuweUrl) {
            return back()->with('error', 'Geen bestand of URL opgegeven.');
        }

        // Voeg toe aan strip_designs lijst
        $designs   = $booking->strip_designs ?? [];
        $designs[] = [
            'url'         => $nieuweUrl,
            'filename'    => $filename,
            'uploaded_at' => now()->toIso8601String(),
        ];

        $updateData = [
            'strip_design_url' => $nieuweUrl,
            'strip_status'     => 'review',
            'strip_designs'    => $designs,
        ];

        // Wis opmerkingen als het ontwerp is aangepast
        if ($nieuweUrl !== $oudDesignUrl) {
            $updateData['strip_notes']       = null;
            $updateData['strip_feedback']    = null;
            $updateData['strip_feedback_at'] = null;
        }

        $booking->update($updateData);

        if ($nieuweUrl !== $oudDesignUrl && $booking->customer_email) {
            $booking->load('account');
            app(MailService::class)->send('customer_strip_design_added', $booking, $booking->customer_email);
        }

        return redirect()->route('bookings.index')->with('success', "Ontwerp toegevoegd en mail verstuurd naar {$booking->customer_name}.");
    }

    /** Verwijder een specifiek ontwerp uit de lijst */
    public function deleteStripDesign(Request $request, Booking $booking): RedirectResponse
    {
        $url     = $request->input('url');
        $designs = collect($booking->strip_designs ?? []);

        // Verwijder bestand van disk als het lokaal is opgeslagen
        $design = $designs->firstWhere('url', $url);
        if ($design && str_contains($url, '/storage/strip-designs/')) {
            $storagePath = 'strip-designs/' . basename(parse_url($url, PHP_URL_PATH));
            Storage::disk('public')->delete($storagePath);
        }

        // Verwijder uit lijst
        $designs = $designs->reject(fn($d) => $d['url'] === $url)->values()->all();

        // Als het actieve ontwerp verwijderd is, zet het laatste resterende als actief
        $nieuweActief = null;
        if ($booking->strip_design_url === $url) {
            $last = end($designs);
            $nieuweActief = $last ? $last['url'] : null;
        }

        $updateData = ['strip_designs' => $designs];
        if ($nieuweActief !== null || $booking->strip_design_url === $url) {
            $updateData['strip_design_url'] = $nieuweActief;
            if (! $nieuweActief) {
                $updateData['strip_status'] = null;
            }
        }

        $booking->update($updateData);

        return redirect()->route('bookings.index')->with('success', 'Ontwerp verwijderd.');
    }

    /** Planning overzicht: Gantt-timeline per photobooth */
    public function planning(Request $request): View
    {
        $weeks  = max(4, min(16, (int) $request->input('weeks', 8)));
        $offset = (int) $request->input('offset', 0);

        $startDate = Carbon::now()->startOfWeek()->addWeeks($offset);
        $endDate   = $startDate->copy()->addWeeks($weeks)->subDay();

        // Genereer alle dagen in het bereik
        $days = collect();
        for ($d = $startDate->copy(); $d->lte($endDate); $d->addDay()) {
            $days->push($d->copy());
        }

        // Haal alle photobooth assets op
        $photobooths = Asset::where('category', 'photobooth')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Haal alle relevante boekingen op (alleen bevestigd/afgerond, in datumbereik, met photobooth items)
        $bookings = Booking::with(['items' => fn($q) => $q->with('asset')])
            ->whereIn('status', ['confirmed', 'completed'])
            ->where('event_date', '<=', $endDate->toDateString())
            ->whereRaw("COALESCE(event_end_date, event_date) >= ?", [$startDate->toDateString()])
            ->orderBy('event_date')
            ->get()
            ->filter(fn($b) => $b->items->contains(fn($i) => $i->asset?->category === 'photobooth'));

        // Bouw per photobooth N rijen (1 per unit), gebruik opgeslagen unit_number
        $rows = [];
        foreach ($photobooths as $pb) {
            $pbBookings = $bookings
                ->filter(fn($b) => $b->items->where('asset_id', $pb->id)->isNotEmpty())
                ->sortBy('event_date')
                ->values();

            $numSlots = max(1, (int) $pb->stock);

            // Splits: unit-gebaseerde boekingen (vaste unit) vs legacy (geen unit_number)
            $unitBased = $pbBookings->filter(function ($b) use ($pb) {
                return $b->items->contains(fn($i) => $i->asset_id === $pb->id && ! is_null($i->unit_number));
            });
            $legacy = $pbBookings->filter(function ($b) use ($pb) {
                return $b->items->contains(fn($i) => $i->asset_id === $pb->id && is_null($i->unit_number));
            });

            // Legacy: auto-toewijzen met oud algoritme
            $legacySlots = array_fill(0, $numSlots, []);
            foreach ($legacy->sortBy('event_date') as $booking) {
                $bookStart = $booking->event_date->copy();
                $bookEnd   = $booking->event_end_date ? $booking->event_end_date->copy() : $bookStart;
                $qty       = max(1, (int) ($booking->items->firstWhere('asset_id', $pb->id)?->quantity ?? 1));

                $assignedCount = 0;
                for ($s = 0; $s < $numSlots && $assignedCount < $qty; $s++) {
                    $conflict = false;
                    foreach ($legacySlots[$s] as $existing) {
                        $exStart = $existing->event_date->copy();
                        $exEnd   = $existing->event_end_date ? $existing->event_end_date->copy() : $exStart;
                        if ($bookStart->lte($exEnd) && $bookEnd->gte($exStart)) {
                            $conflict = true;
                            break;
                        }
                    }
                    if (! $conflict) {
                        $legacySlots[$s][] = $booking;
                        $assignedCount++;
                    }
                }
                while ($assignedCount < $qty) {
                    $legacySlots[$numSlots - 1][] = $booking;
                    $assignedCount++;
                }
            }

            // Bouw per slot een segment-rij
            for ($s = 0; $s < $numSlots; $s++) {
                $unitNumber = $s + 1;
                $label      = $numSlots > 1 ? $pb->name . ' ' . $unitNumber : $pb->name;

                // Unit-gebaseerde boekingen specifiek voor dit unit nummer
                $slotBookings = $unitBased->filter(function ($b) use ($pb, $unitNumber) {
                    return $b->items->contains(
                        fn($i) => $i->asset_id === $pb->id && (int) $i->unit_number === $unitNumber
                    );
                });

                // Voeg legacy toe (auto-toegewezen aan dit slot)
                $slotBookings = $slotBookings
                    ->merge(collect($legacySlots[$s]))
                    ->sortBy('event_date')
                    ->values();

                $segments  = [];
                $dayIndex  = 0;
                $totalDays = $days->count();

                while ($dayIndex < $totalDays) {
                    $currentDay     = $days[$dayIndex];
                    $matchedBooking = null;
                    $colspan        = 1;

                    foreach ($slotBookings as $booking) {
                        $bookStart = $booking->event_date->copy();
                        $bookEnd   = $booking->event_end_date ? $booking->event_end_date->copy() : $bookStart;

                        $visStart = $bookStart->lt($startDate) ? $startDate->copy() : $bookStart;
                        $visEnd   = $bookEnd->gt($endDate)     ? $endDate->copy()   : $bookEnd;

                        if ($currentDay->gte($visStart) && $currentDay->lte($visEnd)) {
                            $matchedBooking = $booking;
                            $colspan        = $currentDay->diffInDays($visEnd) + 1;
                            break;
                        }
                    }

                    if ($matchedBooking) {
                        $segments[] = ['booking' => $matchedBooking, 'colspan' => $colspan];
                        $dayIndex  += $colspan;
                    } else {
                        $segments[] = ['booking' => null, 'colspan' => 1];
                        $dayIndex++;
                    }
                }

                $rows[] = [
                    'photobooth'  => $pb,
                    'label'       => $label,
                    'slot'        => $unitNumber,
                    'total_slots' => $numSlots,
                    'segments'    => $segments,
                ];
            }
        }

        $prevOffset = $offset - $weeks;
        $nextOffset = $offset + $weeks;

        return view('bookings.planning', compact(
            'photobooths', 'days', 'rows', 'startDate', 'endDate',
            'weeks', 'offset', 'prevOffset', 'nextOffset'
        ));
    }

    public function destroy(Booking $booking): RedirectResponse
    {
        $booking->delete();
        return redirect()->route('bookings.index')->with('success', 'Boeking verwijderd.');
    }

    /** AJAX: geef bezette units per photobooth-asset voor de opgegeven datums */
    public function unitAvailability(Request $request): JsonResponse
    {
        $eventDate    = $request->input('event_date');
        $eventEndDate = $request->input('event_end_date') ?: $eventDate;
        $excludeId    = $request->input('exclude_booking_id');

        if (! $eventDate) {
            return response()->json([]);
        }

        $photobooths = Asset::where('category', 'photobooth')->where('is_active', true)->get();
        $result      = [];

        foreach ($photobooths as $pb) {
            $overlapScope = function ($q) use ($eventDate, $eventEndDate, $excludeId) {
                $q->whereIn('status', ['confirmed', 'completed'])
                  ->where('event_date', '<=', $eventEndDate)
                  ->whereRaw("COALESCE(event_end_date, event_date) >= ?", [$eventDate]);
                if ($excludeId) {
                    $q->where('id', '!=', $excludeId);
                }
            };

            $specificUnits = BookingItem::where('asset_id', $pb->id)
                ->whereNotNull('unit_number')
                ->whereHas('booking', $overlapScope)
                ->pluck('unit_number')
                ->toArray();

            $legacyQty = (int) BookingItem::where('asset_id', $pb->id)
                ->whereNull('unit_number')
                ->whereHas('booking', $overlapScope)
                ->sum('quantity');

            $bookedUnits = array_values(array_unique($specificUnits));
            for ($u = 1; $u <= $pb->stock && $legacyQty > 0; $u++) {
                if (! in_array($u, $bookedUnits)) {
                    $bookedUnits[] = $u;
                    $legacyQty--;
                }
            }

            $result[$pb->id] = $bookedUnits;
        }

        return response()->json($result);
    }

    /** Controleer of photobooth units beschikbaar zijn voor de gegeven datums */
    private function checkPhotboothAvailability(
        array $assetsInput,
        string $eventDate,
        ?string $eventEndDate,
        ?int $excludeBookingId = null
    ): array {
        $errors  = [];
        $endDate = $eventEndDate ?? $eventDate;

        foreach ($assetsInput as $assetId => $data) {
            $asset = Asset::find($assetId);
            if (! $asset || $asset->category !== 'photobooth') continue;

            $selectedUnits = array_values(array_filter(array_map('intval', $data['units'] ?? [])));
            if (empty($selectedUnits)) continue;

            foreach ($selectedUnits as $unitNumber) {
                $alreadyBooked = BookingItem::where('asset_id', $assetId)
                    ->where('unit_number', $unitNumber)
                    ->whereHas('booking', function ($q) use ($eventDate, $endDate, $excludeBookingId) {
                        $q->where('status', '!=', 'cancelled');
                        if ($excludeBookingId) {
                            $q->where('id', '!=', $excludeBookingId);
                        }
                        $q->where('event_date', '<=', $endDate)
                          ->whereRaw("COALESCE(event_end_date, event_date) >= ?", [$eventDate]);
                    })
                    ->exists();

                if ($alreadyBooked) {
                    $errors[] = "'{$asset->name}' Unit {$unitNumber} is al geboekt voor de geselecteerde periode.";
                }
            }
        }

        return $errors;
    }

    private function syncBookingItems(Booking $booking, array $assetsInput): void
    {
        foreach ($assetsInput as $assetId => $input) {
            $asset = Asset::find($assetId);
            if (! $asset) continue;

            if ($asset->category === 'photobooth') {
                // Unit-gebaseerd: één BookingItem per geselecteerde unit
                $units = array_values(array_filter(array_map('intval', $input['units'] ?? [])));
                if (empty($units)) continue;

                $price = isset($input['price']) && $input['price'] !== '' ? (float) $input['price'] : $asset->price;

                foreach ($units as $unitNumber) {
                    BookingItem::create([
                        'booking_id'     => $booking->id,
                        'asset_id'       => $asset->id,
                        'name_snapshot'  => $asset->name,
                        'price_snapshot' => $price,
                        'quantity'       => 1,
                        'unit_number'    => $unitNumber,
                    ]);
                }
            } else {
                // Hoeveelheid-gebaseerd: bestaand gedrag
                if (empty($input['selected'])) continue;

                $qty   = max(1, (int) ($input['quantity'] ?? 1));
                $price = isset($input['price']) && $input['price'] !== '' ? (float) $input['price'] : $asset->price;

                BookingItem::create([
                    'booking_id'     => $booking->id,
                    'asset_id'       => $asset->id,
                    'name_snapshot'  => $asset->name,
                    'price_snapshot' => $price,
                    'quantity'       => $qty,
                    'unit_number'    => null,
                ]);
            }
        }
    }
}
