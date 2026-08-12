<?php

namespace Tests\Feature;

use App\Models\PresupuestoCategoria;
use App\Models\PresupuestoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Catálogo de rubros del presupuesto — solo super_admin. Ver
 * PresupuestoCategoriaController y elascenso/event/brain/ (sesión
 * 11/08/2026).
 */
class PresupuestoCategoriaTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_crea_categoria(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson('/api/v1/presupuesto-categorias', ['nombre' => 'Seguros', 'tipo' => 'gasto'])
            ->assertStatus(201)
            ->assertJsonPath('data.nombre', 'Seguros');

        $this->assertDatabaseHas('presupuesto_categorias', ['nombre' => 'Seguros', 'tipo' => 'gasto']);
    }

    public function test_destroy_bloquea_si_tiene_movimientos_asociados(): void
    {
        $categoria = PresupuestoCategoria::factory()->create();
        PresupuestoEvento::factory()->create(['presupuesto_categoria_id' => $categoria->id]);

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->deleteJson("/api/v1/presupuesto-categorias/{$categoria->id}")->assertStatus(409);

        $this->assertDatabaseHas('presupuesto_categorias', ['id' => $categoria->id]);
    }

    public function test_destroy_permite_borrar_categoria_sin_historial(): void
    {
        $categoria = PresupuestoCategoria::factory()->create();
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->deleteJson("/api/v1/presupuesto-categorias/{$categoria->id}")->assertStatus(200);

        $this->assertDatabaseMissing('presupuesto_categorias', ['id' => $categoria->id]);
    }

    public function test_admin_no_superadmin_no_puede_gestionar_categorias(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin']);

        $this->getJson('/api/v1/presupuesto-categorias')->assertStatus(403);
        $this->postJson('/api/v1/presupuesto-categorias', ['nombre' => 'X', 'tipo' => 'gasto'])->assertStatus(403);
    }
}
