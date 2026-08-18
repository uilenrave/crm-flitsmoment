<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only logboek van AI-generaties per account, zodat we in de instellingen kunnen tonen
 * hoeveel "credits" (generaties) elk account gebruikt — Gemini (achtergronden) en GPT/OpenAI
 * (logo's vrijstellen). Bewust los van design_templates (die worden goedgekeurd/verwijderd en
 * zijn dus geen betrouwbare verbruiksteller).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 32);   // gemini | openai
            $table->string('operation', 40);  // background | logo_cutout
            $table->timestamps();

            $table->index(['account_id', 'provider']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_events');
    }
};
