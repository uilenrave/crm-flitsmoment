<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_hours', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->enum('role', ['delivery', 'pickup', 'handover']);
            $table->decimal('hours', 4, 2);
            $table->decimal('hours_approved', 4, 2)->nullable();
            $table->enum('status', ['pending', 'approved', 'paid'])->default('pending');
            $table->text('staff_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['staff_id', 'booking_id', 'role']);
            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_hours');
    }
};
