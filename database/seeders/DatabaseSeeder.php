<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Booking;
use App\Models\EventType;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{

    public function run(): void
    {
        // Account aanmaken
        $account = Account::create([
            'name'     => 'Flitsmoment Utrecht',
            'code'     => 'UTR',
            'type'     => 'headquarters',
            'email'    => 'info@flitsmoment.nl',
            'phone'    => '030 123 4567',
            'address'  => 'Utrecht, Nederland',
            'timezone' => 'Europe/Amsterdam',
            'currency' => 'EUR',
            'status'   => 'active',
        ]);

        // Admin user
        User::create([
            'account_id' => $account->id,
            'email'      => 'info@flitsmoment.nl',
            'password'   => Hash::make('wachtwoord'),
            'first_name' => 'Jesse',
            'last_name'  => 'Test',
            'role'       => 'admin',
            'status'     => 'active',
        ]);

        // Lead statussen
        $statussen = [
            ['name' => 'new',              'display_name' => 'Nieuwe lead',       'color' => '#3b82f6', 'sort_order' => 1],
            ['name' => 'contacted',        'display_name' => 'Contact opgenomen', 'color' => '#8b5cf6', 'sort_order' => 2],
            ['name' => 'offer_sent',       'display_name' => 'Offerte verstuurd', 'color' => '#f59e0b', 'sort_order' => 3],
            ['name' => 'won',              'display_name' => 'Gewonnen',          'color' => '#10b981', 'sort_order' => 4],
            ['name' => 'lost',             'display_name' => 'Verloren',          'color' => '#ef4444', 'sort_order' => 5],
        ];

        foreach ($statussen as $s) {
            LeadStatus::create(array_merge(['account_id' => $account->id], $s));
        }

        // Lead bronnen
        $bronnen = ['Website', 'Facebook', 'Instagram', 'Google Ads', 'Doorverwijzing', 'Bruiloftsbeurs'];
        foreach ($bronnen as $bron) {
            LeadSource::create(['account_id' => $account->id, 'name' => $bron]);
        }

        // Event types
        $typen = [
            ['name' => 'Bruiloft',       'base_price' => 750.00],
            ['name' => 'Verjaardag',     'base_price' => 450.00],
            ['name' => 'Bedrijfsfeest',  'base_price' => 600.00],
            ['name' => 'Kinderfeest',    'base_price' => 350.00],
            ['name' => 'Jubileum',       'base_price' => 500.00],
        ];

        foreach ($typen as $type) {
            EventType::create(array_merge(['account_id' => $account->id], $type));
        }

        // Voorbeeldlead
        $nieuwStatus = LeadStatus::where('account_id', $account->id)->where('name', 'new')->first();
        Lead::create([
            'account_id'     => $account->id,
            'lead_number'    => 'UTR-2026-001',
            'name'           => 'Lisa van den Berg',
            'email'          => 'lisa@example.nl',
            'phone'          => '06 12345678',
            'event_date'     => '2026-06-14',
            'event_location' => 'Utrecht',
            'notes'          => 'Interesse in fotobooth voor bruiloft.',
            'status_id'      => $nieuwStatus->id,
        ]);

        // Voorbeeldboeking
        Booking::create([
            'account_id'     => $account->id,
            'booking_number' => 'UTR-B-2026-001',
            'customer_name'  => 'Mark de Jong',
            'customer_email' => 'mark@example.nl',
            'customer_phone' => '06 98765432',
            'event_date'     => '2026-04-20',
            'event_location' => 'Partycentrum Den Haag',
            'base_price'     => 600.00,
            'total_price'    => 650.00,
            'status'         => 'confirmed',
            'payment_status' => 'unpaid',
            'portal_enabled' => true,
            'confirmed_at'   => now(),
        ]);
    }
}
