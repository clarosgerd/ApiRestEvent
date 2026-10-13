<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Evento;
use App\Models\Taller;
use App\Models\TallerCombo;
use App\Models\TallerComboPrecio;
use App\Support\Taller\ResolverCombosTallerAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Combos de talleres (12/10/2026, pedido del usuario) — casos límite del
 * matching/reparto descritos en el plan: subconjunto, combo superpuesto,
 * taller en 2 combos, redondeo, vigencia por fecha/activo. Precio por
 * categoría (13/10/2026) — el mismo combo puede cobrar distinto según la
 * categoría del participante, ver TallerCombo::precioParaCategoria().
 */
class ResolverCombosTallerActionTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evento = Evento::factory()->create();
    }

    private function crearCombo(array $talleres, array $overrides = []): TallerCombo
    {
        $combo = TallerCombo::create(array_merge([
            'evento_id' => $this->evento->id,
            'nombre' => 'Combo',
            'activo' => true,
        ], $overrides));

        $combo->talleres()->sync(array_map(fn (Taller $t) => $t->id, $talleres));

        return $combo;
    }

    /**
     * Precio por categoría (13/10/2026) — $categoriaId=null crea la fila
     * DEFAULT (aplica a cualquier categoría sin fila propia).
     */
    private function agregarPrecio(TallerCombo $combo, float $precio, ?int $categoriaId = null): TallerComboPrecio
    {
        $fila = TallerComboPrecio::create([
            'taller_combo_id' => $combo->id,
            'category_id' => $categoriaId,
            'precio' => $precio,
        ]);
        $combo->unsetRelation('precios');

        return $fila;
    }

    private function seleccion(Taller $taller, float $unitPrice, ?int $sesionId = null): array
    {
        static $next = 1;

        return [
            'sesionId' => $sesionId ?? $next++,
            'tallerId' => $taller->id,
            'unitPrice' => $unitPrice,
        ];
    }

    public function test_combo_2x1_cobra_el_precio_del_combo_en_vez_de_la_suma(): void
    {
        $a = Taller::factory()->create(['evento_id' => $this->evento->id, 'precio' => 100]);
        $b = Taller::factory()->create(['evento_id' => $this->evento->id, 'precio' => 100]);
        $combo = $this->crearCombo([$a, $b]);
        $this->agregarPrecio($combo, 100);

        $resultado = ResolverCombosTallerAction::resolver([
            $this->seleccion($a, 100),
            $this->seleccion($b, 100),
        ], $this->evento);

        $this->assertSame(100.0, $resultado[0]['discount'] + $resultado[1]['discount']);
        $this->assertSame(100.0, $resultado[0]['total'] + $resultado[1]['total']);
        $this->assertNotNull($resultado[0]['tallerComboId']);
        $this->assertSame($resultado[0]['tallerComboId'], $resultado[1]['tallerComboId']);
    }

    public function test_tercer_taller_fuera_del_combo_queda_a_precio_normal(): void
    {
        $a = Taller::factory()->create(['evento_id' => $this->evento->id, 'precio' => 100]);
        $b = Taller::factory()->create(['evento_id' => $this->evento->id, 'precio' => 100]);
        $c = Taller::factory()->create(['evento_id' => $this->evento->id, 'precio' => 50]);
        $combo = $this->crearCombo([$a, $b]);
        $this->agregarPrecio($combo, 100);

        $resultado = ResolverCombosTallerAction::resolver([
            $this->seleccion($a, 100),
            $this->seleccion($b, 100),
            $this->seleccion($c, 50),
        ], $this->evento);

        $this->assertSame(0.0, $resultado[2]['discount']);
        $this->assertSame(50.0, $resultado[2]['total']);
        $this->assertNull($resultado[2]['tallerComboId']);
    }

    public function test_elegir_solo_un_taller_del_combo_no_aplica_descuento(): void
    {
        $a = Taller::factory()->create(['evento_id' => $this->evento->id, 'precio' => 100]);
        $b = Taller::factory()->create(['evento_id' => $this->evento->id, 'precio' => 100]);
        $combo = $this->crearCombo([$a, $b]);
        $this->agregarPrecio($combo, 100);

        $resultado = ResolverCombosTallerAction::resolver([
            $this->seleccion($a, 100),
        ], $this->evento);

        $this->assertSame(0.0, $resultado[0]['discount']);
        $this->assertSame(100.0, $resultado[0]['total']);
        $this->assertNull($resultado[0]['tallerComboId']);
    }

    public function test_reparto_proporcional_con_redondeo_cierra_exacto(): void
    {
        $a = Taller::factory()->create(['evento_id' => $this->evento->id, 'precio' => 100]);
        $b = Taller::factory()->create(['evento_id' => $this->evento->id, 'precio' => 50]);
        // Suma 150, combo 100 -> descuento total 50, repartido 33.33/16.67
        // proporcional (100/150 y 50/150) con el último absorbiendo el resto.
        $combo = $this->crearCombo([$a, $b]);
        $this->agregarPrecio($combo, 100);

        $resultado = ResolverCombosTallerAction::resolver([
            $this->seleccion($a, 100),
            $this->seleccion($b, 50),
        ], $this->evento);

        $sumaDescuentos = round($resultado[0]['discount'] + $resultado[1]['discount'], 2);
        $sumaTotales = round($resultado[0]['total'] + $resultado[1]['total'], 2);
        $this->assertSame(50.0, $sumaDescuentos);
        $this->assertSame(100.0, $sumaTotales);
    }

    public function test_dos_combos_superpuestos_elige_el_de_mayor_ahorro(): void
    {
        $a = Taller::factory()->create(['evento_id' => $this->evento->id, 'precio' => 100]);
        $b = Taller::factory()->create(['evento_id' => $this->evento->id, 'precio' => 100]);
        $c = Taller::factory()->create(['evento_id' => $this->evento->id, 'precio' => 100]);
        // Combo 1: A+B por 150 (ahorro 50). Combo 2: A+C por 50 (ahorro 150) — gana este.
        $comboAB = $this->crearCombo([$a, $b], ['nombre' => 'Combo AB']);
        $this->agregarPrecio($comboAB, 150);
        $comboAC = $this->crearCombo([$a, $c], ['nombre' => 'Combo AC']);
        $this->agregarPrecio($comboAC, 50);

        $resultado = ResolverCombosTallerAction::resolver([
            $this->seleccion($a, 100),
            $this->seleccion($b, 100),
            $this->seleccion($c, 100),
        ], $this->evento);

        // A quedó consumido por el combo de mayor ahorro (AC) — B no tiene
        // con quién completar el combo AB y queda a precio normal.
        $this->assertSame($comboAC->id, $resultado[0]['tallerComboId']);
        $this->assertSame($comboAC->id, $resultado[2]['tallerComboId']);
        $this->assertNull($resultado[1]['tallerComboId']);
        $this->assertSame(0.0, $resultado[1]['discount']);
    }

    public function test_combo_vencido_no_se_aplica(): void
    {
        $a = Taller::factory()->create(['evento_id' => $this->evento->id, 'precio' => 100]);
        $b = Taller::factory()->create(['evento_id' => $this->evento->id, 'precio' => 100]);
        $combo = $this->crearCombo([$a, $b], [
            'fecha_desde' => Carbon::today()->subDays(20),
            'fecha_hasta' => Carbon::today()->subDays(10),
        ]);
        $this->agregarPrecio($combo, 100);

        $resultado = ResolverCombosTallerAction::resolver([
            $this->seleccion($a, 100),
            $this->seleccion($b, 100),
        ], $this->evento);

        $this->assertNull($resultado[0]['tallerComboId']);
        $this->assertNull($resultado[1]['tallerComboId']);
    }

    public function test_combo_inactivo_no_se_aplica(): void
    {
        $a = Taller::factory()->create(['evento_id' => $this->evento->id, 'precio' => 100]);
        $b = Taller::factory()->create(['evento_id' => $this->evento->id, 'precio' => 100]);
        $combo = $this->crearCombo([$a, $b], ['activo' => false]);
        $this->agregarPrecio($combo, 100);

        $resultado = ResolverCombosTallerAction::resolver([
            $this->seleccion($a, 100),
            $this->seleccion($b, 100),
        ], $this->evento);

        $this->assertNull($resultado[0]['tallerComboId']);
    }

    public function test_sin_combos_en_el_evento_no_cambia_nada(): void
    {
        $a = Taller::factory()->create(['evento_id' => $this->evento->id, 'precio' => 100]);

        $resultado = ResolverCombosTallerAction::resolver([
            $this->seleccion($a, 100),
        ], $this->evento);

        $this->assertSame(0.0, $resultado[0]['discount']);
        $this->assertSame(100.0, $resultado[0]['total']);
    }

    public function test_dos_sesiones_del_mismo_taller_la_mas_cara_queda_fuera_del_combo(): void
    {
        $a = Taller::factory()->create(['evento_id' => $this->evento->id, 'precio' => 100]);
        $b = Taller::factory()->create(['evento_id' => $this->evento->id, 'precio' => 100]);
        $combo = $this->crearCombo([$a, $b]);
        $this->agregarPrecio($combo, 100);

        // Participante eligió 2 sesiones del taller A (horarios distintos,
        // ej. el mismo taller se repite) con precios distintos.
        $resultado = ResolverCombosTallerAction::resolver([
            $this->seleccion($a, 100, 901),
            $this->seleccion($a, 120, 902),
            $this->seleccion($b, 100, 903),
        ], $this->evento);

        // La sesión 901 (más barata) entra al combo con B; la 902 queda
        // siempre a precio normal.
        $this->assertNotNull($resultado[0]['tallerComboId']);
        $this->assertNull($resultado[1]['tallerComboId']);
        $this->assertSame(0.0, $resultado[1]['discount']);
        $this->assertSame(120.0, $resultado[1]['total']);
    }

    // ── Precio por categoría (13/10/2026, pedido del usuario) ──────────

    public function test_mismo_combo_cobra_distinto_segun_la_categoria(): void
    {
        $a = Taller::factory()->create(['evento_id' => $this->evento->id, 'precio' => 700]);
        $b = Taller::factory()->create(['evento_id' => $this->evento->id, 'precio' => 500]);
        $especialista = Category::factory()->create(['event_id' => $this->evento->id]);
        $residente = Category::factory()->create(['event_id' => $this->evento->id]);
        $combo = $this->crearCombo([$a, $b]);
        $this->agregarPrecio($combo, 1000, $especialista->id);
        $this->agregarPrecio($combo, 800, $residente->id);

        $paraEspecialista = ResolverCombosTallerAction::resolver([
            $this->seleccion($a, 700, 1),
            $this->seleccion($b, 500, 2),
        ], $this->evento, (string) $especialista->id);
        $paraResidente = ResolverCombosTallerAction::resolver([
            $this->seleccion($a, 700, 3),
            $this->seleccion($b, 500, 4),
        ], $this->evento, (string) $residente->id);

        $this->assertSame(1000.0, round($paraEspecialista[0]['total'] + $paraEspecialista[1]['total'], 2));
        $this->assertSame(800.0, round($paraResidente[0]['total'] + $paraResidente[1]['total'], 2));
    }

    public function test_categoria_sin_fila_propia_ni_default_no_recibe_el_combo(): void
    {
        $a = Taller::factory()->create(['evento_id' => $this->evento->id, 'precio' => 700]);
        $b = Taller::factory()->create(['evento_id' => $this->evento->id, 'precio' => 500]);
        $especialista = Category::factory()->create(['event_id' => $this->evento->id]);
        $licenciado = Category::factory()->create(['event_id' => $this->evento->id]);
        $combo = $this->crearCombo([$a, $b]);
        $this->agregarPrecio($combo, 1000, $especialista->id); // sin fila default

        $resultado = ResolverCombosTallerAction::resolver([
            $this->seleccion($a, 700),
            $this->seleccion($b, 500),
        ], $this->evento, (string) $licenciado->id);

        $this->assertNull($resultado[0]['tallerComboId']);
        $this->assertSame(1200.0, round($resultado[0]['total'] + $resultado[1]['total'], 2));
    }

    public function test_categoria_sin_fila_propia_usa_la_fila_default(): void
    {
        $a = Taller::factory()->create(['evento_id' => $this->evento->id, 'precio' => 700]);
        $b = Taller::factory()->create(['evento_id' => $this->evento->id, 'precio' => 500]);
        $otraCategoria = Category::factory()->create(['event_id' => $this->evento->id]);
        $combo = $this->crearCombo([$a, $b]);
        $this->agregarPrecio($combo, 900); // default, sin category_id

        $resultado = ResolverCombosTallerAction::resolver([
            $this->seleccion($a, 700),
            $this->seleccion($b, 500),
        ], $this->evento, (string) $otraCategoria->id);

        $this->assertNotNull($resultado[0]['tallerComboId']);
        $this->assertSame(900.0, round($resultado[0]['total'] + $resultado[1]['total'], 2));
    }
}
