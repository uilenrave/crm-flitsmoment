<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stap 1: ENUM uitbreiden met ALLE waarden (oud + nieuw) zodat UPDATE veilig is
        DB::statement("ALTER TABLE bookings MODIFY COLUMN strip_status ENUM('waiting','in_progress','review','accepted','done','waiting_input','designing','ready') NULL");

        // Stap 2: Oude waarden hernoemen
        DB::statement("UPDATE bookings SET strip_status = 'waiting_input' WHERE strip_status = 'waiting'");
        DB::statement("UPDATE bookings SET strip_status = 'designing'     WHERE strip_status = 'in_progress'");
        DB::statement("UPDATE bookings SET strip_status = 'ready'         WHERE strip_status = 'done'");

        // Stap 3: ENUM beperken tot enkel nieuwe waarden
        DB::statement("ALTER TABLE bookings MODIFY COLUMN strip_status ENUM('waiting_input','designing','review','accepted','ready') NULL");

        // Stap 4: strip_intake_data kolom toevoegen
        Schema::table('bookings', function (Blueprint $table) {
            $table->json('strip_intake_data')->nullable()->after('strip_designs');
        });
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE bookings MODIFY COLUMN strip_status ENUM('waiting_input','designing','review','accepted','ready','waiting','in_progress','done') NULL");

        DB::statement("UPDATE bookings SET strip_status = 'waiting'     WHERE strip_status = 'waiting_input'");
        DB::statement("UPDATE bookings SET strip_status = 'in_progress' WHERE strip_status = 'designing'");
        DB::statement("UPDATE bookings SET strip_status = 'done'        WHERE strip_status = 'ready'");

        DB::statement("ALTER TABLE bookings MODIFY COLUMN strip_status ENUM('waiting','in_progress','review','accepted','done') NULL");

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('strip_intake_data');
        });
    }
};
