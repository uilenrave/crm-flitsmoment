<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ride_signups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->index();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            // Rol van de rit: delivery | pickup | handover
            $table->string('role', 20);
            // Antwoord van de medewerker: yes = kan de rit doen, no = kan niet
            $table->string('response', 10)->default('yes');
            $table->timestamps();

            // Eén aanmelding per medewerker per rit (idempotent bij gelijktijdig aanmelden)
            $table->unique(['booking_id', 'role', 'staff_id']);
            $table->index(['booking_id', 'role']);
            $table->index('staff_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_signups');
    }
};
