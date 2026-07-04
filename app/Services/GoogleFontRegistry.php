<?php

namespace App\Services;

/**
 * Vaste, kleine set Google Fonts voor de tekst-tool. TTF-bestanden staan gebundeld in
 * resources/fonts/google/ (geen runtime-download nodig, werkt dus ook zonder netwerktoegang
 * vanaf de Hostinger-server). De preview in de browser laadt dezelfde fonts via Google's
 * eigen CSS-link; het server-side renderen (DesignRenderService) gebruikt de lokale TTF's
 * zodat preview en definitieve PNG er identiek uitzien.
 */
class GoogleFontRegistry
{
    public const FONTS = [
        'roboto'           => ['label' => 'Roboto',           'cssFamily' => 'Roboto:wght@400'],
        'playfair-display' => ['label' => 'Playfair Display', 'cssFamily' => 'Playfair+Display:wght@400'],
        'montserrat'       => ['label' => 'Montserrat',       'cssFamily' => 'Montserrat:wght@400'],
        'pacifico'         => ['label' => 'Pacifico',         'cssFamily' => 'Pacifico'],
        'oswald'           => ['label' => 'Oswald',           'cssFamily' => 'Oswald:wght@400'],
        'merriweather'     => ['label' => 'Merriweather',     'cssFamily' => 'Merriweather:wght@400'],
        'dancing-script'   => ['label' => 'Dancing Script',   'cssFamily' => 'Dancing+Script:wght@400'],
        'bebas-neue'       => ['label' => 'Bebas Neue',       'cssFamily' => 'Bebas+Neue'],
    ];

    public const DEFAULT_SLUG = 'roboto';

    public static function labels(): array
    {
        return array_map(fn (array $f) => $f['label'], self::FONTS);
    }

    public static function googleFontsCssUrl(): string
    {
        $families = collect(self::FONTS)
            ->pluck('cssFamily')
            ->map(fn (string $f) => 'family=' . $f)
            ->implode('&');

        return 'https://fonts.googleapis.com/css2?' . $families . '&display=swap';
    }

    public static function ttfPath(string $slug): string
    {
        if (! array_key_exists($slug, self::FONTS)) {
            $slug = self::DEFAULT_SLUG;
        }

        return resource_path("fonts/google/{$slug}.ttf");
    }
}
