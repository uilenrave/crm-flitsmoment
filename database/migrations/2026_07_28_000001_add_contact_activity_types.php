<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Breidt lead_activities.activity_type uit met granulaire contactmoment-types voor de contact-log
 * in het leadoverzicht. Enum-waarden toevoegen is veilig: bestaande rijen blijven geldig.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE lead_activities MODIFY COLUMN activity_type ENUM('note','call','email','meeting','quote_sent','status_change','offer_created','offer_sent','offer_accepted','call_no_answer','call_answered','contact_other') NOT NULL DEFAULT 'note'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE lead_activities MODIFY COLUMN activity_type ENUM('note','call','email','meeting','quote_sent','status_change','offer_created','offer_sent','offer_accepted') NOT NULL DEFAULT 'note'");
    }
};
