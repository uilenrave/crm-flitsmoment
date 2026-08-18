<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\MailTemplate;
use App\Scopes\AccountScope;
use Illuminate\Database\Seeder;

/**
 * Voegt alléén de twee 48u-herinneringstemplates toe (To Go + Full Service) per account,
 * zonder bestaande — mogelijk door de admin aangepaste — templates te overschrijven.
 * Idempotent: opnieuw draaien werkt alleen deze twee keys bij.
 */
class ToGoFullServiceReminderSeeder extends Seeder
{
    public function run(): void
    {
        $templates = (new MailTemplateSeeder)->only([
            'customer_to_go_reminder',
            'customer_full_service_reminder',
        ]);

        foreach (Account::all() as $account) {
            foreach ($templates as $tpl) {
                MailTemplate::withoutGlobalScope(AccountScope::class)
                    ->updateOrCreate(
                        ['account_id' => $account->id, 'key' => $tpl['key']],
                        array_merge($tpl, ['account_id' => $account->id])
                    );
            }
        }
    }
}
