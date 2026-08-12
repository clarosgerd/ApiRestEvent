<?php

namespace Tests\Feature;

use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cargo de servicio configurable por evento — ver
 * PRD-cargo-servicio-por-evento.md (sesión 11/08/2026). Antes 5% fijo
 * hardcodeado en elascenso/event, ahora `eventos.fee_pct` (fracción,
 * default 0.05), editable solo por super_admin.
 */
class EventoFeePctTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    protected function setUp(): void
    {
        parent::setUp();

        $pais = Pais::factory()->create();
        $ciudad = Ciudad::factory()->create(['pais_id' => $pais->id]);
        $organizador = Organizador::factory()->create();
        $tipoEvento = TipoEvento::factory()->create();
        $subtipoEvento = SubtipoEvento::factory()->create(['tipo_evento_id' => $tipoEvento->id]);

        $this->evento = Evento::factory()->create([
            'organizador_id' => $organizador->id,
            'tipo_evento_id' => $tipoEvento->id,
            'subtipo_evento_id' => $subtipoEvento->id,
            'pais_id' => $pais->id,
            'ciudad_id' => $ciudad->id,
        ]);
    }

    public function test_evento_nuevo_tiene_5_por_ciento_por_default(): void
    {
        // refresh(): create() no vuelve a leer columnas con DEFAULT de la
        // BD que no se pasaron explícitamente (fee_pct no está en
        // EventoFactory) — el objeto en memoria queda con el valor antes
        // del insert, no el que MySQL le puso. Sin este refresh, esta
        // aserción falla aunque el valor guardado en la BD sí sea 0.05
        // (confirmado por el resto de los tests de este archivo, que
        // siempre leen vía una request/query fresca).
        $this->assertEquals(0.05, (float) $this->evento->refresh()->fee_pct);
    }

    public function test_event_resource_expone_fee_pct(): void
    {
        $this->getJson("/api/v1/event/{$this->evento->id}")
            ->assertOk()
            ->assertJsonPath('eventos.fee_pct', 0.05);
    }

    public function test_super_admin_puede_actualizar_fee_pct(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->putJson("/api/v1/event/{$this->evento->id}", ['feePct' => 0.08])
            ->assertOk();

        $this->assertEquals(0.08, (float) $this->evento->refresh()->fee_pct);
    }

    public function test_admin_scoped_no_puede_actualizar_fee_pct(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $this->evento->id]);

        $this->putJson("/api/v1/event/{$this->evento->id}", ['feePct' => 0.08])
            ->assertStatus(403);

        $this->assertEquals(0.05, (float) $this->evento->refresh()->fee_pct);
    }

    public function test_admin_scoped_si_puede_actualizar_otros_campos_sin_tocar_fee_pct(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $this->evento->id]);

        $this->putJson("/api/v1/event/{$this->evento->id}", ['description' => 'Nueva descripción'])
            ->assertOk();

        $this->evento->refresh();
        $this->assertSame('Nueva descripción', $this->evento->descripcion);
        $this->assertEquals(0.05, (float) $this->evento->fee_pct);
    }

    public function test_rechaza_fee_pct_fuera_de_rango(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->putJson("/api/v1/event/{$this->evento->id}", ['feePct' => 0.5])
            ->assertStatus(422);
    }
}
