<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Rit opengezet voor aanmelding door medewerkers?
            // delivery_open geldt voor bezorging (Full Service) én afgifte (To Go) — net als delivery_staff_id
            $table->boolean('delivery_open')->default(false)->after('pickup_staff_id');
            $table->boolean('pickup_open')->default(false)->after('delivery_open');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['delivery_open', 'pickup_open']);
        });
    }
};
