<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Inschatting conversiekans: 1 = laag, 2 = gemiddeld, 3 = hoog (null = niet ingeschat)
            $table->tinyInteger('conversion_chance')->nullable()->after('follow_up_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('conversion_chance');
        });
    }
};
