<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Registration;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sync de participantes de un congreso externo (07/09/2026) — ver
 * brain/PLAN-SYNC-CONGRESO-EXTERNO-07092026.md, App\Actions\
 * SincronizarParticipanteExternoAction, App\Http\Controllers\Internal\
 * SyncExternoController. Endpoint llamado por el Google Apps Script de un
 * organizador externo (ej. COLABIOCLI 2026) — nunca por elascenso/event ni
 * por un admin logueado.
 */
class SyncParticipanteExternoTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private FormType $formType;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.external_sync.secret' => 'test-secret-123']);

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

        $this->formType = FormType::factory()->create(['event_id' => $this->evento->id]);
    }

    private function postSync(array $participantes, ?string $secret = 'test-secret-123'): \Illuminate\Testing\TestResponse
    {
        $headers = $secret !== null ? ['X-External-Sync-Secret' => $secret] : [];

        return $this->postJson(
            "/api/v1/internal/event/{$this->evento->id}/participantes-externos/sync",
            ['participantes' => $participantes],
            $headers
        );
    }

    public function test_rechaza_sin_secreto(): void
    {
        $this->postSync([['nombre' => 'Ana', 'apellido' => 'Test', 'correo' => 'ana@test.net']], null)
            ->assertStatus(403);

        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_rechaza_con_secreto_incorrecto(): void
    {
        $this->postSync([['nombre' => 'Ana', 'apellido' => 'Test', 'correo' => 'ana@test.net']], 'secreto-equivocado')
            ->assertStatus(403);
    }

    public function test_crea_participante_nuevo_como_paid(): void
    {
        $this->postSync([
            ['nombre' => 'Ana', 'apellido' => 'Gutierrez', 'correo' => 'ana@test.net', 'telefono' => '77712345', 'categoria' => 'Estudiante', 'ubicacion' => 'Santa Cruz'],
        ])->assertOk()->assertJson(['success' => true, 'creados' => 1, 'actualizados' => 0]);

        $this->assertDatabaseHas('participantes', [
            'nombre' => 'Ana', 'apellido' => 'Gutierrez', 'numero_documento' => 'ana@test.net',
            'tipo_documento' => 'EMAIL', 'categoria' => 'Estudiante', 'ciudad' => 'Santa Cruz',
        ]);
        $this->assertDatabaseHas('registrations', [
            'evento_id' => $this->evento->id, 'pago_status' => 'paid', 'tipo_pago' => 'externo',
        ]);

        $registration = Registration::where('evento_id', $this->evento->id)->first();
        $this->assertSame(0.0, (float) $registration->totals->grand_total);
    }

    public function test_normaliza_el_correo_a_minusculas(): void
    {
        $this->postSync([
            ['nombre' => 'Ana', 'apellido' => 'Gutierrez', 'correo' => 'ANA@TEST.NET'],
        ])->assertOk();

        $this->assertDatabaseHas('participantes', ['numero_documento' => 'ana@test.net']);
    }

    /**
     * Idempotencia — clave de todo el diseño: el Apps Script del
     * organizador postea el sheet COMPLETO en cada disparo, no solo "lo
     * nuevo". Reenviar la misma fila no debe duplicar, sí debe actualizar
     * datos cambiados.
     */
    public function test_reenviar_la_misma_fila_actualiza_en_vez_de_duplicar(): void
    {
        $this->postSync([
            ['nombre' => 'Ana', 'apellido' => 'Gutierrez', 'correo' => 'ana@test.net', 'categoria' => 'Estudiante'],
        ])->assertOk()->assertJson(['creados' => 1, 'actualizados' => 0]);

        $this->postSync([
            ['nombre' => 'Ana', 'apellido' => 'Gutierrez Perez', 'correo' => 'ana@test.net', 'categoria' => 'Profesional Miembro'],
        ])->assertOk()->assertJson(['creados' => 0, 'actualizados' => 1]);

        $this->assertDatabaseCount('registrations', 1);
        $this->assertDatabaseCount('participantes', 1);
        $this->assertDatabaseHas('participantes', [
            'numero_documento' => 'ana@test.net', 'apellido' => 'Gutierrez Perez', 'categoria' => 'Profesional Miembro',
        ]);
    }

    public function test_fila_sin_correo_se_omite_sin_tumbar_el_resto(): void
    {
        $this->postSync([
            ['nombre' => 'Ana', 'apellido' => 'Gutierrez', 'correo' => 'ana@test.net'],
            ['nombre' => 'Sin', 'apellido' => 'Correo', 'correo' => ''],
            ['nombre' => 'Correo', 'apellido' => 'Invalido', 'correo' => 'no-es-un-correo'],
        ])->assertOk()->assertJson(['creados' => 1, 'actualizados' => 0]);

        $this->assertDatabaseCount('participantes', 1);
    }

    public function test_fila_sin_nombre_o_apellido_se_omite(): void
    {
        $response = $this->postSync([
            ['nombre' => '', 'apellido' => 'Test', 'correo' => 'a@test.net'],
        ])->assertOk();

        $this->assertCount(1, $response->json('omitidos'));
        $this->assertDatabaseCount('participantes', 0);
    }

    public function test_rechaza_si_el_evento_no_tiene_exactamente_un_form_type(): void
    {
        FormType::factory()->create(['event_id' => $this->evento->id]); // ahora hay 2

        $this->postSync([['nombre' => 'Ana', 'apellido' => 'Test', 'correo' => 'ana@test.net']])
            ->assertStatus(422);

        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_evento_inexistente_da_404(): void
    {
        $this->postJson(
            '/api/v1/internal/event/999999/participantes-externos/sync',
            ['participantes' => [['nombre' => 'Ana', 'apellido' => 'Test', 'correo' => 'ana@test.net']]],
            ['X-External-Sync-Secret' => 'test-secret-123']
        )->assertStatus(404);
    }

    /**
     * Integración con Retiro en sitio (elascenso/delivery): el CSV que
     * consume ese sync (organizador.dashboard.export) tiene que traer al
     * participante sincronizado con los campos que ese flujo necesita.
     */
    public function test_el_participante_sincronizado_aparece_en_el_export_csv_para_retiro_en_sitio(): void
    {
        $this->postSync([
            ['nombre' => 'Ana', 'apellido' => 'Gutierrez', 'correo' => 'ana@test.net', 'categoria' => 'Estudiante'],
        ])->assertOk();

        $url = \Illuminate\Support\Facades\URL::signedRoute('organizador.dashboard.export', ['evento' => $this->evento->id]);
        $csv = $this->get($url)->assertOk()->streamedContent();

        $lines = array_map('str_getcsv', explode("\n", trim($csv)));
        $header = $lines[0];
        $docIdx = array_search('Documento', $header);
        $estadoIdx = array_search('Estado de pago', $header);
        $catIdx = array_search('Categoría', $header);

        $fila = collect($lines)->first(fn ($l) => str_contains($l[$docIdx] ?? '', 'ana@test.net'));

        $this->assertNotNull($fila, 'El participante sincronizado no aparece en el CSV de retiro en sitio.');
        $this->assertSame('paid', $fila[$estadoIdx]);
        $this->assertSame('Estudiante', $fila[$catIdx]);
    }
}
