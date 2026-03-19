<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('assigned_to');
            $table->string('archive_reason', 50)->nullable()->after('archived_at'); // 'won' of 'lost'
            $table->index(['account_id', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['archived_at', 'archive_reason']);
        });
    }
};
