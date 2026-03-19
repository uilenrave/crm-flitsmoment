<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->integer('eboekhouden_relation_id')->nullable()->after('eboekhouden_invoice_id');
            $table->index(['account_id', 'eboekhouden_relation_id']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'eboekhouden_relation_id']);
            $table->dropColumn(['eboekhouden_relation_id']);
        });
    }
};
