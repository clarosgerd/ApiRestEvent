<?php

namespace Tests\Unit;

use App\Support\FechaReporteData;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Hora real de Bolivia en reportes/resources (21/10/2026, pedido real del
 * usuario) — created_at/updated_at son UTC de verdad (config('app.timezone')),
 * pero los reportes los mostraban crudos: alguien en Bolivia leía la hora
 * UTC pensando que era la hora real, 4 horas adelantada.
 */
class FechaReporteDataTest extends TestCase
{
    public function test_convierte_una_fecha_utc_a_hora_real_de_bolivia(): void
    {
        $utc = Carbon::create(2026, 10, 21, 11, 47, 17, 'UTC');

        $iso = FechaReporteData::iso($utc);

        $this->assertSame('2026-10-21T07:47:17-04:00', $iso);
    }

    public function test_null_devuelve_null(): void
    {
        $this->assertNull(FechaReporteData::iso(null));
    }

    public function test_no_modifica_la_instancia_original(): void
    {
        $utc = Carbon::create(2026, 10, 21, 11, 47, 17, 'UTC');

        FechaReporteData::iso($utc);

        $this->assertSame('UTC', $utc->timezoneName);
    }
}
