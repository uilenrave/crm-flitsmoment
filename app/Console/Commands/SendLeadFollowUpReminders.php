<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\Account;
use App\Services\MailService;
use Illuminate\Console\Command;

class SendLeadFollowUpReminders extends Command
{
    protected $signature   = 'leads:send-followup-reminders';
    protected $description = 'Stuur dagelijkse follow-up herinneringen voor leads';

    public function handle(MailService $mail): void
    {
        $today = now()->toDateString();

        // Groepeer per account zodat er één mail per account gaat
        $leads = Lead::withoutGlobalScopes()
            ->whereNull('archived_at')
            ->whereDate('follow_up_at', '<=', $today)
            ->with(['account'])
            ->get()
            ->groupBy('account_id');

        foreach ($leads as $accountId => $accountLeads) {
            $account = Account::find($accountId);
            if (! $account?->email) continue;

            $rows = '';
            foreach ($accountLeads as $lead) {
                $url      = url('/leads/' . $lead->id);
                $datum    = $lead->follow_up_at->format('d M Y');
                $overdue  = $lead->follow_up_at->isPast() && ! $lead->follow_up_at->isToday();
                $datumHtml = $overdue
                    ? "<span style='color:#dc2626;font-weight:700;'>{$datum} (achterstallig)</span>"
                    : "<span style='color:#d97706;font-weight:700;'>{$datum}</span>";

                $rows .= "
                <tr>
                    <td style='padding:10px 12px;border-bottom:1px solid #f1f5f9;'>
                        <a href='{$url}' style='color:#1d4ed8;font-weight:600;text-decoration:none;'>{$lead->lead_number}</a>
                    </td>
                    <td style='padding:10px 12px;border-bottom:1px solid #f1f5f9;'>{$lead->name}</td>
                    <td style='padding:10px 12px;border-bottom:1px solid #f1f5f9;'>
                        " . ($lead->phone ? "<a href='tel:{$lead->phone}' style='color:#374151;text-decoration:none;'>{$lead->phone}</a>" : '—') . "
                    </td>
                    <td style='padding:10px 12px;border-bottom:1px solid #f1f5f9;'>{$datumHtml}</td>
                    <td style='padding:10px 12px;border-bottom:1px solid #f1f5f9;'>
                        <a href='{$url}' style='display:inline-block;background:#f59e0b;color:#fff;padding:5px 12px;border-radius:6px;text-decoration:none;font-size:12px;font-weight:600;'>Openen →</a>
                    </td>
                </tr>";
            }

            $count   = $accountLeads->count();
            $subject = "📅 {$count} lead" . ($count === 1 ? '' : 's') . " om op te volgen vandaag";

            $html = "
            <div style='font-family:sans-serif;max-width:640px;margin:0 auto;padding:24px;'>
                <h2 style='margin:0 0 8px;font-size:18px;color:#1e293b;'>📅 Follow-up herinneringen</h2>
                <p style='color:#64748b;margin:0 0 20px;font-size:14px;'>
                    Je hebt vandaag <strong>{$count} lead" . ($count === 1 ? '' : 's') . "</strong> die opvolging nodig heeft.
                </p>
                <table style='width:100%;border-collapse:collapse;font-size:13px;'>
                    <thead>
                        <tr style='background:#f8fafc;'>
                            <th style='padding:8px 12px;text-align:left;font-weight:600;color:#64748b;border-bottom:2px solid #e2e8f0;'>Lead</th>
                            <th style='padding:8px 12px;text-align:left;font-weight:600;color:#64748b;border-bottom:2px solid #e2e8f0;'>Naam</th>
                            <th style='padding:8px 12px;text-align:left;font-weight:600;color:#64748b;border-bottom:2px solid #e2e8f0;'>Telefoon</th>
                            <th style='padding:8px 12px;text-align:left;font-weight:600;color:#64748b;border-bottom:2px solid #e2e8f0;'>Datum</th>
                            <th style='padding:8px 12px;border-bottom:2px solid #e2e8f0;'></th>
                        </tr>
                    </thead>
                    <tbody>{$rows}</tbody>
                </table>
                <p style='margin-top:20px;font-size:12px;color:#94a3b8;'>
                    Na opvolging kun je de datum aanpassen of verwijderen in de lead.
                </p>
            </div>";

            $mail->sendRaw($account->email, $subject, $html);

            $this->info("Mail verstuurd naar {$account->email} ({$count} leads)");
        }

        $this->info('Follow-up herinneringen verstuurd.');
    }
}
