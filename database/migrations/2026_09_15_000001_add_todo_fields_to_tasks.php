<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Soort item: taak | kopen | leren
            $table->string('type', 16)->default('taak')->after('task_list_id');

            // Herkomst: handmatig | crm | mail
            $table->string('source', 16)->default('handmatig')->after('type');

            // Verwijzing naar de CRM-rij waar dit item uit voortkomt
            $table->string('source_type', 40)->nullable()->after('source');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');

            // Unieke sleutel per signaal, zodat de sync niet elk uur dubbel aanmaakt
            $table->string('signal_key', 120)->nullable()->after('source_id');

            // Koop-items
            $table->unsignedInteger('price_cents')->nullable()->after('notes');
            $table->string('vendor', 120)->nullable()->after('price_cents');

            // Leer-items (en webshoplink bij kopen)
            $table->string('url', 500)->nullable()->after('vendor');
            $table->string('kind', 32)->nullable()->after('url');

            // "Morgen" wegleggen zonder de deadline te verzetten
            $table->date('snoozed_until')->nullable()->after('due_date');

            // Nu ongebruikt: alleen Jesse. Voorkomt later een migratie.
            $table->unsignedBigInteger('owner_id')->nullable()->after('account_id');

            $table->index(['account_id', 'type']);
            $table->index(['account_id', 'due_date']);
            $table->unique(['account_id', 'signal_key']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique(['account_id', 'signal_key']);
            $table->dropIndex(['account_id', 'type']);
            $table->dropIndex(['account_id', 'due_date']);
            $table->dropColumn([
                'type', 'source', 'source_type', 'source_id', 'signal_key',
                'price_cents', 'vendor', 'url', 'kind', 'snoozed_until', 'owner_id',
            ]);
        });
    }
};
