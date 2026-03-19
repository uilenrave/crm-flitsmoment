<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Add multi-day event support
            $table->date('event_end_date')->nullable()->after('event_date')->comment('End date for multi-day bookings');
            $table->boolean('is_multi_day')->default(false)->after('event_end_date')->comment('Flag to indicate multi-day booking');

            // Add index for filtering by date range
            $table->index(['account_id', 'event_date', 'event_end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'event_date', 'event_end_date']);
            $table->dropColumn(['event_end_date', 'is_multi_day']);
        });
    }
};
