<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->json('intake_data')->nullable()->after('event_notes');
            $table->unsignedTinyInteger('intake_current_step')->default(0)->after('intake_data');
            $table->boolean('intake_completed')->default(false)->after('intake_current_step');
            $table->timestamp('intake_completed_at')->nullable()->after('intake_completed');
            $table->string('strip_format', 10)->nullable()->after('intake_completed_at');
            $table->string('pickup_preference', 20)->nullable()->after('strip_format');
            $table->string('pickup_contact_person', 150)->nullable()->after('pickup_preference');
            $table->time('pickup_contact_time')->nullable()->after('pickup_contact_person');

            $table->index(['account_id', 'intake_completed']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'intake_completed']);
            $table->dropColumn([
                'intake_data', 'intake_current_step', 'intake_completed', 'intake_completed_at',
                'strip_format', 'pickup_preference', 'pickup_contact_person', 'pickup_contact_time',
            ]);
        });
    }
};
