<?php

namespace App\Services;

use App\Mail\BoothDailyDigest;
use App\Mail\BoothEventNotification;
use App\Models\Account;
use App\Models\BoothEvent;
use App\Models\BoothRelease;
use App\Models\BoothToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Overzicht en meldingen voor alle booths van een account: gebeurtenissen vastleggen,
 * direct mailen bij problemen/updates, en de dagelijkse samenvatting.
 */
class BoothFleetService
{
    /** Actieve (niet-ingetrokken) booths van een account. */
    public function booths(int $accountId): Collection
    {
        return BoothToken::where('account_id', $accountId)
            ->whereNull('revoked_at')
            ->with(['asset', 'account'])
            ->orderBy('unit_number')
            ->orderBy('name')
            ->get();
    }

    /**
     * Nieuwste versie per kanaal. Bron is de versielijst (booth_releases); zolang een kanaal
     * daar nog geen versie heeft, geldt de hoogste versie die een booth op dat kanaal meldt.
     *
     * @return array<string, string>
     */
    public function latestVersions(Collection $booths): array
    {
        $latest = [];
        foreach ($booths as $booth) {
            if (! $booth->app_version) {
                continue;
            }
            $channel = $booth->channel();
            if (! isset($latest[$channel]) || version_compare($booth->app_version, $latest[$channel], '>')) {
                $latest[$channel] = $booth->app_version;
            }
        }

        $releases = BoothRelease::all();
        foreach (array_keys(config('booth.channels')) as $channel) {
            if ($release = BoothRelease::latestFor($channel, $releases)) {
                $latest[$channel] = $release->version;
            }
        }

        return $latest;
    }

    /** Loopt deze booth achter op de nieuwste versie van zijn kanaal? Een vastgezette versie telt niet als achter. */
    public function isBehind(BoothToken $booth, array $latestVersions): bool
    {
        $latest = $latestVersions[$booth->channel()] ?? null;

        if (! $latest || ! $booth->app_version || $booth->pinned_version) {
            return false;
        }

        return version_compare($booth->app_version, $latest, '<');
    }

    /** Gebeurtenis vastleggen en — als het type dat vraagt — direct mailen naar het account. */
    public function recordEvent(BoothToken $booth, string $type, ?string $message = null, ?string $version = null, array $data = []): BoothEvent
    {
        $event = BoothEvent::create([
            'account_id'     => $booth->account_id,
            'booth_token_id' => $booth->id,
            'type'           => $type,
            'app_version'    => $version ?? $booth->app_version,
            'message'        => $message,
            'data'           => $data ?: null,
        ]);

        if (config("booth.events.{$type}.mail") && ! $this->inCooldown($event)) {
            $this->mailEvent($event);
        }

        return $event;
    }

    /** Zelfde type storing van dezelfde booth binnen de cooldown al gemaild? Dan niet opnieuw. */
    private function inCooldown(BoothEvent $event): bool
    {
        return BoothEvent::where('booth_token_id', $event->booth_token_id)
            ->where('type', $event->type)
            ->where('id', '!=', $event->id)
            ->whereNotNull('mailed_at')
            ->where('mailed_at', '>=', now()->subMinutes((int) config('booth.alert_cooldown_minutes', 60)))
            ->exists();
    }

    private function mailEvent(BoothEvent $event): void
    {
        $to = $event->booth?->account?->email;
        if (! $to) {
            return;
        }

        try {
            Mail::to($to)->send(new BoothEventNotification($event));
            $event->forceFill(['mailed_at' => now()])->save();
        } catch (\Throwable $e) {
            // Een mislukte mail mag de booth-API nooit laten falen.
            Log::warning('Booth-melding niet verzonden: ' . $e->getMessage(), ['event_id' => $event->id]);
        }
    }

    /**
     * Gegevens voor de dagelijkse samenvatting van één account.
     *
     * @return array{rows: array<int, array>, events: Collection, upcoming: array<int, array>, latest: array<string, string>}
     */
    public function digestData(Account $account, ?Carbon $now = null): array
    {
        $now    = $now ?? now();
        $booths = $this->booths($account->id);
        $latest = $this->latestVersions($booths);

        $rows = [];
        $upcoming = [];
        foreach ($booths as $booth) {
            $status = is_array($booth->last_status) ? $booth->last_status : [];

            $printsSince = ($booth->prints_total !== null && $booth->prints_total_at_digest !== null)
                ? max(0, $booth->prints_total - $booth->prints_total_at_digest)
                : null;

            $rows[] = [
                'booth'           => $booth,
                'name'            => $booth->displayName(),
                'version'         => $booth->app_version,
                'channel'         => $booth->channel(),
                'pinned'          => $booth->pinned_version,
                'behind'          => $this->isBehind($booth, $latest),
                'last_seen'       => $booth->last_heartbeat_at,
                'selftest_ok'     => $booth->selftest_ok,
                'prints_since'    => $printsSince,
                'media_remaining' => $status['media_remaining'] ?? null,
            ];

            // Events in de komende 48 uur: is de booth er klaar voor?
            $next = $booth->nextBooking($now);
            if ($next && $next->event_date->lte($now->copy()->addDays(2))) {
                $upcoming[] = [
                    'name'        => $booth->displayName(),
                    'booking'     => $next,
                    'behind'      => $this->isBehind($booth, $latest),
                    'selftest_ok' => $booth->selftest_ok,
                    'last_seen'   => $booth->last_heartbeat_at,
                ];
            }
        }

        $events = BoothEvent::where('account_id', $account->id)
            ->where('created_at', '>=', $now->copy()->subDay())
            ->with('booth.asset')
            ->orderBy('created_at')
            ->get();

        return ['rows' => $rows, 'events' => $events, 'upcoming' => $upcoming, 'latest' => $latest];
    }

    /** Verstuurt de dagelijkse samenvatting voor één account. Geeft false terug als er niets te versturen viel. */
    public function sendDailyDigest(Account $account): bool
    {
        $data = $this->digestData($account);

        if (! $account->email || empty($data['rows'])) {
            return false;
        }

        Mail::to($account->email)->send(new BoothDailyDigest($account, $data));

        // Printteller-stand vastleggen, zodat de volgende samenvatting het verschil toont.
        foreach ($data['rows'] as $row) {
            $booth = $row['booth'];
            if ($booth->prints_total !== null) {
                $booth->forceFill(['prints_total_at_digest' => $booth->prints_total])->saveQuietly();
            }
        }

        return true;
    }
}
