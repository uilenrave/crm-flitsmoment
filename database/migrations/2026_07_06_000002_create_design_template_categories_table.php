<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categorieën voor de fotostrip-achtergrond-templatebibliotheek. Bewust GEEN account_id:
 * de templatebibliotheek is één gedeelde set voor beide Flitsmoment-vestigingen (net als de
 * huidige gedeelde map op schijf).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_template_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('label', 120);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_template_categories');
    }
};
