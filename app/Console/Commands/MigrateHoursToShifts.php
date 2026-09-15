<?php

namespace App\Console\Commands;

use App\Models\Shift;
use App\Models\ShiftJob;
use App\Models\StaffHours;
use App\Scopes\AccountScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateHoursToShifts extends Command
{
    protected $signature = 'shifts:migrate-hours {--dry-run : Alleen rapporteren, niets wegschrijven}';

    protected $description = 'Zet bestaande per-rit uren (StaffHours) om naar 1-klus-shifts en koppelt ze (lossless).';

    /** role + booking_type → shift-actie */
    private function actionFor(string $role, string $bookingType): ?string
    {
        return match (true) {
            $bookingType === 'full_service' && $role === 'delivery' => 'opbouwen',
            $bookingType === 'full_service' && $role === 'pickup'   => 'afbouwen',
            $bookingType === 'to_go' && in_array($role, ['delivery', 'handover']) => 'afgeven',
            $bookingType === 'to_go' && $role === 'pickup'          => 'aannemen',
            default => null,
        };
    }

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        // Alleen nog-niet-gemigreerde, boeking-gebaseerde uren (shift_id leeg, booking + role aanwezig)
        $rows = StaffHours::withoutGlobalScopes()
            ->whereNull('shift_id')
            ->whereNotNull('booking_id')
            ->whereNotNull('role')
            ->with('booking')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('Niets te migreren — geen ongekoppelde per-rit uren gevonden.');
            return self::SUCCESS;
        }

        // Groepeer per (booking + actie): één shift per rit, meerdere medewerkers = 1e/2e persoon
        $groups = [];
        $skipped = [];
        foreach ($rows as $r) {
            if (! $r->booking) {
                $skipped[] = $r->id;
                continue;
            }
            $action = $this->actionFor($r->role, $r->booking->booking_type);
            if (! $action) {
                $skipped[] = $r->id;
                continue;
            }
            $groups[$r->booking_id . ':' . $action][] = $r;
        }

        $this->info(sprintf(
            '%d uren-regels → %d shifts aan te maken%s.',
            $rows->count(),
            count($groups),
            $skipped ? sprintf(' (%d overgeslagen: onbekende rol/boeking)', count($skipped)) : ''
        ));

        $twoPersons = collect($groups)->filter(fn ($g) => collect($g)->pluck('staff_id')->unique()->count() > 1)->count();
        if ($twoPersons) {
            $this->line("  waarvan {$twoPersons} shift(s) met 2 medewerkers.");
        }

        if ($dry) {
            $this->warn('DRY-RUN: er is niets weggeschreven.');
            return self::SUCCESS;
        }

        $created = 0;
        DB::transaction(function () use ($groups, &$created) {
            foreach ($groups as $key => $hoursRows) {
                [$bookingId, $action] = explode(':', $key);
                $first   = $hoursRows[0];
                $booking = $first->booking;

                $timeField = ShiftJob::ACTIONS[$action]['time_field'];
                $date = ($timeField && $booking->{$timeField})
                    ? $booking->{$timeField}->toDateString()
                    : $booking->event_date->toDateString();

                // 1e/2e persoon uit de betrokken medewerkers
                $staffIds = collect($hoursRows)->pluck('staff_id')->unique()->values();

                $shift = Shift::create([
                    'account_id'    => $first->account_id,
                    'staff_id'      => $staffIds->get(0),
                    'staff_id_2'    => $staffIds->get(1),
                    'shift_date'    => $date,
                    'start_address' => Shift::DEPOT_ADDRESS,
                ]);

                ShiftJob::create([
                    'shift_id'         => $shift->id,
                    'booking_id'       => $booking->id,
                    'action'           => $action,
                    'sort_order'       => 0,
                    'duration_minutes' => ShiftJob::ACTIONS[$action]['default_minutes'],
                ]);

                // Alle uren-regels aan deze shift koppelen (lossless: bestaande data blijft)
                foreach ($hoursRows as $r) {
                    $r->forceFill(['shift_id' => $shift->id])->save();
                }

                $created++;
            }
        });

        $this->info("✅ Klaar: {$created} shifts aangemaakt en uren gekoppeld.");
        return self::SUCCESS;
    }
}
