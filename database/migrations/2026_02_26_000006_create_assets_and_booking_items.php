<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Voorraad / assets
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->enum('category', ['photobooth', 'background', 'prop_box', 'extra']);
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['account_id', 'category', 'is_active']);
        });

        // Boekingsregels (wat zit er in de boeking)
        Schema::create('booking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name_snapshot', 150);   // naam op moment van boeken
            $table->decimal('price_snapshot', 10, 2); // prijs op moment van boeken
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->timestamps();

            $table->index(['booking_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_items');
        Schema::dropIfExists('assets');
    }
};
