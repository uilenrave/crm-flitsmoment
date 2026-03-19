<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // event_location bestaat al — alleen de adresdetails toevoegen
            $table->string('event_address', 255)->nullable()->after('event_location');
            $table->string('event_postcode', 20)->nullable()->after('event_address');
            $table->string('event_city', 100)->nullable()->after('event_postcode');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->unsignedInteger('stock')->default(1)->after('price');
        });

        Schema::create('lead_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['lead_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_assets');

        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('stock');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['event_address', 'event_postcode', 'event_city']);
        });
    }
};
