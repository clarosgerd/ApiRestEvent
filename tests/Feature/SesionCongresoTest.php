<?php

namespace Tests\Feature;

use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\SesionCongreso;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRUD de sesiones de congreso — admin scoped a su evento, o super_admin.
 * Ver SesionCongresoController y elascenso/event/brain/ (sesión
 * 11/08/2026).
 */
class SesionCongresoTest extends TestCase
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

    public function test_admin_scoped_a_su_evento_puede_crear_sesion(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $this->evento->id]);

        $response = $this->postJson("/api/v1/event/{$this->evento->id}/sesiones", [
            'titulo' => 'Keynote de apertura',
            'ponente' => 'Jane Doe',
            'sala' => 'Auditorio principal',
            'fecha' => '2026-09-01',
            'hora_inicio' => '09:00',
            'hora_fin' => '10:00',
            'cupo' => 50,
        ]);

        $response->assertStatus(201)->assertJson(['success' => true]);
        $this->assertDatabaseHas('sesiones_congreso', ['titulo' => 'Keynote de apertura', 'evento_id' => $this->evento->id]);
    }

    public function test_admin_de_otro_evento_no_puede_crear_sesion(): void
    {
        $otroEvento = Evento::factory()->create([
            'organizador_id' => $this->evento->organizador_id,
            'tipo_evento_id' => $this->evento->tipo_evento_id,
            'subtipo_evento_id' => $this->evento->subtipo_evento_id,
            'pais_id' => $this->evento->pais_id,
            'ciudad_id' => $this->evento->ciudad_id,
        ]);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $otroEvento->id]);

        $this->postJson("/api/v1/event/{$this->evento->id}/sesiones", [
            'titulo' => 'X', 'fecha' => '2026-09-01', 'hora_inicio' => '09:00', 'hora_fin' => '10:00',
        ])->assertStatus(403);
    }

    public function test_hora_fin_debe_ser_posterior_a_hora_inicio(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson("/api/v1/event/{$this->evento->id}/sesiones", [
            'titulo' => 'X', 'fecha' => '2026-09-01', 'hora_inicio' => '10:00', 'hora_fin' => '09:00',
        ])->assertStatus(422);
    }

    public function test_no_permite_editar_sesion_de_otro_evento(): void
    {
        $otroEvento = Evento::factory()->create([
            'organizador_id' => $this->evento->organizador_id,
            'tipo_evento_id' => $this->evento->tipo_evento_id,
            'subtipo_evento_id' => $this->evento->subtipo_evento_id,
            'pais_id' => $this->evento->pais_id,
            'ciudad_id' => $this->evento->ciudad_id,
        ]);
        $sesion = SesionCongreso::factory()->create(['evento_id' => $otroEvento->id]);

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->putJson("/api/v1/event/{$this->evento->id}/sesiones/{$sesion->id}", ['titulo' => 'Nuevo'])
            ->assertStatus(404);
    }

    public function test_destroy_elimina_la_sesion(): void
    {
        $sesion = SesionCongreso::factory()->create(['evento_id' => $this->evento->id]);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->deleteJson("/api/v1/event/{$this->evento->id}/sesiones/{$sesion->id}")->assertStatus(200);

        $this->assertDatabaseMissing('sesiones_congreso', ['id' => $sesion->id]);
    }
}
