<?php

namespace App\Http\Controllers;

use App\Helpers\CalendarHelper;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadStatus;
use App\Models\RideSignup;
use App\Models\Staff;
use App\Services\MailService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BookingController extends Controller
{
    /**
     * Ajax: lijst van bezorg/ophaal-momenten van andere boekingen op een specifieke datum.
     * Wordt gebruikt door het rechter-paneel bij create/edit om back-to-back planning makkelijk te maken.
     */
    public function dayLogistics(Request $request): JsonResponse
    {
        $request->validate([
            'date'       => ['required', 'date'],
            'exclude_id' => ['nullable', 'integer'],
        ]);

        $date = Carbon::parse($request->date)->toDateString();
        $excludeId = (int) $request->input('exclude_id', 0);

        $bookings = Booking::where('account_id', auth()->user()->account_id)
            ->whereIn('status', ['confirmed', 'completed'])
            ->where('id', '!=', $excludeId)
            ->where(function ($q) use ($date) {
                $q->whereDate('delivery_at', $date)
                  ->orWhereDate('pickup_at', $date)
                  ->orWhereDate('customer_pickup_at', $date)
                  ->orWhereDate('customer_return_at', $date);
            })
            ->get([
                'id', 'booking_number', 'customer_name', 'booking_type',
                'event_address', 'event_postcode', 'event_city',
                'delivery_at', 'pickup_at', 'customer_pickup_at', 'customer_return_at',
            ]);

        $moments = [];
        $warehouse = 'Ravenswade 132, 3439 LD Nieuwegein';

        // Helper: voeg moment toe alleen als datum klopt EN de tijd niet 00:00 is (= geen echte tijd ingevuld)
        $addMoment = function (&$out, $ts, array $base, string $type, string $label, string $icon, string $address) use ($date) {
            if (! $ts) return;
            $c = Carbon::parse($ts);
            if ($c->toDateString() !== $date) return;
            // Skip "datum-only" timestamps (00:00 zonder echte tijd) — die zijn vaak stale data of placeholders
            if ($c->hour === 0 && $c->minute === 0) return;
            $out[] = $base + [
                'time'    => $c->format('H:i'),
                'type'    => $type,
                'label'   => $label,
                'icon'    => $icon,
                'address' => $address,
            ];
        };

        foreach ($bookings as $b) {
            $address = collect([$b->event_address, $b->event_postcode, $b->event_city])
                ->filter()->implode(', ');

            $base = [
                'id'              => $b->id,
                'booking_number'  => $b->booking_number,
                'customer_name'   => $b->customer_name,
            ];

            // Full Service: alleen delivery_at + pickup_at relevant
            if ($b->booking_type === 'full_service') {
                $addMoment($moments, $b->delivery_at, $base, 'delivery', 'Bezorging', '🚚', $address);
                $addMoment($moments, $b->pickup_at,   $base, 'pickup',   'Ophalen',   '↩',  $address);
            }
            // To Go: alleen customer_pickup_at + customer_return_at relevant
            if ($b->booking_type === 'to_go') {
                $addMoment($moments, $b->customer_pickup_at, $base, 'customer_pickup', 'Klant haalt op',     '📦', $warehouse);
                $addMoment($moments, $b->customer_return_at, $base, 'customer_return', 'Klant brengt terug', '🔄', $warehouse);
            }
        }

        // Sorteer op tijd oplopend; momenten zonder tijd (00:00 zonder echte tijd) onderaan
        usort($moments, fn($a, $b) => strcmp($a['time'], $b['time']));

        return response()->json([
            'date'    => $date,
            'count'   => count($moments),
            'moments' => $moments,
        ]);
    }

    public function index(Request $request): View
    {
        $query = Booking::orderBy('event_date', 'asc')
            ->whereDate('event_date', '>=', today());

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('betaling')) {
            $this->applyPaymentFilter($query, $request->betaling);
        }
        if ($request->filled('zoeken')) {
            $query->where(function ($q) use ($request) {
                $s = "%{$request->zoeken}%";
                $q->where('customer_name', 'like', $s)
                  ->orWhere('customer_email', 'like', $s)
                  ->orWhere('customer_phone', 'like', $s)
                  ->orWhere('booking_number', 'like', $s)
                  ->orWhere('event_address', 'like', $s)
                  ->orWhere('event_city', 'like', $s)
                  ->orWhere('event_notes', 'like', $s);
            });
        }

        $bookings = $query->with(['items.asset'])->paginate(20)->withQueryString();
        $account  = auth()->user()->account;

        return view('bookings.index', compact('bookings', 'account'));
    }

    public function archive(Request $request): View
    {
        $query = Booking::orderBy('event_date', 'desc')
            ->whereDate('event_date', '<', today());

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('betaling')) {
            $this->applyPaymentFilter($query, $request->betaling);
        }
        if ($request->filled('zoeken')) {
            $query->where(function ($q) use ($request) {
                $s = "%{$request->zoeken}%";
                $q->where('customer_name', 'like', $s)
                  ->orWhere('customer_email', 'like', $s)
                  ->orWhere('customer_phone', 'like', $s)
                  ->orWhere('booking_number', 'like', $s)
                  ->orWhere('event_address', 'like', $s)
                  ->orWhere('event_city', 'like', $s)
                  ->orWhere('event_notes', 'like', $s);
            });
        }

        $bookings = $query->with(['items.asset'])->paginate(20)->withQueryString();
        $account  = auth()->user()->account;
        $pageTitle = 'Archief';

        return view('bookings.index', compact('bookings', 'account', 'pageTitle'));
    }

    private function resolvePaymentFilter(string $betaling): array
    {
        return match($betaling) {
            'openstaand' => ['unpaid', 'partial'],
            default      => [$betaling],
        };
    }

    /** Pas de betaling-filter toe op de query, met uitsluiting van geannuleerde boekingen bij "openstaand". */
    private function applyPaymentFilter($query, string $betaling): void
    {
        $query->whereIn('payment_status', $this->resolvePaymentFilter($betaling));

        // Geannuleerde boekingen tellen niet als "openstaand" — die ga je niet meer innen
        if ($betaling === 'openstaand') {
            $query->where('status', '!=', 'cancelled');
        }
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
        // Browsers sturen soms HH:MM:SS door step="900" — afkappen naar HH:MM
        $request->merge(array_filter([
            'event_start_time' => $request->event_start_time ? substr($request->event_start_time, 0, 5) : null,
            'event_end_time'   => $request->event_end_time   ? substr($request->event_end_time, 0, 5)   : null,
        ], fn($v) => $v !== null));

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
            'delivery_instructions'         => ['nullable', 'string', 'max:5000'],
            'delivery_instructions_files'   => ['nullable', 'array'],
            'delivery_instructions_files.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'strip_status'         => ['nullable', 'in:waiting_input,awaiting_customer_design,designing,review,accepted,ready'],
            'strip_design_method'  => ['nullable', 'in:self,template,custom'],
            'strip_self_tool'      => ['nullable', 'in:canva,photoshop'],
            'strip_template_id'    => ['nullable', 'exists:strip_templates,id'],
            'total_price'          => ['nullable', 'numeric', 'min:0'],
            'hide_prices'          => ['nullable', 'boolean'],
            'assets'               => ['nullable', 'array'],
            'assets.*.selected'    => ['nullable', 'in:1'],
            'assets.*.asset_id'    => ['nullable', 'exists:assets,id'],
            'assets.*.quantity'    => ['nullable', 'integer', 'min:1'],
            'assets.*.price'       => ['nullable', 'numeric', 'min:0'],
        ]);

        $data['hide_prices'] = $request->boolean('hide_prices');

        // To Go heeft geen bezorg/ophaal door ons — altijd leegmaken om stale data te voorkomen
        if (($data['booking_type'] ?? '') === 'to_go') {
            $data['delivery_at'] = null;
            $data['pickup_at']   = null;
        }

        // Zakelijk betaalt altijd via iDEAL, nooit bij levering
        if (($data['customer_type'] ?? 'particulier') === 'zakelijk') {
            $data['payment_method'] = 'ideal';
        } elseif (empty($data['payment_method'])) {
            $data['payment_method'] = 'ideal';
        }

        $data['confirmed_at'] = now();

        $assetsInput = $request->input('assets', []);
        unset($data['assets']);

        // Beschikbaarheidscheck voor photobooths (op bezorg/ophaal bereik)
        $availErrors = $this->checkPhotboothAvailability(
            $assetsInput,
            $data['event_date'],
            $data['event_end_date'] ?? null,
            null,
            $data['booking_type'] ?? null,
            $data['delivery_at'] ?? null,
            $data['pickup_at'] ?? null,
            $data['customer_pickup_at'] ?? null,
            $data['customer_return_at'] ?? null,
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
                $wonStatus = LeadStatus::whereIn('name', ['gewonnen', 'won'])->first();
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

        $booking->load(['lead', 'payments', 'items', 'account', 'deliveryStaff', 'pickupStaff', 'stripTemplate']);

        return view('bookings.show', compact('booking'));
    }

    public function edit(Booking $booking): View
    {
        $booking->load('items');
        $assets        = Asset::where('is_active', true)->orderBy('category')->orderBy('name')->get();
        $selectedItems = $booking->items->keyBy('asset_id');
        $itemsByAsset  = $booking->items->groupBy('asset_id');

        // Bereken welke units al geboekt zijn door andere boekingen voor deze datumperiode
        // Gebruik het effectieve bezet-bereik van de huidige boeking (bezorg/ophaal of event_date)
        $bookedUnitsByAsset = [];

        if ($booking->booking_type === 'to_go' && $booking->customer_pickup_at) {
            $checkStart = \Carbon\Carbon::parse($booking->customer_pickup_at)->toDateString();
            $checkEnd   = $booking->customer_return_at
                ? \Carbon\Carbon::parse($booking->customer_return_at)->toDateString()
                : $checkStart;
        } elseif ($booking->delivery_at) {
            $checkStart = \Carbon\Carbon::parse($booking->delivery_at)->toDateString();
            $checkEnd   = $booking->pickup_at
                ? \Carbon\Carbon::parse($booking->pickup_at)->toDateString()
                : $checkStart;
        } else {
            $checkStart = $booking->event_date->toDateString();
            $checkEnd   = ($booking->event_end_date ?? $booking->event_date)->toDateString();
        }

        foreach ($assets->where('category', 'photobooth') as $pb) {
            $overlapScope = function ($q) use ($checkStart, $checkEnd, $booking) {
                $q->whereIn('status', ['confirmed', 'completed'])
                  ->where('id', '!=', $booking->id)
                  ->whereRaw("
                    (CASE
                        WHEN booking_type = 'to_go' AND customer_pickup_at IS NOT NULL THEN DATE(customer_pickup_at)
                        WHEN delivery_at IS NOT NULL THEN DATE(delivery_at)
                        ELSE event_date
                    END) <= ?
                    AND
                    (CASE
                        WHEN booking_type = 'to_go' AND customer_pickup_at IS NOT NULL THEN DATE(COALESCE(customer_return_at, customer_pickup_at))
                        WHEN delivery_at IS NOT NULL THEN DATE(COALESCE(pickup_at, delivery_at))
                        ELSE COALESCE(event_end_date, event_date)
                    END) >= ?
                  ", [$checkEnd, $checkStart]);
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

        $staffMembers = Staff::where('is_active', true)->orderBy('name')->get();

        return view('bookings.edit', compact('booking', 'assets', 'selectedItems', 'itemsByAsset', 'bookedUnitsByAsset', 'staffMembers'));
    }

    public function update(Request $request, Booking $booking): RedirectResponse
    {
        // Browsers sturen soms HH:MM:SS door step="900" — afkappen naar HH:MM
        $request->merge(array_filter([
            'event_start_time' => $request->event_start_time ? substr($request->event_start_time, 0, 5) : null,
            'event_end_time'   => $request->event_end_time   ? substr($request->event_end_time, 0, 5)   : null,
        ], fn($v) => $v !== null));

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
            'delivery_instructions'         => ['nullable', 'string', 'max:5000'],
            'delivery_instructions_files'   => ['nullable', 'array'],
            'delivery_instructions_files.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'strip_status'         => ['nullable', 'in:waiting_input,awaiting_customer_design,designing,review,accepted,ready'],
            'strip_design_method'  => ['nullable', 'in:self,template,custom'],
            'strip_self_tool'      => ['nullable', 'in:canva,photoshop'],
            'strip_template_id'    => ['nullable', 'exists:strip_templates,id'],
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
            'mockup'               => ['nullable', 'boolean'],
            'delivery_staff_id'    => ['nullable', 'exists:staff,id'],
            'pickup_staff_id'      => ['nullable', 'exists:staff,id'],
            'hide_prices'          => ['nullable', 'boolean'],
        ]);

        $data['hide_prices'] = $request->boolean('hide_prices');

        // To Go heeft geen bezorg/ophaal door ons — altijd leegmaken om stale data te voorkomen
        if (($data['booking_type'] ?? $booking->booking_type) === 'to_go') {
            $data['delivery_at'] = null;
            $data['pickup_at']   = null;
        }

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
            if ($request->boolean('mockup')) {
                $path = $this->maybeApplyMockup($path, $request->file('strip_design_file')->getMimeType());
            }
            $data['strip_design_url'] = Storage::disk('public')->url($path);
        }

        // ── Leverinstructies afbeeldingen: nieuwe uploads toevoegen aan bestaande lijst ──
        $existingImages = $booking->delivery_instructions_images ?? [];
        if ($request->hasFile('delivery_instructions_files')) {
            foreach ($request->file('delivery_instructions_files') as $file) {
                $path = $file->store('delivery-instructions', 'public');
                $existingImages[] = $path;
            }
        }
        $data['delivery_instructions_images'] = ! empty($existingImages) ? array_values($existingImages) : null;

        $assetsInput = $request->input('assets', []);
        unset($data['assets'], $data['strip_design_file'], $data['delivery_instructions_files']);

        // Beschikbaarheidscheck voor photobooths (excl. huidige boeking, op bezorg/ophaal bereik)
        $availErrors = $this->checkPhotboothAvailability(
            $assetsInput,
            $data['event_date'],
            $data['event_end_date'] ?? null,
            $booking->id,
            $data['booking_type'] ?? null,
            $data['delivery_at'] ?? null,
            $data['pickup_at'] ?? null,
            $data['customer_pickup_at'] ?? null,
            $data['customer_return_at'] ?? null,
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
            ->where('gallery_skipped', false)
            ->orderByDesc('event_date')
            ->get();

        return view('bookings.follow-up', compact('bookings'));
    }

    /** Ajax: sla galerij URL op en stuur mail */
    public function skipGallery(Booking $booking): \Illuminate\Http\JsonResponse
    {
        $booking->update(['gallery_skipped' => true]);
        return response()->json(['success' => true]);
    }

    public function saveGallery(Request $request, Booking $booking): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'gallery_url' => ['required', 'url', 'max:500'],
        ]);

        // Genereer een unieke gallery token als die er nog niet is
        $token = $booking->gallery_token ?? \Illuminate\Support\Str::random(24);

        $booking->update([
            'gallery_url'   => $request->gallery_url,
            'gallery_token' => $token,
            'status'        => 'completed',
        ]);

        $shareUrl = route('gallery.show', $token);

        $mailSent = false;
        if ($booking->customer_email) {
            app(MailService::class)->send('customer_gallery_ready', $booking, $booking->customer_email);
            $mailSent = true;
        }

        return response()->json([
            'success'   => true,
            'mail_sent' => $mailSent,
            'share_url' => $shareUrl,
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
            $this->syncInvoicePayUrl($booking); // betaallink van de factuur-PDF ophalen voor het portaal
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

    /**
     * Resolve het e-Boekhouden factuur-ID (als alleen het nummer bekend is) en haal de
     * Mollie-betaallink van de factuur-PDF op, zodat het portaal de factuur kan tonen
     * en de "Betaal nu"-knop naar e-Boekhoudens betaallink kan wijzen.
     * Doet niets voor accounts zonder e-Boekhouden (bv. Den Haag).
     */
    private function syncInvoicePayUrl(Booking $booking): void
    {
        try {
            if (! $booking->account?->eboekhouden_enabled) {
                return;
            }

            $service = app(\App\Services\EBoekhoudenService::class)
                ->withApiKey($booking->account->getEBoekhoudenApiKey());

            $invoiceId = $booking->eboekhouden_invoice_id;

            // Handmatig factuurnummer (geen ID) → ID opzoeken via het nummer
            if (! $invoiceId && $booking->eboekhouden_invoice_number) {
                $invoice   = $service->findInvoiceByNumber($booking->eboekhouden_invoice_number);
                $invoiceId = $invoice['id'] ?? null;
                if ($invoiceId) {
                    $booking->eboekhouden_invoice_id = $invoiceId;
                }
            }

            if (! $invoiceId) {
                return;
            }

            $booking->eboekhouden_pay_url = $service->getPaymentUrl((string) $invoiceId);
            $booking->save();
        } catch (\Throwable $e) {
            \Log::error('syncInvoicePayUrl fout: ' . $e->getMessage());
        }
    }

    /** Betaalstatus wijzigen via AJAX */
    public function updatePaymentStatus(Request $request, Booking $booking): JsonResponse
    {
        abort_if($booking->account_id !== auth()->user()->account_id, 403);

        $request->validate([
            'payment_status' => ['required', 'in:unpaid,partial,paid,cancelled,refunded'],
        ]);

        $booking->update(['payment_status' => $request->payment_status]);

        return response()->json(['ok' => true]);
    }

    /** Geen factuur nodig toggle */
    public function toggleSkipInvoice(Request $request, Booking $booking): RedirectResponse
    {
        abort_if($booking->account_id !== auth()->user()->account_id, 403);

        $booking->update(['eboekhouden_skip_invoice' => !$booking->eboekhouden_skip_invoice]);

        $msg = $booking->eboekhouden_skip_invoice
            ? 'Boeking gemarkeerd als "geen factuur nodig".'
            : 'Markering verwijderd — factuur kan weer aangemaakt worden.';

        return redirect()->route('bookings.show', $booking)->with('success', $msg);
    }

    /** Handmatig factuurnummer opslaan (zonder factuur aan te maken via CRM) */
    public function saveManualInvoiceNumber(Request $request, Booking $booking): RedirectResponse
    {
        abort_if($booking->account_id !== auth()->user()->account_id, 403);

        $request->validate([
            'eboekhouden_invoice_number' => ['required', 'string', 'max:50'],
        ]);

        $newNumber = trim($request->eboekhouden_invoice_number);
        $isChange  = $booking->eboekhouden_invoice_number && $booking->eboekhouden_invoice_number !== $newNumber;

        $update = [
            'eboekhouden_invoice_number' => $newNumber,
            'eboekhouden_status'         => 'manual',
        ];

        // Bij een wijziging: reset sync-gegevens en betaalstatus zodat opnieuw gecontroleerd moet worden
        if ($isChange) {
            $update['eboekhouden_invoice_id'] = null;
            $update['eboekhouden_synced_at']  = null;
            $update['payment_status']         = 'unpaid';
        }

        $booking->update($update);

        // ID opzoeken + Mollie-betaallink van de factuur-PDF ophalen voor het portaal
        $this->syncInvoicePayUrl($booking);

        $msg = $isChange
            ? 'Factuurnummer gewijzigd. Betaalstatus is teruggezet naar "niet betaald" — controleer de status opnieuw.'
            : 'Factuurnummer opgeslagen. Gebruik "Controleer betaalstatus" om de status te synchroniseren.';

        return redirect()->route('bookings.show', $booking)->with('success', $msg);
    }

    /** Aanvullende factuur (handmatig in e-Boekhouden gemaakt) koppelen aan de boeking */
    public function addExtraInvoice(Request $request, Booking $booking): RedirectResponse
    {
        abort_if($booking->account_id !== auth()->user()->account_id, 403);

        $data = $request->validate([
            'number'      => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:120'],
            'amount'      => ['nullable', 'numeric', 'min:0', 'max:99999'],
        ]);

        $list   = $booking->extra_invoices ?? [];
        $list[] = [
            'number'      => trim($data['number']),
            'description' => $data['description'] ? trim($data['description']) : null,
            'amount'      => isset($data['amount']) && $data['amount'] !== null ? (float) $data['amount'] : null,
            'added_at'    => now()->toIso8601String(),
        ];

        $booking->update(['extra_invoices' => $list]);

        return redirect()->route('bookings.show', $booking)->with('success', 'Aanvullende factuur gekoppeld.');
    }

    /** Aanvullende factuur ontkoppelen */
    public function removeExtraInvoice(Booking $booking, int $index): RedirectResponse
    {
        abort_if($booking->account_id !== auth()->user()->account_id, 403);

        $list = $booking->extra_invoices ?? [];
        if (array_key_exists($index, $list)) {
            array_splice($list, $index, 1);
            $booking->update(['extra_invoices' => $list ?: null]);
        }

        return redirect()->route('bookings.show', $booking)->with('success', 'Aanvullende factuur ontkoppeld.');
    }

    /** Betaalstatus ophalen van e-boekhouden */
    public function syncInvoiceStatus(Booking $booking): RedirectResponse
    {
        abort_if($booking->account_id !== auth()->user()->account_id, 403);

        $booking->loadMissing('account');

        if (!$booking->account->eboekhouden_enabled) {
            return redirect()->route('bookings.show', $booking)
                ->with('error', 'e-Boekhouden koppeling is uitgeschakeld voor dit account.');
        }

        if (!$booking->eboekhouden_invoice_id && !$booking->eboekhouden_invoice_number) {
            return redirect()->route('bookings.show', $booking)
                ->with('error', 'Geen factuur-ID of factuurnummer bekend. Maak eerst een factuur aan of vul het factuurnummer in.');
        }

        $syncService = app(\App\Services\EBoekhoudenPaymentSyncService::class);
        $result = $syncService->syncSingleBooking($booking);

        $flashType = match($result['status']) {
            'paid'      => 'success',
            'unpaid'    => 'warning',
            'not_found' => 'error',
            default     => 'error',
        };

        return redirect()->route('bookings.show', $booking)
            ->with($flashType, $result['message']);
    }

    /** Batch: betaalstatus controleren voor alle onbetaalde boekingen */
    public function syncAllPayments(): RedirectResponse
    {
        $account = auth()->user()->account;

        if (!$account->eboekhouden_enabled) {
            return redirect()->route('bookings.index')
                ->with('error', 'e-Boekhouden koppeling is uitgeschakeld voor dit account.');
        }

        $syncService = app(\App\Services\EBoekhoudenPaymentSyncService::class);
        $result = $syncService->syncPaymentStatuses($account->id);

        $message = "{$result['synced']} boeking(en) bijgewerkt naar betaald";
        if ($result['errors'] > 0) {
            $message .= ", {$result['errors']} fout(en)";
        }
        if ($result['skipped'] > 0) {
            $message .= ", {$result['skipped']} overgeslagen";
        }
        $message .= '.';

        $flashType = $result['synced'] > 0 ? 'success' : 'warning';

        return redirect()->route('bookings.index')->with($flashType, $message);
    }

    /** Terugbrengtijdstip verzoek goedkeuren of afwijzen */
    public function handleReturnTimeRequest(Request $request, Booking $booking): RedirectResponse
    {
        $action = $request->input('action'); // 'approve' of 'reject'

        if ($action === 'approve' && $booking->proposed_return_time && $booking->customer_return_at) {
            $newReturnAt = $booking->customer_return_at->setTimeFromTimeString($booking->proposed_return_time);
            $booking->update([
                'customer_return_at'     => $newReturnAt,
                'proposed_return_status' => 'approved',
            ]);

            // Mail naar klant
            if ($booking->customer_email) {
                $portalUrl = route('portal.show', $booking->public_token);
                $subject   = "✅ Retourtijdstip bevestigd — {$booking->booking_number}";
                $body      = "
                    <p>Beste {$booking->customer_name},</p>
                    <p>Je retourtijdstip is goedgekeurd:</p>
                    <table style='border-collapse:collapse;margin:1rem 0;font-size:15px;'>
                        <tr>
                            <td style='padding:.4rem 1rem .4rem 0;color:#6b7280;'>🔄 Terugbrengen</td>
                            <td style='padding:.4rem 0;font-weight:700;'>{$newReturnAt->translatedFormat('l j F Y')} om {$newReturnAt->format('H:i')}</td>
                        </tr>
                        <tr>
                            <td style='padding:.4rem 1rem .4rem 0;color:#6b7280;'>📍 Adres</td>
                            <td style='padding:.4rem 0;'>Ravenswade 132, 3439 LD Nieuwegein</td>
                        </tr>
                    </table>
                    <p><a href='{$portalUrl}' style='display:inline-block;background:#ea580c;color:#fff;padding:.6rem 1.25rem;border-radius:.4rem;text-decoration:none;font-weight:700;'>Bekijk je boeking →</a></p>
                ";
                app(MailService::class)->sendRaw($booking->customer_email, $subject, $body);
            }

            return redirect()->route('bookings.show', $booking)
                ->with('success', '✅ Terugbrengtijdstip goedgekeurd en bijgewerkt naar ' . $newReturnAt->format('H:i') . ' uur.');
        }

        if ($action === 'reject') {
            $booking->update([
                'proposed_return_time'   => null,
                'proposed_return_status' => 'rejected',
            ]);

            return redirect()->route('bookings.show', $booking)
                ->with('success', 'Terugbrengverzoek afgewezen. Het originele tijdstip blijft staan.');
        }

        return redirect()->route('bookings.show', $booking);
    }

    /** Boekingsbevestiging opnieuw sturen */
    public function resendConfirmation(Booking $booking): RedirectResponse
    {
        if (! $booking->customer_email) {
            return redirect()->route('bookings.show', $booking)
                ->with('error', 'Geen e-mailadres bekend voor deze boeking.');
        }

        app(MailService::class)->send('customer_booking_confirmation', $booking, $booking->customer_email);

        return redirect()->route('bookings.show', $booking)
            ->with('success', 'Boekingsbevestiging opnieuw verstuurd naar ' . $booking->customer_email . '.');
    }

    /** Galerij-klaar mail (opnieuw) versturen */
    public function resendGalleryMail(Booking $booking): RedirectResponse
    {
        abort_if($booking->account_id !== auth()->user()->account_id, 403);

        if (! $booking->customer_email) {
            return redirect()->route('bookings.show', $booking)
                ->with('error', 'Geen e-mailadres bekend voor deze boeking.');
        }
        if (! $booking->gallery_url) {
            return redirect()->route('bookings.show', $booking)
                ->with('error', 'Er is nog geen galerij-link ingevuld voor deze boeking.');
        }

        $booking->load('account');
        $ok = app(MailService::class)->send('customer_gallery_ready', $booking, $booking->customer_email);

        if (! $ok) {
            return redirect()->route('bookings.show', $booking)
                ->with('error', 'Mail kon niet verstuurd worden. Controleer of de template "Fotogalerij gereed" actief is in /mail-templates.');
        }

        return redirect()->route('bookings.show', $booking)
            ->with('success', 'Galerij-mail opnieuw verstuurd naar ' . $booking->customer_email . '.');
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
    public function designModal(Booking $booking): \Illuminate\Http\Response
    {
        return response(view('bookings._design_modal', compact('booking'))->render());
    }

    /**
     * Stel desgewenst een mockup samen van een geüploade strip-afbeelding.
     * Geeft het (mogelijk nieuwe) opslagpad terug; bij een PDF of een fout blijft het origineel.
     */
    private function maybeApplyMockup(string $path, ?string $mime): string
    {
        if (! str_starts_with((string) $mime, 'image/')) {
            return $path;
        }
        try {
            $blob     = app(\App\Services\StripMockupService::class)->render(Storage::disk('public')->path($path));
            $mockPath = 'strip-designs/mockup_' . \Illuminate\Support\Str::random(20) . '.jpg';
            Storage::disk('public')->put($mockPath, $blob);
            return $mockPath;
        } catch (\Throwable $e) {
            \Log::error('Strip-mockup samenstellen mislukt: ' . $e->getMessage());
            return $path;
        }
    }

    public function uploadStripDesign(Request $request, Booking $booking): RedirectResponse
    {
        $request->validate([
            'strip_design_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,pdf', 'max:20480'],
            'strip_design_url'  => ['nullable', 'url'],
            'mockup'            => ['nullable', 'boolean'],
        ]);

        $oudDesignUrl = $booking->strip_design_url;
        $nieuweUrl    = null;
        $filename     = null;

        if ($request->hasFile('strip_design_file')) {
            $file      = $request->file('strip_design_file');
            $filename  = $file->getClientOriginalName();
            $path      = $file->store('strip-designs', 'public');

            // Mockup samenstellen als het vinkje aanstaat (alleen voor afbeeldingen)
            if ($request->boolean('mockup')) {
                $mockPath = $this->maybeApplyMockup($path, $file->getMimeType());
                if ($mockPath !== $path) {
                    $path     = $mockPath;
                    $filename = pathinfo($filename, PATHINFO_FILENAME) . ' (mockup).jpg';
                }
            }

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

    /** Verwijder één afbeelding uit de leverinstructies */
    public function deleteDeliveryImage(Request $request, Booking $booking): RedirectResponse
    {
        abort_if($booking->account_id !== auth()->user()->account_id, 403);

        $path   = (string) $request->input('path');
        $images = $booking->delivery_instructions_images ?? [];

        if (! in_array($path, $images, true)) {
            return back()->with('error', 'Afbeelding niet gevonden.');
        }

        Storage::disk('public')->delete($path);
        $images = array_values(array_filter($images, fn($p) => $p !== $path));
        $booking->update(['delivery_instructions_images' => $images ?: null]);

        return back()->with('success', 'Afbeelding verwijderd.');
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

    /** Personeel inplannen: overzicht alle aankomende boekingen */
    public function staffPlanning(Request $request): View
    {
        $staffMembers = Staff::where('is_active', true)->orderBy('name')->get();

        $bookings = Booking::with(['deliveryStaff', 'pickupStaff'])
            ->whereIn('status', ['confirmed'])
            ->where('event_date', '>=', now()->toDateString())
            ->orderBy('event_date')
            ->orderBy('delivery_at')
            ->get();

        // Aanmeldingen in één query ophalen (geen N+1), gegroepeerd op "booking_id|role"
        $signups = RideSignup::with('staff')
            ->whereIn('booking_id', $bookings->pluck('id'))
            ->get()
            ->groupBy(fn ($s) => $s->booking_id . '|' . $s->role);

        // Publieke bord-URL voor de WhatsApp-groep
        $boardUrl = route('rides.board', auth()->user()->account_id);

        return view('bookings.staff-planning', compact('bookings', 'staffMembers', 'signups', 'boardUrl'));
    }

    /** AJAX: sla bezorger/ophaler op voor één boeking */
    public function assignStaff(Request $request, Booking $booking): JsonResponse
    {
        $data = $request->validate([
            'field' => ['required', 'in:delivery_staff_id,pickup_staff_id'],
            'staff_id' => ['nullable', 'exists:staff,id'],
        ]);

        $openCol = $data['field'] === 'delivery_staff_id' ? 'delivery_open' : 'pickup_open';

        if ($data['staff_id']) {
            // Rollen die bij dit veld horen: delivery_staff_id dekt delivery + handover, pickup_staff_id dekt pickup
            $roles = $data['field'] === 'delivery_staff_id' ? ['delivery', 'handover'] : ['pickup'];

            // Had deze medewerker zich aangemeld voor deze rit? Zo ja → bevestigingsmail.
            // (Bij gewone handmatige planning zonder aanmelding sturen we niets, om spam te voorkomen.)
            $hadSignedUp = RideSignup::withoutGlobalScope(\App\Scopes\AccountScope::class)
                ->where('booking_id', $booking->id)
                ->whereIn('role', $roles)
                ->where('staff_id', $data['staff_id'])
                ->where('response', 'yes')
                ->exists();

            // Toewijzen: zet rit dicht voor aanmelding en ruim aanmeldingen op
            $booking->update([
                $data['field'] => $data['staff_id'],
                $openCol       => false,
            ]);

            RideSignup::withoutGlobalScope(\App\Scopes\AccountScope::class)
                ->where('booking_id', $booking->id)
                ->whereIn('role', $roles)
                ->delete();

            $staff = Staff::find($data['staff_id']);

            if ($hadSignedUp) {
                $this->notifyStaffAssigned($booking, $staff, $data['field']);
            }

            return response()->json(['ok' => true, 'name' => $staff?->name]);
        }

        // Loskoppelen: laat open-vlag + aanmeldingen ongemoeid
        $booking->update([$data['field'] => null]);

        return response()->json(['ok' => true, 'name' => null]);
    }

    /** AJAX: zet één rit open of dicht voor aanmelding door medewerkers */
    public function toggleRideOpen(Request $request, Booking $booking): JsonResponse
    {
        $data = $request->validate([
            'field' => ['required', 'in:delivery_staff_id,pickup_staff_id'],
            'open'  => ['required', 'boolean'],
        ]);

        $openCol = $data['field'] === 'delivery_staff_id' ? 'delivery_open' : 'pickup_open';

        // Een al toegewezen rit kun je niet openzetten
        if ($data['open'] && $booking->{$data['field']}) {
            return response()->json(['ok' => false, 'error' => 'Deze rit is al toegewezen.'], 422);
        }

        $booking->update([$openCol => $data['open']]);

        return response()->json(['ok' => true, 'open' => $data['open']]);
    }

    /** Zet alle nog niet-ingeplande ritten open voor aanmelding en mail de medewerkers */
    public function openAllUnassigned(Request $request): RedirectResponse
    {
        $accountId = auth()->user()->account_id;

        $base = Booking::whereIn('status', ['confirmed'])
            ->where('event_date', '>=', now()->toDateString());

        $opened = (clone $base)->whereNull('delivery_staff_id')->where('delivery_open', false)
            ->update(['delivery_open' => true]);
        $opened += (clone $base)->whereNull('pickup_staff_id')->where('pickup_open', false)
            ->update(['pickup_open' => true]);

        if ($opened > 0) {
            $this->notifyStaffRidesOpened($accountId);
        }

        return redirect()->route('bookings.staff-planning')
            ->with('success', $opened > 0
                ? "$opened rit(ten) opengezet — medewerkers hebben een mail gekregen."
                : 'Er waren geen ritten meer om open te zetten.');
    }

    /** Mail alle actieve medewerkers dat er ritten openstaan */
    private function notifyStaffRidesOpened(int $accountId): void
    {
        try {
            $staffMembers = Staff::withoutGlobalScope(\App\Scopes\AccountScope::class)
                ->where('account_id', $accountId)
                ->where('is_active', true)
                ->whereNotNull('email')
                ->get();

            if ($staffMembers->isEmpty()) return;

            // Tel hoeveel ritten er nu openstaan voor dit account
            $openCount = Booking::withoutGlobalScope(\App\Scopes\AccountScope::class)
                ->where('account_id', $accountId)
                ->whereIn('status', ['confirmed'])
                ->where('event_date', '>=', now()->toDateString())
                ->where(function ($q) {
                    $q->where('delivery_open', true)->orWhere('pickup_open', true);
                })
                ->count();

            $mailService = app(MailService::class);

            foreach ($staffMembers as $staff) {
                $link = route('staff.portal', $staff->public_token) . '?tab=beschikbaar';
                $htmlBody = '
                <div style="font-family:sans-serif;max-width:560px;margin:0 auto;padding:24px;">
                    <h2 style="margin:0 0 8px;font-size:18px;">🚗 Er staan ritten open</h2>
                    <p style="color:#64748b;margin:0 0 20px;">Hoi '.$staff->name.', er staan ritten open waar je je voor kunt aanmelden. Wie het eerst komt... — meld je aan voor de ritten die jij kunt doen.</p>
                    <a href="'.$link.'" style="display:inline-block;background:#7c3aed;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;">Bekijk beschikbare ritten →</a>
                    <p style="color:#94a3b8;margin:18px 0 0;font-size:12px;">Je beheerder bevestigt daarna wie de rit doet.</p>
                </div>';

                try {
                    $mailService->sendRaw($staff->email, '🚗 Er staan ritten open — Flitsmoment', $htmlBody);
                } catch (\Exception $e) {
                    \Log::error('Ritten-open mail fout (' . $staff->email . '): ' . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            \Log::error('notifyStaffRidesOpened fout: ' . $e->getMessage());
        }
    }

    /** Mail de gekozen medewerker dat hij is ingepland voor een rit */
    private function notifyStaffAssigned(Booking $booking, ?Staff $staff, string $field): void
    {
        if (! $staff || ! $staff->email) return;

        try {
            $rolLabel = $field === 'pickup_staff_id'
                ? 'ophalen'
                : ($booking->booking_type === 'to_go' ? 'afgeven' : 'bezorgen');
            $link = route('staff.portal', $staff->public_token);
            $datum = $booking->event_date?->translatedFormat('l j F Y');

            $htmlBody = '
            <div style="font-family:sans-serif;max-width:560px;margin:0 auto;padding:24px;">
                <h2 style="margin:0 0 8px;font-size:18px;">✅ Je bent ingepland</h2>
                <p style="color:#64748b;margin:0 0 20px;">Hoi '.$staff->name.', je bent ingepland om te <strong>'.$rolLabel.'</strong> voor boeking <strong>'.$booking->booking_number.'</strong> ('.$booking->customer_name.')'.($datum ? ' op '.$datum : '').'.</p>
                <a href="'.$link.'" style="display:inline-block;background:#7c3aed;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;">Bekijk je planning →</a>
            </div>';

            app(MailService::class)->sendRaw($staff->email, '✅ Je bent ingepland — ' . $booking->booking_number, $htmlBody);
        } catch (\Exception $e) {
            \Log::error('notifyStaffAssigned mail fout: ' . $e->getMessage());
        }
    }

    /** Planning overzicht: Gantt-timeline per photobooth */
    public function planning(Request $request): View
    {
        $weeks  = max(1, min(16, (int) $request->input('weeks', 2)));
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

        // Haal achtergronden, prop_boxes en extra's op
        $extraAssets = Asset::whereIn('category', ['background', 'prop_box', 'extra'])
            ->where('is_active', true)
            ->where('ignore_stock', false)  // verberg consumables/kosten uit planning
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        // Helper: effectieve Gantt-datums op basis van bezorg/ophaalmoment
        $ganttDates = function (Booking $b): array {
            if ($b->booking_type === 'to_go' && $b->customer_pickup_at) {
                // To Go: klant haalt op → retour
                $start = Carbon::parse($b->customer_pickup_at)->startOfDay();
                $end   = $b->customer_return_at ? Carbon::parse($b->customer_return_at)->startOfDay() : $start->copy();
            } elseif ($b->delivery_at) {
                // Full Service: bezorging → ophalen
                $start = Carbon::parse($b->delivery_at)->startOfDay();
                $end   = $b->pickup_at ? Carbon::parse($b->pickup_at)->startOfDay() : $start->copy();
            } else {
                // Fallback: event_date
                $start = $b->event_date->copy();
                $end   = $b->event_end_date ? $b->event_end_date->copy() : $start->copy();
            }
            return [$start, $end];
        };

        // Helper: tijdsfractie van de dag (0.0 = middernacht, 1.0 = 23:59)
        // Geeft [startFractie, eindFractie] terug voor visuele balkpositionering
        $ganttTimeFracs = function (Booking $b): array {
            if ($b->booking_type === 'to_go' && $b->customer_pickup_at) {
                $dtStart = Carbon::parse($b->customer_pickup_at);
                $dtEnd   = $b->customer_return_at ? Carbon::parse($b->customer_return_at) : null;
            } elseif ($b->delivery_at) {
                $dtStart = Carbon::parse($b->delivery_at);
                $dtEnd   = $b->pickup_at ? Carbon::parse($b->pickup_at) : null;
            } else {
                return [0.0, 1.0]; // geen tijdinfo → volledige dag
            }
            $startFrac = ($dtStart->hour * 60 + $dtStart->minute) / 1440;
            $endFrac   = $dtEnd ? ($dtEnd->hour * 60 + $dtEnd->minute) / 1440 : 1.0;
            // Als eindtijd 00:00 is (middernacht) behandelen als einde van de dag
            if ($dtEnd && $dtEnd->hour === 0 && $dtEnd->minute === 0) $endFrac = 1.0;
            return [$startFrac, $endFrac];
        };

        // Helper: bereken margin-left/right percentages voor een segment
        // Boekingen die extra assets bevatten in dit datumbereik
        $extraBookings = Booking::with(['items' => fn($q) => $q->with('asset')])
            ->whereIn('status', ['confirmed', 'completed'])
            ->where('event_date', '<=', $endDate->toDateString())
            ->whereRaw("COALESCE(event_end_date, event_date) >= ?", [$startDate->toDateString()])
            ->orderBy('event_date')
            ->get()
            ->filter(fn($b) => $b->items->contains(
                fn($i) => in_array($i->asset?->category, ['background', 'prop_box', 'extra'])
            ));

        // Bouw Gantt-rijen per extra asset (zelfde logica als photobooths)
        $extraRows = [];
        foreach ($extraAssets as $asset) {
            $assetBookings = $extraBookings
                ->filter(fn($b) => $b->items->where('asset_id', $asset->id)->isNotEmpty())
                ->sortBy('event_date')
                ->values();

            $numSlots = $asset->ignore_stock ? 1 : min(20, max(1, (int) $asset->stock));

            // Wijs boekingen toe aan slots op basis van conflicten
            $slots = array_fill(0, $numSlots, []);
            foreach ($assetBookings as $booking) {
                [$bookStart, $bookEnd] = $ganttDates($booking);
                // Qty begrensd op numSlots — voorkomt oneindige overflow bij wegwerpartikelen
                $qty = min($numSlots, max(1, (int) ($booking->items->firstWhere('asset_id', $asset->id)?->quantity ?? 1)));

                $assigned = 0;
                for ($s = 0; $s < $numSlots && $assigned < $qty; $s++) {
                    $conflict = false;
                    foreach ($slots[$s] as $existing) {
                        [$exStart, $exEnd] = $ganttDates($existing);
                        if ($bookStart->lte($exEnd) && $bookEnd->gte($exStart)) { $conflict = true; break; }
                    }
                    if (! $conflict) { $slots[$s][] = $booking; $assigned++; }
                }
                // Overflow: plak in de laatste slot (kan nu maximaal $numSlots keer)
                while ($assigned < $qty) { $slots[$numSlots - 1][] = $booking; $assigned++; }
            }

            // Bouw per-dag segmenten per slot (elk dagcel kan meerdere boekingen tonen)
            for ($s = 0; $s < $numSlots; $s++) {
                $slotBookings = collect($slots[$s])->sortBy('event_date')->values();
                $segments     = [];

                foreach ($days as $day) {
                    $dayBookings = [];
                    foreach ($slotBookings as $booking) {
                        [$bookStart, $bookEnd] = $ganttDates($booking);
                        if ($day->lt($bookStart) || $day->gt($bookEnd)) continue;

                        [$startFrac, $endFrac] = $ganttTimeFracs($booking);
                        $isFirstDay = $day->isSameDay($bookStart);
                        $isLastDay  = $day->isSameDay($bookEnd);

                        $startPct = $isFirstDay ? round($startFrac * 100, 2) : 0.0;
                        $endPct   = $isLastDay  ? round($endFrac   * 100, 2) : 100.0;

                        // Skip lege segmenten (bijv. ophaal exact om 00:00)
                        if ($endPct <= $startPct) continue;

                        $dayBookings[] = [
                            'booking'      => $booking,
                            'start_pct'    => $startPct,
                            'end_pct'      => $endPct,
                            'is_first_day' => $isFirstDay,
                            'is_last_day'  => $isLastDay,
                        ];
                    }

                    // Sorteer op start_pct zodat bars naast elkaar passen
                    usort($dayBookings, fn($a, $b) => $a['start_pct'] <=> $b['start_pct']);

                    // Detecteer tijds-overlap tussen boekingen op deze dag
                    $hasConflict = false;
                    for ($i = 1; $i < count($dayBookings); $i++) {
                        if ($dayBookings[$i]['start_pct'] < $dayBookings[$i - 1]['end_pct']) {
                            $hasConflict = true;
                            break;
                        }
                    }

                    $segments[] = [
                        'bookings'     => $dayBookings,
                        'has_conflict' => $hasConflict,
                    ];
                }

                $extraRows[] = [
                    'asset'       => $asset,
                    'label'       => $numSlots > 1 ? $asset->name . ' ' . ($s + 1) : $asset->name,
                    'slot'        => $s + 1,
                    'total_slots' => $numSlots,
                    'category'    => $asset->category,
                    'segments'    => $segments,
                ];
            }
        }

        // Haal alle relevante boekingen op (ook to_go: pickup t/m return datum)
        $bookings = Booking::with(['items' => fn($q) => $q->with('asset')])
            ->whereIn('status', ['confirmed', 'completed'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->where(function ($q2) use ($startDate, $endDate) {
                    // Normale boekingen: event_date bereik
                    $q2->where('event_date', '<=', $endDate->toDateString())
                       ->whereRaw("COALESCE(event_end_date, event_date) >= ?", [$startDate->toDateString()]);
                })->orWhere(function ($q2) use ($startDate, $endDate) {
                    // To Go: ophaalmoment t/m retourdatum
                    $q2->where('booking_type', 'to_go')
                       ->whereNotNull('customer_pickup_at')
                       ->whereRaw("DATE(customer_pickup_at) <= ?", [$endDate->toDateString()])
                       ->whereRaw("DATE(COALESCE(customer_return_at, customer_pickup_at)) >= ?", [$startDate->toDateString()]);
                })->orWhere(function ($q2) use ($startDate, $endDate) {
                    // Full Service: bezorging t/m ophalen (nooit To Go)
                    $q2->where('booking_type', '!=', 'to_go')
                       ->whereNotNull('delivery_at')
                       ->whereRaw("DATE(delivery_at) <= ?", [$endDate->toDateString()])
                       ->whereRaw("DATE(COALESCE(pickup_at, delivery_at)) >= ?", [$startDate->toDateString()]);
                });
            })
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
                [$bookStart, $bookEnd] = $ganttDates($booking);
                $qty       = max(1, (int) ($booking->items->firstWhere('asset_id', $pb->id)?->quantity ?? 1));

                $assignedCount = 0;
                for ($s = 0; $s < $numSlots && $assignedCount < $qty; $s++) {
                    $conflict = false;
                    foreach ($legacySlots[$s] as $existing) {
                        [$exStart, $exEnd] = $ganttDates($existing);
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

                // Bouw per-dag segmenten (elk dagcel kan meerdere boekingen tonen)
                $segments = [];
                foreach ($days as $day) {
                    $dayBookings = [];
                    foreach ($slotBookings as $booking) {
                        [$bookStart, $bookEnd] = $ganttDates($booking);
                        if ($day->lt($bookStart) || $day->gt($bookEnd)) continue;

                        [$startFrac, $endFrac] = $ganttTimeFracs($booking);
                        $isFirstDay = $day->isSameDay($bookStart);
                        $isLastDay  = $day->isSameDay($bookEnd);

                        $startPct = $isFirstDay ? round($startFrac * 100, 2) : 0.0;
                        $endPct   = $isLastDay  ? round($endFrac   * 100, 2) : 100.0;

                        if ($endPct <= $startPct) continue;

                        $dayBookings[] = [
                            'booking'      => $booking,
                            'start_pct'    => $startPct,
                            'end_pct'      => $endPct,
                            'is_first_day' => $isFirstDay,
                            'is_last_day'  => $isLastDay,
                        ];
                    }

                    usort($dayBookings, fn($a, $b) => $a['start_pct'] <=> $b['start_pct']);

                    $hasConflict = false;
                    for ($i = 1; $i < count($dayBookings); $i++) {
                        if ($dayBookings[$i]['start_pct'] < $dayBookings[$i - 1]['end_pct']) {
                            $hasConflict = true;
                            break;
                        }
                    }

                    $segments[] = [
                        'bookings'     => $dayBookings,
                        'has_conflict' => $hasConflict,
                    ];
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

        // ── Team-rijen (medewerkers) ──────────────────────────────────────────
        $allStaff = Staff::where('is_active', true)->orderBy('name')->get();

        // Alle boekingen in dit bereik met staff-koppeling
        $staffBookings = Booking::with(['deliveryStaff', 'pickupStaff'])
            ->whereIn('status', ['confirmed', 'completed'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereNotNull('delivery_staff_id')
                  ->orWhereNotNull('pickup_staff_id');
            })
            ->where('event_date', '<=', $endDate->toDateString())
            ->whereRaw("COALESCE(event_end_date, event_date) >= ?", [$startDate->toDateString()])
            ->orderBy('event_date')
            ->get();

        $teamRows = [];
        foreach ($allStaff as $member) {
            // Bezorger: delivery_at dag (of event_date als fallback)
            $deliveryBookings = $staffBookings->filter(fn($b) => $b->delivery_staff_id === $member->id);
            // Ophaler: pickup_at dag
            $pickupBookings   = $staffBookings->filter(fn($b) => $b->pickup_staff_id === $member->id);

            // Bouw gesegmenteerde rij
            $buildRow = function (string $roleLabel, $roleBookings, string $color) use ($days, $ganttDates, $member): array {
                $totalDays = $days->count();
                $dayIndex  = 0;
                $segments  = [];
                $sorted    = $roleBookings->sortBy('event_date')->values();

                while ($dayIndex < $totalDays) {
                    $currentDay   = $days[$dayIndex];
                    $matchedBook  = null;
                    $colspan      = 1;

                    foreach ($sorted as $b) {
                        // Voor bezorger: gebruik delivery_at dag; voor ophaler: pickup_at dag
                        if ($roleLabel === 'Bezorger' || $roleLabel === 'Afgever') {
                            $segStart = $b->delivery_at
                                ? Carbon::parse($b->delivery_at)->startOfDay()
                                : ($b->booking_type === 'to_go' && $b->customer_pickup_at
                                    ? Carbon::parse($b->customer_pickup_at)->startOfDay()
                                    : $b->event_date->copy());
                            $segEnd = $segStart->copy();
                        } else { // Ophaler
                            $segStart = $b->pickup_at
                                ? Carbon::parse($b->pickup_at)->startOfDay()
                                : $b->event_date->copy();
                            $segEnd = $segStart->copy();
                        }

                        if ($currentDay->between($segStart, $segEnd)) {
                            $matchedBook = $b;
                            $colspan = 1;
                            break;
                        }
                    }

                    if ($matchedBook) {
                        $segments[] = ['booking' => $matchedBook, 'colspan' => $colspan, 'conflict' => false, 'conflict_bookings' => [], 'color' => $color];
                    } else {
                        $segments[] = ['booking' => null, 'colspan' => 1, 'conflict' => false, 'conflict_bookings' => [], 'color' => $color];
                    }

                    $dayIndex += $colspan;
                }
                return $segments;
            };

            $hasDelivery = $deliveryBookings->isNotEmpty();
            $hasPickup   = $pickupBookings->isNotEmpty();

            if (! $hasDelivery && ! $hasPickup) continue;

            // Combineer: één rij per medewerker (bezorger + ophaler gecombineerd per dag)
            $totalDays = $days->count();
            $dayIndex  = 0;
            $segments  = [];
            $allMemberBookings = $staffBookings->filter(fn($b) =>
                $b->delivery_staff_id === $member->id || $b->pickup_staff_id === $member->id
            )->sortBy('event_date')->values();

            while ($dayIndex < $totalDays) {
                $currentDay  = $days[$dayIndex];
                $matchedBook = null;
                $roleLabel   = null;

                foreach ($allMemberBookings as $b) {
                    // Bezorger/afgever: op delivery_at dag
                    if ($b->delivery_staff_id === $member->id) {
                        $segDay = $b->delivery_at
                            ? Carbon::parse($b->delivery_at)->startOfDay()
                            : ($b->booking_type === 'to_go' && $b->customer_pickup_at
                                ? Carbon::parse($b->customer_pickup_at)->startOfDay()
                                : $b->event_date->copy());
                        if ($currentDay->isSameDay($segDay)) {
                            $matchedBook = $b;
                            $roleLabel   = $b->booking_type === 'to_go' ? 'Afgever' : 'Bezorger';
                            break;
                        }
                    }
                    // Ophaler: op pickup_at dag (FS) of customer_return_at dag (To Go)
                    if ($b->pickup_staff_id === $member->id) {
                        if ($b->booking_type === 'to_go') {
                            $segDay = $b->customer_return_at
                                ? Carbon::parse($b->customer_return_at)->startOfDay()
                                : $b->event_date->copy();
                        } else {
                            $segDay = $b->pickup_at
                                ? Carbon::parse($b->pickup_at)->startOfDay()
                                : $b->event_date->copy();
                        }
                        if ($currentDay->isSameDay($segDay)) {
                            $matchedBook = $b;
                            $roleLabel   = $b->booking_type === 'to_go' ? 'Ophaler (To Go)' : 'Ophaler';
                            break;
                        }
                    }
                }

                $segments[] = [
                    'booking'           => $matchedBook,
                    'colspan'           => 1,
                    'conflict'          => false,
                    'conflict_bookings' => [],
                    'role_label'        => $roleLabel,
                ];
                $dayIndex++;
            }

            $teamRows[] = [
                'label'    => $member->name,
                'member'   => $member,
                'segments' => $segments,
            ];
        }

        // Verzamel alle conflicten (tijds-overlap binnen één unit) voor de waarschuwingsbanner
        $conflicts = [];
        foreach ($rows as $row) {
            $seenPairs = [];
            foreach ($row['segments'] as $seg) {
                if (! $seg['has_conflict']) continue;
                $bookings = $seg['bookings'];
                for ($i = 0; $i < count($bookings); $i++) {
                    for ($j = $i + 1; $j < count($bookings); $j++) {
                        $a = $bookings[$i];
                        $b = $bookings[$j];
                        if ($a['start_pct'] < $b['end_pct'] && $a['end_pct'] > $b['start_pct']) {
                            $key = min($a['booking']->id, $b['booking']->id) . '-' . max($a['booking']->id, $b['booking']->id);
                            if (! isset($seenPairs[$key])) {
                                $seenPairs[$key] = true;
                                $conflicts[]    = ['label' => $row['label'], 'a' => $a['booking'], 'b' => $b['booking']];
                            }
                        }
                    }
                }
            }
        }

        return view('bookings.planning', compact(
            'photobooths', 'days', 'rows', 'startDate', 'endDate',
            'weeks', 'offset', 'prevOffset', 'nextOffset',
            'extraAssets', 'extraRows', 'conflicts', 'teamRows'
        ));
    }

    /** iCal feed — token-beveiligd, geen login nodig */
    public function ical(string $token): \Illuminate\Http\Response
    {
        // Zoek account op basis van HMAC-token (deterministisch, geen DB-kolom nodig)
        $account = \App\Models\Account::all()->first(function ($a) use ($token) {
            return hash_equals(
                hash_hmac('sha256', (string) $a->id, config('app.key')),
                $token
            );
        });

        abort_if(! $account, 404);

        // Boekingen: 3 maanden terug t/m 2 jaar vooruit
        $bookings = Booking::with(['deliveryStaff', 'pickupStaff'])
            ->where('account_id', $account->id)
            ->whereIn('status', ['confirmed', 'completed'])
            ->where('event_date', '>=', now()->subMonths(3)->toDateString())
            ->where('event_date', '<=', now()->addYears(2)->toDateString())
            ->orderBy('event_date')
            ->get();

        // Helper: eerste letter van medewerker of 🔴 als niemand ingepland
        $staffPrefix = function ($staff): string {
            if (! $staff) return '🔴';
            return mb_strtoupper(mb_substr($staff->name, 0, 1));
        };

        // Hulpfunctie: naive datetime (opgeslagen als Amsterdamse lokale tijd) → UTC iCal string
        // Carbon::parse() accepteert flexibel formaat en respecteert de opgegeven timezone
        $utc = function (Carbon $dt): string {
            return Carbon::parse($dt->format('Y-m-d H:i:s'), 'Europe/Amsterdam')
                ->utc()->format('Ymd\THis\Z');
        };

        $now = Carbon::now()->utc()->format('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Flitsmoment CRM//NL',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->icalEscape($account->name . ' — Boekingen'),
            'X-WR-TIMEZONE:Europe/Amsterdam',
            'X-WR-CALDESC:Boekingen en logistieke momenten vanuit het Flitsmoment CRM',
        ];

        foreach ($bookings as $booking) {
            $bn   = $booking->booking_number;
            $name = $booking->customer_name;
            $addr = $booking->event_address ? ($booking->event_address . ', ' . $booking->event_city) : $booking->event_city;
            $typeLabel = $booking->booking_type === 'to_go' ? 'To Go' : 'Full Service';

            // ── 1. Event ──────────────────────────────────────────────
            $eventDateStr = $booking->event_date->format('Y-m-d');
            $endDateStr   = ($booking->is_multi_day && $booking->event_end_date)
                ? $booking->event_end_date->format('Y-m-d')
                : $eventDateStr;

            if ($booking->event_start_time && $booking->event_end_time) {
                $startCarbon = Carbon::parse($eventDateStr . ' ' . $booking->event_start_time, 'Europe/Amsterdam');
                $endCarbon   = Carbon::parse($endDateStr   . ' ' . $booking->event_end_time,   'Europe/Amsterdam');

                // Eindtijd vóór starttijd → event loopt door naar de volgende dag (bijv. 19:00–01:00)
                if ($endCarbon->lte($startCarbon)) {
                    $endCarbon->addDay();
                }

                $startProp = 'DTSTART:' . $startCarbon->utc()->format('Ymd\THis\Z');
                $endProp   = 'DTEND:'   . $endCarbon->utc()->format('Ymd\THis\Z');
            } else {
                // Geen tijden: hele-dag event
                $startProp = 'DTSTART;VALUE=DATE:' . str_replace('-', '', $eventDateStr);
                $endProp   = 'DTEND;VALUE=DATE:' . Carbon::parse($endDateStr)->addDay()->format('Ymd');
            }

            $desc = "Boeking: {$bn}\\nType: {$typeLabel}";
            if ($addr) $desc .= "\\nLocatie: " . $this->icalEscape($addr);

            $lines = array_merge($lines, [
                'BEGIN:VEVENT',
                'UID:booking-' . $booking->id . '-event@crm.flitsmoment.nl',
                'DTSTAMP:' . $now,
                $startProp,
                $endProp,
                'SUMMARY:' . $this->icalEscape("🎉 {$name}"),
                'DESCRIPTION:' . $desc,
                'END:VEVENT',
            ]);

            // ── 2. Bezorging (full_service) ───────────────────────────
            if ($booking->booking_type === 'full_service' && $booking->delivery_at) {
                $dStart  = $utc($booking->delivery_at);
                $dEnd    = $utc($booking->delivery_at->copy()->addHour());
                $dPrefix = $staffPrefix($booking->deliveryStaff);
                $lines = array_merge($lines, [
                    'BEGIN:VEVENT',
                    'UID:booking-' . $booking->id . '-delivery@crm.flitsmoment.nl',
                    'DTSTAMP:' . $now,
                    'DTSTART:' . $dStart,
                    'DTEND:' . $dEnd,
                    'SUMMARY:' . $this->icalEscape("🚚 {$dPrefix} | Bezorging: {$name}"),
                    'DESCRIPTION:' . $this->icalEscape("Boeking: {$bn}\\nNaar: " . ($addr ?: '—')),
                    'END:VEVENT',
                ]);
            }

            // ── 3. Ophaling (full_service) ────────────────────────────
            if ($booking->booking_type === 'full_service' && $booking->pickup_at) {
                $pStart  = $utc($booking->pickup_at);
                $pEnd    = $utc($booking->pickup_at->copy()->addHour());
                $pPrefix = $staffPrefix($booking->pickupStaff);
                $lines = array_merge($lines, [
                    'BEGIN:VEVENT',
                    'UID:booking-' . $booking->id . '-pickup@crm.flitsmoment.nl',
                    'DTSTAMP:' . $now,
                    'DTSTART:' . $pStart,
                    'DTEND:' . $pEnd,
                    'SUMMARY:' . $this->icalEscape("↩️ {$pPrefix} | Ophaling: {$name}"),
                    'DESCRIPTION:' . $this->icalEscape("Boeking: {$bn}\\nVan: " . ($addr ?: '—')),
                    'END:VEVENT',
                ]);
            }

            // ── 4. Klant haalt op (to_go) ─────────────────────────────
            if ($booking->booking_type === 'to_go' && $booking->customer_pickup_at) {
                $cpStart  = $utc($booking->customer_pickup_at);
                $cpEnd    = $utc($booking->customer_pickup_at->copy()->addHour());
                $cpPrefix = $staffPrefix($booking->deliveryStaff);
                $lines = array_merge($lines, [
                    'BEGIN:VEVENT',
                    'UID:booking-' . $booking->id . '-customer-pickup@crm.flitsmoment.nl',
                    'DTSTAMP:' . $now,
                    'DTSTART:' . $cpStart,
                    'DTEND:' . $cpEnd,
                    'SUMMARY:' . $this->icalEscape("📦 {$cpPrefix} | Afhalen: {$name}"),
                    'DESCRIPTION:' . $this->icalEscape("Boeking: {$bn}"),
                    'END:VEVENT',
                ]);
            }

            // ── 5. Klant brengt terug (to_go) ────────────────────────
            if ($booking->booking_type === 'to_go' && $booking->customer_return_at) {
                $crStart  = $utc($booking->customer_return_at);
                $crEnd    = $utc($booking->customer_return_at->copy()->addHour());
                $crPrefix = $staffPrefix($booking->pickupStaff);
                $lines = array_merge($lines, [
                    'BEGIN:VEVENT',
                    'UID:booking-' . $booking->id . '-customer-return@crm.flitsmoment.nl',
                    'DTSTAMP:' . $now,
                    'DTSTART:' . $crStart,
                    'DTEND:' . $crEnd,
                    'SUMMARY:' . $this->icalEscape("↩️ {$crPrefix} | Retour: {$name}"),
                    'DESCRIPTION:' . $this->icalEscape("Boeking: {$bn}"),
                    'END:VEVENT',
                ]);
            }
        }

        $lines[] = 'END:VCALENDAR';
        $content  = implode("\r\n", $lines) . "\r\n";

        return response($content, 200, [
            'Content-Type'        => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="flitsmoment.ics"',
            'Cache-Control'       => 'no-cache, must-revalidate',
        ]);
    }

    /** Escape tekst voor iCal SUMMARY/DESCRIPTION */
    private function icalEscape(string $text): string
    {
        return str_replace(
            ['\\',   ';',   ',',   "\n"],
            ['\\\\', '\\;', '\\,', '\\n'],
            $text
        );
    }

    public function destroy(Booking $booking): RedirectResponse
    {
        abort_if($booking->account_id !== auth()->user()->account_id, 403);
        $booking->delete();
        return redirect()->route('bookings.index')->with('success', 'Boeking verwijderd.');
    }

    /** AJAX: geef bezette en bijna-bezette units per photobooth-asset voor de opgegeven datums */
    public function unitAvailability(Request $request): JsonResponse
    {
        // Bepaal datetime-bereik van de nieuwe boeking
        $bookingType = $request->input('booking_type');
        if ($bookingType === 'to_go' && $request->input('customer_pickup_at')) {
            $newStart   = Carbon::parse($request->input('customer_pickup_at'));
            $newEnd     = $request->input('customer_return_at') ? Carbon::parse($request->input('customer_return_at')) : null;
            $checkStart = $newStart->toDateString();
            $checkEnd   = $newEnd ? $newEnd->toDateString() : $checkStart;
        } elseif ($request->input('delivery_at')) {
            $newStart   = Carbon::parse($request->input('delivery_at'));
            $newEnd     = $request->input('pickup_at') ? Carbon::parse($request->input('pickup_at')) : null;
            $checkStart = $newStart->toDateString();
            $checkEnd   = $newEnd ? $newEnd->toDateString() : $checkStart;
        } else {
            $newStart   = $request->input('event_date') ? Carbon::parse($request->input('event_date'))->startOfDay() : null;
            $newEnd     = $request->input('event_end_date') ? Carbon::parse($request->input('event_end_date'))->endOfDay() : null;
            $checkStart = $request->input('event_date');
            $checkEnd   = $request->input('event_end_date') ?: $checkStart;
        }

        $excludeId = $request->input('exclude_booking_id');

        if (! $checkStart) {
            return response()->json([]);
        }

        // Dutch month abbreviations for warning messages
        $dutchM = ['', 'jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
        $fmtDt  = fn(Carbon $dt) => $dt->format('j') . ' ' . $dutchM[(int)$dt->format('n')] . ' ' . $dt->format('H:i');

        // Classificeer een bestaande boeking: 'conflict', 'warning' (binnen 24u) of 'ok'
        $classify = function (Booking $b) use ($newStart, $newEnd, $fmtDt): array {
            [$exStart, $exEnd] = $this->bookingDatetimeRange($b);
            $ns = $newStart ?? $exStart->copy()->startOfDay();
            $ne = $newEnd   ?? $ns->copy()->endOfDay();

            // Echte datetime overlap → hard conflict
            if ($ns->lt($exEnd) && $ne->gt($exStart)) {
                return ['status' => 'conflict', 'message' => ''];
            }

            // Geen overlap — check kloof in uren
            $gap = PHP_INT_MAX;
            if ($exEnd->lte($ns)) {
                $gap = min($gap, $exEnd->diffInMinutes($ns) / 60.0);
            }
            if ($ne->lte($exStart)) {
                $gap = min($gap, $ne->diffInMinutes($exStart) / 60.0);
            }

            if ($gap < 24) {
                $gapFmt = $gap < 1 ? round($gap * 60) . ' min' : round($gap, 1) . ' uur';
                $msg    = $exEnd->lte($ns)
                    ? "Wordt opgehaald om {$fmtDt($exEnd)} ({$b->booking_number} — {$b->customer_name}). Jouw bezorging start om {$fmtDt($ns)}. Slechts {$gapFmt} ertussen."
                    : "Jouw ophaling is om {$fmtDt($ne)}. Bezorging {$b->booking_number} ({$b->customer_name}) start om {$fmtDt($exStart)}. Slechts {$gapFmt} ertussen.";
                return ['status' => 'warning', 'message' => $msg];
            }

            return ['status' => 'ok', 'message' => ''];
        };

        $photobooths = Asset::where('category', 'photobooth')->where('is_active', true)->get();
        $output      = [];

        foreach ($photobooths as $pb) {
            $overlapScope = function ($q) use ($checkStart, $checkEnd, $excludeId) {
                $q->whereIn('status', ['confirmed', 'completed'])
                  ->whereRaw("
                    (CASE
                        WHEN booking_type = 'to_go' AND customer_pickup_at IS NOT NULL THEN DATE(customer_pickup_at)
                        WHEN delivery_at IS NOT NULL THEN DATE(delivery_at)
                        ELSE event_date
                    END) <= ?
                    AND
                    (CASE
                        WHEN booking_type = 'to_go' AND customer_pickup_at IS NOT NULL THEN DATE(COALESCE(customer_return_at, customer_pickup_at))
                        WHEN delivery_at IS NOT NULL THEN DATE(COALESCE(pickup_at, delivery_at))
                        ELSE COALESCE(event_end_date, event_date)
                    END) >= ?
                  ", [$checkEnd, $checkStart]);
                if ($excludeId) {
                    $q->where('id', '!=', $excludeId);
                }
            };

            $specificItems = BookingItem::where('asset_id', $pb->id)
                ->whereNotNull('unit_number')
                ->whereHas('booking', $overlapScope)
                ->with('booking')
                ->get();

            $legacyItems = BookingItem::where('asset_id', $pb->id)
                ->whereNull('unit_number')
                ->whereHas('booking', $overlapScope)
                ->with('booking')
                ->get();

            $bookedUnits  = [];
            $warningUnits = [];
            $warningInfo  = [];

            foreach ($specificItems as $item) {
                $unit = (int) $item->unit_number;
                $cls  = $classify($item->booking);
                if ($cls['status'] === 'conflict') {
                    $bookedUnits[] = $unit;
                } elseif ($cls['status'] === 'warning') {
                    $warningUnits[]     = $unit;
                    $warningInfo[$unit] = ['message' => $cls['message']];
                }
            }

            // Legacy items: tel conflicten en warnings, wijs auto toe aan eerste vrije slots
            $legacyConflict = 0;
            $legacyWarning  = 0;
            $legacyWarnMsg  = '';
            foreach ($legacyItems as $item) {
                $cls = $classify($item->booking);
                if ($cls['status'] === 'conflict') {
                    $legacyConflict += max(1, (int) ($item->quantity ?? 1));
                } elseif ($cls['status'] === 'warning') {
                    $legacyWarning += max(1, (int) ($item->quantity ?? 1));
                    if (! $legacyWarnMsg) $legacyWarnMsg = $cls['message'];
                }
            }
            for ($u = 1; $u <= $pb->stock && $legacyConflict > 0; $u++) {
                if (! in_array($u, $bookedUnits)) { $bookedUnits[] = $u; $legacyConflict--; }
            }
            for ($u = 1; $u <= $pb->stock && $legacyWarning > 0; $u++) {
                if (! in_array($u, $bookedUnits) && ! in_array($u, $warningUnits)) {
                    $warningUnits[]     = $u;
                    $warningInfo[$u]    = ['message' => $legacyWarnMsg];
                    $legacyWarning--;
                }
            }

            $output[$pb->id] = [
                'booked'      => array_values(array_unique($bookedUnits)),
                'warning'     => array_values(array_diff(array_unique($warningUnits), $bookedUnits)),
                'warningInfo' => $warningInfo,
            ];
        }

        return response()->json($output);
    }

    /** Geeft de effectieve datetime-range van een boeking terug als [start, end] Carbon-paar */
    private function bookingDatetimeRange(Booking $booking): array
    {
        if ($booking->booking_type === 'to_go' && $booking->customer_pickup_at) {
            $start = Carbon::parse($booking->customer_pickup_at);
            $end   = $booking->customer_return_at
                ? Carbon::parse($booking->customer_return_at)
                : $start->copy()->endOfDay();
        } elseif ($booking->delivery_at) {
            $start = Carbon::parse($booking->delivery_at);
            $end   = $booking->pickup_at
                ? Carbon::parse($booking->pickup_at)
                : $start->copy()->endOfDay();
        } else {
            $start = Carbon::parse($booking->event_date)->startOfDay();
            $end   = Carbon::parse($booking->event_end_date ?? $booking->event_date)->endOfDay();
        }
        return [$start, $end];
    }

    /** Controleer of photobooth units beschikbaar zijn voor de gegeven datums (datetime-gebaseerd) */
    private function checkPhotboothAvailability(
        array $assetsInput,
        string $eventDate,
        ?string $eventEndDate,
        ?int $excludeBookingId = null,
        ?string $bookingType    = null,
        ?string $deliveryAt     = null,
        ?string $pickupAt       = null,
        ?string $customerPickupAt  = null,
        ?string $customerReturnAt  = null,
    ): array {
        $errors = [];

        // Bepaal het datetime-bereik van de nieuwe boeking
        if ($bookingType === 'to_go' && $customerPickupAt) {
            $newStart = Carbon::parse($customerPickupAt);
            $newEnd   = $customerReturnAt ? Carbon::parse($customerReturnAt) : $newStart->copy()->endOfDay();
        } elseif ($deliveryAt) {
            $newStart = Carbon::parse($deliveryAt);
            $newEnd   = $pickupAt ? Carbon::parse($pickupAt) : $newStart->copy()->endOfDay();
        } else {
            $newStart = Carbon::parse($eventDate)->startOfDay();
            $newEnd   = Carbon::parse($eventEndDate ?? $eventDate)->endOfDay();
        }

        $checkStart = $newStart->toDateString();
        $checkEnd   = $newEnd->toDateString();

        foreach ($assetsInput as $assetId => $data) {
            $asset = Asset::find($assetId);
            if (! $asset || $asset->category !== 'photobooth') continue;

            $selectedUnits = array_values(array_filter(array_map('intval', $data['units'] ?? [])));
            if (empty($selectedUnits)) continue;

            foreach ($selectedUnits as $unitNumber) {
                $conflictExists = BookingItem::where('asset_id', $assetId)
                    ->where('unit_number', $unitNumber)
                    ->whereHas('booking', function ($q) use ($checkStart, $checkEnd, $excludeBookingId) {
                        $q->where('status', '!=', 'cancelled');
                        if ($excludeBookingId) {
                            $q->where('id', '!=', $excludeBookingId);
                        }
                        $q->whereRaw("
                            (CASE
                                WHEN booking_type = 'to_go' AND customer_pickup_at IS NOT NULL THEN DATE(customer_pickup_at)
                                WHEN delivery_at IS NOT NULL THEN DATE(delivery_at)
                                ELSE event_date
                            END) <= ?
                            AND
                            (CASE
                                WHEN booking_type = 'to_go' AND customer_pickup_at IS NOT NULL THEN DATE(COALESCE(customer_return_at, customer_pickup_at))
                                WHEN delivery_at IS NOT NULL THEN DATE(COALESCE(pickup_at, delivery_at))
                                ELSE COALESCE(event_end_date, event_date)
                            END) >= ?
                        ", [$checkEnd, $checkStart]);
                    })
                    ->with('booking')
                    ->get()
                    ->contains(function ($item) use ($newStart, $newEnd) {
                        [$exStart, $exEnd] = $this->bookingDatetimeRange($item->booking);
                        return $newStart->lt($exEnd) && $newEnd->gt($exStart);
                    });

                if ($conflictExists) {
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
