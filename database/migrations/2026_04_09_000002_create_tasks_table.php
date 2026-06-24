<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_list_id')->constrained('task_lists')->cascadeOnDelete();
            $table->foreignId('parent_task_id')->nullable()->constrained('tasks')->cascadeOnDelete();
            $table->string('title', 500);
            $table->text('notes')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['account_id', 'task_list_id']);
            $table->index(['task_list_id', 'sort_order']);
            $table->index(['parent_task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
