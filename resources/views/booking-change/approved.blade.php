<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wijziging {{ $alreadyDone ? 'verwerkt' : 'goedgekeurd' }}</title>
    <style>
        body { margin:0; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; background:#f1f5f9; color:#1e293b; }
        .wrap { max-width:520px; margin:8vh auto; padding:0 1rem; }
        .card { background:#fff; border-radius:14px; box-shadow:0 4px 20px rgba(15,23,42,.08); overflow:hidden; }
        .top { padding:28px 32px; text-align:center; background:{{ $alreadyDone ? '#f8fafc' : '#f0fdf4' }}; }
        .icon { font-size:44px; line-height:1; }
        h1 { margin:.5rem 0 0; font-size:20px; }
        .body { padding:24px 32px 32px; font-size:15px; line-height:1.6; }
        .row { display:flex; justify-content:space-between; gap:1rem; padding:.5rem 0; border-bottom:1px solid #f1f5f9; }
        .row .lbl { color:#64748b; }
        .row .val { font-weight:700; text-align:right; }
        .muted { color:#94a3b8; font-size:13px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        @if($alreadyDone)
            <div class="top"><div class="icon">✅</div><h1>Al verwerkt</h1></div>
            <div class="body">
                <p>Deze wijziging is al goedgekeurd of de link is niet meer geldig. Er is niets veranderd.</p>
                @if($booking)
                    <p class="muted">Boeking {{ $booking->booking_number }}</p>
                @endif
            </div>
        @else
            <div class="top"><div class="icon">🎉</div><h1>Wijziging goedgekeurd</h1></div>
            <div class="body">
                <p>De nieuwe tijden zijn toegepast op boeking <strong>{{ $booking->booking_number }}</strong> ({{ $booking->customer_name }}). De klant heeft automatisch een bevestigingsmail gekregen.</p>
                @if($booking->booking_type === 'to_go')
                    @if($booking->customer_pickup_at)<div class="row"><span class="lbl">📦 Ophalen bij ons</span><span class="val">{{ $booking->customer_pickup_at->translatedFormat('l j F') }} · {{ $booking->customer_pickup_at->format('H:i') }}</span></div>@endif
                    @if($booking->customer_return_at)<div class="row"><span class="lbl">🔄 Terugbrengen</span><span class="val">{{ $booking->customer_return_at->translatedFormat('l j F') }} · {{ $booking->customer_return_at->format('H:i') }}</span></div>@endif
                @else
                    @if($booking->delivery_at)<div class="row"><span class="lbl">🚚 Bezorgen</span><span class="val">{{ $booking->delivery_at->translatedFormat('l j F') }} · {{ $booking->delivery_at->format('H:i') }}</span></div>@endif
                    @if($booking->pickup_at)<div class="row"><span class="lbl">📦 Ophalen (door ons)</span><span class="val">{{ $booking->pickup_at->translatedFormat('l j F') }} · {{ $booking->pickup_at->format('H:i') }}</span></div>@endif
                @endif
                <p class="muted" style="margin-top:1rem;">Je kunt dit venster sluiten.</p>
            </div>
        @endif
    </div>
</div>
</body>
</html>
