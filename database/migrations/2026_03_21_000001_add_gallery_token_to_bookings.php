<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('gallery_token', 32)->nullable()->unique()->after('gallery_url');
        });

        // Genereer tokens voor bestaande boekingen met een gallery_url
        \DB::table('bookings')
            ->whereNotNull('gallery_url')
            ->whereNull('gallery_token')
            ->orderBy('id')
            ->each(function ($booking) {
                \DB::table('bookings')
                    ->where('id', $booking->id)
                    ->update(['gallery_token' => Str::random(24)]);
            });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('gallery_token');
        });
    }
};
