<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Evento;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Ciudad;
use App\Models\TipoEvento;
use App\Models\SubtipoEvento;
use App\Models\Taller;
use Tests\TestCase;

/**
 * CRUD admin de talleres (18/08/2026) — ver
 * brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md. Mismo
 * criterio de scoping que SesionCongresoController: admin scoped a su
 * evento + super_admin.
 */
class TallerCrudTest extends TestCase
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

    public function test_super_admin_puede_listar_talleres_de_un_evento(): void
    {
        $evento = $this->evento();
        Taller::factory()->count(2)->create(['evento_id' => $evento->id]);

        $this->actingAsAdmin($this->superAdmin());
        $resp = $this->getJson("/api/v1/event/{$evento->id}/talleres");

        $resp->assertOk()->assertJsonStructure(['success', 'data']);
        $this->assertCount(2, $resp->json('data'));
    }

    public function test_admin_scoped_puede_crear_un_taller_en_su_evento(): void
    {
        $evento = $this->evento();
        $this->actingAsAdmin($this->adminDeEvento($evento->id));

        $resp = $this->postJson(
            "/api/v1/event/{$evento->id}/talleres",
            ['nombre' => 'Ética', 'modalidad' => 'REQUIRED', 'precio' => 50, 'orden' => 1, 'activo' => true]
        );

        $resp->assertCreated();
        $this->assertDatabaseHas('talleres', [
            'evento_id' => $evento->id,
            'nombre' => 'Ética',
            'modalidad' => 'REQUIRED',
            'precio' => 50,
        ]);
    }

    public function test_admin_de_otro_evento_no_puede_crear_taller(): void
    {
        $evento = $this->evento();
        $otro = $this->evento();
        $this->actingAsAdmin($this->adminDeEvento($otro->id));

        $resp = $this->postJson(
            "/api/v1/event/{$evento->id}/talleres",
            ['nombre' => 'Hack', 'modalidad' => 'OPTIONAL']
        );

        $resp->assertForbidden();
    }

    public function test_validacion_modalidad_es_obligatoria(): void
    {
        $evento = $this->evento();
        $this->actingAsAdmin($this->adminDeEvento($evento->id));

        $resp = $this->postJson(
            "/api/v1/event/{$evento->id}/talleres",
            ['nombre' => 'Sin modalidad']
        );

        $resp->assertStatus(422)->assertJsonValidationErrors(['modalidad']);
    }

    public function test_update_solo_cambia_campos_enviados(): void
    {
        $evento = $this->evento();
        $this->actingAsAdmin($this->adminDeEvento($evento->id));
        $taller = Taller::factory()->create([
            'evento_id' => $evento->id,
            'nombre' => 'Original',
            'modalidad' => 'OPTIONAL',
            'precio' => 25,
        ]);

        $resp = $this->putJson(
            "/api/v1/event/{$evento->id}/talleres/{$taller->id}",
            ['nombre' => 'Renombrado']
        );

        $resp->assertOk();
        $taller->refresh();
        $this->assertSame('Renombrado', $taller->nombre);
        $this->assertSame('OPTIONAL', $taller->modalidad); // intacto
        $this->assertEquals(25, $taller->precio); // intacto
    }

    public function test_destroy_elimina_el_taller(): void
    {
        $evento = $this->evento();
        $this->actingAsAdmin($this->adminDeEvento($evento->id));
        $taller = Taller::factory()->create(['evento_id' => $evento->id]);

        $resp = $this->deleteJson("/api/v1/event/{$evento->id}/talleres/{$taller->id}");

        $resp->assertOk();
        $this->assertDatabaseMissing('talleres', ['id' => $taller->id]);
    }
}