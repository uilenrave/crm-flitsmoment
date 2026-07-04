<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Productie — {{ $account->name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8fafc; color: #1e293b; min-height: 100vh; }
        .header { background: #fff; border-bottom: 1px solid #e2e8f0; padding: 1rem 1.25rem; display: flex; align-items: center; gap: .75rem; }
        .logo { height: 32px; }
        .header-title { font-size: 1rem; font-weight: 700; color: #1e293b; }
        .header-sub { font-size: .8rem; color: #64748b; margin-top: .1rem; }
        .content { max-width: 680px; margin: 0 auto; padding: 1.5rem 1rem 3rem; }

        .intro { background: #ede9fe; border: 1px solid #ddd6fe; color: #5b21b6; border-radius: .75rem; padding: .85rem 1rem; margin-bottom: 1.25rem; font-size: .85rem; line-height: 1.5; }

        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: .75rem; margin-bottom: .625rem; padding: .875rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: .75rem; }
        .card-name { font-weight: 600; color: #374151; font-size: .9rem; }
        .card-date { font-size: .78rem; color: #94a3b8; font-weight: 500; margin-top: .1rem; }
        .card-number { font-size: .72rem; color: #94a3b8; }

        .download-btn { display: inline-flex; align-items: center; gap: .35rem; font-size: .82rem; font-weight: 700; color: #fff; background: #7c3aed; border-radius: .5rem; padding: .5rem .9rem; text-decoration: none; white-space: nowrap; }
        .download-btn:hover { background: #6d28d9; }
        .pending-badge { font-size: .72rem; color: #94a3b8; font-style: italic; white-space: nowrap; }

        .month-header { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #64748b; margin: 1.25rem 0 .6rem; }
        .month-header:first-of-type { margin-top: 0; }

        .empty { text-align: center; padding: 2.5rem 1rem; color: #94a3b8; }
        .empty-icon { font-size: 2rem; margin-bottom: .5rem; }
    </style>
</head>
<body>

<div class="header">
    <img src="/logo.png?v=3" alt="Flitsmoment" class="logo">
    <div>
        <div class="header-title">Productie</div>
        <div class="header-sub">{{ $account->name }}</div>
    </div>
</div>

<div class="content">
    <div class="intro">
        📥 Hieronder staan de aankomende events. Zodra er een fotostrip-ontwerp klaarstaat, verschijnt er een downloadknop.
    </div>

    @if($bookings->isEmpty())
        <div class="empty">
            <div class="empty-icon">📭</div>
            <p style="font-weight:600;margin-bottom:.25rem;">Geen aankomende events</p>
        </div>
    @else
        @php $lastMonth = null; @endphp
        @foreach($bookings as $booking)
            @php $month = $booking->event_date?->format('Y-m'); @endphp
            @if($month !== $lastMonth)
                <div class="month-header">{{ $booking->event_date?->translatedFormat('F Y') }}</div>
                @php $lastMonth = $month; @endphp
            @endif
            <div class="card">
                <div>
                    <div class="card-name">{{ $booking->customer_name }}</div>
                    <div class="card-date">{{ $booking->event_date?->format('d-m-Y') }}</div>
                    <div class="card-number">{{ $booking->booking_number }}</div>
                </div>
                @if($booking->production_file_path)
                    <a href="{{ route('production.download', [$token, $booking]) }}" class="download-btn">⬇ Download PNG</a>
                @else
                    <span class="pending-badge">Nog niet klaar</span>
                @endif
            </div>
        @endforeach
    @endif
</div>

</body>
</html>
