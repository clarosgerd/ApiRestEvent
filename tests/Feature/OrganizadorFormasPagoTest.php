<?php

namespace Tests\Feature;

use App\Models\FormasPago;
use App\Models\Organizador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Activación de formas de pago por organizador (19/08/2026) — ver
 * brain/PLAN-INTEGRACION-PAGO-MERU-19082026.md y
 * Organizador::formasPagoSeleccionadas()/formasPagoEfectivas().
 */
class OrganizadorFormasPagoTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_disponibles_marca_las_ya_seleccionadas(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $organizador = Organizador::factory()->create();
        $sip = FormasPago::factory()->create(['slug' => 'sip', 'organizador_id' => null]);
        $meru = FormasPago::factory()->create(['slug' => 'meru', 'organizador_id' => null]);
        $organizador->formasPagoSeleccionadas()->attach($sip->id, ['activo' => true]);

        $response = $this->getJson("/api/v1/organizadores/{$organizador->id}/formas-pago")
            ->assertOk()
            ->assertJsonPath('usandoDefaultDelSistema', false);

        $data = collect($response->json('data'));
        $this->assertTrue($data->firstWhere('id', $sip->id)['seleccionada']);
        $this->assertFalse($data->firstWhere('id', $meru->id)['seleccionada']);
    }

    public function test_pivote_vacio_reporta_default_del_sistema(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $organizador = Organizador::factory()->create();
        FormasPago::factory()->create(['organizador_id' => null]);

        $this->getJson("/api/v1/organizadores/{$organizador->id}/formas-pago")
            ->assertOk()
            ->assertJsonPath('usandoDefaultDelSistema', true);
    }

    public function test_admin_scoped_no_puede_ver_ni_editar(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin']);

        $organizador = Organizador::factory()->create();

        $this->getJson("/api/v1/organizadores/{$organizador->id}/formas-pago")->assertStatus(403);
        $this->putJson("/api/v1/organizadores/{$organizador->id}/formas-pago", ['forma_pago_ids' => []])->assertStatus(403);
    }

    public function test_super_admin_puede_actualizar_seleccion(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $organizador = Organizador::factory()->create();
        $sip = FormasPago::factory()->create(['slug' => 'sip', 'organizador_id' => null]);
        $meru = FormasPago::factory()->create(['slug' => 'meru', 'organizador_id' => null]);

        $this->putJson("/api/v1/organizadores/{$organizador->id}/formas-pago", [
            'forma_pago_ids' => [$sip->id, $meru->id],
        ])->assertOk();

        $this->assertDatabaseHas('organizador_formas_pago', [
            'organizador_id' => $organizador->id,
            'forma_pago_id' => $sip->id,
            'activo' => 1,
        ]);
        $this->assertDatabaseHas('organizador_formas_pago', [
            'organizador_id' => $organizador->id,
            'forma_pago_id' => $meru->id,
            'activo' => 1,
        ]);
    }

    public function test_no_puede_seleccionar_forma_de_pago_de_otro_organizador(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $organizadorA = Organizador::factory()->create();
        $organizadorB = Organizador::factory()->create();
        $formaDeB = FormasPago::factory()->create(['organizador_id' => $organizadorB->id]);

        $this->putJson("/api/v1/organizadores/{$organizadorA->id}/formas-pago", [
            'forma_pago_ids' => [$formaDeB->id],
        ])->assertOk();

        $this->assertDatabaseMissing('organizador_formas_pago', [
            'organizador_id' => $organizadorA->id,
            'forma_pago_id' => $formaDeB->id,
        ]);
    }

    public function test_lista_vacia_quita_todas_las_selecciones(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $organizador = Organizador::factory()->create();
        $sip = FormasPago::factory()->create(['organizador_id' => null]);
        $organizador->formasPagoSeleccionadas()->attach($sip->id, ['activo' => true]);

        $this->putJson("/api/v1/organizadores/{$organizador->id}/formas-pago", [
            'forma_pago_ids' => [],
        ])->assertOk();

        $this->assertCount(0, $organizador->formasPagoSeleccionadas()->get());
    }
}
