<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('proposed_return_time', 5)->nullable()->after('customer_return_at');  // bijv. "11:00"
            $table->string('proposed_return_status', 20)->nullable()->after('proposed_return_time'); // pending / approved / rejected
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['proposed_return_time', 'proposed_return_status']);
        });
    }
};
