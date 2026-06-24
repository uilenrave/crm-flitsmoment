<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // bezorger (Full Service) of afgever (To Go)
            $table->foreignId('delivery_staff_id')->nullable()->after('pickup_contact_time')
                ->constrained('staff')->nullOnDelete();
            // ophaler (Full Service only)
            $table->foreignId('pickup_staff_id')->nullable()->after('delivery_staff_id')
                ->constrained('staff')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['delivery_staff_id']);
            $table->dropForeign(['pickup_staff_id']);
            $table->dropColumn(['delivery_staff_id', 'pickup_staff_id']);
        });
    }
};
