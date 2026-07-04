<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE bookings MODIFY strip_design_method ENUM('self', 'template', 'custom', 'ai') NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE bookings MODIFY strip_design_method ENUM('self', 'template', 'custom') NULL");
    }
};
