<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\View\View;

class RidesBoardController extends Controller
{
    /**
     * Publiek, read-only bord met open ritten — bedoeld om in de WhatsApp-groep te delen.
     * Toont alleen plaats + datum (geen klantgegevens). Aanmelden gebeurt via de persoonlijke
     * medewerker-link, niet hier.
     */
    public function show(Account $account): View
    {
        $now = Carbon::now()->startOfDay();

        $openBookings = Booking::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->whereIn('status', ['confirmed'])
            ->where('event_date', '>=', $now->toDateString())
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('delivery_open', true)->whereNull('delivery_staff_id');
                })->orWhere(function ($q2) {
                    $q2->where('pickup_open', true)->whereNull('pickup_staff_id');
                });
            })
            ->orderBy('event_date')
            ->get();

        $ritten = collect();
        foreach ($openBookings as $b) {
            if ($b->delivery_open && ! $b->delivery_staff_id) {
                $role = $b->booking_type === 'to_go' ? 'handover' : 'delivery';
                $datum = $b->booking_type === 'to_go'
                    ? ($b->customer_pickup_at ? Carbon::parse($b->customer_pickup_at) : $b->event_date->copy()->startOfDay())
                    : ($b->delivery_at ? Carbon::parse($b->delivery_at) : $b->event_date->copy()->startOfDay());
                $ritten->push(['type' => $role, 'datum' => $datum, 'plaats' => $b->event_city]);
            }
            if ($b->pickup_open && ! $b->pickup_staff_id) {
                $datum = $b->booking_type === 'to_go'
                    ? ($b->customer_return_at ? Carbon::parse($b->customer_return_at) : $b->event_date->copy()->startOfDay())
                    : ($b->pickup_at ? Carbon::parse($b->pickup_at) : $b->event_date->copy()->startOfDay());
                $ritten->push(['type' => 'pickup', 'datum' => $datum, 'plaats' => $b->event_city]);
            }
        }
        $ritten = $ritten->sortBy('datum')->values();

        return view('rides.board', compact('account', 'ritten'));
    }
}
