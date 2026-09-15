<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_hours', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->after('booking_id')->constrained('shifts')->nullOnDelete();
        });

        // booking_id en role optioneel maken: shift-uren hangen aan een shift, niet aan één rit
        Schema::table('staff_hours', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->unsignedBigInteger('booking_id')->nullable()->change();
            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();
            $table->enum('role', ['delivery', 'pickup', 'handover'])->nullable()->change();
        });

        // Eén uren-invoer per medewerker per shift (naast de bestaande per-rit unique)
        Schema::table('staff_hours', function (Blueprint $table) {
            $table->unique(['staff_id', 'shift_id']);
        });
    }

    public function down(): void
    {
        Schema::table('staff_hours', function (Blueprint $table) {
            $table->dropUnique(['staff_id', 'shift_id']);
        });
        Schema::table('staff_hours', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->unsignedBigInteger('booking_id')->nullable(false)->change();
            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();
            $table->enum('role', ['delivery', 'pickup', 'handover'])->nullable(false)->change();
        });
        Schema::table('staff_hours', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
        });
    }
};
