<?php

namespace App\Mail;

use App\Models\Account;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Dagelijkse samenvatting van alle booths van een account. */
class BoothDailyDigest extends Mailable
{
    /** @param array{rows: array, events: \Illuminate\Support\Collection, upcoming: array, latest: array} $data */
    public function __construct(public Account $account, public array $data) {}

    public function envelope(): Envelope
    {
        $problems = collect($this->data['rows'])->filter(fn ($r) => $r['selftest_ok'] === false || $r['behind'])->count();
        $suffix   = $problems > 0 ? " — {$problems} aandachtspunt" . ($problems > 1 ? 'en' : '') : ' — alles in orde';

        return new Envelope(subject: 'Booths dagoverzicht ' . now()->setTimezone('Europe/Amsterdam')->format('d-m') . $suffix);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin.booth-daily-digest', with: $this->data + ['account' => $this->account]);
    }
}
