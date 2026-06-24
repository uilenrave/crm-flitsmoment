<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('event_start_time', 10)->nullable()->after('event_date');
            $table->string('event_end_time', 10)->nullable()->after('event_start_time');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['event_start_time', 'event_end_time']);
        });
    }
};
