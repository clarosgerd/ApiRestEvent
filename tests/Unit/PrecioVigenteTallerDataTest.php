<?php

namespace Tests\Unit;

use App\Models\Taller;
use App\Models\TallerPricePeriod;
use App\Support\Taller\PrecioVigenteTallerData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Precios por período para talleres (12/10/2026) — mismos 4 casos que
 * PreciosPeriodosFechasTest cubre para PrecioVigenteData::paraCategoria(),
 * acá aplicados a PrecioVigenteTallerData::paraTaller().
 */
class PrecioVigenteTallerDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_periodos_devuelve_el_precio_del_taller(): void
    {
        $taller = Taller::factory()->create(['precio' => 80]);

        $vigente = PrecioVigenteTallerData::paraTaller($taller);

        $this->assertSame(80.0, $vigente['precio']);
        $this->assertNull($vigente['periodo_nombre']);
        $this->assertNull($vigente['periodo_fecha_hasta']);
        $this->assertSame([], $vigente['periodos']);
    }

    public function test_sin_precio_ni_periodos_devuelve_cero(): void
    {
        $taller = Taller::factory()->create(['precio' => null]);

        $vigente = PrecioVigenteTallerData::paraTaller($taller);

        $this->assertSame(0.0, $vigente['precio']);
    }

    public function test_periodo_vigente_hoy_gana_sobre_el_precio_base(): void
    {
        $taller = Taller::factory()->create(['precio' => 80]);
        TallerPricePeriod::create([
            'taller_id' => $taller->id,
            'nombre' => 'Preventa',
            'price' => 50,
            'fecha_desde' => Carbon::today()->subDays(5),
            'fecha_hasta' => Carbon::today()->addDays(5),
        ]);

        $vigente = PrecioVigenteTallerData::paraTaller($taller->fresh());

        $this->assertSame(50.0, $vigente['precio']);
        $this->assertSame('Preventa', $vigente['periodo_nombre']);
    }

    public function test_hueco_entre_periodos_cae_al_vencido_mas_reciente(): void
    {
        $taller = Taller::factory()->create(['precio' => 80]);
        TallerPricePeriod::create([
            'taller_id' => $taller->id,
            'nombre' => 'Preventa',
            'price' => 50,
            'fecha_desde' => Carbon::today()->subDays(20),
            'fecha_hasta' => Carbon::today()->subDays(10),
        ]);
        TallerPricePeriod::create([
            'taller_id' => $taller->id,
            'nombre' => 'Precio regular',
            'price' => 65,
            'fecha_desde' => Carbon::today()->subDays(9),
            'fecha_hasta' => Carbon::today()->subDays(2),
        ]);

        $vigente = PrecioVigenteTallerData::paraTaller($taller->fresh());

        $this->assertSame(65.0, $vigente['precio']);
        $this->assertSame('Precio regular', $vigente['periodo_nombre']);
    }

    public function test_todos_los_periodos_futuros_cae_al_precio_base(): void
    {
        $taller = Taller::factory()->create(['precio' => 80]);
        TallerPricePeriod::create([
            'taller_id' => $taller->id,
            'nombre' => 'Preventa',
            'price' => 50,
            'fecha_desde' => Carbon::today()->addDays(5),
            'fecha_hasta' => Carbon::today()->addDays(15),
        ]);

        $vigente = PrecioVigenteTallerData::paraTaller($taller->fresh());

        $this->assertSame(80.0, $vigente['precio']);
        $this->assertNull($vigente['periodo_nombre']);
    }

    public function test_price_usd_del_periodo_cae_al_price_usd_del_taller_si_falta(): void
    {
        $taller = Taller::factory()->create(['precio' => 80, 'price_usd' => 12]);
        TallerPricePeriod::create([
            'taller_id' => $taller->id,
            'nombre' => 'Preventa',
            'price' => 50,
            'price_usd' => null,
            'fecha_desde' => Carbon::today()->subDays(5),
            'fecha_hasta' => Carbon::today()->addDays(5),
        ]);

        $vigente = PrecioVigenteTallerData::paraTaller($taller->fresh());

        $this->assertSame(12.0, $vigente['precio_usd']);
    }
}
