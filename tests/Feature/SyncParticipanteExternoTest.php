<?php

namespace Tests\Feature;

use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\FormularioCampos;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Registration;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sync de participantes de un congreso externo (07/09/2026, rediseñado
 * 12/09/2026 con el archivo real de COLABIOCLI 2026) — ver
 * brain/PLAN-SYNC-CONGRESO-EXTERNO-07092026.md, App\Actions\
 * SincronizarParticipanteExternoAction, App\Http\Controllers\Internal\
 * SyncExternoController. Endpoint llamado por el Google Apps Script de un
 * organizador externo — nunca por elascenso/event ni por un admin logueado.
 *
 * El archivo real reveló 2 productos distintos en el mismo evento
 * ("Congresista" y "Curso Pre-Congreso") — el body ahora manda "grupos",
 * cada uno con su `form_type_codigo`, en vez de una lista plana única.
 */
class SyncParticipanteExternoTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private FormType $congresista;

    private FormType $cursoPreCongreso;

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

        $this->congresista = FormType::factory()->create(['event_id' => $this->evento->id, 'name' => 'Congresista']);
        $this->cursoPreCongreso = FormType::factory()->create(['event_id' => $this->evento->id, 'name' => 'Curso Pre-Congreso']);
    }

    private function postGrupos(array $grupos, ?string $secret = 'test-secret-123'): \Illuminate\Testing\TestResponse
    {
        $headers = $secret !== null ? ['X-External-Sync-Secret' => $secret] : [];

        return $this->postJson(
            "/api/v1/internal/event/{$this->evento->id}/participantes-externos/sync",
            ['grupos' => $grupos],
            $headers
        );
    }

    private function postSync(array $participantes, string $formTypeCodigo = 'Congresista', ?string $secret = 'test-secret-123'): \Illuminate\Testing\TestResponse
    {
        return $this->postGrupos([
            ['form_type_codigo' => $formTypeCodigo, 'participantes' => $participantes],
        ], $secret);
    }

    public function test_rechaza_sin_secreto(): void
    {
        $this->postSync([['nombre' => 'Ana', 'apellido' => 'Test', 'correo' => 'ana@test.net']], secret: null)
            ->assertStatus(403);

        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_rechaza_con_secreto_incorrecto(): void
    {
        $this->postSync([['nombre' => 'Ana', 'apellido' => 'Test', 'correo' => 'ana@test.net']], secret: 'secreto-equivocado')
            ->assertStatus(403);
    }

    public function test_crea_participante_nuevo_como_paid(): void
    {
        $this->postSync([
            ['nombre' => 'Ana', 'apellido' => 'Gutierrez', 'correo' => 'ana@test.net', 'telefono' => '77712345', 'categoria' => 'Estudiante', 'ubicacion' => 'Santa Cruz'],
        ])->assertOk()->assertJson(['success' => true, 'grupos' => [['form_type_codigo' => 'Congresista', 'creados' => 1, 'actualizados' => 0]]]);

        $this->assertDatabaseHas('participantes', [
            'nombre' => 'Ana', 'apellido' => 'Gutierrez', 'numero_documento' => 'ana@test.net',
            'tipo_documento' => 'EMAIL', 'categoria' => 'Estudiante', 'ciudad' => 'Santa Cruz',
        ]);
        $this->assertDatabaseHas('registrations', [
            'evento_id' => $this->evento->id, 'form_types_id' => $this->congresista->id,
            'pago_status' => 'paid', 'tipo_pago' => 'externo',
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
        ])->assertOk()->assertJson(['grupos' => [['creados' => 1, 'actualizados' => 0]]]);

        $this->postSync([
            ['nombre' => 'Ana', 'apellido' => 'Gutierrez Perez', 'correo' => 'ana@test.net', 'categoria' => 'Profesional Miembro'],
        ])->assertOk()->assertJson(['grupos' => [['creados' => 0, 'actualizados' => 1]]]);

        $this->assertDatabaseCount('registrations', 1);
        $this->assertDatabaseCount('participantes', 1);
        $this->assertDatabaseHas('participantes', [
            'numero_documento' => 'ana@test.net', 'apellido' => 'Gutierrez Perez', 'categoria' => 'Profesional Miembro',
        ]);
    }

    /**
     * Fallback (12/09/2026, hallado con el archivo real): INSCRIPCIONES
     * LIBERADAS (invitados VIP, sin costo) a veces no trae correo. Sin
     * correo válido, la clave de idempotencia pasa a ser nombre+apellido
     * normalizado — para no perder la fila entera.
     */
    public function test_fila_sin_correo_valido_usa_nombre_apellido_como_documento(): void
    {
        $this->postSync([
            ['nombre' => 'Patricia', 'apellido' => 'Esperon', 'correo' => ''],
            ['nombre' => 'Romina', 'apellido' => 'Medeiros', 'correo' => 'no-es-un-correo'],
        ])->assertOk()->assertJson(['grupos' => [['creados' => 2, 'actualizados' => 0]]]);

        $this->assertDatabaseCount('participantes', 2);
        $this->assertDatabaseHas('participantes', [
            'numero_documento' => 'patricia esperon', 'tipo_documento' => 'NOMBRE', 'correo' => '',
        ]);
        $this->assertDatabaseHas('participantes', [
            'numero_documento' => 'romina medeiros', 'tipo_documento' => 'NOMBRE', 'correo' => '',
        ]);
    }

    /**
     * Reenviar la misma fila SIN correo (mismo nombre+apellido) tampoco
     * debe duplicar — mismo criterio de idempotencia que con correo, solo
     * que la clave es otra.
     */
    public function test_reenviar_fila_sin_correo_actualiza_en_vez_de_duplicar(): void
    {
        $this->postSync([['nombre' => 'Patricia', 'apellido' => 'Esperon', 'correo' => '', 'categoria' => 'Presidente']])
            ->assertOk()->assertJson(['grupos' => [['creados' => 1]]]);

        $this->postSync([['nombre' => 'Patricia', 'apellido' => 'Esperon', 'correo' => '', 'categoria' => 'Ex-Presidente']])
            ->assertOk()->assertJson(['grupos' => [['creados' => 0, 'actualizados' => 1]]]);

        $this->assertDatabaseCount('participantes', 1);
        $this->assertDatabaseHas('participantes', ['numero_documento' => 'patricia esperon', 'categoria' => 'Ex-Presidente']);
    }

    public function test_fila_sin_nombre_o_apellido_se_omite(): void
    {
        $response = $this->postSync([
            ['nombre' => '', 'apellido' => 'Test', 'correo' => 'a@test.net'],
        ])->assertOk();

        $this->assertCount(1, $response->json('grupos.0.omitidos'));
        $this->assertDatabaseCount('participantes', 0);
    }

    /**
     * Hallazgo real (archivo de COLABIOCLI 2026, 12/09/2026): una persona
     * puede estar anotada al congreso Y a un curso pre-congreso con el
     * mismo correo — son 2 inscripciones distintas, no deben pisarse.
     */
    public function test_misma_persona_en_dos_form_types_crea_dos_registrations(): void
    {
        $this->postSync([
            ['nombre' => 'Ana', 'apellido' => 'Gutierrez', 'correo' => 'ana@test.net', 'categoria' => 'Estudiante'],
        ], formTypeCodigo: 'Congresista')->assertOk();

        $this->postSync([
            ['nombre' => 'Ana', 'apellido' => 'Gutierrez', 'correo' => 'ana@test.net', 'nombre_curso' => 'Urianálisis'],
        ], formTypeCodigo: 'Curso Pre-Congreso')->assertOk();

        $this->assertDatabaseCount('registrations', 2);
        $this->assertDatabaseCount('participantes', 2);
        $this->assertDatabaseHas('registrations', ['evento_id' => $this->evento->id, 'form_types_id' => $this->congresista->id]);
        $this->assertDatabaseHas('registrations', ['evento_id' => $this->evento->id, 'form_types_id' => $this->cursoPreCongreso->id]);
        $this->assertDatabaseHas('participantes', ['numero_documento' => 'ana@test.net', 'categoria' => 'Estudiante']);
        $this->assertDatabaseHas('participantes', ['numero_documento' => 'ana@test.net', 'categoria' => 'Urianálisis']);
    }

    /**
     * Cursos_Pre_Congreso manda "Curso Pre-Congreso" como Categoría para
     * TODAS las filas (no distingue nada) — el dato real está en
     * `nombre_curso`, que pisa a `categoria` cuando viene.
     */
    public function test_nombre_curso_pisa_a_categoria_cuando_viene(): void
    {
        $this->postSync([
            ['nombre' => 'Fabiola', 'apellido' => 'Linares', 'correo' => 'fabiola@test.net', 'categoria' => 'Curso Pre-Congreso', 'nombre_curso' => 'Elevando la calidad en el laboratorio'],
        ], formTypeCodigo: 'Curso Pre-Congreso')->assertOk();

        $this->assertDatabaseHas('participantes', ['numero_documento' => 'fabiola@test.net', 'categoria' => 'Elevando la calidad en el laboratorio']);
    }

    /**
     * nombre_certificado (12/09/2026) — se guarda como respuesta al
     * sistema genérico de preguntas adicionales, SI la pregunta está
     * configurada para el FormType (setup manual). Sin parsear el texto,
     * tal cual viene de la hoja.
     */
    public function test_nombre_certificado_se_guarda_como_respuesta_a_la_pregunta_configurada(): void
    {
        $pregunta = FormularioCampos::factory()->create([
            'form_types_id' => $this->congresista->id,
            'nombre_campo' => 'nombre_certificado',
        ]);

        $this->postSync([
            ['nombre' => 'Frida', 'apellido' => 'Camargo Arce', 'correo' => 'frida@test.net', 'nombre_certificado' => 'MsC. FRIDA CAMARGO ARCE'],
        ])->assertOk();

        $participante = \App\Models\Participante::where('numero_documento', 'frida@test.net')->first();

        $this->assertDatabaseHas('answers', [
            'form_types_id' => $this->congresista->id,
            'question_id' => $pregunta->id,
            'participante_id' => $participante->id,
            'value' => 'MsC. FRIDA CAMARGO ARCE',
        ]);
    }

    /**
     * Sin la pregunta configurada para ese FormType, el sync no debe
     * fallar — es una pregunta opcional, no un requisito.
     */
    public function test_nombre_certificado_sin_pregunta_configurada_no_falla_y_no_guarda_nada(): void
    {
        $this->postSync([
            ['nombre' => 'Frida', 'apellido' => 'Camargo Arce', 'correo' => 'frida@test.net', 'nombre_certificado' => 'MsC. FRIDA CAMARGO ARCE'],
        ])->assertOk()->assertJson(['grupos' => [['creados' => 1]]]);

        $this->assertDatabaseCount('answers', 0);
    }

    /**
     * Reenviar la misma fila con un nombre_certificado distinto actualiza
     * la respuesta existente, no duplica — mismo criterio de idempotencia
     * que el resto del Action.
     */
    public function test_reenviar_nombre_certificado_actualiza_la_respuesta_existente(): void
    {
        FormularioCampos::factory()->create([
            'form_types_id' => $this->congresista->id,
            'nombre_campo' => 'nombre_certificado',
        ]);

        $this->postSync([
            ['nombre' => 'Frida', 'apellido' => 'Camargo Arce', 'correo' => 'frida@test.net', 'nombre_certificado' => 'Lic. Frida Camargo'],
        ])->assertOk();

        $this->postSync([
            ['nombre' => 'Frida', 'apellido' => 'Camargo Arce', 'correo' => 'frida@test.net', 'nombre_certificado' => 'MsC. Frida Camargo Arce'],
        ])->assertOk();

        $this->assertDatabaseCount('answers', 1);
        $this->assertDatabaseHas('answers', ['value' => 'MsC. Frida Camargo Arce']);
    }

    public function test_form_type_codigo_inexistente_da_422(): void
    {
        $this->postSync([['nombre' => 'Ana', 'apellido' => 'Test', 'correo' => 'ana@test.net']], formTypeCodigo: 'No Existe')
            ->assertStatus(422);

        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_resuelve_form_type_codigo_sin_importar_mayusculas(): void
    {
        $this->postSync([['nombre' => 'Ana', 'apellido' => 'Test', 'correo' => 'ana@test.net']], formTypeCodigo: 'congresista')
            ->assertOk()->assertJson(['grupos' => [['creados' => 1]]]);
    }

    public function test_evento_inexistente_da_404(): void
    {
        $this->postJson(
            '/api/v1/internal/event/999999/participantes-externos/sync',
            ['grupos' => [['form_type_codigo' => 'Congresista', 'participantes' => [['nombre' => 'Ana', 'apellido' => 'Test', 'correo' => 'ana@test.net']]]]],
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
