<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('briefings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('title', 200)->nullable();
            $table->date('date_from');
            $table->date('date_to');
            // notes keyed by "{booking_id}:{role}" → free-text note
            $table->json('notes')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'staff_id']);
            $table->index(['date_from', 'date_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('briefings');
    }
};
