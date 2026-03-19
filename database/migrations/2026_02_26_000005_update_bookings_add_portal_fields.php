<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Boekingstype
            $table->enum('booking_type', ['full_service', 'to_go'])->default('full_service')->after('booking_number');

            // Tijden
            $table->time('event_start_time')->nullable()->after('event_date');
            $table->time('event_end_time')->nullable()->after('event_start_time');

            // Full Service: bezorgen en ophalen door ons
            $table->dateTime('delivery_at')->nullable()->after('event_end_time');
            $table->dateTime('pickup_at')->nullable()->after('delivery_at');

            // To Go: klant haalt op en brengt terug
            $table->dateTime('customer_pickup_at')->nullable()->after('pickup_at');
            $table->dateTime('customer_return_at')->nullable()->after('customer_pickup_at');

            // Adresgegevens apart (voor Google Maps)
            $table->string('event_address', 255)->nullable()->after('event_location');
            $table->string('event_postcode', 10)->nullable()->after('event_address');
            $table->string('event_city', 100)->nullable()->after('event_postcode');

            // Fotostrip status
            $table->enum('strip_status', ['waiting', 'in_progress', 'done'])->default('waiting')->after('event_city');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'booking_type',
                'event_start_time', 'event_end_time',
                'delivery_at', 'pickup_at',
                'customer_pickup_at', 'customer_return_at',
                'event_address', 'event_postcode', 'event_city',
                'strip_status',
            ]);
        });
    }
};
