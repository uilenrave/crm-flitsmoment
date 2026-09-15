<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Booking;
use App\Models\Offer;
use App\Models\Task;
use App\Models\TaskList;

/**
 * Zet CRM-situaties om in taken voor de takenapp — en vinkt ze weer af
 * zodra de situatie in het CRM is opgelost.
 *
 * Drie signalen:
 *   1. offerte verstuurd en X dagen stil          → nabellen
 *   2. klus geweest, gallery_url nog leeg         → galerij uploaden
 *   3. klus binnenkort met open bezorging/ophaling → medewerker zoeken
 *
 * Afspraken:
 *   - elk signaal heeft een vaste signal_key, dus één taak per situatie
 *   - een taak die jij wegvinkt komt NOOIT terug, ook al blijft de situatie bestaan
 *   - lost het CRM de situatie op, dan vinkt de sync de taak zelf af
 */
class TodoSignalService
{
    /** Niet verder terugkijken dan dit, anders vult de app zich met oude klussen */
    private const MAX_LOOKBACK_DAYS = 60;

    /** @return array{aangemaakt:int, afgerond:int} */
    public function syncAll(): array
    {
        $totals = ['aangemaakt' => 0, 'afgerond' => 0];

        foreach (Account::all() as $account) {
            $result = $this->syncAccount($account);
            $totals['aangemaakt'] += $result['aangemaakt'];
            $totals['afgerond']   += $result['afgerond'];
        }

        return $totals;
    }

    /** @return array{aangemaakt:int, afgerond:int} */
    public function syncAccount(Account $account): array
    {
        $created = 0;
        $created += $this->offerFollowUps($account);
        $created += $this->galleryUploads($account);
        $created += $this->openShifts($account);

        return ['aangemaakt' => $created, 'afgerond' => $this->resolveFinished($account)];
    }

    // ── Signaal 1: offerte zonder reactie ────────────────────────────────────

    private function offerFollowUps(Account $account): int
    {
        $days = $account->todoSetting('offer_followup_days');

        $offers = Offer::withoutGlobalScopes()
            ->with('lead')
            ->where('account_id', $account->id)
            ->where('status', 'sent')
            ->whereNotNull('sent_at')
            ->whereDate('sent_at', '<=', now()->subDays($days)->toDateString())
            ->whereDate('sent_at', '>=', now()->subDays(self::MAX_LOOKBACK_DAYS)->toDateString())
            ->whereNull('accepted_at')
            ->get();

        $created = 0;

        foreach ($offers as $offer) {
            $naam  = $offer->lead?->name ?: 'klant';
            $dagen = (int) $offer->sent_at->startOfDay()->diffInDays(now()->startOfDay());

            $created += $this->ensure($account, "offer:{$offer->id}:followup", [
                'title'       => "Nabellen: {$naam} over {$offer->offer_number}",
                'notes'       => "Offerte verstuurd op {$offer->sent_at->format('d-m-Y')} ({$dagen} dagen geleden) en nog geen reactie. Bedrag: € "
                                 . number_format((float) $offer->total, 2, ',', '.') . '.',
                'due_date'    => now()->toDateString(),
                'source_type' => 'offer',
                'source_id'   => $offer->id,
            ]);
        }

        return $created;
    }

    // ── Signaal 2: boeking zonder galerij ────────────────────────────────────

    private function galleryUploads(Account $account): int
    {
        $days = $account->todoSetting('gallery_days');

        $bookings = Booking::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->whereNull('gallery_url')
            ->where(function ($q) {
                $q->whereNull('gallery_skipped')->orWhere('gallery_skipped', false);
            })
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereDate('event_date', '<=', now()->subDays($days)->toDateString())
            ->whereDate('event_date', '>=', now()->subDays(self::MAX_LOOKBACK_DAYS)->toDateString())
            ->get();

        $created = 0;

        foreach ($bookings as $booking) {
            $created += $this->ensure($account, "booking:{$booking->id}:gallery", [
                'title'       => "Galerij uploaden: {$booking->customer_name}",
                'notes'       => "Klus was op {$booking->event_date->format('d-m-Y')}"
                                 . ($booking->event_city ? " in {$booking->event_city}" : '')
                                 . ". De galerij-link staat nog leeg in het CRM.",
                'due_date'    => $booking->event_date->copy()->addDays($days)->toDateString(),
                'source_type' => 'booking',
                'source_id'   => $booking->id,
            ]);
        }

        return $created;
    }

    // ── Signaal 3: shift met open plekken ────────────────────────────────────

    private function openShifts(Account $account): int
    {
        $days = $account->todoSetting('shift_days');

        $bookings = Booking::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereDate('event_date', '>=', now()->toDateString())
            ->whereDate('event_date', '<=', now()->addDays($days)->toDateString())
            ->where(function ($q) {
                $q->where(fn ($q2) => $q2->where('delivery_open', true)->whereNull('delivery_staff_id'))
                  ->orWhere(fn ($q2) => $q2->where('pickup_open', true)->whereNull('pickup_staff_id'));
            })
            ->get();

        $created = 0;

        foreach ($bookings as $booking) {
            foreach ($this->openRoles($booking) as $rol => $label) {
                $plaats = $booking->event_city ? ", {$booking->event_city}" : '';

                $created += $this->ensure($account, "shift:{$booking->id}:{$rol}", [
                    'title'       => "Medewerker zoeken: {$booking->event_date->format('D j M')}{$plaats} — {$label}",
                    'notes'       => "{$label} staat open op het dienstenbord voor {$booking->customer_name}. Niemand heeft zich aangemeld.",
                    'due_date'    => $booking->event_date->copy()->subDays(2)->toDateString(),
                    'source_type' => 'shift',
                    'source_id'   => $booking->id,
                ]);
            }
        }

        return $created;
    }

    /** @return array<string,string> rol => label */
    private function openRoles(Booking $booking): array
    {
        $roles = [];

        if ($booking->delivery_open && ! $booking->delivery_staff_id) $roles['delivery'] = 'Bezorging';
        if ($booking->pickup_open   && ! $booking->pickup_staff_id)   $roles['pickup']   = 'Ophaling';

        return $roles;
    }

    // ── Afronden zodra het CRM het oplost ────────────────────────────────────

    private function resolveFinished(Account $account): int
    {
        $open = Task::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->where('source', Task::SOURCE_CRM)
            ->whereNotNull('signal_key')
            ->whereNull('completed_at')
            ->get();

        $afgerond = 0;

        foreach ($open as $task) {
            if ($this->isResolved($task)) {
                $task->update([
                    'completed_at' => now(),
                    'notes'        => trim(($task->notes ?? '') . "\n\nAutomatisch afgerond op "
                                      . now()->format('d-m-Y') . ': opgelost in het CRM.'),
                ]);
                $afgerond++;
            }
        }

        return $afgerond;
    }

    private function isResolved(Task $task): bool
    {
        [$soort, $id, $detail] = array_pad(explode(':', (string) $task->signal_key), 3, null);

        if ($soort === 'offer') {
            $offer = Offer::withoutGlobalScopes()->find($id);

            // Offerte weg, of niet langer "verstuurd en stil"
            return ! $offer || $offer->status !== 'sent' || $offer->accepted_at !== null;
        }

        if ($soort === 'booking' && $detail === 'gallery') {
            $booking = Booking::withoutGlobalScopes()->find($id);

            return ! $booking
                || $booking->gallery_url !== null
                || (bool) $booking->gallery_skipped
                || in_array($booking->status, ['cancelled', 'no_show'], true);
        }

        if ($soort === 'shift') {
            $booking = Booking::withoutGlobalScopes()->find($id);

            if (! $booking || in_array($booking->status, ['cancelled', 'no_show'], true)) return true;

            // Klus is geweest → niet meer relevant
            if ($booking->event_date->isBefore(now()->startOfDay())) return true;

            return ! array_key_exists($detail, $this->openRoles($booking));
        }

        return false;
    }

    // ── Aanmaken, precies één keer ───────────────────────────────────────────

    /** @return int 1 als er een taak is aangemaakt, anders 0 */
    private function ensure(Account $account, string $signalKey, array $attributes): int
    {
        $bestaat = Task::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->where('signal_key', $signalKey)
            ->exists();

        // Ook een afgevinkte taak telt: eenmaal weggewerkt komt dit signaal niet terug.
        if ($bestaat) return 0;

        $list = TaskList::defaultFor($account->id);

        Task::withoutGlobalScopes()->create(array_merge([
            'account_id'   => $account->id,
            'task_list_id' => $list->id,
            'type'         => Task::TYPE_TAAK,
            'source'       => Task::SOURCE_CRM,
            'signal_key'   => $signalKey,
            'sort_order'   => 0,
        ], $attributes));

        return 1;
    }
}
