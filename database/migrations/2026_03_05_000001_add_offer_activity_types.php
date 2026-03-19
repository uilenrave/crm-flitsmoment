<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE lead_activities MODIFY COLUMN activity_type ENUM('note','call','email','meeting','quote_sent','status_change','offer_created','offer_sent','offer_accepted') NOT NULL DEFAULT 'note'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE lead_activities MODIFY COLUMN activity_type ENUM('note','call','email','meeting','quote_sent','status_change') NOT NULL DEFAULT 'note'");
    }
};
