<?php

namespace Tests\Unit;

use App\Models\Evento;
use App\Models\SesionCongreso;
use App\Models\Taller;
use App\Support\Taller\ResolverPrecioTallerData;
use Tests\TestCase;

/**
 * Cobertura de las reglas de precio efectivo de una sesión de taller.
 * Ver brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md §1.5 y
 * decisión T6 (talleres NO entran en la base del fee).
 */
class ResolverPrecioTallerDataTest extends TestCase
{
    private function evento(bool $conCosto): Evento
    {
        $e = new Evento();
        $e->talleres_con_costo = $conCosto;
        return $e;
    }

    private function taller(?float $precio): Taller
    {
        $t = new Taller();
        $t->precio = $precio;
        return $t;
    }

    private function sesion(?float $precio): SesionCongreso
    {
        $s = new SesionCongreso();
        $s->precio = $precio;
        return $s;
    }

    public function test_evento_sin_costo_siempre_devuelve_cero(): void
    {
        $evento = $this->evento(false);
        $taller = $this->taller(50.0);
        $sesion = $this->sesion(80.0);

        $this->assertSame(0.0, ResolverPrecioTallerData::unitPrice($taller, $sesion, $evento));
        $this->assertSame(0.0, ResolverPrecioTallerData::total($taller, $sesion, $evento));
    }

    public function test_override_de_sesion_gana_sobre_precio_del_taller(): void
    {
        $evento = $this->evento(true);
        $taller = $this->taller(50.0);
        $sesion = $this->sesion(80.0);

        $this->assertSame(80.0, ResolverPrecioTallerData::unitPrice($taller, $sesion, $evento));
    }

    public function test_hereda_precio_del_taller_si_la_sesion_no_tiene_override(): void
    {
        $evento = $this->evento(true);
        $taller = $this->taller(50.0);
        $sesion = $this->sesion(null);

        $this->assertSame(50.0, ResolverPrecioTallerData::unitPrice($taller, $sesion, $evento));
    }

    public function test_devuelve_cero_si_no_hay_precio_en_ningun_nivel(): void
    {
        $evento = $this->evento(true);
        $taller = $this->taller(null);
        $sesion = $this->sesion(null);

        $this->assertSame(0.0, ResolverPrecioTallerData::unitPrice($taller, $sesion, $evento));
        $this->assertSame(0.0, ResolverPrecioTallerData::total($taller, $sesion, $evento));
    }

    public function test_total_redondea_a_dos_decimales(): void
    {
        $evento = $this->evento(true);
        $taller = $this->taller(33.333);
        $sesion = $this->sesion(null);

        $this->assertSame(33.33, ResolverPrecioTallerData::total($taller, $sesion, $evento));
    }
}