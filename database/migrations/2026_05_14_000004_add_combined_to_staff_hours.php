<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_hours', function (Blueprint $table) {
            $table->foreignId('combined_with_booking_id')
                  ->nullable()
                  ->after('booking_id')
                  ->constrained('bookings')
                  ->nullOnDelete();
        });

        // 'hours' was NOT NULL; voor combinatieritten willen we 0 kunnen opslaan zonder problemen.
        // Verander default zodat updateOrCreate met hours=0 werkt.
        DB::statement("ALTER TABLE staff_hours MODIFY COLUMN hours DECIMAL(4,2) NOT NULL DEFAULT 0");

        // Voeg 'combined' toe aan de status enum
        DB::statement("ALTER TABLE staff_hours MODIFY COLUMN status ENUM('pending','approved','paid','combined') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        Schema::table('staff_hours', function (Blueprint $table) {
            $table->dropConstrainedForeignId('combined_with_booking_id');
        });

        DB::statement("ALTER TABLE staff_hours MODIFY COLUMN status ENUM('pending','approved','paid') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE staff_hours MODIFY COLUMN hours DECIMAL(4,2) NOT NULL");
    }
};
