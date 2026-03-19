<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Add feedback field for customer feedback on strip design
            $table->text('strip_feedback')->nullable()->after('strip_design_url')->comment('Customer feedback on the strip design');
            
            // Timestamp when feedback was given
            $table->datetime('strip_feedback_at')->nullable()->after('strip_feedback')->comment('When the customer provided feedback');
            
            // Version tracking for design iterations
            $table->integer('strip_version')->default(1)->after('strip_feedback_at')->comment('Design version counter');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['strip_feedback', 'strip_feedback_at', 'strip_version']);
        });
    }
};
