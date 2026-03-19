<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('mollie_key')->nullable()->after('currency');
            $table->boolean('mollie_enabled')->default(true)->after('mollie_key');
            $table->boolean('eboekhouden_enabled')->default(true)->after('mollie_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['mollie_key', 'mollie_enabled', 'eboekhouden_enabled']);
        });
    }
};
