<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booths dagoverzicht</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #333; line-height: 1.6;">
@php
    $tz = 'Europe/Amsterdam';
    $th = 'text-align:left;padding:8px 10px;font-size:12px;color:#6b7280;border-bottom:2px solid #e5e7eb;';
    $td = 'padding:8px 10px;font-size:14px;border-bottom:1px solid #f1f5f9;vertical-align:top;';
@endphp
<div style="max-width: 680px; margin: 0 auto; padding: 20px;">

    <h1 style="color: #1f2937; font-size: 20px; margin-bottom: 4px;">Booths dagoverzicht</h1>
    <p style="margin: 0 0 20px 0; color: #6b7280; font-size: 14px;">{{ now()->setTimezone($tz)->format('d-m-Y') }} · {{ $account->name }}</p>

    @if(count($upcoming))
        <h2 style="color: #1f2937; font-size: 16px; margin: 20px 0 8px 0;">Events komende 48 uur</h2>
        <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
            <tr><th style="{{ $th }}">Booth</th><th style="{{ $th }}">Boeking</th><th style="{{ $th }}">Klaar?</th></tr>
            @foreach($upcoming as $u)
                @php
                    $issues = [];
                    if ($u['behind']) $issues[] = 'loopt achter op versie';
                    if ($u['selftest_ok'] === false) $issues[] = 'zelftest gefaald';
                    if (! $u['last_seen']) $issues[] = 'nog nooit gezien';
                @endphp
                <tr>
                    <td style="{{ $td }}"><strong>{{ $u['name'] }}</strong></td>
                    <td style="{{ $td }}">{{ $u['booking']->booking_number }} · {{ $u['booking']->event_date->format('d-m') }}<br><span style="color:#6b7280;font-size:12px;">{{ $u['booking']->customer_name }}</span></td>
                    <td style="{{ $td }}">
                        @if(count($issues))
                            <span style="color:#b45309;">⚠️ {{ implode(', ', $issues) }}</span>
                        @else
                            <span style="color:#15803d;">✅ In orde</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2 style="color: #1f2937; font-size: 16px; margin: 20px 0 8px 0;">Alle booths</h2>
    <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
        <tr>
            <th style="{{ $th }}">Booth</th>
            <th style="{{ $th }}">Versie</th>
            <th style="{{ $th }}">Laatst gezien</th>
            <th style="{{ $th }}">Zelftest</th>
            <th style="{{ $th }}">Prints</th>
            <th style="{{ $th }}">Papier over</th>
        </tr>
        @foreach($rows as $r)
            <tr>
                <td style="{{ $td }}"><strong>{{ $r['name'] }}</strong><br><span style="color:#6b7280;font-size:12px;">{{ $r['channel'] }}@if($r['pinned']) · vast op {{ $r['pinned'] }}@endif</span></td>
                <td style="{{ $td }}">
                    {{ $r['version'] ?? '—' }}
                    @if($r['behind'])<br><span style="color:#b45309;font-size:12px;">achter (nieuwste: {{ $latest[$r['channel']] ?? '?' }})</span>@endif
                </td>
                <td style="{{ $td }}">{{ $r['last_seen'] ? $r['last_seen']->setTimezone($tz)->format('d-m H:i') : 'nooit' }}</td>
                <td style="{{ $td }}">
                    @if($r['selftest_ok'] === true)<span style="color:#15803d;">✅</span>
                    @elseif($r['selftest_ok'] === false)<span style="color:#b45309;">⚠️ gefaald</span>
                    @else — @endif
                </td>
                <td style="{{ $td }}">{{ $r['prints_since'] ?? '—' }}</td>
                <td style="{{ $td }}">{{ $r['media_remaining'] ?? '—' }}</td>
            </tr>
        @endforeach
    </table>

    <h2 style="color: #1f2937; font-size: 16px; margin: 20px 0 8px 0;">Gebeurtenissen afgelopen 24 uur</h2>
    @if($events->isEmpty())
        <p style="color:#6b7280;font-size:14px;margin:0 0 20px 0;">Geen gebeurtenissen.</p>
    @else
        <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
            @foreach($events as $e)
                <tr>
                    <td style="{{ $td }}white-space:nowrap;color:#6b7280;">{{ $e->created_at->setTimezone($tz)->format('d-m H:i') }}</td>
                    <td style="{{ $td }}"><strong>{{ $e->booth?->displayName() ?? 'Booth' }}</strong></td>
                    <td style="{{ $td }}">{{ $e->isOk() ? '✅' : '⚠️' }} {{ $e->label() }}@if($e->app_version) ({{ $e->app_version }})@endif @if($e->message)<br><span style="color:#6b7280;font-size:12px;">{{ $e->message }}</span>@endif</td>
                </tr>
            @endforeach
        </table>
    @endif

    <div style="text-align: center; margin-top: 30px;">
        <a href="{{ route('booths.index') }}" style="display: inline-block; padding: 12px 24px; background: #1f2937; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600;">
            Naar booths in CRM →
        </a>
    </div>

    <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">
    <p style="font-size: 12px; color: #6b7280; margin: 0;">Prints = aantal sinds de vorige samenvatting. Automatisch verstuurd door het Flitsmoment CRM.</p>

</div>
</body>
</html>
