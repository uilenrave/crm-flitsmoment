<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ander terugbrengtijdstip aangevraagd</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #333; line-height: 1.6;">

<div style="max-width: 600px; margin: 0 auto; padding: 20px;">

    <h1 style="color: #1f2937; margin-bottom: 20px;">⏰ Ander terugbrengtijdstip aangevraagd</h1>

    <div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #f97316;">
        <p style="margin: 0 0 8px 0;"><strong>Boeking:</strong> {{ $booking->booking_number }}</p>
        <p style="margin: 0 0 8px 0;"><strong>Klant:</strong> {{ $booking->customer_name }}</p>
        <p style="margin: 0 0 8px 0;"><strong>Eventdatum:</strong> {{ $booking->event_date->translatedFormat('l j F Y') }}</p>
        <p style="margin: 0;"><strong>Terugbrengdatum:</strong> {{ $booking->customer_return_at?->translatedFormat('l j F Y') ?? '—' }}</p>
    </div>

    <div style="background: #fff7ed; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #f97316;">
        <p style="margin: 0 0 6px 0; font-size: 14px; color: #6b7280;">Huidig tijdstip</p>
        <p style="margin: 0 0 16px 0; font-size: 20px; font-weight: 700; color: #374151; text-decoration: line-through;">
            {{ $booking->customer_return_at?->format('H:i') ?? '—' }}
        </p>
        <p style="margin: 0 0 6px 0; font-size: 14px; color: #6b7280;">Aangevraagd tijdstip</p>
        <p style="margin: 0; font-size: 28px; font-weight: 700; color: #ea580c;">
            {{ $booking->proposed_return_time }}
        </p>
    </div>

    <p style="color: #374151; margin-bottom: 24px;">
        Ga naar de boeking in het CRM om dit verzoek goed te keuren of af te wijzen.
    </p>

    <div style="text-align: center; margin-top: 30px;">
        <a href="{{ route('bookings.show', $booking) }}" style="display: inline-block; padding: 14px 28px; background: #ea580c; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 16px;">
            Beoordelen in CRM →
        </a>
    </div>

    <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">
    <p style="font-size: 12px; color: #6b7280; margin: 0;">
        Dit is een automatisch gegenereerde e-mail van het Flitsmoment CRM.
    </p>

</div>

</body>
</html>
