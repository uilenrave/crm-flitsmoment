<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_masks', function (Blueprint $table) {
            $table->string('preview_photos_path')->nullable()->after('svg_path');
        });
    }

    public function down(): void
    {
        Schema::table('design_masks', function (Blueprint $table) {
            $table->dropColumn('preview_photos_path');
        });
    }
};
