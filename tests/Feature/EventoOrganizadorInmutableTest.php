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
 * `eventos.organizador_id` es editable por super_admin mientras el evento
 * siga en borrador, y queda fijo apenas se publica — ver
 * PRD-organizadores-crud.md (sesión 11/08/2026) y
 * EventoController::update()/publicar().
 */
class EventoOrganizadorInmutableTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private Organizador $organizadorOriginal;

    private Organizador $organizadorNuevo;

    protected function setUp(): void
    {
        parent::setUp();

        $pais = Pais::factory()->create();
        $ciudad = Ciudad::factory()->create(['pais_id' => $pais->id]);
        $tipoEvento = TipoEvento::factory()->create();
        $subtipoEvento = SubtipoEvento::factory()->create(['tipo_evento_id' => $tipoEvento->id]);

        $this->organizadorOriginal = Organizador::factory()->create();
        $this->organizadorNuevo = Organizador::factory()->create();

        $this->evento = Evento::factory()->create([
            'organizador_id' => $this->organizadorOriginal->id,
            'tipo_evento_id' => $tipoEvento->id,
            'subtipo_evento_id' => $subtipoEvento->id,
            'pais_id' => $pais->id,
            'ciudad_id' => $ciudad->id,
            'publicado' => false,
        ]);
    }

    public function test_super_admin_puede_cambiar_organizador_de_un_evento_en_borrador(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->putJson("/api/v1/event/{$this->evento->id}", ['organizador_id' => $this->organizadorNuevo->id])
            ->assertOk()
            ->assertJsonPath('eventos.organizadorId', $this->organizadorNuevo->id);
    }

    public function test_admin_scoped_no_puede_cambiar_organizador_aunque_sea_borrador(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $this->evento->id]);

        $this->putJson("/api/v1/event/{$this->evento->id}", ['organizador_id' => $this->organizadorNuevo->id])
            ->assertStatus(403);

        $this->assertEquals($this->organizadorOriginal->id, $this->evento->refresh()->organizador_id);
    }

    public function test_no_se_puede_cambiar_organizador_de_un_evento_ya_publicado(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->evento->update(['publicado' => true]);

        $this->putJson("/api/v1/event/{$this->evento->id}", ['organizador_id' => $this->organizadorNuevo->id])
            ->assertStatus(422)
            ->assertJsonPath('error', 'No se puede cambiar el organizador de un evento ya publicado.');

        $this->assertEquals($this->organizadorOriginal->id, $this->evento->refresh()->organizador_id);
    }

    public function test_evento_publicado_sigue_aceptando_otros_campos_sin_tocar_organizador(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->evento->update(['publicado' => true]);

        $this->putJson("/api/v1/event/{$this->evento->id}", ['description' => 'Descripción nueva'])
            ->assertOk();

        $this->evento->refresh();
        $this->assertSame('Descripción nueva', $this->evento->descripcion);
        $this->assertEquals($this->organizadorOriginal->id, $this->evento->organizador_id);
    }
}
