<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strip_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->integer('number');
            $table->string('name')->nullable();
            $table->enum('theme', ['bruiloft', 'bedrijfsfeest', 'verjaardag', 'kerst']);
            $table->enum('format', ['strips_5x15', 'photo_10x15']);
            $table->string('image_path');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['account_id', 'number']);
            $table->index(['account_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strip_templates');
    }
};
