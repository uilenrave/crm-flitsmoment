<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Offer;
use App\Services\MailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OfferController extends Controller
{
    public function create(Request $request): View
    {
        $lead = Lead::with('assets')->findOrFail($request->query('lead_id'));

        // Pre-build line items uit lead assets
        $lineItems = $lead->assets->map(fn ($asset) => [
            'description' => $asset->name,
            'quantity'     => $asset->pivot->quantity ?? 1,
            'unit_price'   => (float) $asset->price,
            'total'        => (float) $asset->price * ($asset->pivot->quantity ?? 1),
        ])->values()->toArray();

        $subtotal = array_sum(array_column($lineItems, 'total'));
        $defaultTerms = config('offers.default_terms', '');
        $expiryDays = config('offers.expiry_days', 14);

        return view('offers.create', compact('lead', 'lineItems', 'subtotal', 'defaultTerms', 'expiryDays'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'lead_id'      => ['required', 'exists:leads,id'],
            'title'        => ['required', 'string', 'max:200'],
            'line_items'   => ['required', 'json'],
            'subtotal'     => ['required', 'numeric', 'min:0'],
            'discount'     => ['nullable', 'numeric', 'min:0'],
            'total'        => ['required', 'numeric', 'min:0'],
            'terms_text'   => ['nullable', 'string'],
            'expires_at'   => ['nullable', 'date', 'after:today'],
        ]);

        $data['line_items'] = json_decode($data['line_items'], true);
        $data['discount'] = $data['discount'] ?? 0;
        $data['status'] = 'sent';
        $data['sent_at'] = now();
        if (empty($data['expires_at'])) {
            $data['expires_at'] = now()->addDays(config('offers.expiry_days', 14));
        }

        $offer = Offer::create($data);

        LeadActivity::create([
            'lead_id'       => $data['lead_id'],
            'user_id'       => auth()->id(),
            'activity_type' => 'offer_created',
            'title'         => 'Offerte aangemaakt',
            'description'   => "Offerte {$offer->offer_number} aangemaakt ({$offer->formatted_total}).",
        ]);

        return redirect()->route('offers.show', $offer)->with('success', 'Offerte aangemaakt. De link is direct actief.');
    }

    public function show(Offer $offer): View
    {
        $offer->load(['lead', 'account', 'booking']);

        return view('offers.show', compact('offer'));
    }

    public function edit(Offer $offer): View
    {
        if (! in_array($offer->status, ['draft', 'sent'])) {
            return redirect()->route('offers.show', $offer)->with('error', 'Deze offerte kan niet meer bewerkt worden.');
        }

        $offer->load('lead');
        $defaultTerms = config('offers.default_terms', '');

        return view('offers.edit', compact('offer', 'defaultTerms'));
    }

    public function update(Request $request, Offer $offer): RedirectResponse
    {
        if (! in_array($offer->status, ['draft', 'sent'])) {
            return redirect()->route('offers.show', $offer)->with('error', 'Deze offerte kan niet meer bewerkt worden.');
        }

        $data = $request->validate([
            'title'        => ['required', 'string', 'max:200'],
            'line_items'   => ['required', 'json'],
            'subtotal'     => ['required', 'numeric', 'min:0'],
            'discount'     => ['nullable', 'numeric', 'min:0'],
            'total'        => ['required', 'numeric', 'min:0'],
            'terms_text'   => ['nullable', 'string'],
            'expires_at'   => ['nullable', 'date'],
        ]);

        $data['line_items'] = json_decode($data['line_items'], true);
        $data['discount'] = $data['discount'] ?? 0;

        $offer->update($data);

        return redirect()->route('offers.show', $offer)->with('success', 'Offerte bijgewerkt.');
    }

    public function send(Offer $offer): RedirectResponse
    {
        if (in_array($offer->status, ['accepted', 'declined'])) {
            return redirect()->route('offers.show', $offer)->with('error', 'Deze offerte kan niet meer worden verstuurd.');
        }

        $offer->load(['lead', 'account']);

        if (! $offer->lead?->email) {
            return redirect()->route('offers.show', $offer)->with('error', 'De lead heeft geen e-mailadres.');
        }

        $mail = app(MailService::class);
        $mail->sendForOffer('offer_sent', $offer, $offer->lead->email);

        LeadActivity::create([
            'lead_id'       => $offer->lead_id,
            'user_id'       => auth()->id(),
            'activity_type' => 'offer_sent',
            'title'         => 'Offerte verstuurd',
            'description'   => "Offerte {$offer->offer_number} verstuurd naar {$offer->lead->email}.",
        ]);

        return redirect()->route('offers.show', $offer)->with('success', 'Offerte verstuurd naar ' . $offer->lead->email);
    }

    public function destroy(Offer $offer): RedirectResponse
    {
        abort_if($offer->account_id !== auth()->user()->account_id, 403);
        if (in_array($offer->status, ['accepted'])) {
            return redirect()->route('offers.show', $offer)->with('error', 'Geaccepteerde offertes kunnen niet worden verwijderd.');
        }

        $leadId = $offer->lead_id;
        $offer->delete();

        return redirect()->route('leads.show', $leadId)->with('success', 'Offerte verwijderd.');
    }
}
