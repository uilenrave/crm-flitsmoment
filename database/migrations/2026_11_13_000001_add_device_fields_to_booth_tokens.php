<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Een booth_token is voortaan ook het "apparaat": koppeling aan asset+unit, updatekanaal,
 * functieschakelaars en de laatst gemelde status. Volledig additief (alles nullable/default).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booth_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('asset_id')->nullable()->after('account_id');   // photobooth-asset (samen met unit_number = de fysieke booth)
            $table->string('update_channel', 20)->default('stable')->after('unit_number');
            $table->string('pinned_version', 30)->nullable()->after('update_channel'); // vastgezette app-versie (null = kanaal volgen)
            $table->json('features')->nullable()->after('pinned_version');             // overrides op config('booth.features'): {key: bool}
            $table->string('app_version', 30)->nullable()->after('features');
            $table->timestamp('last_heartbeat_at')->nullable()->after('app_version');
            $table->boolean('selftest_ok')->nullable()->after('last_heartbeat_at');
            $table->json('last_status')->nullable()->after('selftest_ok');              // laatste heartbeat-payload (selftest, papier, schijf, …)
            $table->unsignedInteger('prints_total')->nullable()->after('last_status');  // oplopende printteller van de booth
            $table->unsignedInteger('prints_total_at_digest')->nullable()->after('prints_total');

            $table->index(['account_id', 'asset_id', 'unit_number']);
        });
    }

    public function down(): void
    {
        Schema::table('booth_tokens', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'asset_id', 'unit_number']);
            $table->dropColumn([
                'asset_id', 'update_channel', 'pinned_version', 'features', 'app_version',
                'last_heartbeat_at', 'selftest_ok', 'last_status', 'prints_total', 'prints_total_at_digest',
            ]);
        });
    }
};
