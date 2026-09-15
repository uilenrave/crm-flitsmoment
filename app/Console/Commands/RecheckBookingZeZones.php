<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Scopes\AccountScope;
use App\Services\ZeZoneService;
use Illuminate\Console\Command;

/**
 * (Her)berekent de zero-emissiezone-vlag voor bestaande boekingen: geocodeert het adres en
 * doet point-in-polygon. Draai na een import of eenmalig als backfill.
 */
class RecheckBookingZeZones extends Command
{
    protected $signature = 'ze-zones:recheck-bookings {--force : opnieuw geocoderen, ook als er al coördinaten zijn} {--only-unchecked : sla boekingen over die al gecheckt zijn}';
    protected $description = 'Bereken de zero-emissiezone-vlag voor bestaande boekingen (geocode + point-in-polygon).';

    public function handle(ZeZoneService $service): int
    {
        $query = Booking::withoutGlobalScope(AccountScope::class)
            ->whereNotIn('status', ['cancelled'])
            ->where(function ($q) {
                $q->whereNotNull('event_city')->orWhereNotNull('event_address');
            });

        if ($this->option('only-unchecked')) {
            $query->whereNull('ze_checked_at');
        }

        $total = (clone $query)->count();
        $this->info("Boekingen om te checken: {$total}");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $inZone = 0;
        $query->orderBy('id')->chunkById(100, function ($bookings) use ($service, &$inZone, $bar) {
            foreach ($bookings as $booking) {
                $service->checkBooking($booking, regeocode: $this->option('force'));
                if ($booking->in_ze_zone) $inZone++;
                $bar->advance();
                usleep(120000); // ~8 req/s richting PDOK — vriendelijk blijven
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Klaar. {$inZone} boeking(en) liggen in een zero-emissiezone.");
        return self::SUCCESS;
    }
}
