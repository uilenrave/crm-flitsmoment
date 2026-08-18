<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vrijgestelde elementen (transparante PNG's) — gedeelde bibliotheek, parallel aan design_templates.
 * Elke AI-vrijstelling komt als 'pending' binnen; na goedkeuring + categorie herbruikbaar in de generator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('design_element_categories')->nullOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('image_path');
            $table->string('label')->nullable();
            $table->enum('source', ['admin_generator', 'customer_portal', 'admin_upload'])->default('admin_generator');
            $table->foreignId('source_account_id')->nullable();
            $table->foreignId('source_booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->integer('usage_count')->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_elements');
    }
};
