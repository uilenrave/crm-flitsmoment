<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fotostrip-achtergrond-templates. Elke door admin/klant gegenereerde achtergrond komt hier
 * binnen als 'pending' en wordt door de admin goedgekeurd + gecategoriseerd. Goedgekeurde
 * templates verschijnen in de "Kies een template"-picker. Gedeeld (geen account_id); source_*
 * legt vast waar een template vandaan kwam (traceerbaarheid).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()
                ->constrained('design_template_categories')->nullOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('image_path');
            $table->string('label', 160)->nullable();
            $table->enum('source', ['disk_import', 'admin_generator', 'customer_portal', 'admin_upload']);
            $table->unsignedBigInteger('source_account_id')->nullable();
            $table->foreignId('source_booking_id')->nullable()
                ->constrained('bookings')->nullOnDelete();
            $table->unsignedInteger('usage_count')->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_templates');
    }
};
