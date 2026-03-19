<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TravelCostController extends Controller
{
    /**
     * Berekent reiskosten op basis van bestemming.
     * Gebruikt Google Maps Distance Matrix API + account-instellingen.
     *
     * GET /reiskosten?destination=Adres+Utrecht
     */
    public function calculate(Request $request): JsonResponse
    {
        $destination = trim($request->query('destination', ''));

        if (empty($destination)) {
            return response()->json(['error' => 'Geen bestemming opgegeven.'], 400);
        }

        /** @var \App\Models\User $user */
        $user    = auth()->user();
        $account = $user->account;

        if (empty($account->origin_address)) {
            return response()->json(['error' => 'Geen vertrekadres ingesteld. Stel dit in via Account instellingen.'], 422);
        }

        $apiKey = config('services.google.maps_key');

        if (empty($apiKey)) {
            return response()->json(['error' => 'Google Maps API key niet geconfigureerd.'], 500);
        }

        // Roep Distance Matrix API aan
        $url = 'https://maps.googleapis.com/maps/api/distancematrix/json?' . http_build_query([
            'origins'      => $account->origin_address,
            'destinations' => $destination,
            'mode'         => 'driving',
            'units'        => 'metric',
            'language'     => 'nl',
            'key'          => $apiKey,
        ]);

        $response = @file_get_contents($url);

        if ($response === false) {
            return response()->json(['error' => 'Kon Google Maps niet bereiken.'], 503);
        }

        $data = json_decode($response, true);

        if (($data['status'] ?? '') !== 'OK') {
            return response()->json(['error' => 'Google Maps API fout: ' . ($data['status'] ?? 'onbekend')], 502);
        }

        $element = $data['rows'][0]['elements'][0] ?? null;

        if (!$element || ($element['status'] ?? '') !== 'OK') {
            return response()->json(['error' => 'Adres niet gevonden of route niet berekenbaar.'], 422);
        }

        $distanceMeters = $element['distance']['value'];
        $distanceKm     = round($distanceMeters / 1000, 1);
        $durationText   = $element['duration']['text'] ?? null;
        $cost           = $account->calculateTravelCost($distanceKm);

        return response()->json([
            'distance_km'   => $distanceKm,
            'duration'      => $durationText,
            'cost'          => $cost,
            'free_km'       => $account->travel_free_km ?? 20,
            'rate_per_km'   => (float) ($account->travel_cost_per_km ?? 4.00),
            'origin'        => $account->origin_address,
        ]);
    }
}
