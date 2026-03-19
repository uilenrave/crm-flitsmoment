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
        // eboekhouden_synced_at on bookings already exists via add_eboekhouden_to_bookings migration

        // Only add to payments table if it exists
        if (Schema::hasTable('payments')) {
            if (!Schema::hasColumn('payments', 'eboekhouden_synced_at')) {
                Schema::table('payments', function (Blueprint $table) {
                    $table->datetime('eboekhouden_synced_at')->nullable()->after('paid_at')->comment('When this payment was synced from e-boekhouden');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'eboekhouden_synced_at')) {
                $table->dropColumn('eboekhouden_synced_at');
            }
        });

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                if (Schema::hasColumn('payments', 'eboekhouden_synced_at')) {
                    $table->dropColumn('eboekhouden_synced_at');
                }
            });
        }
    }
};
