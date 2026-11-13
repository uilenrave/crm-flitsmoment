<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BoothRelease;
use App\Models\BoothToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\BoothFleetService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class BoothApiController extends Controller
{
    /** GET /api/booth/bookings — aankomende bevestigde klussen voor het account van het token. */
    public function bookings(Request $request): JsonResponse
    {
        /** @var BoothToken $booth */
        $booth = $request->attributes->get('booth');

        // Datumvenster
        $from = $this->parseFrom($request->query('from'));
        $days = (int) $request->query('days', 14);
        $days = max(1, min(90, $days));                 // hard afkappen op 90
        $to   = $from->copy()->addDays($days);

        // BELANGRIJK: expliciet zonder global scopes + zelf op account_id filteren.
        // Zonder ingelogde user voegt AccountScope niets toe → anders lekken alle accounts.
        $query = Booking::withoutGlobalScopes()
            ->where('account_id', $booth->account_id)
            ->where('status', 'confirmed')
            ->whereBetween('event_date', [$from->toDateString(), $to->toDateString()])
            ->with('items')
            ->orderBy('event_date');

        $unit = $request->query('unit');
        if ($unit !== null && $unit !== '') {
            $query->whereHas('items', fn ($q) => $q->where('unit_number', (int) $unit));
        }

        $data = $query->get()->map(fn (Booking $b) => $this->transform($b))->all();

        return response()->json(['data' => $data]);
    }

    /** GET /api/booth/bookings/{id}/production-file — het productiebestand (overlay-PNG) van een boeking van dit account. */
    public function productionFile(Request $request, int $id)
    {
        /** @var BoothToken $booth */
        $booth = $request->attributes->get('booth');

        $booking = Booking::withoutGlobalScopes()
            ->where('account_id', $booth->account_id)
            ->whereKey($id)
            ->firstOrFail();

        $disk = Storage::disk('public');
        abort_if(! $booking->production_file_path || ! $disk->exists($booking->production_file_path), 404, 'Geen productiebestand beschikbaar.');

        return $disk->response($booking->production_file_path);
    }

    /** GET /api/booth/config — identiteit, updatekanaal + of updaten nu mag, en functieschakelaars. */
    public function config(Request $request): JsonResponse
    {
        /** @var BoothToken $booth */
        $booth = $request->attributes->get('booth');
        $booth->loadMissing(['asset', 'account']);

        return response()->json(['data' => $booth->deviceConfig()]);
    }

    /**
     * POST /api/booth/heartbeat — de booth meldt versie + status en krijgt de actuele config terug.
     * Een zelftest die van goed naar fout gaat wordt als gebeurtenis vastgelegd (en gemaild).
     */
    public function heartbeat(Request $request, BoothFleetService $fleet): JsonResponse
    {
        /** @var BoothToken $booth */
        $booth = $request->attributes->get('booth');
        $booth->loadMissing(['asset', 'account']);

        $data = $request->validate([
            'app_version'     => ['required', 'string', 'max:30'],
            'selftest'        => ['nullable', 'array'],
            'selftest.*'      => ['boolean'],
            'prints_total'    => ['nullable', 'integer', 'min:0'],
            'diagnostics'     => ['nullable', 'array'],
            'media_remaining' => ['nullable', 'integer', 'min:0'],
            'disk_free_gb'    => ['nullable', 'numeric', 'min:0'],
        ]);

        $selftest   = $data['selftest'] ?? null;
        $selftestOk = $selftest === null ? null : ! in_array(false, array_map('boolval', $selftest), true);
        $wasOk      = $booth->selftest_ok;

        $booth->forceFill([
            'app_version'       => $data['app_version'],
            'last_heartbeat_at' => now(),
            'selftest_ok'       => $selftestOk,
            'last_status'       => [
                'selftest'        => $selftest,
                'media_remaining' => $data['media_remaining'] ?? null,
                'disk_free_gb'    => $data['disk_free_gb'] ?? null,
                'diagnostics'     => $this->cappedDiagnostics($data['diagnostics'] ?? null),
            ],
            'prints_total'      => $data['prints_total'] ?? $booth->prints_total,
        ])->save();

        if ($selftestOk === false && $wasOk !== false) {
            $failed = implode(', ', array_keys(array_filter($selftest, fn ($ok) => ! $ok)));
            $fleet->recordEvent($booth, 'selftest_failed', "Gefaald: {$failed}", $data['app_version'], ['selftest' => $selftest]);
        }

        return response()->json(['data' => $booth->deviceConfig()]);
    }

    /** Diagnose (printers, logregels) alleen bewaren als die klein genoeg is; anders een korte melding. */
    private function cappedDiagnostics(?array $diagnostics): ?array
    {
        if ($diagnostics === null) {
            return null;
        }

        return strlen(json_encode($diagnostics)) <= 40000
            ? $diagnostics
            : ['error' => 'Diagnose te groot, niet opgeslagen.'];
    }

    /**
     * GET /api/booth/update/latest.yml — de updatefeed. De CRM bepaalt welke versie deze booth
     * hoort te draaien (vastgezet of nieuwste van het kanaal) en geeft die terug in het formaat
     * van electron-updater.
     */
    public function updateFeed(Request $request, ?string $version = null)
    {
        /** @var BoothToken $booth */
        $booth = $request->attributes->get('booth');

        // Met een versie in de URL vraagt de booth om precies die versie (automatische rollback).
        $release = $version
            ? BoothRelease::where('version', $version)->first()
            : $booth->targetRelease();

        abort_if(! $release, 404, 'Geen versie beschikbaar.');

        return response($release->toUpdateYml(), 200, ['Content-Type' => 'text/yaml; charset=utf-8']);
    }

    /** GET /api/booth/update/files/{filename} — de installer van een geregistreerde versie. */
    public function updateFile(Request $request, string $filename)
    {
        $release = BoothRelease::where('filename', $filename)->firstOrFail();
        $disk    = Storage::disk('local');

        abort_if(! $disk->exists($release->storagePath()), 404, 'Installer ontbreekt op de server.');

        return $disk->download($release->storagePath(), $release->filename);
    }

    /** POST /api/booth/events — de booth meldt een gebeurtenis (update geslaagd/mislukt, rollback, …). */
    public function event(Request $request, BoothFleetService $fleet): JsonResponse
    {
        /** @var BoothToken $booth */
        $booth = $request->attributes->get('booth');
        $booth->loadMissing(['asset', 'account']);

        $data = $request->validate([
            'type'        => ['required', Rule::in(array_keys(config('booth.events')))],
            'message'     => ['nullable', 'string', 'max:2000'],
            'app_version' => ['nullable', 'string', 'max:30'],
        ]);

        if (! empty($data['app_version'])) {
            $booth->forceFill(['app_version' => $data['app_version']])->save();
        }

        $event = $fleet->recordEvent($booth, $data['type'], $data['message'] ?? null, $data['app_version'] ?? null);

        return response()->json(['data' => ['id' => $event->id]], 201);
    }

    private function parseFrom(?string $raw): Carbon
    {
        if (! $raw) {
            return Carbon::today();
        }
        try {
            return Carbon::createFromFormat('Y-m-d', $raw)->startOfDay();
        } catch (\Throwable $e) {
            return Carbon::today();
        }
    }

    private function transform(Booking $b): array
    {
        $units = $b->items
            ->pluck('unit_number')
            ->reject(fn ($u) => $u === null)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            'id'              => $b->id,
            'booking_number'  => $b->booking_number,
            'customer_name'   => $b->customer_name,
            'event_date'      => optional($b->event_date)->toDateString(),
            'event_location'  => $b->event_location,
            'units'           => array_map('intval', $units),
            'gallery_token'   => $b->gallery_token,
            'strip'           => [
                'status'          => $b->strip_status,           // mag null zijn
                'format'          => $b->strip_format,
                'template_id'     => $b->strip_template_id,
                'design_url'      => $this->absoluteUrl($b->strip_design_url),
                'design_filename' => $this->designFilename($b),
            ],
            // Het print-klare overlay-bestand (PNG met transparante fotovakken) dat de booth laadt.
            'production'      => [
                'available'  => (bool) $b->production_file_path,
                'updated_at' => optional($b->production_file_at)->toIso8601String(),
                'url'        => $b->production_file_path ? URL::to("/api/booth/bookings/{$b->id}/production-file") : null,
            ],
        ];
    }

    /** Maak van een opgeslagen ontwerp-URL/pad een absolute URL; externe URL's blijven ongemoeid. */
    private function absoluteUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        return URL::to($url);
    }

    /** Bestandsnaam uit het laatste strip_designs-item dat matcht met strip_design_url, anders basename. */
    private function designFilename(Booking $b): ?string
    {
        if (! $b->strip_design_url) {
            return null;
        }
        $designs = collect($b->strip_designs ?? []);
        $match = $designs->reverse()->first(fn ($d) => ($d['url'] ?? null) === $b->strip_design_url);
        if ($match && ! empty($match['filename'])) {
            return $match['filename'];
        }
        $path = parse_url($b->strip_design_url, PHP_URL_PATH) ?: $b->strip_design_url;
        return basename($path);
    }
}
