<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback op fotostrip ontwerp</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #333; line-height: 1.6;">

<div style="max-width: 600px; margin: 0 auto; padding: 20px;">

    <h1 style="color: #1f2937; margin-bottom: 20px;">📝 Feedback op Fotostrip Ontwerp</h1>

    <div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #fcd34d;">
        <p style="margin: 0 0 10px 0;"><strong>Boeking:</strong> {{ $booking->booking_number }}</p>
        <p style="margin: 0 0 10px 0;"><strong>Klant:</strong> {{ $booking->customer_name }}</p>
        <p style="margin: 0 0 10px 0;"><strong>Eventdatum:</strong> {{ $booking->event_date->format('d M Y') }}</p>
        <p style="margin: 0;"><strong>Versie:</strong> {{ $booking->strip_version ?? 1 }}</p>
    </div>

    <h2 style="color: #1f2937; font-size: 18px; margin: 20px 0 10px 0;">Feedback van klant:</h2>
    <div style="background: #ffffff; padding: 15px; border: 1px solid #e5e7eb; border-radius: 6px; margin-bottom: 20px;">
        <p style="margin: 0; white-space: pre-wrap;">{{ $booking->strip_feedback }}</p>
    </div>

    <div style="background: #eff6ff; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #3b82f6;">
        <p style="margin: 0 0 10px 0;"><strong>Feedback gegeven:</strong> {{ $booking->strip_feedback_at->format('d M Y H:i') }}</p>
        <p style="margin: 0;">Pas het ontwerp aan en upload een nieuwe versie in het CRM.</p>
    </div>

    <div style="text-align: center; margin-top: 30px;">
        <a href="{{ route('bookings.show', $booking) }}" style="display: inline-block; padding: 12px 24px; background: #fcd34d; color: #000; text-decoration: none; border-radius: 6px; font-weight: 600;">
            Naar boeking in CRM →
        </a>
    </div>

    <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">
    <p style="font-size: 12px; color: #6b7280; margin: 0;">
        Dit is een automatisch gegenereerde e-mail van het Flitsmoment CRM.
    </p>

</div>

</body>
</html>
