<?php

namespace Tests\Unit;

use App\Models\Task;
use App\Services\TodoQuickParser;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class TodoQuickParserTest extends TestCase
{
    private TodoQuickParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new TodoQuickParser();
        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:00')); // dinsdag
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_haalt_prijs_winkel_en_datum_uit_een_koopregel(): void
    {
        $r = $this->parser->parse('accu 12V voor unit 3 € 89 @conrad morgen', Task::TYPE_KOPEN);

        $this->assertSame('accu 12V voor unit 3', $r['title']);
        $this->assertSame(8900, $r['price_cents']);
        $this->assertSame('Conrad', $r['vendor']);
        $this->assertSame('2026-09-16', $r['due_date']);
    }

    public function test_herkent_winkel_achter_het_woord_bij(): void
    {
        $r = $this->parser->parse('bordjes en tape bij Action', Task::TYPE_KOPEN);

        $this->assertSame('bordjes en tape', $r['title']);
        $this->assertSame('Action', $r['vendor']);
    }

    public function test_laat_losse_getallen_met_rust(): void
    {
        $r = $this->parser->parse('accu 12V unit 3', Task::TYPE_KOPEN);

        $this->assertNull($r['price_cents']);
        $this->assertSame('accu 12V unit 3', $r['title']);
    }

    public function test_pakt_link_en_soort_bij_leren(): void
    {
        $r = $this->parser->parse('Laravel queues cursus https://laracasts.com/queues', Task::TYPE_LEREN);

        $this->assertSame('https://laracasts.com/queues', $r['url']);
        $this->assertSame('cursus', $r['kind']);
        $this->assertSame('Laravel queues cursus', $r['title']);
    }

    public function test_raadt_soort_uit_de_link_als_het_woord_ontbreekt(): void
    {
        $r = $this->parser->parse('https://youtube.com/watch?v=abc bekijken', Task::TYPE_LEREN);

        $this->assertSame('video', $r['kind']);
    }

    public function test_weekdag_wordt_de_eerstvolgende(): void
    {
        $r = $this->parser->parse('Hotel Nassau nabellen vrijdag');

        $this->assertSame('2026-09-18', $r['due_date']);
        $this->assertSame('Hotel Nassau nabellen', $r['title']);
    }

    public function test_datum_zonder_jaar_die_al_geweest_is_schuift_naar_volgend_jaar(): void
    {
        $r = $this->parser->parse('jaarrekening 10-01');

        $this->assertSame('2027-01-10', $r['due_date']);
    }

    public function test_prijzen_in_nederlandse_notatie(): void
    {
        $this->assertSame(8950, $this->parser->bedragNaarCenten('€ 89,50'));
        $this->assertSame(123450, $this->parser->bedragNaarCenten('1.234,50'));
        $this->assertNull($this->parser->bedragNaarCenten(''));
    }

    public function test_een_regel_die_alleen_uit_herkende_dingen_bestaat_houdt_zijn_titel(): void
    {
        $r = $this->parser->parse('morgen', Task::TYPE_TAAK);

        $this->assertSame('morgen', $r['title']);
    }
}
