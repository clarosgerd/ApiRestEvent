<?php

namespace Tests\Feature;

use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\EventoSyncExternoConfig;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use App\Services\SyncExternoPullService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Sync periódico (pull) de participantes de un evento con registro propio
 * externo (17/09/2026) — App\Services\SyncExternoPullService. A diferencia
 * del webhook de COLABIOCLI (push, ver SyncParticipanteExternoTest), acá
 * SOMOS nosotros quienes llamamos a la URL de la fuente — Http::fake() la
 * simula (mismo criterio que ChronoTrackSyncTest).
 */
class SyncExternoPullServiceTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private FormType $general;

    private FormType $talleres;

    private EventoSyncExternoConfig $config;

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

        $this->general = FormType::factory()->create(['event_id' => $this->evento->id, 'name' => 'General']);
        $this->talleres = FormType::factory()->create(['event_id' => $this->evento->id, 'name' => 'Congreso']);

        $this->config = EventoSyncExternoConfig::create([
            'evento_id' => $this->evento->id,
            'form_types_id' => $this->general->id,
            'nombre_fuente' => 'Fuente de prueba',
            'url' => 'https://fuente-externa.test/participantes',
            'token' => 'secreto-123',
            'activo' => true,
        ]);
    }

    private function fake(array $body, int $status = 200): void
    {
        Http::fake(['fuente-externa.test/*' => Http::response($body, $status)]);
    }

    public function test_pull_crea_participantes_desde_el_json_de_la_fuente(): void
    {
        $this->fake([
            'participantes' => [
                ['nombre' => 'Ana', 'apellido' => 'Gutierrez', 'correo' => 'ana@test.net', 'categoria' => 'General'],
            ],
        ]);

        $resumen = app(SyncExternoPullService::class)->sincronizar($this->config);

        $this->assertNull($resumen['error']);
        $this->assertSame(1, $resumen['creados']);
        $this->assertSame(0, $resumen['actualizados']);
        $this->assertDatabaseHas('participantes', ['numero_documento' => 'ana@test.net']);
        $this->assertDatabaseHas('registrations', [
            'evento_id' => $this->evento->id, 'form_types_id' => $this->general->id, 'pago_status' => 'paid',
        ]);
    }

    public function test_manda_el_token_como_authorization_bearer(): void
    {
        $this->fake(['participantes' => []]);

        app(SyncExternoPullService::class)->sincronizar($this->config);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer secreto-123'));
    }

    public function test_segunda_corrida_no_duplica(): void
    {
        $this->fake(['participantes' => [
            ['nombre' => 'Ana', 'apellido' => 'Gutierrez', 'correo' => 'ana@test.net'],
        ]]);

        $service = app(SyncExternoPullService::class);
        $service->sincronizar($this->config);
        $resumen2 = $service->sincronizar($this->config);

        $this->assertSame(0, $resumen2['creados']);
        $this->assertSame(1, $resumen2['actualizados']);
        $this->assertDatabaseCount('participantes', 1);
    }

    public function test_form_type_por_participante_resuelve_al_form_type_correcto(): void
    {
        $this->fake(['participantes' => [
            ['nombre' => 'Ana', 'apellido' => 'Gutierrez', 'correo' => 'ana@test.net', 'form_type' => 'congreso'],
        ]]);

        app(SyncExternoPullService::class)->sincronizar($this->config);

        $this->assertDatabaseHas('registrations', ['evento_id' => $this->evento->id, 'form_types_id' => $this->talleres->id]);
    }

    public function test_form_type_ausente_cae_al_default_de_la_config(): void
    {
        $this->fake(['participantes' => [
            ['nombre' => 'Ana', 'apellido' => 'Gutierrez', 'correo' => 'ana@test.net'],
        ]]);

        app(SyncExternoPullService::class)->sincronizar($this->config);

        $this->assertDatabaseHas('registrations', ['evento_id' => $this->evento->id, 'form_types_id' => $this->general->id]);
    }

    public function test_form_type_desconocido_cae_al_default_de_la_config(): void
    {
        $this->fake(['participantes' => [
            ['nombre' => 'Ana', 'apellido' => 'Gutierrez', 'correo' => 'ana@test.net', 'form_type' => 'No Existe'],
        ]]);

        app(SyncExternoPullService::class)->sincronizar($this->config);

        $this->assertDatabaseHas('registrations', ['evento_id' => $this->evento->id, 'form_types_id' => $this->general->id]);
    }

    public function test_fila_sin_nombre_se_omite_sin_bloquear_las_demas(): void
    {
        $this->fake(['participantes' => [
            ['nombre' => '', 'apellido' => 'Sin Nombre', 'correo' => 'sinnombre@test.net'],
            ['nombre' => 'Ana', 'apellido' => 'Gutierrez', 'correo' => 'ana@test.net'],
        ]]);

        $resumen = app(SyncExternoPullService::class)->sincronizar($this->config);

        $this->assertSame(1, $resumen['creados']);
        $this->assertCount(1, $resumen['omitidos']);
        $this->assertDatabaseCount('participantes', 1);
    }

    public function test_error_http_de_la_fuente_no_lanza_y_queda_en_ultimo_resultado(): void
    {
        $this->fake([], 500);

        $resumen = app(SyncExternoPullService::class)->sincronizar($this->config);

        $this->assertNotNull($resumen['error']);
        $this->assertDatabaseCount('participantes', 0);
        $this->assertNotNull($this->config->fresh()->ultima_sincronizacion_at);
    }

    public function test_json_con_forma_invalida_no_lanza_y_no_procesa_nada(): void
    {
        $this->fake(['participantes' => 'esto-no-es-un-array']);

        $resumen = app(SyncExternoPullService::class)->sincronizar($this->config);

        $this->assertNotNull($resumen['error']);
        $this->assertDatabaseCount('participantes', 0);
    }

    public function test_actualiza_ultima_sincronizacion_y_resultado_en_la_config(): void
    {
        $this->fake(['participantes' => [
            ['nombre' => 'Ana', 'apellido' => 'Gutierrez', 'correo' => 'ana@test.net'],
        ]]);

        app(SyncExternoPullService::class)->sincronizar($this->config);

        $fresh = $this->config->fresh();
        $this->assertNotNull($fresh->ultima_sincronizacion_at);
        $this->assertSame(1, $fresh->ultimo_resultado['creados']);
    }

    /**
     * Fix (23/09/2026) — external_id opcional, scopeado por config, evita
     * que un cambio de numero_documento en la fuente cree un duplicado.
     * Ver SincronizarParticipanteExternoActionExtendedTest para la
     * cobertura del matching en sí; acá solo se confirma que el Service
     * arma y pasa la clave correctamente de punta a punta.
     */
    public function test_external_id_evita_duplicado_cuando_la_fuente_corrige_el_documento(): void
    {
        // Http::fake() llamado 2 veces con el mismo patrón de URL NO
        // reemplaza el primer stub (Laravel los apila y usa el primero que
        // matchea) — hace falta fakeSequence() para simular 2 respuestas
        // distintas de la fuente en el mismo test.
        Http::fakeSequence('fuente-externa.test/*')
            ->push(['participantes' => [
                ['nombre' => 'Ana', 'apellido' => 'Gutierrez', 'numero_documento' => '111', 'external_id' => 'FUENTE-42'],
            ]])
            ->push(['participantes' => [
                ['nombre' => 'Ana', 'apellido' => 'Gutierrez', 'numero_documento' => '222', 'external_id' => 'FUENTE-42'],
            ]]);

        app(SyncExternoPullService::class)->sincronizar($this->config);
        $resumen = app(SyncExternoPullService::class)->sincronizar($this->config);

        $this->assertSame(0, $resumen['creados']);
        $this->assertSame(1, $resumen['actualizados']);
        $this->assertDatabaseCount('participantes', 1);
        $this->assertDatabaseHas('participantes', ['numero_documento' => '222']);
        $this->assertDatabaseHas('registrations', ['origen_sync_externo' => "pull:{$this->config->id}:FUENTE-42"]);
    }
}
