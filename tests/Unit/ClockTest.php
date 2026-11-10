<?php

namespace Tests\Unit;

use App\Core\Clock;
use Tests\Support\TestCase;

class ClockTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('FECHA_SIMULADA');
        parent::tearDown();
    }

    public function testLaFechaSimuladaCambiaElDiaYMantieneLaHoraReal(): void
    {
        Clock::fijar(null);
        putenv('FECHA_SIMULADA=2026-11-16');
        $this->assertSame('2026-11-16', Clock::hoy());
        $this->assertMatchesRegularExpression('/^2026-11-16 \d{2}:\d{2}:\d{2}$/', Clock::ahora());
    }

    public function testUnaFechaSimuladaConOtroFormatoSeIgnora(): void
    {
        Clock::fijar(null);
        putenv('FECHA_SIMULADA=16/11/2026');
        $real = (new \DateTime('now', new \DateTimeZone('America/Montevideo')))->format('Y-m-d');
        $this->assertSame($real, Clock::hoy());
    }
}
