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
        'abril-fatface'         => ['label' => 'Abril Fatface',         'cssFamily' => 'Abril+Fatface'],
        'alice'                 => ['label' => 'Alice',                 'cssFamily' => 'Alice'],
        'amatic-sc'             => ['label' => 'Amatic SC',             'cssFamily' => 'Amatic+SC'],
        'andika'                => ['label' => 'Andika',                'cssFamily' => 'Andika'],
        'arvo'                  => ['label' => 'Arvo',                  'cssFamily' => 'Arvo'],
        'bebas-neue'            => ['label' => 'Bebas Neue',            'cssFamily' => 'Bebas+Neue'],
        'cabin'                 => ['label' => 'Cabin',                 'cssFamily' => 'Cabin'],
        'cantarell'             => ['label' => 'Cantarell',             'cssFamily' => 'Cantarell'],
        'cardo'                 => ['label' => 'Cardo',                 'cssFamily' => 'Cardo'],
        'cinzel'                => ['label' => 'Cinzel',                'cssFamily' => 'Cinzel'],
        'cormorant-garamond'    => ['label' => 'Cormorant Garamond',    'cssFamily' => 'Cormorant+Garamond'],
        'crimson-text'          => ['label' => 'Crimson Text',          'cssFamily' => 'Crimson+Text'],
        'dancing-script'        => ['label' => 'Dancing Script',        'cssFamily' => 'Dancing+Script:wght@400'],
        'eb-garamond'           => ['label' => 'EB Garamond',           'cssFamily' => 'EB+Garamond'],
        'fanwood-text'          => ['label' => 'Fanwood Text',          'cssFamily' => 'Fanwood+Text'],
        'fauna-one'             => ['label' => 'Fauna One',             'cssFamily' => 'Fauna+One'],
        'fjalla-one'            => ['label' => 'Fjalla One',            'cssFamily' => 'Fjalla+One'],
        'halant'                => ['label' => 'Halant',                'cssFamily' => 'Halant'],
        'hind'                  => ['label' => 'Hind',                  'cssFamily' => 'Hind'],
        'inconsolata'           => ['label' => 'Inconsolata',           'cssFamily' => 'Inconsolata'],
        'josefin-sans'          => ['label' => 'Josefin Sans',          'cssFamily' => 'Josefin+Sans'],
        'karla'                 => ['label' => 'Karla',                 'cssFamily' => 'Karla'],
        'lato'                  => ['label' => 'Lato',                  'cssFamily' => 'Lato'],
        'ledger'                => ['label' => 'Ledger',                'cssFamily' => 'Ledger'],
        'libre-baskerville'     => ['label' => 'Libre Baskerville',     'cssFamily' => 'Libre+Baskerville'],
        'lora'                  => ['label' => 'Lora',                  'cssFamily' => 'Lora'],
        'lustria'               => ['label' => 'Lustria',               'cssFamily' => 'Lustria'],
        'merriweather'          => ['label' => 'Merriweather',          'cssFamily' => 'Merriweather:wght@400'],
        'montserrat'            => ['label' => 'Montserrat',            'cssFamily' => 'Montserrat:wght@400'],
        'muli'                  => ['label' => 'Muli',                  'cssFamily' => 'Muli'],
        'mulish'                => ['label' => 'Mulish',                'cssFamily' => 'Mulish'],
        'nixie-one'             => ['label' => 'Nixie One',             'cssFamily' => 'Nixie+One'],
        'nunito'                => ['label' => 'Nunito',                'cssFamily' => 'Nunito'],
        'nunito-sans'           => ['label' => 'Nunito Sans',           'cssFamily' => 'Nunito+Sans'],
        'open-sans'             => ['label' => 'Open Sans',             'cssFamily' => 'Open+Sans'],
        'oswald'                => ['label' => 'Oswald',                'cssFamily' => 'Oswald:wght@400'],
        'oxygen'                => ['label' => 'Oxygen',                'cssFamily' => 'Oxygen'],
        'pacifico'              => ['label' => 'Pacifico',              'cssFamily' => 'Pacifico'],
        'philosopher'           => ['label' => 'Philosopher',           'cssFamily' => 'Philosopher'],
        'playfair-display'      => ['label' => 'Playfair Display',      'cssFamily' => 'Playfair+Display:wght@400'],
        'pontano-sans'          => ['label' => 'Pontano Sans',          'cssFamily' => 'Pontano+Sans'],
        'poppins'               => ['label' => 'Poppins',               'cssFamily' => 'Poppins'],
        'proza-libre'           => ['label' => 'Proza Libre',           'cssFamily' => 'Proza+Libre'],
        'pt-sans'               => ['label' => 'PT Sans',               'cssFamily' => 'PT+Sans'],
        'quattrocento'          => ['label' => 'Quattrocento',          'cssFamily' => 'Quattrocento'],
        'quattrocento-sans'     => ['label' => 'Quattrocento Sans',     'cssFamily' => 'Quattrocento+Sans'],
        'quicksand'             => ['label' => 'Quicksand',             'cssFamily' => 'Quicksand'],
        'raleway'               => ['label' => 'Raleway',               'cssFamily' => 'Raleway'],
        'roboto'                => ['label' => 'Roboto',                'cssFamily' => 'Roboto:wght@400'],
        'roboto-condensed'      => ['label' => 'Roboto Condensed',      'cssFamily' => 'Roboto+Condensed'],
        'sacramento'            => ['label' => 'Sacramento',            'cssFamily' => 'Sacramento'],
        'slabo-27px'            => ['label' => 'Slabo 27px',            'cssFamily' => 'Slabo+27px'],
        'source-sans-pro'       => ['label' => 'Source Sans Pro',       'cssFamily' => 'Source+Sans+Pro'],
        'source-serif-pro'      => ['label' => 'Source Serif Pro',      'cssFamily' => 'Source+Serif+Pro'],
        'spectral'              => ['label' => 'Spectral',              'cssFamily' => 'Spectral'],
        'stint-ultra-expanded'  => ['label' => 'Stint Ultra Expanded',  'cssFamily' => 'Stint+Ultra+Expanded'],
        'ubuntu'                => ['label' => 'Ubuntu',                'cssFamily' => 'Ubuntu'],
        'ultra'                 => ['label' => 'Ultra',                 'cssFamily' => 'Ultra'],
        'unica-one'             => ['label' => 'Unica One',             'cssFamily' => 'Unica+One'],
        'work-sans'             => ['label' => 'Work Sans',             'cssFamily' => 'Work+Sans'],
        'yeseva-one'            => ['label' => 'Yeseva One',            'cssFamily' => 'Yeseva+One'],

        // Extra display/script/handgeschreven fonts die goed passen bij photobooth-fotostrips
        // (elegante bruilofts-scripts, speelse scripts, handschrift en vrolijke display-fonts).
        'alex-brush'            => ['label' => 'Alex Brush',            'cssFamily' => 'Alex+Brush'],
        'allura'                => ['label' => 'Allura',                'cssFamily' => 'Allura'],
        'architects-daughter'   => ['label' => 'Architects Daughter',   'cssFamily' => 'Architects+Daughter'],
        'baloo-2'               => ['label' => 'Baloo 2',               'cssFamily' => 'Baloo+2'],
        'bangers'               => ['label' => 'Bangers',               'cssFamily' => 'Bangers'],
        'caveat'                => ['label' => 'Caveat',                'cssFamily' => 'Caveat'],
        'cookie'                => ['label' => 'Cookie',                'cssFamily' => 'Cookie'],
        'courgette'             => ['label' => 'Courgette',             'cssFamily' => 'Courgette'],
        'fredoka'               => ['label' => 'Fredoka',               'cssFamily' => 'Fredoka'],
        'great-vibes'           => ['label' => 'Great Vibes',           'cssFamily' => 'Great+Vibes'],
        'indie-flower'          => ['label' => 'Indie Flower',          'cssFamily' => 'Indie+Flower'],
        'kalam'                 => ['label' => 'Kalam',                 'cssFamily' => 'Kalam'],
        'kaushan-script'        => ['label' => 'Kaushan Script',        'cssFamily' => 'Kaushan+Script'],
        'lobster'               => ['label' => 'Lobster',               'cssFamily' => 'Lobster'],
        'luckiest-guy'          => ['label' => 'Luckiest Guy',          'cssFamily' => 'Luckiest+Guy'],
        'parisienne'            => ['label' => 'Parisienne',            'cssFamily' => 'Parisienne'],
        'patrick-hand'          => ['label' => 'Patrick Hand',          'cssFamily' => 'Patrick+Hand'],
        'permanent-marker'      => ['label' => 'Permanent Marker',      'cssFamily' => 'Permanent+Marker'],
        'righteous'             => ['label' => 'Righteous',             'cssFamily' => 'Righteous'],
        'satisfy'               => ['label' => 'Satisfy',               'cssFamily' => 'Satisfy'],
        'shadows-into-light'    => ['label' => 'Shadows Into Light',    'cssFamily' => 'Shadows+Into+Light'],
        'tangerine'             => ['label' => 'Tangerine',             'cssFamily' => 'Tangerine'],
        'yellowtail'            => ['label' => 'Yellowtail',            'cssFamily' => 'Yellowtail'],
    ];

    public const DEFAULT_SLUG = 'roboto';

    public static function labels(): array
    {
        $labels = array_map(fn (array $f) => $f['label'], self::FONTS);
        asort($labels, SORT_NATURAL | SORT_FLAG_CASE); // alfabetisch in de picker, ongeacht volgorde in de const
        return $labels;
    }

    public static function googleFontsCssUrl(): string
    {
        $families = collect(self::FONTS)
            ->pluck('cssFamily')
            ->map(fn (string $f) => 'family=' . $f)
            ->implode('&');

        return 'https://fonts.googleapis.com/css2?' . $families . '&display=swap';
    }

    /**
     * @font-face-regels die naar de lokaal gebundelde TTF's wijzen (public/fonts/google/*.ttf),
     * i.p.v. Google's eigen CSS-link. Zo werkt de browser-preview zonder afhankelijkheid van
     * fonts.googleapis.com (kan geblokkeerd zijn door adblockers/netwerkbeleid) en gebruikt hij
     * exact hetzelfde lettertype-bestand als de server-side render (DesignRenderService), zodat
     * preview en definitieve PNG gegarandeerd identiek zijn.
     */
    /** Gewichten die als aparte TTF-varianten gedownload kunnen zijn (fonts:download-weights). */
    public const WEIGHTS = [300, 400, 700];

    public static function localFontFacesCss(): string
    {
        $faces = [];

        foreach (self::FONTS as $slug => $font) {
            $label = $font['label'];

            foreach (self::WEIGHTS as $weight) {
                $filename = $weight === 400 ? "{$slug}.ttf" : "{$slug}-{$weight}.ttf";

                // Bestaan checken tegen resource_path (wordt door deploy.sh gedeployd en is dus overal
                // aanwezig), NIET public_path — op productie is public_path laravel/public/, terwijl de
                // geserveerde fonts in de echte docroot public_html/fonts/google/ staan.
                if (! is_file(resource_path("fonts/google/{$filename}"))) {
                    continue; // gewicht bestaat niet voor dit font → overslaan
                }

                $url = asset("fonts/google/{$filename}");
                $faces[] = "@font-face { font-family: '{$label}'; font-weight: {$weight}; src: url('{$url}') format('truetype'); font-display: swap; }";
            }
        }

        return implode("\n", $faces);
    }

    /**
     * Pad naar de lokale TTF voor server-side render. Kiest de gedownloade gewicht-variant
     * ({slug}-{weight}.ttf) als die bestaat, anders de reguliere {slug}.ttf.
     */
    public static function ttfPath(string $slug, int $weight = 400): string
    {
        if (! array_key_exists($slug, self::FONTS)) {
            $slug = self::DEFAULT_SLUG;
        }

        if ($weight !== 400) {
            $variant = resource_path("fonts/google/{$slug}-{$weight}.ttf");
            if (is_file($variant)) {
                return $variant;
            }
        }

        return resource_path("fonts/google/{$slug}.ttf");
    }
}
