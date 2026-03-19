<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_items', function (Blueprint $table) {
            $table->unsignedTinyInteger('unit_number')->nullable()->after('quantity')
                ->comment('Voor photobooth assets: welk specifiek unit is geboekt (1-N)');
        });
    }

    public function down(): void
    {
        Schema::table('booking_items', function (Blueprint $table) {
            $table->dropColumn('unit_number');
        });
    }
};
