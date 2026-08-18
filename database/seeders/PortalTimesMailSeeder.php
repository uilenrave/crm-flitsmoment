<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\MailTemplate;
use App\Scopes\AccountScope;
use Illuminate\Database\Seeder;

/**
 * Seedt/actualiseert alléén de bezorg-/ophaalmoment-mails per account, zonder andere — mogelijk door
 * de admin aangepaste — templates te overschrijven. Idempotent:
 *  - customer_times_updated_to_go / _full_service   (nieuw — "moment aangepast")
 *  - customer_to_go_reminder / customer_full_service_reminder (aangescherpt — 48u herinnering)
 */
class PortalTimesMailSeeder extends Seeder
{
    public function run(): void
    {
        $templates = (new MailTemplateSeeder)->only([
            'customer_times_updated_to_go',
            'customer_times_updated_full_service',
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
