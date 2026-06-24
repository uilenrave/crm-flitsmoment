<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->text('delivery_instructions')->nullable()->after('event_notes');
            $table->json('delivery_instructions_images')->nullable()->after('delivery_instructions');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['delivery_instructions', 'delivery_instructions_images']);
        });
    }
};
