<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_prompt_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('key', 50);
            $table->string('label', 100);
            $table->text('prompt');
            $table->timestamps();

            $table->unique(['account_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_prompt_settings');
    }
};
