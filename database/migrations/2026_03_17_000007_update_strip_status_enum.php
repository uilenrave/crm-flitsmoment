<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE bookings MODIFY COLUMN strip_status ENUM('waiting','in_progress','review','accepted','done') NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE bookings MODIFY COLUMN strip_status ENUM('waiting','in_progress','done') NULL");
    }
};
