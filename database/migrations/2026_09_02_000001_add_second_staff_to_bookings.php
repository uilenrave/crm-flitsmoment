<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('delivery_staff_id_2')->nullable()->after('delivery_staff_id')
                ->constrained('staff')->nullOnDelete();
            $table->foreignId('pickup_staff_id_2')->nullable()->after('pickup_staff_id')
                ->constrained('staff')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_staff_id_2');
            $table->dropConstrainedForeignId('pickup_staff_id_2');
        });
    }
};
