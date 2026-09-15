<?php

namespace App\Services;

use App\Models\Shift;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShiftRouteService
{
    /**
     * Bereken de route + tijdlijn van een shift.
     * Reistijden komen van Google Distance Matrix (per etappe), gecached op de adres-volgorde
     * zodat alleen wijzigingen aan de route/volgorde Google opnieuw aanroepen. Zonder key of bij
     * een fout wordt reistijd 0 gebruikt (travel_ok = false) zodat de planner blijft werken.
     */
    public function compute(Shift $shift): array
    {
        $shift->loadMissing(['jobs.booking', 'account']);
        $jobs = $shift->jobs;

        $start = $shift->startLocation();
        $end   = $shift->end_address ?: null;

        // Adres-volgorde: start → klussen → (optionele eindlocatie)
        $addresses = [$start];
        foreach ($jobs as $job) {
            $job->setRelation('shift', $shift);
            $addresses[] = $job->locationAddress();
        }
        if ($end) {
            $addresses[] = $end;
        }

        $base = [
            'start_at'      => $shift->start_at?->toIso8601String(),
            'end_at'        => null,
            'total_minutes' => null,
            'travel_ok'     => true,
            'jobs'          => [],
            'return_leg'    => null,
            'computed_at'   => now()->toIso8601String(),
        ];

        if ($jobs->isEmpty()) {
            return array_merge($base, ['note' => 'Nog geen klussen in deze shift.']);
        }

        // Reistijden per etappe (met cache op adres-volgorde) — hangt NIET van de starttijd af.
        [$legs, $travelOk] = $this->legsFor($addresses, $shift->route_cache ?? []);

        // Relatieve tijdlijn t.o.v. start = 0 sec: aankomst/vertrek-offset per klus.
        $offset  = 0;                 // seconden sinds start
        $arriveOffsets = [];
        foreach ($jobs as $i => $job) {
            $offset += (int) ($legs[$i]['seconds'] ?? 0);   // reistijd naar deze klus
            $arriveOffsets[$i] = $offset;
            $offset += (int) $job->duration_minutes * 60;   // werktijd op deze klus
        }
        $departOffsetLast = $offset;
        if ($end) {
            $departOffsetLast += (int) ($legs[count($jobs)]['seconds'] ?? 0);   // terugrit
        }

        // Effectieve starttijd bepalen: handmatig vastgezet, anders terugrekenen vanaf de
        // eerste klus met een vaste tijd (bv. opbouw om 16:00, 20 min rijden → start 15:40).
        $start = null;
        $autoStart = false;
        if ($shift->start_manual && $shift->start_at) {
            $start = $shift->start_at->copy();
        } else {
            foreach ($jobs as $i => $job) {
                $req = $job->requiredAt();
                if ($req) {
                    $start = $req->copy()->subSeconds($arriveOffsets[$i]);
                    $autoStart = true;
                    break;
                }
            }
        }

        // Sequentiële tijdlijn. De AFGESPROKEN tijd is leidend: kom je eerder aan dan wacht je
        // tot die tijd (aankomst = max(doorgerekend, afgesproken)); kom je later → ⚠️ te laat.
        $jobRows = [];
        $cursor  = $start ? $start->copy() : null;      // moment van vertrek/klaar-zijn
        foreach ($jobs as $i => $job) {
            $required = $job->requiredAt();
            $arrive = $depart = null;
            $late = false;
            $waitSeconds = 0;

            if ($cursor) {
                $travelArrive = $cursor->copy()->addSeconds((int) ($legs[$i]['seconds'] ?? 0));
                if ($required && $required->greaterThan($travelArrive)) {
                    $arrive = $required->copy();                       // wachten tot de afspraak
                    $waitSeconds = $travelArrive->diffInSeconds($required);
                } else {
                    $arrive = $travelArrive;
                    $late = (bool) ($required && $travelArrive->greaterThan($required));
                }
                $depart = $arrive->copy()->addMinutes((int) $job->duration_minutes);
                $cursor = $depart;
            }

            $jobRows[] = [
                'id'               => $job->id,
                'action'           => $job->action,
                'travel_seconds'   => (int) ($legs[$i]['seconds'] ?? 0),
                'travel_km'        => round(($legs[$i]['meters'] ?? 0) / 1000, 1),
                'wait_seconds'     => $waitSeconds,
                'arrive_at'        => $arrive?->toIso8601String(),
                'depart_at'        => $depart?->toIso8601String(),
                'duration_minutes' => (int) $job->duration_minutes,
                'required_at'      => $required?->toIso8601String(),
                'late'             => $late,
            ];
        }

        $returnLeg = null;
        if ($end) {
            $leg = $legs[count($jobs)] ?? ['seconds' => 0, 'meters' => 0];
            $returnLeg = [
                'travel_seconds' => (int) $leg['seconds'],
                'travel_km'      => round(($leg['meters'] ?? 0) / 1000, 1),
                'to'             => $end,
            ];
            if ($cursor) {
                $cursor = $cursor->copy()->addSeconds((int) $leg['seconds']);   // terugrit
            }
        }

        $endAt = $cursor;

        return array_merge($base, [
            'start_at'      => $start?->toIso8601String(),
            'auto_start'    => $autoStart,
            'end_at'        => $endAt?->toIso8601String(),
            'total_minutes' => ($start && $endAt) ? (int) round($start->diffInSeconds($endAt) / 60) : null,
            'travel_ok'     => $travelOk,
            'jobs'          => $jobRows,
            'return_leg'    => $returnLeg,
            'addr_hash'     => $this->addrHash($addresses),
            'legs'          => $legs,
            'note'          => $start ? null : 'Voeg een klus met een vaste tijd toe, of vul zelf een starttijd in.',
        ]);
    }

    /**
     * Reistijden per opeenvolgende etappe. Hergebruikt de cache als de adres-volgorde
     * onveranderd is; anders roept het Google Distance Matrix per etappe aan.
     *
     * @return array{0: array<int,array{seconds:int,meters:int}>, 1: bool}
     */
    private function legsFor(array $addresses, array $cache): array
    {
        $hash = $this->addrHash($addresses);
        if (($cache['addr_hash'] ?? null) === $hash && ! empty($cache['legs'])) {
            return [$cache['legs'], (bool) ($cache['travel_ok'] ?? true)];
        }

        $apiKey = config('services.google.maps_key');
        $legs   = [];
        $ok     = true;

        for ($i = 0; $i < count($addresses) - 1; $i++) {
            $from = $addresses[$i];
            $to   = $addresses[$i + 1];

            if (empty($apiKey) || empty($from) || empty($to)) {
                $legs[] = ['seconds' => 0, 'meters' => 0];
                $ok = false;
                continue;
            }

            $leg = $this->fetchLeg($from, $to, $apiKey);
            if ($leg === null) {
                $legs[] = ['seconds' => 0, 'meters' => 0];
                $ok = false;
            } else {
                $legs[] = $leg;
            }
        }

        return [$legs, $ok];
    }

    /** Eén etappe via Distance Matrix. Null bij fout. */
    private function fetchLeg(string $from, string $to, string $apiKey): ?array
    {
        try {
            $resp = Http::timeout(6)->get('https://maps.googleapis.com/maps/api/distancematrix/json', [
                'origins'      => $from,
                'destinations' => $to,
                'mode'         => 'driving',
                'units'        => 'metric',
                'language'     => 'nl',
                'key'          => $apiKey,
            ]);

            $data    = $resp->json();
            $element = $data['rows'][0]['elements'][0] ?? null;

            if (($data['status'] ?? '') !== 'OK' || ($element['status'] ?? '') !== 'OK') {
                return null;
            }

            return [
                'seconds' => (int) ($element['duration']['value'] ?? 0),
                'meters'  => (int) ($element['distance']['value'] ?? 0),
            ];
        } catch (\Throwable $e) {
            Log::warning('ShiftRouteService leg fout: ' . $e->getMessage());
            return null;
        }
    }

    private function addrHash(array $addresses): string
    {
        return md5(json_encode(array_map(fn ($a) => trim((string) $a), $addresses)));
    }
}
