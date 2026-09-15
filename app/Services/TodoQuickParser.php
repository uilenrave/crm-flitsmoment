<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Support\Carbon;

/**
 * Leest één getypte regel en haalt daar de losse velden uit.
 *
 *   "accu unit 3 € 89 @conrad morgen"
 *     → titel "accu unit 3", prijs 8900, winkel "conrad", datum morgen
 *
 * Bewust voorzichtig: herkent alleen wat overduidelijk is. Wat niet herkend
 * wordt blijft gewoon in de titel staan, want een verminkte titel is erger
 * dan een leeg veld.
 */
class TodoQuickParser
{
    private const DAGEN = [
        'maandag' => 1, 'dinsdag' => 2, 'woensdag' => 3, 'donderdag' => 4,
        'vrijdag' => 5, 'zaterdag' => 6, 'zondag' => 7,
    ];

    private const SOORTEN = [
        'cursus'   => 'cursus',
        'training' => 'cursus',
        'boek'     => 'boek',
        'video'    => 'video',
        'artikel'  => 'artikel',
        'podcast'  => 'podcast',
    ];

    /** @return array{title:string,notes:null,due_date:?string,price_cents:?int,vendor:?string,url:?string,kind:?string} */
    public function parse(string $invoer, string $type = Task::TYPE_TAAK): array
    {
        $rest = trim($invoer);

        $url    = $this->pakUrl($rest);
        $datum  = $this->pakDatum($rest);
        $prijs  = null;
        $winkel = null;
        $soort  = null;

        if ($type === Task::TYPE_KOPEN || $type === Task::TYPE_LEREN) {
            $prijs = $this->pakBedrag($rest);
        }

        if ($type === Task::TYPE_KOPEN) {
            $winkel = $this->pakWinkel($rest);
        }

        if ($type === Task::TYPE_LEREN) {
            $soort = $this->pakSoort($rest) ?? $this->soortUitUrl($url);
        }

        $titel = trim(preg_replace('/\s{2,}/', ' ', $rest));

        // Niets overgehouden? Dan was het toch allemaal titel.
        if ($titel === '') {
            $titel = trim($invoer);
        }

        return [
            'title'       => $titel,
            'notes'       => null,
            'due_date'    => $datum,
            'price_cents' => $prijs,
            'vendor'      => $winkel,
            'url'         => $url,
            'kind'        => $soort,
        ];
    }

    /** "€ 89,50" / "89 euro" / "89,-" → 8950 */
    public function bedragNaarCenten(?string $bedrag): ?int
    {
        if ($bedrag === null || trim($bedrag) === '') return null;

        $schoon = preg_replace('/[^0-9,.]/', '', $bedrag);
        if ($schoon === '') return null;

        // Nederlandse notatie: punt is duizendtal, komma is decimaal
        $schoon = str_replace('.', '', $schoon);
        $schoon = str_replace(',', '.', $schoon);

        return (int) round(((float) $schoon) * 100);
    }

    public static function kindLabel(?string $soort): string
    {
        return match ($soort) {
            'cursus'  => 'Cursussen',
            'boek'    => 'Boeken',
            'video'   => 'Video’s',
            'artikel' => 'Artikelen',
            'podcast' => 'Podcasts',
            default   => 'Overig',
        };
    }

    // ── Losse onderdelen ─────────────────────────────────────────────────────

    private function pakUrl(string &$rest): ?string
    {
        if (! preg_match('#https?://\S+#i', $rest, $m)) return null;

        $rest = trim(str_replace($m[0], '', $rest));

        return mb_substr($m[0], 0, 500);
    }

    private function pakBedrag(string &$rest): ?int
    {
        // € 89 / €89,50 / 89 euro / 89,-
        $patronen = [
            '/€\s?(\d+(?:[.,]\d{1,2})?)/u',
            '/\b(\d+(?:[.,]\d{1,2})?)\s?euro\b/iu',
            '/\b(\d+)(?:,-|,00)\B/u',
        ];

        foreach ($patronen as $patroon) {
            if (preg_match($patroon, $rest, $m)) {
                $rest = trim(str_replace($m[0], '', $rest));

                return $this->bedragNaarCenten($m[1]);
            }
        }

        return null;
    }

    private function pakWinkel(string &$rest): ?string
    {
        // @conrad
        if (preg_match('/(?:^|\s)@([\p{L}0-9&\'-]{2,40})/u', $rest, $m)) {
            $rest = trim(str_replace($m[0], ' ', $rest));

            return ucfirst($m[1]);
        }

        // "… bij Conrad" aan het eind
        if (preg_match('/\sbij\s+([\p{L}0-9&\'-]{2,40})\s*$/u', $rest, $m)) {
            $rest = trim(str_replace($m[0], '', $rest));

            return ucfirst($m[1]);
        }

        return null;
    }

    private function pakSoort(string &$rest): ?string
    {
        foreach (self::SOORTEN as $woord => $soort) {
            if (preg_match('/(?:^|\s)' . $woord . '(?:\s|$)/iu', $rest)) {
                return $soort; // laten staan in de titel, het is meestal beschrijvend
            }
        }

        return null;
    }

    private function soortUitUrl(?string $url): ?string
    {
        if (! $url) return null;

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return match (true) {
            str_contains($host, 'youtube') || str_contains($host, 'youtu.be') || str_contains($host, 'vimeo') => 'video',
            str_contains($host, 'spotify') || str_contains($host, 'podcast') => 'podcast',
            str_contains($host, 'bol.com') || str_contains($host, 'boek')    => 'boek',
            default => 'artikel',
        };
    }

    private function pakDatum(string &$rest): ?string
    {
        $vervang = function (string $treffer) use (&$rest) {
            $rest = trim(preg_replace('/(?:^|\s)' . preg_quote($treffer, '/') . '(?=\s|$)/iu', ' ', $rest));
        };

        if (preg_match('/(?:^|\s)(vandaag)(?:\s|$)/iu', $rest, $m)) {
            $vervang($m[1]);
            return now()->toDateString();
        }

        if (preg_match('/(?:^|\s)(morgen)(?:\s|$)/iu', $rest, $m)) {
            $vervang($m[1]);
            return now()->addDay()->toDateString();
        }

        if (preg_match('/(?:^|\s)(overmorgen)(?:\s|$)/iu', $rest, $m)) {
            $vervang($m[1]);
            return now()->addDays(2)->toDateString();
        }

        foreach (self::DAGEN as $naam => $nummer) {
            if (preg_match('/(?:^|\s)(' . $naam . ')(?:\s|$)/iu', $rest, $m)) {
                $vervang($m[1]);

                return Carbon::now()->next($nummer)->toDateString();
            }
        }

        // 24-12 of 24/12 (optioneel met jaar)
        if (preg_match('/(?:^|\s)(\d{1,2})[-\/](\d{1,2})(?:[-\/](\d{2,4}))?(?:\s|$)/u', $rest, $m)) {
            $jaar = isset($m[3]) ? (int) (strlen($m[3]) === 2 ? '20' . $m[3] : $m[3]) : (int) now()->year;

            if (checkdate((int) $m[2], (int) $m[1], $jaar)) {
                $datum = Carbon::createFromDate($jaar, (int) $m[2], (int) $m[1])->startOfDay();

                // Datum zonder jaar die al geweest is → volgend jaar
                if (! isset($m[3]) && $datum->isBefore(now()->startOfDay())) {
                    $datum->addYear();
                }

                $rest = trim(str_replace($m[0], ' ', $rest));

                return $datum->toDateString();
            }
        }

        return null;
    }
}
