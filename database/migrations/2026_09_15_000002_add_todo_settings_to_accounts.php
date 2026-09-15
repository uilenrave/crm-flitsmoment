<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            // Drempels voor de CRM-signalen, per account instelbaar.
            // Leeg = de defaults uit config/todo.php gebruiken.
            $table->json('todo_settings')->nullable()->after('shift_durations');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('todo_settings');
        });
    }
};
