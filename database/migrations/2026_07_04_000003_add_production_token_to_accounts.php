<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('production_token', 48)->nullable()->unique()->after('id');
        });

        // Bestaande accounts krijgen meteen een token, zodat de productielink direct werkt.
        foreach (DB::table('accounts')->whereNull('production_token')->pluck('id') as $id) {
            DB::table('accounts')->where('id', $id)->update(['production_token' => Str::random(48)]);
        }
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('production_token');
        });
    }
};
