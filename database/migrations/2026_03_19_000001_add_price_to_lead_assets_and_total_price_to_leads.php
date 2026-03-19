<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_assets', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->default(0)->after('quantity');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->decimal('total_price', 10, 2)->default(0)->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('lead_assets', function (Blueprint $table) {
            $table->dropColumn('price');
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('total_price');
        });
    }
};
