<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('public_token', 48)->nullable()->unique()->after('is_active');
        });

        // Genereer tokens voor bestaande medewerkers
        DB::table('staff')->whereNull('public_token')->get()->each(function ($s) {
            DB::table('staff')->where('id', $s->id)->update(['public_token' => Str::random(48)]);
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('public_token');
        });
    }
};
