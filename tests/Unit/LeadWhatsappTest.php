<?php

namespace Tests\Unit;

use App\Models\Lead;
use PHPUnit\Framework\TestCase;

class LeadWhatsappTest extends TestCase
{
    private function lead(?string $telefoon): Lead
    {
        $lead = new Lead();
        $lead->phone = $telefoon;

        return $lead;
    }

    public function test_nederlands_mobiel_krijgt_landcode(): void
    {
        $this->assertSame('31610713382', $this->lead('0610713382')->whatsappNummer());
        $this->assertSame('31610713382', $this->lead('06 10 71 33 82')->whatsappNummer());
        $this->assertSame('31610713382', $this->lead('06-10713382')->whatsappNummer());
    }

    public function test_internationale_notaties(): void
    {
        $this->assertSame('31610713382', $this->lead('+31 6 10713382')->whatsappNummer());
        $this->assertSame('31610713382', $this->lead('0031610713382')->whatsappNummer());
        $this->assertSame('4915112345678', $this->lead('+49 151 12345678')->whatsappNummer());
    }

    public function test_vast_nummer_werkt_ook(): void
    {
        $this->assertSame('31308774491', $this->lead('030-8774491')->whatsappNummer());
    }

    public function test_geen_of_onbruikbaar_nummer_geeft_null(): void
    {
        $this->assertNull($this->lead(null)->whatsappNummer());
        $this->assertNull($this->lead('')->whatsappNummer());
        $this->assertNull($this->lead('12345')->whatsappNummer());
    }
}
