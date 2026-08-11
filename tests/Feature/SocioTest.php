<?php

namespace Tests\Feature;

use App\Models\Socio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRUD de socios — solo super_admin. Ver SocioController y
 * elascenso/event/brain/ (sesión 11/08/2026).
 */
class SocioTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_incluye_porcentaje_total_de_los_activos(): void
    {
        Socio::query()->delete();
        Socio::factory()->create(['porcentaje' => 60, 'activo' => true]);
        Socio::factory()->create(['porcentaje' => 40, 'activo' => true]);
        Socio::factory()->create(['porcentaje' => 50, 'activo' => false]);

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->getJson('/api/v1/socios')
            ->assertStatus(200)
            ->assertJson(['success' => true, 'porcentaje_total' => 100.0]);
    }

    public function test_store_crea_socio(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson('/api/v1/socios', ['nombre' => 'Nuevo Socio', 'porcentaje' => 5])
            ->assertStatus(201)
            ->assertJsonPath('data.nombre', 'Nuevo Socio');

        $this->assertDatabaseHas('socios', ['nombre' => 'Nuevo Socio', 'porcentaje' => 5.00]);
    }

    public function test_destroy_bloquea_si_tiene_liquidaciones_asociadas(): void
    {
        $socio = Socio::factory()->create();
        $liquidacion = \App\Models\Liquidacion::factory()->create();
        \App\Models\LiquidacionDetalle::create([
            'liquidacion_id' => $liquidacion->id,
            'socio_id' => $socio->id,
            'socio_nombre' => $socio->nombre,
            'porcentaje' => $socio->porcentaje,
            'monto' => 10,
        ]);

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->deleteJson("/api/v1/socios/{$socio->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('socios', ['id' => $socio->id]);
    }

    public function test_destroy_permite_borrar_socio_sin_historial(): void
    {
        $socio = Socio::factory()->create();
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->deleteJson("/api/v1/socios/{$socio->id}")->assertStatus(200);

        $this->assertDatabaseMissing('socios', ['id' => $socio->id]);
    }

    public function test_admin_no_superadmin_no_puede_gestionar_socios(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin']);

        $this->getJson('/api/v1/socios')->assertStatus(403);
        $this->postJson('/api/v1/socios', ['nombre' => 'X', 'porcentaje' => 1])->assertStatus(403);
    }
}
