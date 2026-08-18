<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Portaal-overhaul bezorg/ophaalmomenten:
 *  - contactpersoon op locatie (alleen Full Service in de UI)
 *  - wijzigingsverzoek van de klant (pending_change JSON) + 1-klik goedkeur-token
 * Alle kolommen nullable; bestaande data blijft ongemoeid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('location_contact_type', 30)->nullable()->after('pickup_contact_time');
            $table->string('location_contact_phone', 40)->nullable()->after('location_contact_type');
            $table->string('location_contact_email', 150)->nullable()->after('location_contact_phone');

            $table->json('pending_change')->nullable()->after('location_contact_email');
            $table->string('pending_change_token', 64)->nullable()->index()->after('pending_change');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'location_contact_type',
                'location_contact_phone',
                'location_contact_email',
                'pending_change',
                'pending_change_token',
            ]);
        });
    }
};
