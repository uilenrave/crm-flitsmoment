<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('action');                 // opbouwen|afbouwen|afgeven|aannemen
            $table->integer('sort_order')->default(0);
            $table->integer('duration_minutes');      // prefill uit account-default, per klus aanpasbaar
            $table->timestamps();

            // Een klus (boeking + actie) kan in max één shift zitten
            $table->unique(['booking_id', 'action']);
            $table->index(['shift_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_jobs');
    }
};
