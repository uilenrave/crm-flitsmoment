<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_hours', function (Blueprint $table) {
            $table->decimal('km', 6, 1)->nullable()->after('hours_approved');
        });
    }

    public function down(): void
    {
        Schema::table('staff_hours', function (Blueprint $table) {
            $table->dropColumn('km');
        });
    }
};
