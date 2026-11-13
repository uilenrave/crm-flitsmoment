<?php

namespace App\Mail;

use App\Models\BoothEvent;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Directe melding bij een booth-gebeurtenis (update geslaagd/mislukt, rollback, zelftest gefaald). */
class BoothEventNotification extends Mailable
{
    public function __construct(public BoothEvent $event) {}

    public function envelope(): Envelope
    {
        $icon = $this->event->isOk() ? '✅' : '⚠️';
        $name = $this->event->booth?->displayName() ?? 'Booth';

        return new Envelope(subject: "{$icon} {$name}: {$this->event->label()}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin.booth-event', with: ['event' => $this->event]);
    }
}
