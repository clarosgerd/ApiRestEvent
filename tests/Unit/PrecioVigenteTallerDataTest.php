<?php

namespace Tests\Unit;

use App\Models\Category;
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

    // ── Período por categoría (13/10/2026, pedido del usuario) ──────────

    /**
     * El ejemplo exacto del usuario: un período "Especialista" vigente hoy
     * gana sobre el precio base Y sobre un período GENERAL vigente las
     * mismas fechas — la categoría con track propio nunca mira el general.
     */
    public function test_periodo_de_categoria_especifica_gana_sobre_el_general_vigente_las_mismas_fechas(): void
    {
        $taller = Taller::factory()->create(['precio' => 80]);
        $categoria = Category::factory()->create();
        TallerPricePeriod::create([
            'taller_id' => $taller->id, 'category_id' => null,
            'nombre' => 'Preventa general', 'price' => 50,
            'fecha_desde' => Carbon::today()->subDays(5), 'fecha_hasta' => Carbon::today()->addDays(5),
        ]);
        TallerPricePeriod::create([
            'taller_id' => $taller->id, 'category_id' => $categoria->id,
            'nombre' => 'Preventa especialista', 'price' => 100,
            'fecha_desde' => Carbon::today()->subDays(5), 'fecha_hasta' => Carbon::today()->addDays(5),
        ]);

        $vigenteEspecialista = PrecioVigenteTallerData::paraTaller($taller->fresh(), (string) $categoria->id);
        $this->assertSame(100.0, $vigenteEspecialista['precio']);
        $this->assertSame('Preventa especialista', $vigenteEspecialista['periodo_nombre']);

        // Sin categoría (o cualquier otra sin período propio) sigue viendo
        // el general, sin cambios.
        $vigenteGeneral = PrecioVigenteTallerData::paraTaller($taller->fresh());
        $this->assertSame(50.0, $vigenteGeneral['precio']);
    }

    public function test_categoria_sin_periodo_propio_usa_el_general(): void
    {
        $taller = Taller::factory()->create(['precio' => 80]);
        $categoriaSinPeriodo = Category::factory()->create();
        TallerPricePeriod::create([
            'taller_id' => $taller->id, 'category_id' => null,
            'nombre' => 'Preventa general', 'price' => 50,
            'fecha_desde' => Carbon::today()->subDays(5), 'fecha_hasta' => Carbon::today()->addDays(5),
        ]);

        $vigente = PrecioVigenteTallerData::paraTaller($taller->fresh(), (string) $categoriaSinPeriodo->id);

        $this->assertSame(50.0, $vigente['precio']);
        $this->assertSame('Preventa general', $vigente['periodo_nombre']);
    }

    /**
     * Decisión confirmada con el usuario: una categoría con períodos
     * propios usa ESE track exclusivamente — si ninguno de sus propios
     * períodos está vigente NI vencido hoy (acá: todos a futuro, caso 4 del
     * algoritmo), cae directo al precio BASE del taller, nunca al período
     * general (aunque el general sí esté vigente hoy).
     */
    public function test_categoria_con_periodo_propio_solo_a_futuro_cae_al_precio_base_no_al_general(): void
    {
        $taller = Taller::factory()->create(['precio' => 80]);
        $categoria = Category::factory()->create();
        TallerPricePeriod::create([
            'taller_id' => $taller->id, 'category_id' => null,
            'nombre' => 'General vigente', 'price' => 50,
            'fecha_desde' => Carbon::today()->subDays(5), 'fecha_hasta' => Carbon::today()->addDays(5),
        ]);
        TallerPricePeriod::create([
            'taller_id' => $taller->id, 'category_id' => $categoria->id,
            'nombre' => 'Especialista futuro', 'price' => 100,
            'fecha_desde' => Carbon::today()->addDays(20), 'fecha_hasta' => Carbon::today()->addDays(30),
        ]);

        $vigente = PrecioVigenteTallerData::paraTaller($taller->fresh(), (string) $categoria->id);

        $this->assertSame(80.0, $vigente['precio']);
        $this->assertNull($vigente['periodo_nombre']);
    }

    /**
     * Mismo criterio, pero caso 3 (vencido más reciente) DENTRO del track
     * específico — el período vencido de la categoría gana sobre su propio
     * precio base, y el track general ni se mira.
     */
    public function test_categoria_con_periodo_propio_vencido_usa_ese_vencido_no_el_general(): void
    {
        $taller = Taller::factory()->create(['precio' => 80]);
        $categoria = Category::factory()->create();
        TallerPricePeriod::create([
            'taller_id' => $taller->id, 'category_id' => null,
            'nombre' => 'General vigente', 'price' => 50,
            'fecha_desde' => Carbon::today()->subDays(5), 'fecha_hasta' => Carbon::today()->addDays(5),
        ]);
        TallerPricePeriod::create([
            'taller_id' => $taller->id, 'category_id' => $categoria->id,
            'nombre' => 'Especialista vencido', 'price' => 100,
            'fecha_desde' => Carbon::today()->subDays(30), 'fecha_hasta' => Carbon::today()->subDays(20),
        ]);

        $vigente = PrecioVigenteTallerData::paraTaller($taller->fresh(), (string) $categoria->id);

        $this->assertSame(100.0, $vigente['precio']);
        $this->assertSame('Especialista vencido', $vigente['periodo_nombre']);
    }
}
