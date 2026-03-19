<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->unsignedSmallInteger('booking_sequence')->default(0)->after('code');
            $table->unsignedSmallInteger('booking_sequence_year')->default(0)->after('booking_sequence');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['booking_sequence', 'booking_sequence_year']);
        });
    }
};
