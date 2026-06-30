<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Mollie-betaallink die e-Boekhouden op de factuur-PDF plaatst (paynow.asp).
            // Betaalt de klant hierop, dan koppelt e-Boekhouden het automatisch aan de factuur.
            $table->string('eboekhouden_pay_url', 500)->nullable()->after('extra_invoices');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('eboekhouden_pay_url');
        });
    }
};
