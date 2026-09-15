<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // booking_id optioneel maken → "Laden" is een klus zonder boeking
        Schema::table('shift_jobs', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->unsignedBigInteger('booking_id')->nullable()->change();
            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();
        });

        // Onthoud of de starttijd handmatig is gezet (anders automatisch terugrekenen)
        Schema::table('shifts', function (Blueprint $table) {
            $table->boolean('start_manual')->default(false)->after('start_at');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn('start_manual');
        });
        Schema::table('shift_jobs', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->unsignedBigInteger('booking_id')->nullable(false)->change();
            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();
        });
    }
};
