<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\MailTemplate;
use App\Scopes\AccountScope;
use Illuminate\Database\Seeder;

/**
 * Seedt/actualiseert alléén het "factuur staat klaar"-template per account, zonder andere
 * (mogelijk door de admin aangepaste) templates te overschrijven. Idempotent.
 */
class InvoiceReadyMailSeeder extends Seeder
{
    public function run(): void
    {
        $templates = (new MailTemplateSeeder)->only(['customer_invoice_ready']);

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
