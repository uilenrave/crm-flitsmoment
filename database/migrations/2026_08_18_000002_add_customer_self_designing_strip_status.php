<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nieuwe fotostrip-status 'customer_self_designing' ("Klant ontwerpt zelf") — apart van
 * de generieke 'awaiting_customer_design', zodat direct zichtbaar is dat de klant zelf
 * ontwerpt (via template/Canva/Photoshop of de AI-tool) i.p.v. dat wij nog iets moeten doen.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE bookings MODIFY COLUMN strip_status ENUM('waiting_input','awaiting_customer_design','customer_self_designing','designing','review','accepted','ready') NULL");
    }

    public function down(): void
    {
        DB::table('bookings')
            ->where('strip_status', 'customer_self_designing')
            ->update(['strip_status' => 'awaiting_customer_design']);

        DB::statement("ALTER TABLE bookings MODIFY COLUMN strip_status ENUM('waiting_input','awaiting_customer_design','designing','review','accepted','ready') NULL");
    }
};
