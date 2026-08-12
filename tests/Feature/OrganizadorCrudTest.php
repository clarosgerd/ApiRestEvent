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
 * CRUD de organizadores — ver PRD-organizadores-crud.md (sesión
 * 11/08/2026). Solo super_admin, mismo criterio que SocioController.
 */
class OrganizadorCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_puede_listar_organizadores(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        Organizador::factory()->count(2)->create();

        $this->getJson('/api/v1/organizadores')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_scoped_no_puede_listar_organizadores(): void
    {
        $admin = $this->actingAsAdmin();
        // Sin evento_id: assertIsSuperAdmin() solo mira `rol`, y en esta
        // clase de test no hace falta un Evento real (evento_id=1 fallaría
        // la FK admin_users.evento_id contra una BD recién migrada, vacía).
        $admin->update(['rol' => 'admin']);

        $this->getJson('/api/v1/organizadores')->assertStatus(403);
    }

    public function test_super_admin_puede_crear_organizador(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson('/api/v1/organizadores', [
            'razon_social' => 'Eventos Andinos S.R.L.',
            'email' => 'contacto@eventosandinos.test',
        ])
            ->assertCreated()
            ->assertJsonPath('data.razon_social', 'Eventos Andinos S.R.L.')
            ->assertJsonPath('data.activo', true);

        $this->assertDatabaseHas('organizadores', ['razon_social' => 'Eventos Andinos S.R.L.']);
    }

    public function test_crear_organizador_exige_razon_social_y_email(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson('/api/v1/organizadores', [])->assertStatus(422);
    }

    public function test_super_admin_puede_actualizar_organizador(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $organizador = Organizador::factory()->create(['razon_social' => 'Nombre Viejo']);

        $this->putJson("/api/v1/organizadores/{$organizador->id}", ['razon_social' => 'Nombre Nuevo'])
            ->assertOk()
            ->assertJsonPath('data.razon_social', 'Nombre Nuevo');
    }

    public function test_super_admin_puede_eliminar_organizador_sin_eventos(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $organizador = Organizador::factory()->create();

        $this->deleteJson("/api/v1/organizadores/{$organizador->id}")->assertOk();

        $this->assertDatabaseMissing('organizadores', ['id' => $organizador->id]);
    }

    public function test_no_se_puede_eliminar_organizador_con_eventos_asociados(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $organizador = Organizador::factory()->create();
        $pais = Pais::factory()->create();
        $ciudad = Ciudad::factory()->create(['pais_id' => $pais->id]);
        $tipoEvento = TipoEvento::factory()->create();
        $subtipoEvento = SubtipoEvento::factory()->create(['tipo_evento_id' => $tipoEvento->id]);

        Evento::factory()->create([
            'organizador_id' => $organizador->id,
            'tipo_evento_id' => $tipoEvento->id,
            'subtipo_evento_id' => $subtipoEvento->id,
            'pais_id' => $pais->id,
            'ciudad_id' => $ciudad->id,
        ]);

        $this->deleteJson("/api/v1/organizadores/{$organizador->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('organizadores', ['id' => $organizador->id]);
    }
}
