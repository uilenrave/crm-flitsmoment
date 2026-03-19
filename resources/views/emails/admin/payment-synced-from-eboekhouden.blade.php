<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Betaling ontvangen</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #333; line-height: 1.6;">

<div style="max-width: 600px; margin: 0 auto; padding: 20px;">

    <h1 style="color: #1f2937; margin-bottom: 20px;">✅ Betaling Ontvangen</h1>

    <div style="background: #dcfce7; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #16a34a;">
        <p style="margin: 0 0 10px 0;"><strong>Boeking:</strong> {{ $booking->booking_number }}</p>
        <p style="margin: 0 0 10px 0;"><strong>Klant:</strong> {{ $booking->customer_name }}</p>
        <p style="margin: 0 0 10px 0;"><strong>Bedrag:</strong> €{{ number_format($booking->total_price, 2, ',', '.') }}</p>
        <p style="margin: 0;"><strong>Datum:</strong> {{ now()->format('d M Y H:i') }}</p>
    </div>

    <h2 style="color: #1f2937; font-size: 18px; margin: 20px 0 10px 0;">Betalingsstatus:</h2>
    <div style="background: #f0fdf4; padding: 15px; border: 1px solid #86efac; border-radius: 6px; margin-bottom: 20px;">
        <p style="margin: 0;">De betaling is automatisch gesynchroniseerd vanuit het boekhoudprogramma.</p>
    </div>

    <div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <h3 style="margin: 0 0 10px 0; color: #1f2937; font-size: 16px;">Event Details:</h3>
        <ul style="margin: 0; padding-left: 20px; color: #6b7280;">
            <li>Event datum: {{ $booking->event_date->format('d M Y') }}</li>
            <li>Klant type: {{ $booking->customer_type === 'particulier' ? 'Particulier' : 'Zakelijk' }}</li>
            <li>Boekingtype: {{ $booking->booking_type === 'full_service' ? 'Full Service' : 'To Go' }}</li>
        </ul>
    </div>

    <div style="text-align: center; margin-top: 30px;">
        <a href="{{ route('bookings.show', $booking) }}" style="display: inline-block; padding: 12px 24px; background: #16a34a; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600;">
            Naar boeking in CRM →
        </a>
    </div>

    <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">
    <p style="font-size: 12px; color: #6b7280; margin: 0;">
        Dit is een automatisch gegenereerde e-mail van het Flitsmoment CRM.<br>
        Betaling gesynchroniseerd via e-boekhouden integratie.
    </p>

</div>

</body>
</html>
