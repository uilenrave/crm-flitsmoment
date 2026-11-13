<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Gebeurtenissen die een booth meldt (update geslaagd/mislukt, rollback, zelftest gefaald, …). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booth_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booth_token_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('app_version', 30)->nullable();
            $table->text('message')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('mailed_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booth_events');
    }
};
