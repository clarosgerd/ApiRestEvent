<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\SesionCongreso;
use App\Models\SubtipoEvento;
use App\Models\Taller;
use App\Models\TallerCombo;
use App\Models\TallerComboPrecio;
use App\Models\TallerPricePeriod;
use App\Models\TipoEvento;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * CRUD de precios por período y combos de talleres (12/10/2026, pedido del
 * usuario) — mismo criterio de scoping que TallerCrudTest/
 * PreciosPeriodosFechasTest: admin scoped a su evento, o super_admin.
 */
class TallerPricePeriodComboCrudTest extends TestCase
{
    private function superAdmin(): AdminUser
    {
        return AdminUser::factory()->create(['rol' => 'super_admin', 'evento_id' => null]);
    }

    private function adminDeEvento(int $eventoId): AdminUser
    {
        return AdminUser::factory()->create(['rol' => 'admin', 'evento_id' => $eventoId]);
    }

    private function evento(): Evento
    {
        $pais = Pais::first() ?? Pais::factory()->create();
        $ciudad = Ciudad::where('pais_id', $pais->id)->first() ?? Ciudad::factory()->create(['pais_id' => $pais->id]);
        $organizador = Organizador::first() ?? Organizador::factory()->create();
        $tipo = TipoEvento::firstOrCreate(['nombre' => 'Congreso / No aplica']);
        $subtipo = SubtipoEvento::where('tipo_evento_id', $tipo->id)->first()
            ?? SubtipoEvento::factory()->create(['tipo_evento_id' => $tipo->id]);

        return Evento::factory()->create([
            'organizador_id' => $organizador->id,
            'tipo_evento_id' => $tipo->id,
            'subtipo_evento_id' => $subtipo->id,
            'pais_id' => $pais->id,
            'ciudad_id' => $ciudad->id,
        ]);
    }

    // ── Períodos de precio de un taller ──────────────────────────────

    public function test_admin_scoped_puede_crear_periodo_de_precio_de_su_taller(): void
    {
        $evento = $this->evento();
        $taller = Taller::factory()->create(['evento_id' => $evento->id, 'precio' => 80]);
        $this->actingAsAdmin($this->adminDeEvento($evento->id));

        $resp = $this->postJson("/api/v1/taller/{$taller->id}/periodos", [
            'nombre' => 'Preventa', 'price' => 50,
            'fecha_desde' => Carbon::today()->toDateString(),
            'fecha_hasta' => Carbon::today()->addDays(10)->toDateString(),
        ]);

        $resp->assertCreated()->assertJson(['success' => true]);
        $this->assertDatabaseHas('taller_price_periods', ['taller_id' => $taller->id, 'nombre' => 'Preventa']);
        $this->assertEquals(50, $resp->json('data.precioVigente'));
    }

    public function test_admin_de_otro_evento_no_puede_crear_periodo(): void
    {
        $evento = $this->evento();
        $otro = $this->evento();
        $taller = Taller::factory()->create(['evento_id' => $evento->id]);
        $this->actingAsAdmin($this->adminDeEvento($otro->id));

        $this->postJson("/api/v1/taller/{$taller->id}/periodos", [
            'nombre' => 'Preventa', 'price' => 50,
            'fecha_desde' => Carbon::today()->toDateString(),
            'fecha_hasta' => Carbon::today()->addDays(10)->toDateString(),
        ])->assertStatus(403);
    }

    public function test_rechaza_overlap_de_periodo_del_mismo_taller(): void
    {
        $evento = $this->evento();
        $taller = Taller::factory()->create(['evento_id' => $evento->id]);
        $this->actingAsAdmin($this->superAdmin());

        TallerPricePeriod::create([
            'taller_id' => $taller->id, 'nombre' => 'Preventa', 'price' => 50,
            'fecha_desde' => Carbon::today(), 'fecha_hasta' => Carbon::today()->addDays(10),
        ]);

        $this->postJson("/api/v1/taller/{$taller->id}/periodos", [
            'nombre' => 'Precio regular', 'price' => 65,
            'fecha_desde' => Carbon::today()->addDays(5),
            'fecha_hasta' => Carbon::today()->addDays(20),
        ])->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_show_del_taller_trae_precio_vigente_y_periodos(): void
    {
        $evento = $this->evento();
        $taller = Taller::factory()->create(['evento_id' => $evento->id, 'precio' => 80]);
        TallerPricePeriod::create([
            'taller_id' => $taller->id, 'nombre' => 'Preventa', 'price' => 50,
            'fecha_desde' => Carbon::today()->subDays(5), 'fecha_hasta' => Carbon::today()->addDays(5),
        ]);
        $this->actingAsAdmin($this->superAdmin());

        $resp = $this->getJson("/api/v1/event/{$evento->id}/talleres/{$taller->id}");

        $resp->assertOk();
        $this->assertEquals(50, $resp->json('data.precioVigente'));
        $this->assertSame('Preventa', $resp->json('data.periodoVigenteNombre'));
        $this->assertCount(1, $resp->json('data.periodos'));
    }

    public function test_destroy_elimina_el_periodo(): void
    {
        $evento = $this->evento();
        $taller = Taller::factory()->create(['evento_id' => $evento->id]);
        $this->actingAsAdmin($this->superAdmin());

        $periodo = TallerPricePeriod::create([
            'taller_id' => $taller->id, 'nombre' => 'Preventa', 'price' => 50,
            'fecha_desde' => Carbon::today(), 'fecha_hasta' => Carbon::today()->addDays(10),
        ]);

        $this->deleteJson("/api/v1/taller-price-period/{$periodo->id}")
            ->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseMissing('taller_price_periods', ['id' => $periodo->id]);
    }

    // ── Combos de talleres ────────────────────────────────────────────

    private function dosTalleresConSesionesCompatibles(Evento $evento): array
    {
        $a = Taller::factory()->create(['evento_id' => $evento->id, 'precio' => 100]);
        $b = Taller::factory()->create(['evento_id' => $evento->id, 'precio' => 100]);
        SesionCongreso::factory()->create([
            'evento_id' => $evento->id, 'taller_id' => $a->id,
            'hora_inicio' => '09:00:00', 'hora_fin' => '10:00:00',
        ]);
        SesionCongreso::factory()->create([
            'evento_id' => $evento->id, 'taller_id' => $b->id,
            'hora_inicio' => '11:00:00', 'hora_fin' => '12:00:00',
        ]);

        return [$a, $b];
    }

    public function test_admin_scoped_puede_crear_combo_con_talleres_de_su_evento(): void
    {
        $evento = $this->evento();
        [$a, $b] = $this->dosTalleresConSesionesCompatibles($evento);
        $this->actingAsAdmin($this->adminDeEvento($evento->id));

        $resp = $this->postJson("/api/v1/event/{$evento->id}/taller-combos", [
            'nombre' => 'Combo A+B',
            'taller_ids' => [$a->id, $b->id],
        ]);

        $resp->assertCreated()->assertJson(['success' => true]);
        $this->assertDatabaseHas('taller_combos', ['evento_id' => $evento->id, 'nombre' => 'Combo A+B']);
        $this->assertDatabaseHas('taller_combo_items', ['taller_id' => $a->id]);
        $this->assertDatabaseHas('taller_combo_items', ['taller_id' => $b->id]);
        $this->assertTrue($resp->json('data.vigente'));
        $this->assertSame([], $resp->json('data.precios'));
    }

    public function test_rechaza_taller_de_otro_evento_en_el_combo(): void
    {
        $evento = $this->evento();
        $otroEvento = $this->evento();
        [$a] = $this->dosTalleresConSesionesCompatibles($evento);
        $tallerAjeno = Taller::factory()->create(['evento_id' => $otroEvento->id]);
        $this->actingAsAdmin($this->superAdmin());

        $this->postJson("/api/v1/event/{$evento->id}/taller-combos", [
            'nombre' => 'Combo inválido',
            'taller_ids' => [$a->id, $tallerAjeno->id],
        ])->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_rechaza_combo_con_talleres_sin_ningun_horario_compatible(): void
    {
        $evento = $this->evento();
        $a = Taller::factory()->create(['evento_id' => $evento->id]);
        $b = Taller::factory()->create(['evento_id' => $evento->id]);
        // Mismo horario exacto en ambos -> nunca se podrían elegir juntos.
        SesionCongreso::factory()->create([
            'evento_id' => $evento->id, 'taller_id' => $a->id,
            'hora_inicio' => '09:00:00', 'hora_fin' => '10:00:00',
        ]);
        SesionCongreso::factory()->create([
            'evento_id' => $evento->id, 'taller_id' => $b->id,
            'hora_inicio' => '09:00:00', 'hora_fin' => '10:00:00',
        ]);
        $this->actingAsAdmin($this->superAdmin());

        $this->postJson("/api/v1/event/{$evento->id}/taller-combos", [
            'nombre' => 'Combo imposible',
            'taller_ids' => [$a->id, $b->id],
        ])->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_permite_combo_cuando_alguno_todavia_no_tiene_sesiones_cargadas(): void
    {
        $evento = $this->evento();
        $a = Taller::factory()->create(['evento_id' => $evento->id]);
        $b = Taller::factory()->create(['evento_id' => $evento->id]); // sin sesiones todavía
        SesionCongreso::factory()->create([
            'evento_id' => $evento->id, 'taller_id' => $a->id,
            'hora_inicio' => '09:00:00', 'hora_fin' => '10:00:00',
        ]);
        $this->actingAsAdmin($this->superAdmin());

        $this->postJson("/api/v1/event/{$evento->id}/taller-combos", [
            'nombre' => 'Combo en armado',
            'taller_ids' => [$a->id, $b->id],
        ])->assertCreated();
    }

    public function test_destroy_elimina_el_combo_y_no_rompe_filas_ya_facturadas(): void
    {
        $evento = $this->evento();
        [$a, $b] = $this->dosTalleresConSesionesCompatibles($evento);
        $this->actingAsAdmin($this->superAdmin());

        $combo = TallerCombo::create(['evento_id' => $evento->id, 'nombre' => 'Combo']);
        $combo->talleres()->sync([$a->id, $b->id]);

        $this->deleteJson("/api/v1/taller-combo/{$combo->id}")
            ->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseMissing('taller_combos', ['id' => $combo->id]);
        $this->assertDatabaseMissing('taller_combo_items', ['taller_combo_id' => $combo->id]);
    }

    // ── Precio por categoría del combo (13/10/2026, pedido del usuario) ─

    public function test_admin_scoped_puede_cargar_precio_default_y_por_categoria(): void
    {
        $evento = $this->evento();
        [$a, $b] = $this->dosTalleresConSesionesCompatibles($evento);
        $combo = TallerCombo::create(['evento_id' => $evento->id, 'nombre' => 'Combo']);
        $combo->talleres()->sync([$a->id, $b->id]);
        $especialista = Category::factory()->create(['event_id' => $evento->id, 'name' => 'Especialistas']);
        $this->actingAsAdmin($this->adminDeEvento($evento->id));

        $respDefault = $this->postJson("/api/v1/taller-combo/{$combo->id}/precios", ['precio' => 900]);
        $respDefault->assertCreated()->assertJson(['success' => true]);
        $this->assertDatabaseHas('taller_combo_precios', ['taller_combo_id' => $combo->id, 'category_id' => null, 'precio' => 900]);

        $respCategoria = $this->postJson("/api/v1/taller-combo/{$combo->id}/precios", [
            'category_id' => $especialista->id, 'precio' => 1000,
        ]);
        $respCategoria->assertCreated()->assertJson(['success' => true]);
        $this->assertDatabaseHas('taller_combo_precios', ['taller_combo_id' => $combo->id, 'category_id' => $especialista->id, 'precio' => 1000]);
        $this->assertCount(2, $respCategoria->json('data.precios'));
    }

    public function test_rechaza_categoria_de_otro_evento_en_el_precio_del_combo(): void
    {
        $evento = $this->evento();
        $otroEvento = $this->evento();
        [$a, $b] = $this->dosTalleresConSesionesCompatibles($evento);
        $combo = TallerCombo::create(['evento_id' => $evento->id, 'nombre' => 'Combo']);
        $combo->talleres()->sync([$a->id, $b->id]);
        $categoriaAjena = Category::factory()->create(['event_id' => $otroEvento->id]);
        $this->actingAsAdmin($this->superAdmin());

        $this->postJson("/api/v1/taller-combo/{$combo->id}/precios", [
            'category_id' => $categoriaAjena->id, 'precio' => 1000,
        ])->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_rechaza_una_segunda_fila_default_para_el_mismo_combo(): void
    {
        $evento = $this->evento();
        [$a, $b] = $this->dosTalleresConSesionesCompatibles($evento);
        $combo = TallerCombo::create(['evento_id' => $evento->id, 'nombre' => 'Combo']);
        $combo->talleres()->sync([$a->id, $b->id]);
        TallerComboPrecio::create(['taller_combo_id' => $combo->id, 'precio' => 900]);
        $this->actingAsAdmin($this->superAdmin());

        $this->postJson("/api/v1/taller-combo/{$combo->id}/precios", ['precio' => 850])
            ->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_update_cambia_el_precio_de_la_fila(): void
    {
        $evento = $this->evento();
        [$a, $b] = $this->dosTalleresConSesionesCompatibles($evento);
        $combo = TallerCombo::create(['evento_id' => $evento->id, 'nombre' => 'Combo']);
        $combo->talleres()->sync([$a->id, $b->id]);
        $precio = TallerComboPrecio::create(['taller_combo_id' => $combo->id, 'precio' => 900]);
        $this->actingAsAdmin($this->superAdmin());

        $this->putJson("/api/v1/taller-combo-precio/{$precio->id}", ['precio' => 950])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('taller_combo_precios', ['id' => $precio->id, 'precio' => 950]);
    }

    public function test_destroy_elimina_la_fila_de_precio(): void
    {
        $evento = $this->evento();
        [$a, $b] = $this->dosTalleresConSesionesCompatibles($evento);
        $combo = TallerCombo::create(['evento_id' => $evento->id, 'nombre' => 'Combo']);
        $combo->talleres()->sync([$a->id, $b->id]);
        $precio = TallerComboPrecio::create(['taller_combo_id' => $combo->id, 'precio' => 900]);
        $this->actingAsAdmin($this->superAdmin());

        $this->deleteJson("/api/v1/taller-combo-precio/{$precio->id}")
            ->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseMissing('taller_combo_precios', ['id' => $precio->id]);
    }
}
