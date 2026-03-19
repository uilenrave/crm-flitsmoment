<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('public_token', 64)->unique();
            $table->string('title', 200)->default('Offerte Photobooth');
            $table->json('line_items')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->text('terms_text')->nullable();
            $table->enum('status', ['draft', 'sent', 'accepted', 'expired', 'declined'])->default('draft');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'lead_id']);
            $table->index(['account_id', 'status']);
            $table->index(['public_token']);
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('offer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('booking_number', 50);
            $table->string('customer_name', 150);
            $table->string('customer_email', 255)->nullable();
            $table->string('customer_phone', 50)->nullable();
            $table->date('event_date');
            $table->text('event_location')->nullable();
            $table->text('event_notes')->nullable();
            $table->decimal('base_price', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->decimal('amount_total', 10, 2)->default(0);
            $table->enum('status', ['confirmed', 'cancelled', 'completed', 'no_show'])->default('confirmed');
            $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'cancelled', 'refunded'])->default('unpaid');
            $table->string('public_token', 64)->unique()->nullable();
            $table->boolean('portal_enabled')->default(false);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'booking_number']);
            $table->index(['account_id', 'status']);
            $table->index(['account_id', 'event_date']);
            $table->index(['account_id', 'payment_status']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->enum('provider', ['mollie', 'stripe', 'manual']);
            $table->string('provider_payment_id', 255)->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->text('description')->nullable();
            $table->enum('status', ['created', 'pending', 'paid', 'failed', 'canceled', 'expired', 'refunded'])->default('created');
            $table->timestamp('paid_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'booking_id']);
            $table->index(['provider', 'provider_payment_id']);
            $table->index(['account_id', 'status']);
        });

        Schema::create('booking_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('reference_code', 20);
            $table->timestamp('created_at')->nullable();

            $table->unique(['account_id', 'reference_code']);
            $table->unique(['booking_id']);
            $table->index(['account_id', 'reference_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_references');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('offers');
    }
};
