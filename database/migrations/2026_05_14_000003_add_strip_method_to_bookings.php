<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('strip_design_method', ['self', 'template', 'custom'])->nullable()->after('strip_format');
            $table->enum('strip_self_tool', ['canva', 'photoshop'])->nullable()->after('strip_design_method');
            $table->foreignId('strip_template_id')->nullable()->after('strip_self_tool')
                  ->constrained('strip_templates')->nullOnDelete();
        });

        // Voeg 'awaiting_customer_design' toe aan de strip_status enum
        DB::statement("ALTER TABLE bookings MODIFY COLUMN strip_status ENUM('waiting_input','awaiting_customer_design','designing','review','accepted','ready') NULL");
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('strip_template_id');
            $table->dropColumn(['strip_design_method', 'strip_self_tool']);
        });

        DB::statement("ALTER TABLE bookings MODIFY COLUMN strip_status ENUM('waiting_input','designing','review','accepted','ready') NULL");
    }
};
