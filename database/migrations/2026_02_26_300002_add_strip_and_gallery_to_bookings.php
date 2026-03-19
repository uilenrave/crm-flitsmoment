<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('strip_design_url')->nullable()->after('strip_status');
            $table->text('strip_notes')->nullable()->after('strip_design_url'); // klant feedback
            $table->string('gallery_url')->nullable()->after('strip_notes');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['strip_design_url', 'strip_notes', 'gallery_url']);
        });
    }
};
