<?php

namespace Tests\Feature;

use App\Http\Controllers\EmpresaExpositoraLeadController;
use App\Models\Answer;
use App\Models\FormularioCampos;
use App\Models\LeadCapturado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ArmaExpositores;
use Tests\TestCase;

/**
 * SmartStand (25/09/2026) — captura de leads por la empresa expositora.
 */
class LeadCapturadoTest extends TestCase
{
    use RefreshDatabase, ArmaExpositores;

    public function test_buscar_devuelve_los_participantes_de_la_referencia_con_sus_respuestas(): void
    {
        $evento = $this->crearEvento();
        $cuenta = $this->crearCuenta($evento);
        $categoria = $this->crearCategoria($evento, null, 'Médico general');
        $ft = $this->crearFormType($evento, esExpositor: false);
        $registration = $this->crearInscripcion($evento, $ft, [
            'nombre' => 'Luis', 'apellido' => 'Peña', 'ciudad' => 'La Paz', 'categoria' => (string) $categoria->id,
        ]);
        $pregunta = FormularioCampos::factory()->create([
            'form_types_id' => $ft->id, 'nombre_campo' => 'especialidad', 'etiqueta' => 'Especialidad',
        ]);
        Answer::create([
            'form_types_id' => $ft->id, 'question_id' => $pregunta->id,
            'participante_id' => $registration->participants->first()->id, 'value' => 'Cardiología',
        ]);
        $this->comoExpositor($cuenta);

        $response = $this->getJson('/api/v1/expositor/participantes/' . strtolower($registration->referencia));

        $response->assertOk()
            ->assertJsonPath('referencia', $registration->referencia)
            ->assertJsonPath('participantes.0.nombre', 'Luis')
            ->assertJsonPath('participantes.0.ciudad', 'La Paz')
            ->assertJsonPath('participantes.0.categoria', 'Médico general')
            ->assertJsonPath('participantes.0.respuestas.0.pregunta', 'Especialidad')
            ->assertJsonPath('participantes.0.respuestas.0.respuesta', 'Cardiología')
            ->assertJsonPath('participantes.0.lead', null);
    }

    public function test_buscar_una_referencia_de_otro_evento_es_404(): void
    {
        $evento = $this->crearEvento();
        $otroEvento = $this->crearEvento();
        $cuenta = $this->crearCuenta($evento);
        $registration = $this->crearInscripcion($otroEvento, $this->crearFormType($otroEvento, false));
        $this->comoExpositor($cuenta);

        $this->getJson('/api/v1/expositor/participantes/' . $registration->referencia)->assertNotFound();
    }

    public function test_buscar_una_inscripcion_sin_pago_confirmado_es_404(): void
    {
        $evento = $this->crearEvento();
        $cuenta = $this->crearCuenta($evento);
        $registration = $this->crearInscripcion($evento, $this->crearFormType($evento, false), [], 'pending');
        $this->comoExpositor($cuenta);

        $this->getJson('/api/v1/expositor/participantes/' . $registration->referencia)->assertNotFound();
    }

    public function test_store_crea_el_lead_y_re_escanear_actualiza_sin_duplicar(): void
    {
        $evento = $this->crearEvento();
        $cuenta = $this->crearCuenta($evento);
        $asistente = $this->crearAsistente($evento);
        $this->comoExpositor($cuenta);

        $primero = $this->postJson('/api/v1/expositor/leads', [
            'participante_id' => $asistente->id, 'nota' => 'Pidió catálogo', 'calificacion' => 4,
        ]);
        $primero->assertCreated()->assertJsonPath('creado', true)->assertJsonPath('lead.calificacion', 4);
        $capturadoAt = LeadCapturado::firstOrFail()->capturado_at;

        $this->travel(2)->hours();
        $segundo = $this->postJson('/api/v1/expositor/leads', [
            'participante_id' => $asistente->id, 'calificacion' => 5,
        ]);
        $segundo->assertOk()->assertJsonPath('creado', false);

        $this->assertSame(1, LeadCapturado::count());
        $lead = LeadCapturado::firstOrFail();
        $this->assertSame(5, $lead->calificacion);
        $this->assertSame('Pidió catálogo', $lead->nota, 'Re-escanear sin nota no la borra.');
        $this->assertEquals($capturadoAt, $lead->capturado_at, 'La fecha es la del primer escaneo.');
    }

    public function test_store_valida_la_calificacion(): void
    {
        $evento = $this->crearEvento();
        $cuenta = $this->crearCuenta($evento);
        $asistente = $this->crearAsistente($evento);
        $this->comoExpositor($cuenta);

        $this->postJson('/api/v1/expositor/leads', ['participante_id' => $asistente->id, 'calificacion' => 6])
            ->assertStatus(422)->assertJsonValidationErrors('calificacion');
        $this->postJson('/api/v1/expositor/leads', ['calificacion' => 3])
            ->assertStatus(422)->assertJsonValidationErrors('participante_id');
    }

    public function test_store_de_un_participante_de_otro_evento_o_sin_pago_es_404(): void
    {
        $evento = $this->crearEvento();
        $otroEvento = $this->crearEvento();
        $cuenta = $this->crearCuenta($evento);
        $ajeno = $this->crearAsistente($otroEvento);
        $sinPago = $this->crearAsistente($evento, [], 'pending');
        $this->comoExpositor($cuenta);

        $this->postJson('/api/v1/expositor/leads', ['participante_id' => $ajeno->id])->assertNotFound();
        $this->postJson('/api/v1/expositor/leads', ['participante_id' => $sinPago->id])->assertNotFound();
        $this->assertSame(0, LeadCapturado::count());
    }

    public function test_index_y_csv_devuelven_solo_los_leads_propios(): void
    {
        $evento = $this->crearEvento();
        $mia = $this->crearCuenta($evento);
        $otra = $this->crearCuenta($evento);
        $a1 = $this->crearAsistente($evento, ['nombre' => 'Ana', 'ciudad' => 'Sucre']);
        $a2 = $this->crearAsistente($evento, ['nombre' => 'Beto', 'ciudad' => 'Cochabamba']);
        LeadCapturado::create(['empresa_expositora_id' => $mia->id, 'participante_id' => $a1->id, 'capturado_at' => now(), 'calificacion' => 5]);
        LeadCapturado::create(['empresa_expositora_id' => $otra->id, 'participante_id' => $a2->id, 'capturado_at' => now()]);
        $this->comoExpositor($mia);

        $this->getJson('/api/v1/expositor/leads')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.participante.nombre', 'Ana');

        $csv = $this->get('/api/v1/expositor/leads/export.csv');
        $csv->assertOk();
        $contenido = $csv->streamedContent();
        $this->assertStringContainsString('Ana', $contenido);
        $this->assertStringNotContainsString('Beto', $contenido);
    }

    /** Alias sin extensión (el hosting de UAT bloqueó otro endpoint `.csv` con un 403): mismo contenido, mismo guard. */
    public function test_el_alias_exportar_sin_extension_devuelve_el_mismo_csv_y_exige_token(): void
    {
        $evento = $this->crearEvento();
        $cuenta = $this->crearCuenta($evento);
        $asistente = $this->crearAsistente($evento, ['nombre' => 'Ana']);
        LeadCapturado::create(['empresa_expositora_id' => $cuenta->id, 'participante_id' => $asistente->id, 'capturado_at' => now()]);

        $this->getJson('/api/v1/expositor/leads/exportar')->assertStatus(401);

        $this->comoExpositor($cuenta);
        $alias = $this->get('/api/v1/expositor/leads/exportar');
        $alias->assertOk();
        $this->assertStringContainsString('text/csv', $alias->headers->get('Content-Type'));
        $this->assertStringContainsString('Ana', $alias->streamedContent());
    }

    public function test_el_csv_neutraliza_formulas_de_excel(): void
    {
        $evento = $this->crearEvento();
        $cuenta = $this->crearCuenta($evento);
        $asistente = $this->crearAsistente($evento, ['nombre' => '=HYPERLINK("http://malo.test")']);
        LeadCapturado::create(['empresa_expositora_id' => $cuenta->id, 'participante_id' => $asistente->id, 'capturado_at' => now(), 'nota' => '+cmd|calc']);
        $this->comoExpositor($cuenta);

        $contenido = $this->get('/api/v1/expositor/leads/export.csv')->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $contenido);
        $this->assertStringContainsString("'+cmd|calc", $contenido);
        $this->assertSame("'=x", EmpresaExpositoraLeadController::celdaSegura('=x'));
        $this->assertSame('normal', EmpresaExpositoraLeadController::celdaSegura('normal'));
        $this->assertSame(4, EmpresaExpositoraLeadController::celdaSegura(4));
    }

    public function test_dashboard_propio(): void
    {
        $evento = $this->crearEvento();
        $cuenta = $this->crearCuenta($evento);
        $a1 = $this->crearAsistente($evento, ['ciudad' => 'Sucre']);
        LeadCapturado::create(['empresa_expositora_id' => $cuenta->id, 'participante_id' => $a1->id, 'capturado_at' => now(), 'calificacion' => 4]);
        $this->comoExpositor($cuenta);

        $this->getJson('/api/v1/expositor/dashboard')
            ->assertOk()
            ->assertJsonPath('data.totalLeads', 1)
            ->assertJsonPath('data.porCiudad.0.ciudad', 'Sucre')
            ->assertJsonPath('data.calificacionPromedio', 4);
    }
}
