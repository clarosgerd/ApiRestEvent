<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Organizador;
use App\Models\WhatsappCuenta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WhatsApp Business API oficial por organizador (08/10/2026) — CRUD de
 * cuentas, solo super_admin. Mismo espíritu que SipBancoTest, pero sin
 * endpoints internos: el que USA estas credenciales (NotificacionService)
 * ya vive en este mismo repo, no hace falta cruzar a otro.
 */
class WhatsappCuentaTest extends TestCase
{
    use RefreshDatabase;

    private function cuentaData(array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Cuenta Prueba',
            'phone_number_id' => '123456789012345',
            'business_account_id' => '987654321098765',
            'access_token' => 'EAATestToken123',
            'template_name' => 'notificacion_sistema',
            'template_lang' => 'es',
            'activo' => true,
        ], $overrides);
    }

    public function test_super_admin_puede_crear_cuenta(): void
    {
        $this->actingAsAdmin();
        $org = Organizador::factory()->create();

        $this->postJson('/api/v1/whatsapp-cuentas', $this->cuentaData(['organizador_id' => $org->id]))
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.organizadorId', $org->id)
            ->assertJsonPath('data.phoneNumberId', '123456789012345');

        $this->assertDatabaseHas('whatsapp_cuentas', ['nombre' => 'Cuenta Prueba', 'organizador_id' => $org->id]);
    }

    public function test_admin_scoped_no_puede_gestionar_cuentas_whatsapp(): void
    {
        $org = Organizador::factory()->create();
        $admin = AdminUser::factory()->create(['rol' => 'admin', 'evento_id' => null]);
        $this->actingAsAdmin($admin);

        $this->postJson('/api/v1/whatsapp-cuentas', $this->cuentaData(['organizador_id' => $org->id]))
            ->assertStatus(403);
    }

    public function test_index_nunca_expone_el_access_token(): void
    {
        $this->actingAsAdmin();
        $org = Organizador::factory()->create();
        WhatsappCuenta::create($this->cuentaData(['organizador_id' => $org->id]));

        $response = $this->getJson('/api/v1/whatsapp-cuentas')->assertOk();

        $json = $response->json('data.0');
        $this->assertArrayNotHasKey('accessToken', $json);
        $this->assertArrayNotHasKey('access_token', $json);
    }

    public function test_update_sin_mandar_access_token_no_lo_borra(): void
    {
        $this->actingAsAdmin();
        $org = Organizador::factory()->create();
        $cuenta = WhatsappCuenta::create($this->cuentaData(['organizador_id' => $org->id]));

        $this->putJson("/api/v1/whatsapp-cuentas/{$cuenta->id}", ['nombre' => 'Cuenta Renombrada'])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Cuenta Renombrada');

        $cuenta->refresh();
        $this->assertSame('EAATestToken123', $cuenta->access_token);
    }

    public function test_update_puede_reemplazar_el_access_token(): void
    {
        $this->actingAsAdmin();
        $org = Organizador::factory()->create();
        $cuenta = WhatsappCuenta::create($this->cuentaData(['organizador_id' => $org->id]));

        $this->putJson("/api/v1/whatsapp-cuentas/{$cuenta->id}", ['access_token' => 'EAANuevoToken456'])
            ->assertOk();

        $cuenta->refresh();
        $this->assertSame('EAANuevoToken456', $cuenta->access_token);
    }

    public function test_destroy_elimina_la_cuenta(): void
    {
        $this->actingAsAdmin();
        $org = Organizador::factory()->create();
        $cuenta = WhatsappCuenta::create($this->cuentaData(['organizador_id' => $org->id]));

        $this->deleteJson("/api/v1/whatsapp-cuentas/{$cuenta->id}")->assertOk();

        $this->assertDatabaseMissing('whatsapp_cuentas', ['id' => $cuenta->id]);
    }

    public function test_template_name_y_lang_tienen_default_si_no_se_mandan(): void
    {
        $this->actingAsAdmin();
        $org = Organizador::factory()->create();
        $data = $this->cuentaData(['organizador_id' => $org->id]);
        unset($data['template_name'], $data['template_lang']);

        $this->postJson('/api/v1/whatsapp-cuentas', $data)->assertCreated();

        $this->assertDatabaseHas('whatsapp_cuentas', [
            'organizador_id' => $org->id,
            'template_name' => 'notificacion_sistema',
            'template_lang' => 'es',
        ]);
    }
}
