<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('eboekhouden_invoice_id', 50)->nullable()->after('public_token');
            $table->string('eboekhouden_status', 20)->default('draft')->after('eboekhouden_invoice_id');
            $table->timestamp('eboekhouden_synced_at')->nullable()->after('eboekhouden_status');

            $table->index(['account_id', 'eboekhouden_invoice_id']);
            $table->index(['account_id', 'eboekhouden_status']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'eboekhouden_invoice_id']);
            $table->dropIndex(['account_id', 'eboekhouden_status']);
            $table->dropColumn(['eboekhouden_invoice_id', 'eboekhouden_status', 'eboekhouden_synced_at']);
        });
    }
};
