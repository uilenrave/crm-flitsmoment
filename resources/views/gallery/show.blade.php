<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Jouw foto's — Flitsmoment</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            flex-direction: column;
            height: 100dvh;
            overflow: hidden;
            background: #f5f5f5;
        }

        /* ── Top promo strip ────────────────────────────────── */
        .promo-strip {
            background: #111827;
            color: rgba(255,255,255,.75);
            font-size: .72rem;
            font-weight: 500;
            text-align: center;
            padding: .35rem 1rem;
            letter-spacing: .03em;
            flex-shrink: 0;
        }

        .promo-strip span {
            color: #fff;
            font-weight: 700;
        }

        /* ── Main header ─────────────────────────────────────── */
        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .75rem 1.75rem;
            background: #fff;
            border-bottom: 3px solid #3b82f6;
            flex-shrink: 0;
            gap: 1rem;
            min-height: 64px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
            min-width: 0;
        }

        .logo {
            height: 34px;
            width: auto;
            flex-shrink: 0;
        }

        .sep {
            width: 1px;
            height: 22px;
            background: #e5e7eb;
            flex-shrink: 0;
        }

        .tagline {
            font-size: .85rem;
            color: #6b7280;
            line-height: 1.5;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .tagline strong {
            display: block;
            font-size: .95rem;
            color: #111827;
            font-weight: 700;
        }

        /* ── CTA knop ────────────────────────────────────────── */
        .cta-btn {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .65rem 1.5rem;
            background: #3b82f6;
            color: #fff;
            font-size: .875rem;
            font-weight: 700;
            border-radius: 10px;
            text-decoration: none;
            white-space: nowrap;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(59,130,246,.4);
            transition: background .15s, transform .15s, box-shadow .15s;
        }

        .cta-btn:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(59,130,246,.5);
        }

        .cta-btn .arrow {
            font-style: normal;
            font-size: 1rem;
        }

        /* ── Iframe wrapper ──────────────────────────────────── */
        .gallery-wrapper {
            flex: 1;
            position: relative;
            overflow: hidden;
        }

        .gallery-wrapper iframe {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            border: none;
        }

        /* Fallback wanneer iframe geblokkeerd wordt */
        .iframe-fallback {
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            gap: 1.25rem;
            text-align: center;
            padding: 2rem;
            color: #6b7280;
        }

        .iframe-fallback .icon { font-size: 3rem; }
        .iframe-fallback p { font-size: .95rem; line-height: 1.6; }

        .open-link {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .65rem 1.5rem;
            background: #3b82f6;
            color: #fff;
            font-weight: 700;
            border-radius: 10px;
            text-decoration: none;
            font-size: .95rem;
            transition: background .15s;
        }

        .open-link:hover { background: #2563eb; }

        @media (max-width: 560px) {
            .sep, .tagline { display: none; }
            header { padding: .6rem 1rem; min-height: 56px; }
            .cta-btn { padding: .55rem 1rem; font-size: .8rem; }
            .promo-strip { display: none; }
        }
    </style>
</head>
<body>

    <div class="promo-strip">
        ✓ Onbeperkt foto's &nbsp;·&nbsp; ✓ Gratis bezorging in Utrecht & Den Haag &nbsp;·&nbsp; ✓ Gratis ontwerp fotostrip &nbsp;·&nbsp;
        <span>flitsmoment.nl</span>
    </div>

    <header>
        <div class="header-left">
            <a href="https://www.flitsmoment.nl" target="_blank" rel="noopener">
                <img src="{{ asset('logo.png') }}" alt="Flitsmoment" class="logo">
            </a>
            <div class="sep"></div>
            <span class="tagline">
                Jouw foto's
                <strong>{{ \Carbon\Carbon::parse($booking->event_date)->translatedFormat('j F Y') }}</strong>
            </span>
        </div>
        <a href="https://www.flitsmoment.nl" target="_blank" rel="noopener" class="cta-btn">
            📸 Boek een fotobooth <em class="arrow">→</em>
        </a>
    </header>

    <div class="gallery-wrapper" id="gallery-wrapper">
        <iframe
            src="{{ $booking->gallery_url }}"
            id="gallery-frame"
            allowfullscreen
            loading="lazy"
            title="Fotogalerij"
        ></iframe>

        {{-- Fallback als iframe geblokkeerd wordt --}}
        <div class="iframe-fallback" id="gallery-fallback">
            <span class="icon">📷</span>
            <p>De galerij kan niet in dit venster worden geladen.<br>Klik hieronder om de foto's te bekijken.</p>
            <a href="{{ $booking->gallery_url }}" target="_blank" rel="noopener" class="open-link">
                Galerij openen →
            </a>
        </div>
    </div>

    <script>
        // Als iframe na 5 seconden niets heeft geladen → fallback tonen
        const frame = document.getElementById('gallery-frame');
        let loaded = false;

        frame.addEventListener('load', () => { loaded = true; });

        setTimeout(() => {
            if (!loaded) {
                frame.style.display = 'none';
                document.getElementById('gallery-fallback').style.display = 'flex';
            }
        }, 5000);
    </script>
</body>
</html>
