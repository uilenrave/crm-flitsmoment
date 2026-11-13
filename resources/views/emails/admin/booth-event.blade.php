<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $event->label() }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #333; line-height: 1.6;">
@php
    $ok     = $event->isOk();
    $bg     = $ok ? '#dcfce7' : '#fef3c7';
    $border = $ok ? '#16a34a' : '#d97706';
@endphp
<div style="max-width: 600px; margin: 0 auto; padding: 20px;">

    <h1 style="color: #1f2937; font-size: 20px; margin-bottom: 20px;">{{ $ok ? '✅' : '⚠️' }} {{ $event->label() }}</h1>

    <div style="background: {{ $bg }}; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid {{ $border }};">
        <p style="margin: 0 0 10px 0;"><strong>Booth:</strong> {{ $event->booth?->displayName() ?? 'Onbekend' }}</p>
        @if($event->app_version)
            <p style="margin: 0 0 10px 0;"><strong>Versie:</strong> {{ $event->app_version }}</p>
        @endif
        <p style="margin: 0;"><strong>Tijdstip:</strong> {{ $event->created_at->setTimezone('Europe/Amsterdam')->format('d-m-Y H:i') }}</p>
    </div>

    @if($event->message)
        <div style="background: #f9fafb; padding: 15px; border: 1px solid #e5e7eb; border-radius: 6px; margin-bottom: 20px;">
            <p style="margin: 0; white-space: pre-line;">{{ $event->message }}</p>
        </div>
    @endif

    <div style="text-align: center; margin-top: 30px;">
        <a href="{{ route('booths.index') }}" style="display: inline-block; padding: 12px 24px; background: #1f2937; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600;">
            Naar booths in CRM →
        </a>
    </div>

    <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">
    <p style="font-size: 12px; color: #6b7280; margin: 0;">Automatische melding van het Flitsmoment CRM.</p>

</div>
</body>
</html>
