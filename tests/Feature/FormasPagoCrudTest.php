<?php

namespace Tests\Feature;

use App\Models\FormasPago;
use App\Models\Organizador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRUD del catálogo global de formas de pago (19/08/2026) — ver
 * brain/PLAN-INTEGRACION-PAGO-MERU-19082026.md. Solo super_admin, mismo
 * criterio que OrganizadorCrudTest/CatalogosGlobalesTest.
 */
class FormasPagoCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_puede_listar_formas_de_pago(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        FormasPago::factory()->count(2)->create(['organizador_id' => null]);
        // Una propia de un organizador no debe aparecer en el catálogo global.
        $organizador = Organizador::factory()->create();
        FormasPago::factory()->create(['organizador_id' => $organizador->id]);

        $this->getJson('/api/v1/catalogos/formas-pago')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_scoped_no_puede_listar_formas_de_pago(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin']);

        $this->getJson('/api/v1/catalogos/formas-pago')->assertStatus(403);
    }

    public function test_super_admin_puede_crear_forma_de_pago(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson('/api/v1/catalogos/formas-pago', [
            'slug' => 'meru',
            'nombre' => 'Meru',
            'pasarela' => 'meru',
            'tipo' => 'integrado',
        ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'meru')
            ->assertJsonPath('data.organizador_id', null)
            ->assertJsonPath('data.activo', true);

        $this->assertDatabaseHas('formas_pagos', ['slug' => 'meru', 'organizador_id' => null]);
    }

    public function test_crear_forma_de_pago_exige_slug_nombre_y_tipo(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson('/api/v1/catalogos/formas-pago', [])->assertStatus(422);
    }

    public function test_no_permite_slug_duplicado(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        FormasPago::factory()->create(['slug' => 'meru']);

        $this->postJson('/api/v1/catalogos/formas-pago', [
            'slug' => 'meru',
            'nombre' => 'Meru duplicado',
            'tipo' => 'manual',
        ])->assertStatus(422);
    }

    public function test_super_admin_puede_actualizar_forma_de_pago(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $forma = FormasPago::factory()->create(['nombre' => 'Nombre viejo']);

        $this->putJson("/api/v1/catalogos/formas-pago/{$forma->id}", ['nombre' => 'Nombre nuevo'])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Nombre nuevo');
    }

    public function test_super_admin_puede_eliminar_forma_de_pago_sin_organizadores(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $forma = FormasPago::factory()->create();

        $this->deleteJson("/api/v1/catalogos/formas-pago/{$forma->id}")->assertOk();

        $this->assertDatabaseMissing('formas_pagos', ['id' => $forma->id]);
    }

    public function test_no_se_puede_eliminar_forma_de_pago_seleccionada_por_un_organizador(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $forma = FormasPago::factory()->create();
        $organizador = Organizador::factory()->create();
        $organizador->formasPagoSeleccionadas()->attach($forma->id, ['activo' => true]);

        $this->deleteJson("/api/v1/catalogos/formas-pago/{$forma->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('formas_pagos', ['id' => $forma->id]);
    }
}
