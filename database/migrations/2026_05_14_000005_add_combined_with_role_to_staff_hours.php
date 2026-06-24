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
            $table->enum('combined_with_role', ['delivery', 'pickup', 'handover'])
                  ->nullable()
                  ->after('combined_with_booking_id');
        });

        // Voor bestaande combined-rijen: kopieer hun eigen role naar combined_with_role als beste gok
        DB::statement("UPDATE staff_hours SET combined_with_role = role WHERE status = 'combined' AND combined_with_booking_id IS NOT NULL AND combined_with_role IS NULL");
    }

    public function down(): void
    {
        Schema::table('staff_hours', function (Blueprint $table) {
            $table->dropColumn('combined_with_role');
        });
    }
};
