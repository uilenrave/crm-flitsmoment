<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 100);
            $table->string('color', 7)->default('#64748b');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['account_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_lists');
    }
};
