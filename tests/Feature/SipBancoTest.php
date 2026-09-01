<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Evento;
use App\Models\Organizador;
use App\Models\SipBanco;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SIP multi-banco (28/08/2026) — ver
 * PLAN-SIP-MULTIBANCO-28082026.md. Cubre el middleware
 * RequiresInternalSecret, los 2 endpoints internos
 * (SipBancoInternalController) y el CRUD de admin (SipBancoController,
 * solo super_admin).
 */
class SipBancoTest extends TestCase
{
    use RefreshDatabase;

    private function bancoData(array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Banco Prueba',
            'sip_username' => 'TESTUSER',
            'sip_password' => 'TestPass123',
            'sip_apikey' => 'apikey123',
            'sip_apikey_servicio' => 'apikeyservicio123',
            'callback_basic_user' => 'cbuser',
            'callback_basic_password' => 'cbpass',
            'activo' => true,
        ], $overrides);
    }

    // ── Middleware RequiresInternalSecret ───────────────────────────────

    public function test_endpoint_interno_rechaza_sin_header(): void
    {
        $evento = Evento::factory()->create();

        $this->getJson("/api/v1/internal/event/{$evento->id}/sip-banco")
            ->assertStatus(403);
    }

    public function test_endpoint_interno_rechaza_con_secreto_incorrecto(): void
    {
        $evento = Evento::factory()->create();

        $this->withHeader('X-Internal-Secret', 'incorrecto')
            ->getJson("/api/v1/internal/event/{$evento->id}/sip-banco")
            ->assertStatus(403);
    }

    public function test_endpoint_interno_acepta_con_secreto_correcto(): void
    {
        $evento = Evento::factory()->create();

        $this->withHeader('X-Internal-Secret', config('services.internal.secret'))
            ->getJson("/api/v1/internal/event/{$evento->id}/sip-banco")
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // ── SipBancoInternalController::paraEvento() ─────────────────────────

    public function test_evento_de_organizador_sin_banco_asignado_devuelve_null(): void
    {
        $org = Organizador::factory()->create();
        $evento = Evento::factory()->create(['organizador_id' => $org->id]);

        $this->withHeader('X-Internal-Secret', config('services.internal.secret'))
            ->getJson("/api/v1/internal/event/{$evento->id}/sip-banco")
            ->assertStatus(200)
            ->assertJsonPath('banco', null);
    }

    public function test_evento_de_organizador_con_banco_asignado_devuelve_credenciales_completas(): void
    {
        $org = Organizador::factory()->create();
        $evento = Evento::factory()->create(['organizador_id' => $org->id]);
        $banco = SipBanco::create($this->bancoData(['organizador_id' => $org->id]));

        $this->withHeader('X-Internal-Secret', config('services.internal.secret'))
            ->getJson("/api/v1/internal/event/{$evento->id}/sip-banco")
            ->assertStatus(200)
            ->assertJsonPath('banco.sipUsername', 'TESTUSER')
            ->assertJsonPath('banco.sipPassword', 'TestPass123')
            ->assertJsonPath('banco.sipApikey', 'apikey123')
            ->assertJsonPath('banco.cacheKey', 'sip_token_banco_' . $banco->id);
    }

    public function test_banco_inactivo_no_se_devuelve(): void
    {
        $org = Organizador::factory()->create();
        $evento = Evento::factory()->create(['organizador_id' => $org->id]);
        SipBanco::create($this->bancoData(['organizador_id' => $org->id, 'activo' => false]));

        $this->withHeader('X-Internal-Secret', config('services.internal.secret'))
            ->getJson("/api/v1/internal/event/{$evento->id}/sip-banco")
            ->assertStatus(200)
            ->assertJsonPath('banco', null);
    }

    public function test_evento_de_otro_organizador_no_ve_el_banco_ajeno(): void
    {
        $orgConBanco = Organizador::factory()->create();
        SipBanco::create($this->bancoData(['organizador_id' => $orgConBanco->id]));

        $otroOrg = Organizador::factory()->create();
        $eventoAjeno = Evento::factory()->create(['organizador_id' => $otroOrg->id]);

        $this->withHeader('X-Internal-Secret', config('services.internal.secret'))
            ->getJson("/api/v1/internal/event/{$eventoAjeno->id}/sip-banco")
            ->assertStatus(200)
            ->assertJsonPath('banco', null);
    }

    // ── SipBancoInternalController::callbackCredenciales() ──────────────

    public function test_callback_credenciales_solo_lista_bancos_activos(): void
    {
        $org1 = Organizador::factory()->create();
        $org2 = Organizador::factory()->create();
        SipBanco::create($this->bancoData(['organizador_id' => $org1->id, 'callback_basic_user' => 'activo1']));
        SipBanco::create($this->bancoData(['organizador_id' => $org2->id, 'callback_basic_user' => 'inactivo1', 'activo' => false]));

        $response = $this->withHeader('X-Internal-Secret', config('services.internal.secret'))
            ->getJson('/api/v1/internal/sip-bancos/callback-credenciales')
            ->assertStatus(200);

        $users = collect($response->json('callbacks'))->pluck('user');
        $this->assertTrue($users->contains('activo1'));
        $this->assertFalse($users->contains('inactivo1'));
    }

    // ── SipBancoController (CRUD admin, solo super_admin) ────────────────

    public function test_super_admin_puede_crear_banco(): void
    {
        $this->actingAsAdmin();
        $org = Organizador::factory()->create();

        $this->postJson('/api/v1/sip-bancos', $this->bancoData(['organizador_id' => $org->id]))
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.organizadorId', $org->id);

        $this->assertDatabaseHas('sip_bancos', ['nombre' => 'Banco Prueba', 'organizador_id' => $org->id]);
    }

    public function test_admin_scoped_no_puede_gestionar_bancos_sip(): void
    {
        $org = Organizador::factory()->create();
        $admin = AdminUser::factory()->create(['rol' => 'admin', 'evento_id' => null]);
        $this->actingAsAdmin($admin);

        $this->postJson('/api/v1/sip-bancos', $this->bancoData(['organizador_id' => $org->id]))
            ->assertStatus(403);
    }

    public function test_index_nunca_expone_secretos(): void
    {
        $this->actingAsAdmin();
        $org = Organizador::factory()->create();
        SipBanco::create($this->bancoData(['organizador_id' => $org->id]));

        $response = $this->getJson('/api/v1/sip-bancos')->assertOk();

        $json = $response->json('data.0');
        $this->assertArrayNotHasKey('sipPassword', $json);
        $this->assertArrayNotHasKey('sipApikey', $json);
        $this->assertArrayNotHasKey('sipApikeyServicio', $json);
        $this->assertArrayNotHasKey('callbackBasicPassword', $json);
    }

    public function test_update_sin_mandar_secretos_no_los_borra(): void
    {
        $this->actingAsAdmin();
        $org = Organizador::factory()->create();
        $banco = SipBanco::create($this->bancoData(['organizador_id' => $org->id]));

        $this->putJson("/api/v1/sip-bancos/{$banco->id}", ['nombre' => 'Banco Renombrado'])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Banco Renombrado');

        $banco->refresh();
        $this->assertSame('TestPass123', $banco->sip_password);
        $this->assertSame('apikey123', $banco->sip_apikey);
    }

    public function test_update_puede_reemplazar_un_secreto(): void
    {
        $this->actingAsAdmin();
        $org = Organizador::factory()->create();
        $banco = SipBanco::create($this->bancoData(['organizador_id' => $org->id]));

        $this->putJson("/api/v1/sip-bancos/{$banco->id}", ['sip_password' => 'NuevaClave456'])
            ->assertOk();

        $banco->refresh();
        $this->assertSame('NuevaClave456', $banco->sip_password);
    }

    public function test_destroy_elimina_el_banco(): void
    {
        $this->actingAsAdmin();
        $org = Organizador::factory()->create();
        $banco = SipBanco::create($this->bancoData(['organizador_id' => $org->id]));

        $this->deleteJson("/api/v1/sip-bancos/{$banco->id}")->assertOk();

        $this->assertDatabaseMissing('sip_bancos', ['id' => $banco->id]);
    }
}
