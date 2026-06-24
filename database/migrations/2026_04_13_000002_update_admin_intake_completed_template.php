<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $newBody = <<<'HTML'
<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"></head>
<body style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#333;line-height:1.6;margin:0;padding:0;background:#f3f4f6;">
<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.1);">
  <div style="background:#ea580c;padding:24px 32px;">
    <h1 style="color:#fff;margin:0;font-size:20px;">📋 Intake ingevuld</h1>
  </div>
  <div style="padding:28px 32px;">
    <p><strong>{{klant_naam}}</strong> heeft de intake ingevuld voor boeking <strong>{{boeking_nummer}}</strong>.</p>
    <table width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;border-collapse:collapse;">
      <tr style="background:#f8fafc;"><td style="padding:8px 12px;font-weight:600;border:1px solid #e2e8f0;width:40%;">Event datum</td><td style="padding:8px 12px;border:1px solid #e2e8f0;">{{event_datum}}</td></tr>
      <tr><td style="padding:8px 12px;font-weight:600;border:1px solid #e2e8f0;">Locatie</td><td style="padding:8px 12px;border:1px solid #e2e8f0;">{{event_locatie}}</td></tr>
      <tr style="background:#f8fafc;"><td style="padding:8px 12px;font-weight:600;border:1px solid #e2e8f0;">Printformaat</td><td style="padding:8px 12px;border:1px solid #e2e8f0;">{{strip_formaat}}</td></tr>
      {{to_go_tijden_sectie}}
    </table>
    <p style="text-align:center;margin:28px 0;">
      <a href="{{crm_boeking_link}}" style="background:#ea580c;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:700;font-size:15px;display:inline-block;">Bekijken &amp; goedkeuren in CRM →</a>
    </p>
  </div>
  <div style="padding:16px 32px;background:#f9fafb;border-top:1px solid #e5e7eb;">
    <p style="font-size:12px;color:#9ca3af;margin:0;">Automatisch gegenereerd door het Flitsmoment CRM</p>
  </div>
</div>
</body></html>
HTML;

        DB::table('mail_templates')
            ->where('key', 'admin_intake_completed')
            ->update(['body' => $newBody]);
    }

    public function down(): void
    {
        // Niet terugdraaien — originele template was al in DB
    }
};
