<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\LeadActivity;
use App\Models\LeadStatus;
use App\Models\Offer;
use App\Scopes\AccountScope;
use App\Services\MailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OfferPublicController extends Controller
{
    public function show(string $token): View
    {
        $offer = $this->findOffer($token);

        // Concept-offertes zijn niet publiek toegankelijk
        if ($offer->status === 'draft') {
            abort(404);
        }

        return view('portal.offer', compact('offer'));
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $offer = $this->findOffer($token);

        if (! $offer->isAcceptable()) {
            return back()->with('error', 'Deze offerte kan niet meer worden geaccepteerd.');
        }

        $request->validate([
            'terms_accepted' => ['required', 'accepted'],
        ], [
            'terms_accepted.required' => 'U moet akkoord gaan met de voorwaarden.',
            'terms_accepted.accepted' => 'U moet akkoord gaan met de voorwaarden.',
        ]);

        $booking = DB::transaction(function () use ($offer) {
            $lead = $offer->lead;

            // 1. Boeking aanmaken
            $booking = Booking::withoutGlobalScope(AccountScope::class)->create([
                'account_id'     => $offer->account_id,
                'lead_id'        => $lead->id,
                'offer_id'       => $offer->id,
                'customer_name'  => $lead->name,
                'customer_email' => $lead->email,
                'customer_phone' => $lead->phone,
                'event_date'     => $lead->event_date,
                'event_location' => $lead->event_location,
                'event_address'  => $lead->event_address,
                'event_postcode' => $lead->event_postcode,
                'event_city'     => $lead->event_city,
                'total_price'    => $offer->total,
                'base_price'     => $offer->subtotal,
                'status'         => 'confirmed',
                'payment_status' => 'unpaid',
                'portal_enabled' => true,
                'confirmed_at'   => now(),
            ]);

            // 2. BookingItems aanmaken uit line_items
            foreach ($offer->line_items ?? [] as $item) {
                BookingItem::create([
                    'booking_id'     => $booking->id,
                    'name_snapshot'  => $item['description'] ?? '',
                    'price_snapshot' => $item['unit_price'] ?? 0,
                    'quantity'       => $item['quantity'] ?? 1,
                ]);
            }

            // 3. Offerte markeren als geaccepteerd
            $offer->update([
                'status'      => 'accepted',
                'accepted_at' => now(),
            ]);

            // 4. Lead archiveren als 'won'
            $wonStatus = LeadStatus::withoutGlobalScope(AccountScope::class)
                ->where('account_id', $offer->account_id)
                ->where('name', 'won')
                ->first();

            if ($wonStatus) {
                $lead->status_id = $wonStatus->id;
            }
            $lead->archiveer('won');

            // 5. LeadActivity
            LeadActivity::withoutGlobalScope(AccountScope::class)->create([
                'account_id'    => $offer->account_id,
                'lead_id'       => $lead->id,
                'user_id'       => null,
                'activity_type' => 'offer_accepted',
                'title'         => 'Offerte geaccepteerd',
                'description'   => "Offerte {$offer->offer_number} geaccepteerd door klant. Boeking {$booking->booking_number} aangemaakt.",
            ]);

            // 6. Mails sturen
            $mail = app(MailService::class);
            $offer->loadMissing('account');

            if ($lead->email) {
                $mail->sendForOffer('offer_accepted_customer', $offer, $lead->email);
            }
            if ($offer->account->email) {
                $mail->sendForOffer('offer_accepted_admin', $offer, $offer->account->email);
            }

            return $booking;
        });

        return redirect()
            ->route('portal.show', $booking->public_token)
            ->with('success', 'Uw offerte is geaccepteerd! Uw boeking is bevestigd.');
    }

    private function findOffer(string $token): Offer
    {
        return Offer::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)
            ->with(['lead', 'account'])
            ->firstOrFail();
    }
}
