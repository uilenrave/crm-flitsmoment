<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Versies van de booth-app. De CRM is de updatefeed: booths vragen hier welke versie ze moeten draaien. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booth_releases', function (Blueprint $table) {
            $table->id();
            $table->string('version', 30)->unique();
            $table->string('filename');                       // installer in storage/app/private/booth-updates/
            $table->string('sha512', 120);                    // base64, zoals de updater het verwacht
            $table->unsignedBigInteger('size');
            $table->string('channel', 20)->default('beta');   // 'beta' = alleen testbooth, 'stable' = alle booths
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booth_releases');
    }
};
