@php
    $lead = $offer->lead;
    $account = $offer->account;
    $firstName = explode(' ', $lead?->name ?? '')[0];

    $whatsappNr = null;
    if ($account?->phone) {
        $nr = preg_replace('/\D/', '', $account->phone);
        if (str_starts_with($nr, '00')) $nr = substr($nr, 2);
        elseif (str_starts_with($nr, '0')) $nr = '31' . substr($nr, 1);
        $whatsappNr = $nr;
    }
@endphp
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Offerte — {{ $account?->name ?? 'Flitsmoment' }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary: #f59e0b;
            --primary-dark: #d97706;
            --primary-light: #fef3c7;
            --green: #16a34a;
            --green-light: #dcfce7;
            --blue: #2563eb;
            --red: #dc2626;
            --red-light: #fee2e2;
            --orange: #d97706;
            --orange-light: #fef3c7;
            --text: #111827;
            --muted: #6b7280;
            --border: #e5e7eb;
            --bg: #f8f7f5;
            --card-bg: #ffffff;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        .header {
            background: var(--card-bg);
            border-bottom: 1px solid var(--border);
            padding: .875rem 1.5rem;
            position: sticky;
            top: 0;
            z-index: 50;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
        }
        .header-inner {
            max-width: 720px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header-brand {
            font-weight: 700;
            font-size: 1rem;
            color: var(--text);
        }
        .header-label {
            font-size: .75rem;
            color: var(--muted);
            background: #f3f4f6;
            padding: .25rem .625rem;
            border-radius: 9999px;
        }

        .container {
            max-width: 720px;
            margin: 0 auto;
            padding: 1.5rem 1rem 3rem;
        }

        /* Sections */
        .section {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: .75rem;
            margin-bottom: 1.25rem;
            overflow: hidden;
            box-shadow: -1px -1px 3px rgba(255,255,255,.8), 2px 2px 5px rgba(0,0,0,.08);
        }
        .section-body { padding: 1.25rem 1.5rem; }
        .section-header-bar {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border);
            font-weight: 700;
            font-size: .9rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        /* Status banners */
        .banner {
            padding: 1rem 1.5rem;
            border-radius: .75rem;
            margin-bottom: 1.25rem;
            font-size: .9rem;
            font-weight: 500;
        }
        .banner-expired { background: var(--orange-light); color: #92400e; border: 1px solid #fbbf24; }
        .banner-declined { background: var(--red-light); color: #991b1b; border: 1px solid #fca5a5; }
        .banner-accepted { background: var(--green-light); color: #166534; border: 1px solid #86efac; }

        /* Greeting */
        .greeting h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: .5rem;
        }
        .greeting p {
            color: var(--muted);
            font-size: .95rem;
        }

        /* Gallery */
        .gallery-scroll {
            display: flex;
            gap: .75rem;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
            padding: .5rem 0;
            scrollbar-width: none;
        }
        .gallery-scroll::-webkit-scrollbar { display: none; }
        .gallery-item {
            flex: 0 0 260px;
            scroll-snap-align: center;
            border-radius: .625rem;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,.12);
        }
        .gallery-item img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            display: block;
        }

        /* Checklist */
        .checklist { list-style: none; }
        .checklist li {
            padding: .625rem 0;
            font-size: .9rem;
            display: flex;
            align-items: flex-start;
            gap: .625rem;
            border-bottom: 1px solid #f3f4f6;
        }
        .checklist li:last-child { border-bottom: none; }
        .check-icon {
            color: var(--green);
            font-weight: 700;
            font-size: 1rem;
            flex-shrink: 0;
            line-height: 1.6;
        }

        /* Pricing table */
        .pricing-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .875rem;
        }
        .pricing-table th {
            text-align: left;
            padding: .625rem .75rem;
            background: #f9fafb;
            border-bottom: 2px solid var(--border);
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--muted);
        }
        .pricing-table td {
            padding: .625rem .75rem;
            border-bottom: 1px solid #f3f4f6;
        }
        .pricing-table .text-right { text-align: right; }
        .pricing-summary {
            padding: .75rem;
            border-top: 1px solid var(--border);
        }
        .pricing-row {
            display: flex;
            justify-content: space-between;
            padding: .25rem 0;
            font-size: .875rem;
        }
        .pricing-total {
            font-weight: 700;
            font-size: 1.125rem;
            padding-top: .5rem;
            border-top: 2px solid #1e293b;
            margin-top: .25rem;
        }

        /* Reviews */
        .review-card {
            padding: 1rem 1.25rem;
            background: #fafaf9;
            border-radius: .625rem;
            margin-bottom: .75rem;
            border: 1px solid #f0eeeb;
        }
        .review-stars { color: #f59e0b; font-size: .9rem; margin-bottom: .375rem; }
        .review-text { font-size: .875rem; color: #374151; font-style: italic; margin-bottom: .375rem; line-height: 1.5; }
        .review-author { font-size: .75rem; color: var(--muted); font-weight: 600; }

        /* Accept section */
        .accept-section { text-align: center; padding: 2rem 1.5rem; }
        .terms-check {
            display: flex;
            align-items: flex-start;
            gap: .5rem;
            text-align: left;
            font-size: .8rem;
            color: var(--muted);
            margin-bottom: 1.25rem;
            justify-content: center;
        }
        .terms-check input[type="checkbox"] {
            margin-top: .2rem;
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
            flex-shrink: 0;
        }
        .terms-check a { color: var(--blue); text-decoration: underline; }

        .btn-accept {
            display: inline-block;
            background: linear-gradient(135deg, #fcd34d 0%, #f59e0b 100%);
            color: #1e293b;
            padding: 1rem 2.5rem;
            border: none;
            border-radius: .625rem;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(245,158,11,.3);
            transition: transform .15s, box-shadow .15s;
        }
        .btn-accept:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(245,158,11,.4);
        }
        .btn-accept:disabled {
            opacity: .5;
            cursor: not-allowed;
            transform: none;
        }
        .accept-sub {
            font-size: .8rem;
            color: var(--muted);
            margin-top: .75rem;
        }

        /* Terms accordion */
        .terms-details summary {
            cursor: pointer;
            font-weight: 600;
            font-size: .9rem;
            padding: .75rem 0;
            list-style: none;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .terms-details summary::before {
            content: '▸';
            transition: transform .2s;
        }
        .terms-details[open] summary::before {
            transform: rotate(90deg);
        }
        .terms-content {
            font-size: .8rem;
            color: #475569;
            white-space: pre-line;
            line-height: 1.7;
            padding-bottom: 1rem;
        }

        /* Contact section */
        .btn-whatsapp {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            background: #25d366;
            color: #fff;
            padding: .75rem 1.5rem;
            border-radius: .625rem;
            text-decoration: none;
            font-weight: 600;
            font-size: .9rem;
            transition: background .15s;
        }
        .btn-whatsapp:hover { background: #1fb855; }

        .btn-pdf {
            display: inline-flex;
            align-items: center;
            gap: .375rem;
            background: var(--card-bg);
            color: var(--text);
            padding: .5rem 1rem;
            border: 1px solid var(--border);
            border-radius: .5rem;
            text-decoration: none;
            font-size: .8rem;
            cursor: pointer;
            transition: background .15s;
        }
        .btn-pdf:hover { background: #f9fafb; }

        .contact-links {
            display: flex;
            flex-direction: column;
            gap: .375rem;
            margin-top: .75rem;
        }
        .contact-links a {
            font-size: .85rem;
            color: var(--blue);
            text-decoration: none;
        }
        .contact-links a:hover { text-decoration: underline; }

        /* Footer */
        .page-footer {
            text-align: center;
            padding: 1.5rem 1rem;
            font-size: .75rem;
            color: var(--muted);
        }

        /* Error message */
        .error-msg {
            background: var(--red-light);
            color: #991b1b;
            padding: .75rem 1rem;
            border-radius: .5rem;
            margin-bottom: 1rem;
            font-size: .875rem;
        }

        /* Print styles */
        @media print {
            .header, .gallery-scroll, .accept-section, .contact-section, .btn-pdf, .no-print { display: none !important; }
            body { background: #fff; }
            .container { padding: 0; max-width: 100%; }
            .section { box-shadow: none; border: 1px solid #ccc; break-inside: avoid; }
        }

        /* Responsive */
        @media (max-width: 480px) {
            .container { padding: 1rem .75rem 2rem; }
            .section-body { padding: 1rem; }
            .gallery-item { flex: 0 0 220px; }
            .gallery-item img { height: 150px; }
            .greeting h1 { font-size: 1.25rem; }
            .btn-accept { padding: .875rem 2rem; font-size: 1rem; }
        }
    </style>
</head>
<body>

{{-- Header --}}
<div class="header">
    <div class="header-inner">
        <span class="header-brand">{{ $account?->name ?? 'Flitsmoment' }}</span>
        <span class="header-label">Offerte {{ $offer->offer_number }}</span>
    </div>
</div>

<div class="container">

    {{-- Error messages --}}
    @if(session('error'))
        <div class="error-msg">{{ session('error') }}</div>
    @endif

    {{-- Status banners --}}
    @if($offer->status === 'expired' || ($offer->expires_at && $offer->expires_at->isPast() && $offer->status === 'sent'))
        <div class="banner banner-expired">
            Deze offerte is verlopen{{ $offer->expires_at ? ' op ' . $offer->expires_at->format('d-m-Y') : '' }}. Neem contact op voor een nieuwe offerte.
        </div>
    @elseif($offer->status === 'declined')
        <div class="banner banner-declined">
            Deze offerte is afgewezen.
        </div>
    @elseif($offer->status === 'accepted')
        <div class="banner banner-accepted">
            U heeft deze offerte geaccepteerd{{ $offer->accepted_at ? ' op ' . $offer->accepted_at->format('d-m-Y') : '' }}. Uw boeking is bevestigd!
        </div>
    @endif

    {{-- Greeting --}}
    <div class="section">
        <div class="section-body greeting">
            <h1>Hallo {{ $firstName }}!</h1>
            <p>
                Bedankt voor uw interesse in een photobooth
                @if($lead?->event_date)
                    voor uw evenement op <strong>{{ $lead->event_date->translatedFormat('j F Y') }}</strong>
                @endif
                @if($lead?->event_location)
                    bij <strong>{{ $lead->event_location }}</strong>
                @endif.
                Hieronder vindt u onze offerte op maat.
            </p>
        </div>
    </div>

    {{-- Photo gallery --}}
    @php
        $galleryImages = [];
        for ($i = 1; $i <= 6; $i++) {
            $path = public_path("images/gallery/gallery-{$i}.jpg");
            if (file_exists($path)) $galleryImages[] = "/images/gallery/gallery-{$i}.jpg";
        }
    @endphp
    @if(count($galleryImages) > 0)
    <div class="section no-print">
        <div class="section-body" style="padding:.75rem 1rem;">
            <div class="gallery-scroll">
                @foreach($galleryImages as $img)
                <div class="gallery-item">
                    <img src="{{ $img }}" alt="Photobooth impressie" loading="lazy">
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- What you get --}}
    <div class="section">
        <div class="section-header-bar">
            <span>Wat krijg je?</span>
        </div>
        <div class="section-body">
            <ul class="checklist">
                <li><span class="check-icon">✓</span> Professionele photobooth opstelling op locatie</li>
                <li><span class="check-icon">✓</span> Onbeperkt foto's maken gedurende het evenement</li>
                <li><span class="check-icon">✓</span> Direct printen van fotostrips voor uw gasten</li>
                <li><span class="check-icon">✓</span> Digitale galerij met alle foto's na het evenement</li>
                <li><span class="check-icon">✓</span> Persoonlijk ontworpen fotostrip design</li>
                <li><span class="check-icon">✓</span> Bezorgen, opbouwen en weer ophalen</li>
            </ul>
        </div>
    </div>

    {{-- Pricing breakdown --}}
    <div class="section">
        <div class="section-header-bar">
            <span>Uw offerte</span>
        </div>
        <table class="pricing-table">
            <thead>
                <tr>
                    <th>Omschrijving</th>
                    <th class="text-right">Aantal</th>
                    <th class="text-right">Prijs</th>
                    <th class="text-right">Totaal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($offer->line_items ?? [] as $item)
                <tr>
                    <td>{{ $item['description'] ?? '—' }}</td>
                    <td class="text-right">{{ $item['quantity'] ?? 1 }}</td>
                    <td class="text-right">€ {{ number_format($item['unit_price'] ?? 0, 2, ',', '.') }}</td>
                    <td class="text-right" style="font-weight:600;">€ {{ number_format($item['total'] ?? 0, 2, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="pricing-summary">
            <div class="pricing-row">
                <span style="color:var(--muted);">Subtotaal</span>
                <span>€ {{ number_format((float)$offer->subtotal, 2, ',', '.') }}</span>
            </div>
            @if((float)$offer->discount > 0)
            <div class="pricing-row">
                <span style="color:var(--green);">Korting</span>
                <span style="color:var(--green);">−€ {{ number_format((float)$offer->discount, 2, ',', '.') }}</span>
            </div>
            @endif
            <div class="pricing-row pricing-total">
                <span>Totaal</span>
                <span>€ {{ number_format((float)$offer->total, 2, ',', '.') }}</span>
            </div>
        </div>
    </div>

    {{-- Reviews --}}
    <div class="section">
        <div class="section-header-bar">
            <span>Wat onze klanten zeggen</span>
        </div>
        <div class="section-body">
            <div class="review-card">
                <div class="review-stars">★★★★★</div>
                <div class="review-text">"Wat een feest! De photobooth was het hoogtepunt van de avond. Iedereen ging helemaal los met de props!"</div>
                <div class="review-author">Lisa & Mark — Bruiloft</div>
            </div>
            <div class="review-card">
                <div class="review-stars">★★★★★</div>
                <div class="review-text">"Superprofessioneel en de foto's waren geweldig. De fotostrips zijn een mooie herinnering voor alle collega's."</div>
                <div class="review-author">Bedrijfsevent Rabobank</div>
            </div>
            <div class="review-card">
                <div class="review-stars">★★★★★</div>
                <div class="review-text">"De kinderen vonden het fantastisch! De fotostrips waren een perfect aandenken aan het feest."</div>
                <div class="review-author">Familie De Vries — Verjaardagsfeest</div>
            </div>
        </div>
    </div>

    {{-- Accept offer --}}
    @if($offer->isAcceptable())
    <div class="section">
        <div class="accept-section">
            <form method="POST" action="{{ route('offer.accept', $offer->public_token) }}" id="accept-form">
                @csrf

                <div class="terms-check">
                    <input type="checkbox" name="terms_accepted" id="terms_accepted" value="1" required>
                    <label for="terms_accepted">
                        Ik ga akkoord met de <a href="#voorwaarden" onclick="document.getElementById('terms-details').open=true">algemene voorwaarden</a>
                    </label>
                </div>

                @error('terms_accepted')
                    <div style="color:var(--red);font-size:.8rem;margin-bottom:.75rem;">{{ $message }}</div>
                @enderror

                <button type="submit" class="btn-accept" id="accept-btn" onclick="this.disabled=true;this.textContent='Even geduld...';this.form.submit();">
                    Accepteer offerte
                </button>

                <div class="accept-sub">
                    Door te accepteren wordt uw boeking automatisch bevestigd.
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- Terms --}}
    @if($offer->terms_text)
    <div class="section" id="voorwaarden">
        <div class="section-body">
            <details class="terms-details" id="terms-details">
                <summary>Algemene voorwaarden</summary>
                <div class="terms-content">{{ $offer->terms_text }}</div>
            </details>
        </div>
    </div>
    @endif

    {{-- Contact / Questions --}}
    <div class="section contact-section">
        <div class="section-header-bar">
            <span>Heeft u vragen?</span>
        </div>
        <div class="section-body" style="text-align:center;">
            <p style="color:var(--muted);font-size:.9rem;margin-bottom:1rem;">
                We helpen u graag! Neem gerust contact op.
            </p>

            @if($whatsappNr)
                <a href="https://wa.me/{{ $whatsappNr }}?text={{ urlencode('Hallo, ik heb een vraag over mijn offerte ' . $offer->offer_number . '.') }}" target="_blank" class="btn-whatsapp">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    Stuur een WhatsApp
                </a>
            @endif

            <div class="contact-links">
                @if($account?->email)
                    <a href="mailto:{{ $account->email }}">{{ $account->email }}</a>
                @endif
                @if($account?->phone)
                    <a href="tel:{{ $account->phone }}">{{ $account->phone }}</a>
                @endif
            </div>

            <div style="margin-top:1.25rem;">
                <button type="button" class="btn-pdf" onclick="window.print()">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"/></svg>
                    Download als PDF
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Footer --}}
<div class="page-footer">
    @if($offer->expires_at && $offer->status === 'sent')
        <div style="margin-bottom:.375rem;">Offerte geldig tot {{ $offer->expires_at->format('d-m-Y') }}</div>
    @endif
    <div>{{ $account?->name ?? 'Flitsmoment' }}</div>
</div>

</body>
</html>
