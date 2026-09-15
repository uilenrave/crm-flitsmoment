<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zero-emissiezones: opgeslagen polygonen (uit officiële RWS-open-data) + per boeking een
 * berekende vlag of het bezorgadres in zo'n zone valt. Zo hoeft elke weergave alleen de vlag
 * te lezen i.p.v. per keer een point-in-polygon te doen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zero_emission_zones', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable()->index();   // id uit de bronset (voor upsert)
            $table->string('name')->nullable();
            $table->string('gemeente')->nullable();
            $table->string('source')->default('rws');
            $table->longText('geometry');                          // GeoJSON geometry (Polygon/MultiPolygon)
            // Bounding box voor een snelle voorfilter vóór de dure point-in-polygon
            $table->double('min_lng')->nullable();
            $table->double('min_lat')->nullable();
            $table->double('max_lng')->nullable();
            $table->double('max_lat')->nullable();
            $table->timestamps();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->double('latitude')->nullable()->after('event_city');
            $table->double('longitude')->nullable()->after('latitude');
            $table->boolean('in_ze_zone')->default(false)->after('longitude');
            $table->string('ze_zone_name')->nullable()->after('in_ze_zone');
            $table->timestamp('ze_checked_at')->nullable()->after('ze_zone_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zero_emission_zones');
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'in_ze_zone', 'ze_zone_name', 'ze_checked_at']);
        });
    }
};
