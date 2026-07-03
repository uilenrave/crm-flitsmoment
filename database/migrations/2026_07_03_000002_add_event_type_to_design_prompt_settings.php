<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_prompt_settings', function (Blueprint $table) {
            $table->string('event_type', 30)->nullable()->after('key');
        });

        // Nieuwe unique index eerst toevoegen zodat er altijd een index op account_id
        // beschikbaar blijft voor de foreign key (MySQL weigert anders de oude te droppen).
        Schema::table('design_prompt_settings', function (Blueprint $table) {
            $table->unique(['account_id', 'key', 'event_type'], 'design_prompt_settings_account_key_type_unique');
        });

        Schema::table('design_prompt_settings', function (Blueprint $table) {
            $table->dropUnique('design_prompt_settings_account_id_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('design_prompt_settings', function (Blueprint $table) {
            $table->unique(['account_id', 'key'], 'design_prompt_settings_account_id_key_unique');
        });

        Schema::table('design_prompt_settings', function (Blueprint $table) {
            $table->dropUnique('design_prompt_settings_account_key_type_unique');
            $table->dropColumn('event_type');
        });
    }
};
