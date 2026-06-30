<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Aanvullende facturen (handmatig in e-Boekhouden gemaakt), gekoppeld aan de boeking.
            // Lijst van {number, description, amount, added_at}.
            $table->json('extra_invoices')->nullable()->after('eboekhouden_skip_invoice');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('extra_invoices');
        });
    }
};
