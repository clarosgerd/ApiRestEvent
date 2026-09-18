<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\EventoSyncExternoConfig;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Admin UI (§6 del plan) para EventoSyncExternoConfig — SOLO super_admin
 * (token de integración sensible, a diferencia de Presupuesto/Numeración
 * que sí admiten admin scoped a su propio evento).
 */
class SyncExternoConfigControllerTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private FormType $formType;

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
            'publicado' => false,
        ]);

        $this->formType = FormType::factory()->create(['event_id' => $this->evento->id, 'name' => 'General']);
    }

    public function test_sin_auth_da_401(): void
    {
        $this->getJson("/api/v1/event/{$this->evento->id}/sync-externo")->assertStatus(401);
    }

    public function test_admin_scoped_a_su_evento_no_puede_acceder(): void
    {
        $admin = AdminUser::factory()->create(['rol' => 'admin', 'evento_id' => $this->evento->id]);
        $this->actingAsAdmin($admin);

        $this->getJson("/api/v1/event/{$this->evento->id}/sync-externo")->assertStatus(403);
    }

    public function test_super_admin_puede_crear_configuracion(): void
    {
        $this->actingAsAdmin(AdminUser::factory()->create(['rol' => 'super_admin', 'evento_id' => null]));

        $resp = $this->postJson("/api/v1/event/{$this->evento->id}/sync-externo", [
            'form_types_id' => $this->formType->id,
            'nombre_fuente' => 'Sistema del organizador',
            'url' => 'https://fuente.test/participantes',
            'token' => 'secreto-real-123',
        ]);

        $resp->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('eventos_sync_externo', [
            'evento_id' => $this->evento->id, 'form_types_id' => $this->formType->id,
            'url' => 'https://fuente.test/participantes', 'token' => 'secreto-real-123',
        ]);
    }

    public function test_el_token_nunca_vuelve_completo_en_la_respuesta(): void
    {
        $this->actingAsAdmin(AdminUser::factory()->create(['rol' => 'super_admin', 'evento_id' => null]));

        $resp = $this->postJson("/api/v1/event/{$this->evento->id}/sync-externo", [
            'form_types_id' => $this->formType->id,
            'url' => 'https://fuente.test/participantes',
            'token' => 'secreto-real-123',
        ]);

        $body = $resp->json('data');
        $this->assertArrayNotHasKey('token', $body);
        $this->assertSame('••••-123', $body['tokenPreview']);
        $this->assertTrue($body['tieneToken']);
    }

    public function test_reenviar_sin_token_no_borra_el_token_existente(): void
    {
        $this->actingAsAdmin(AdminUser::factory()->create(['rol' => 'super_admin', 'evento_id' => null]));
        $config = EventoSyncExternoConfig::create([
            'evento_id' => $this->evento->id, 'form_types_id' => $this->formType->id,
            'url' => 'https://fuente.test/v1', 'token' => 'token-original', 'activo' => true,
        ]);

        $this->postJson("/api/v1/event/{$this->evento->id}/sync-externo", [
            'form_types_id' => $this->formType->id,
            'url' => 'https://fuente.test/v2',
        ])->assertOk();

        $this->assertSame('token-original', $config->fresh()->token);
        $this->assertSame('https://fuente.test/v2', $config->fresh()->url);
    }

    public function test_form_type_de_otro_evento_da_422(): void
    {
        $this->actingAsAdmin(AdminUser::factory()->create(['rol' => 'super_admin', 'evento_id' => null]));

        $pais = Pais::factory()->create();
        $otroEvento = Evento::factory()->create([
            'organizador_id' => Organizador::factory(), 'tipo_evento_id' => TipoEvento::factory(),
            'subtipo_evento_id' => SubtipoEvento::factory(), 'pais_id' => $pais->id,
            'ciudad_id' => Ciudad::factory()->create(['pais_id' => $pais->id])->id,
        ]);
        $formTypeAjeno = FormType::factory()->create(['event_id' => $otroEvento->id]);

        $this->postJson("/api/v1/event/{$this->evento->id}/sync-externo", [
            'form_types_id' => $formTypeAjeno->id,
            'url' => 'https://fuente.test/participantes',
        ])->assertStatus(422);

        $this->assertDatabaseCount('eventos_sync_externo', 0);
    }

    public function test_sincronizar_ahora_sin_configuracion_da_422(): void
    {
        $this->actingAsAdmin(AdminUser::factory()->create(['rol' => 'super_admin', 'evento_id' => null]));

        $this->postJson("/api/v1/event/{$this->evento->id}/sync-externo/sincronizar-ahora")->assertStatus(422);
    }

    public function test_sincronizar_ahora_corre_el_pull_y_devuelve_el_resultado(): void
    {
        $this->actingAsAdmin(AdminUser::factory()->create(['rol' => 'super_admin', 'evento_id' => null]));
        EventoSyncExternoConfig::create([
            'evento_id' => $this->evento->id, 'form_types_id' => $this->formType->id,
            'url' => 'https://fuente-para-probar.test/participantes', 'activo' => true,
        ]);
        Http::fake(['fuente-para-probar.test/*' => Http::response([
            'participantes' => [['nombre' => 'Ana', 'apellido' => 'Test', 'correo' => 'ana@test.net']],
        ])]);

        $resp = $this->postJson("/api/v1/event/{$this->evento->id}/sync-externo/sincronizar-ahora");

        $resp->assertOk()->assertJson(['success' => true, 'data' => ['creados' => 1]]);
        $this->assertDatabaseHas('participantes', ['numero_documento' => 'ana@test.net']);
    }
}
