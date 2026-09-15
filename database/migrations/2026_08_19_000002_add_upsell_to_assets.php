<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Upsell-vlag + actietarief per asset — voor klant-upsells in het portaal. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->boolean('is_upsell')->default(false)->after('is_active');
            $table->decimal('upsell_price', 10, 2)->nullable()->after('is_upsell');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['is_upsell', 'upsell_price']);
        });
    }
};
