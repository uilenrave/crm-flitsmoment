<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('lead_number', 50);
            $table->string('name', 150);
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->date('event_date')->nullable();
            $table->text('event_location')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('status_id')->constrained('lead_statuses')->restrictOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('lead_sources')->nullOnDelete();
            $table->foreignId('event_type_id')->nullable()->constrained('event_types')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['account_id', 'lead_number']);
            $table->index(['account_id', 'status_id']);
            $table->index(['account_id', 'event_date']);
            $table->index(['account_id', 'created_at']);
        });

        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('activity_type', ['note', 'call', 'email', 'meeting', 'quote_sent', 'status_change']);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('old_status', 50)->nullable();
            $table->string('new_status', 50)->nullable();
            $table->timestamps();

            $table->index(['account_id', 'lead_id']);
            $table->index(['account_id', 'user_id']);
            $table->index(['lead_id', 'activity_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_activities');
        Schema::dropIfExists('leads');
    }
};
