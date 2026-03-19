<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('origin_address')->nullable()->after('email');
            $table->unsignedInteger('travel_free_km')->default(20)->after('origin_address');
            $table->decimal('travel_cost_per_km', 8, 2)->default(4.00)->after('travel_free_km');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['origin_address', 'travel_free_km', 'travel_cost_per_km']);
        });
    }
};
