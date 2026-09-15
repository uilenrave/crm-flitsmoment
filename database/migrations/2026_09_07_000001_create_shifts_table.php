<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->date('shift_date');
            $table->dateTime('start_at')->nullable();          // vertrek vanaf startlocatie
            $table->string('start_address')->nullable();        // default = account.origin_address
            $table->string('end_address')->nullable();          // optionele afwijkende eindlocatie
            $table->text('notes')->nullable();
            $table->json('route_cache')->nullable();            // etappes + aankomst/vertrek + totalen + hash
            $table->integer('total_minutes')->nullable();       // cache voor overzicht
            $table->dateTime('end_at')->nullable();             // cache: berekende eindtijd
            $table->timestamps();

            $table->index(['account_id', 'shift_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
