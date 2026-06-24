<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Open ritten — {{ $account->name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8fafc; color: #1e293b; min-height: 100vh; }
        .header { background: #fff; border-bottom: 1px solid #e2e8f0; padding: 1rem 1.25rem; display: flex; align-items: center; gap: .75rem; }
        .logo { height: 32px; }
        .header-title { font-size: 1rem; font-weight: 700; color: #1e293b; }
        .header-sub { font-size: .8rem; color: #64748b; margin-top: .1rem; }
        .content { max-width: 680px; margin: 0 auto; padding: 1.5rem 1rem 3rem; }

        .intro { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e3a8a; border-radius: .75rem; padding: .85rem 1rem; margin-bottom: 1.25rem; font-size: .85rem; line-height: 1.5; }

        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: .75rem; margin-bottom: .625rem; padding: .875rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: .75rem; }
        .card-left { display: flex; align-items: center; gap: .75rem; }
        .role-badge { display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .65rem; border-radius: 999px; font-size: .72rem; font-weight: 700; white-space: nowrap; }
        .badge-delivery { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .badge-pickup   { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-handover { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .card-loc { font-weight: 600; color: #374151; font-size: .9rem; }
        .card-date { font-size: .8rem; font-weight: 700; color: #64748b; text-align: right; white-space: nowrap; }
        .card-time { font-size: .75rem; color: #94a3b8; font-weight: 500; }

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
        <div class="header-title">Open ritten</div>
        <div class="header-sub">{{ $account->name }}</div>
    </div>
</div>

<div class="content">
    <div class="intro">
        👋 Hieronder staan de ritten die nog open zijn. Wil je er één doen? Meld je aan via <strong>jouw persoonlijke planningslink</strong> (uit de mail). De beheerder bevestigt daarna wie de rit doet.
    </div>

    @if($ritten->isEmpty())
        <div class="empty">
            <div class="empty-icon">📭</div>
            <p style="font-weight:600;margin-bottom:.25rem;">Geen open ritten</p>
            <p style="font-size:.875rem;">Er staan op dit moment geen ritten open.</p>
        </div>
    @else
        @php $lastMonth = null; @endphp
        @foreach($ritten as $rit)
            @php $month = $rit['datum']->format('Y-m'); @endphp
            @if($month !== $lastMonth)
                @php $lastMonth = $month; @endphp
                <div class="month-header">{{ $rit['datum']->translatedFormat('F Y') }}</div>
            @endif
            <div class="card">
                <div class="card-left">
                    @if($rit['type'] === 'delivery')
                        <span class="role-badge badge-delivery">⬇ Bezorgen</span>
                    @elseif($rit['type'] === 'handover')
                        <span class="role-badge badge-handover">🤝 Afgeven</span>
                    @else
                        <span class="role-badge badge-pickup">⬆ Ophalen</span>
                    @endif
                    <span class="card-loc">{{ $rit['plaats'] ?: '—' }}</span>
                </div>
                <div>
                    <div class="card-date">{{ $rit['datum']->translatedFormat('D j M') }}</div>
                    @if($rit['datum']->format('H:i') !== '00:00')
                    <div class="card-time">{{ $rit['datum']->format('H:i') }}</div>
                    @endif
                </div>
            </div>
        @endforeach
    @endif
</div>

</body>
</html>
