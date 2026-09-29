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

    /**
     * Identificar talleres precongreso/formato (28/09/2026) — ver
     * brain/PLAN-REGISTRO-EFICIENTE-TALLER-PRECONGRESO-28092026.md.
     */
    public function test_crea_taller_con_es_precongreso_y_formato(): void
    {
        $evento = $this->evento();
        $this->actingAsAdmin($this->adminDeEvento($evento->id));

        $resp = $this->postJson(
            "/api/v1/event/{$evento->id}/talleres",
            [
                'nombre' => 'ALTO – Analgesia Multimodal',
                'modalidad' => 'OPTIONAL',
                'precio' => 1500,
                'es_precongreso' => true,
                'formato' => 'VIRTUAL',
            ]
        );

        $resp->assertCreated();
        $resp->assertJsonPath('data.es_precongreso', true);
        $resp->assertJsonPath('data.formato', 'VIRTUAL');
        $this->assertDatabaseHas('talleres', [
            'evento_id' => $evento->id,
            'es_precongreso' => true,
            'formato' => 'VIRTUAL',
        ]);
    }

    public function test_es_precongreso_default_false_y_formato_null_si_no_se_manda(): void
    {
        $evento = $this->evento();
        $this->actingAsAdmin($this->adminDeEvento($evento->id));

        $resp = $this->postJson(
            "/api/v1/event/{$evento->id}/talleres",
            ['nombre' => 'Taller normal', 'modalidad' => 'OPTIONAL']
        );

        $resp->assertCreated();
        $resp->assertJsonPath('data.es_precongreso', false);
        $resp->assertJsonPath('data.formato', null);
    }

    public function test_formato_invalido_es_rechazado(): void
    {
        $evento = $this->evento();
        $this->actingAsAdmin($this->adminDeEvento($evento->id));

        $resp = $this->postJson(
            "/api/v1/event/{$evento->id}/talleres",
            ['nombre' => 'Taller', 'modalidad' => 'OPTIONAL', 'formato' => 'TELEPORTACION']
        );

        $resp->assertStatus(422)->assertJsonValidationErrors(['formato']);
    }

    public function test_update_puede_marcar_es_precongreso_y_formato_sin_tocar_lo_demas(): void
    {
        $evento = $this->evento();
        $this->actingAsAdmin($this->adminDeEvento($evento->id));
        $taller = Taller::factory()->create([
            'evento_id' => $evento->id,
            'nombre' => 'ALTO – Analgesia Multimodal | Teórico Virtual',
            'precio' => 1500,
            'es_precongreso' => false,
            'formato' => null,
        ]);

        $resp = $this->putJson(
            "/api/v1/event/{$evento->id}/talleres/{$taller->id}",
            ['es_precongreso' => true, 'formato' => 'VIRTUAL']
        );

        $resp->assertOk();
        $taller->refresh();
        $this->assertTrue($taller->es_precongreso);
        $this->assertSame('VIRTUAL', $taller->formato);
        $this->assertEquals(1500, $taller->precio); // intacto
    }

    public function test_taller_resource_publico_expone_esprecongreso_y_formato(): void
    {
        $evento = $this->evento();
        Taller::factory()->create([
            'evento_id' => $evento->id,
            'es_precongreso' => true,
            'formato' => 'HIBRIDO',
        ]);

        $resp = $this->getJson("/api/v1/event/{$evento->id}");

        $resp->assertOk();
        $talleres = $resp->json('eventos.talleres');
        $this->assertNotEmpty($talleres, 'El endpoint público del evento no trajo talleres para verificar.');
        $this->assertTrue($talleres[0]['esPrecongreso']);
        $this->assertSame('HIBRIDO', $talleres[0]['formato']);
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