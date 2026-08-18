<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Voegt de legacy strip-status 'waiting_input' samen met 'awaiting_customer_design'
 * ("Wachten op klant"). Beide betekenden "actie ligt bij de klant"; de tweetal was
 * verwarrend, o.a. omdat de boekingenlijst-dropdown 'awaiting_customer_design' niet toonde.
 *
 * De DB-enum blijft ongewijzigd (projectregel: nooit enum-waarden droppen). We zetten alleen
 * bestaande rijen om; de applicatiecode produceert 'waiting_input' vanaf nu niet meer.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('bookings')
            ->where('strip_status', 'waiting_input')
            ->update(['strip_status' => 'awaiting_customer_design']);
    }

    public function down(): void
    {
        // Bewust geen terugmigratie: 'waiting_input' en 'awaiting_customer_design' zijn
        // samengevoegd tot één betekenis. Terugsplitsen zou willekeurig zijn en de enum-waarde
        // blijft sowieso bestaan, dus dit is niet destructief.
    }
};
