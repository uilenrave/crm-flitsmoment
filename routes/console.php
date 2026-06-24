<?php

use App\Models\Booking;
use App\Scopes\AccountScope;
use App\Services\MailService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Dagelijkse mailautomatisering om 08:00
 *
 * Lokaal testen: php artisan schedule:work
 * Productie cron: * * * * * cd /pad/naar/project && php artisan schedule:run >> /dev/null 2>&1
 */
Schedule::call(function () {
    $mail = app(MailService::class);

    // ── 1. 7 dagen na event: upsell gedrukte foto's → klant ──
    Booking::withoutGlobalScope(AccountScope::class)
        ->with('account')
        ->whereDate('event_date', now()->subDays(7))
        ->whereNotIn('status', ['cancelled', 'no_show'])
        ->each(function (Booking $booking) use ($mail) {
            if ($booking->customer_email) {
                $mail->send('customer_photo_upsell', $booking, $booking->customer_email);
            }
        });

    // ── 2. 7 dagen voor event: nog geen fotostrip ontwerp → admin ──
    Booking::withoutGlobalScope(AccountScope::class)
        ->with('account')
        ->whereDate('event_date', now()->addDays(7))
        ->whereNull('strip_design_url')
        ->whereNotIn('status', ['cancelled', 'no_show'])
        ->each(function (Booking $booking) use ($mail) {
            if ($booking->account->email) {
                $mail->send('admin_strip_reminder', $booking, $booking->account->email);
            }
        });

    // ── 3. Dag na event: foto's digitaal doorsturen reminder → admin ──
    Booking::withoutGlobalScope(AccountScope::class)
        ->with('account')
        ->whereDate('event_date', now()->subDay())
        ->whereNotIn('status', ['cancelled', 'no_show'])
        ->each(function (Booking $booking) use ($mail) {
            if ($booking->account->email) {
                $mail->send('admin_event_finished', $booking, $booking->account->email);
            }
        });

    // ── 4. 14 dagen voor event: nog geen fotostrip-methode gekozen → klant ──
    Booking::withoutGlobalScope(AccountScope::class)
        ->with('account')
        ->where('status', 'confirmed')
        ->whereDate('event_date', now()->addDays(14))
        ->whereNull('strip_design_method')
        ->whereNotNull('customer_email')
        ->each(function (Booking $booking) use ($mail) {
            $mail->send('customer_strip_choice_reminder', $booking, $booking->customer_email);
        });

})->daily()->at('08:00')->name('dagelijkse-mail-automatisering')->withoutOverlapping();

/**
 * Elk uur: review-mail 24u na event starttijd
 *
 * Stuurt één keer een review verzoek naar klanten waarvan het event
 * 24 uur geleden is gestart (op basis van event_date + event_start_time).
 */
Schedule::call(function () {
    $mail = app(MailService::class);

    Booking::withoutGlobalScope(AccountScope::class)
        ->with('account')
        ->whereNull('review_mail_sent_at')
        ->whereNotNull('customer_email')
        ->whereIn('status', ['confirmed', 'completed'])
        ->whereRaw("TIMESTAMP(event_date, IFNULL(event_start_time, '00:00:00')) <= DATE_SUB(NOW(), INTERVAL 24 HOUR)")
        ->whereRaw("TIMESTAMP(event_date, IFNULL(event_start_time, '00:00:00')) >= DATE_SUB(NOW(), INTERVAL 7 DAY)")
        ->each(function (Booking $booking) use ($mail) {
            $sent = $mail->send('customer_review_request', $booking, $booking->customer_email);
            if ($sent) {
                $booking->update(['review_mail_sent_at' => now()]);
            }
        });

})->hourly()->name('review-mail-automatisering')->withoutOverlapping();
