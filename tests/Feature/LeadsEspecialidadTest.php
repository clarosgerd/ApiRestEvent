<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\FormularioCampos;
use App\Models\LeadCapturado;
use App\Support\LeadsCapturadosData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ArmaExpositores;
use Tests\TestCase;

/**
 * SmartStand fase 4 — mapa de especialidades e instituciones de los leads.
 * La pregunta la indica el organizador (por etiqueta) en expositores_config.
 */
class LeadsEspecialidadTest extends TestCase
{
    use RefreshDatabase, ArmaExpositores;

    /** Asistente capturado por la cuenta, con su respuesta a una pregunta (en SU form_type). */
    private function leadConRespuesta($cuenta, string $pregunta, ?string $respuesta, array $participante = []): LeadCapturado
    {
        $asistente = $this->crearAsistente($cuenta->evento, $participante);
        if ($respuesta !== null) {
            $ftId = $asistente->registration->form_types_id;
            $campo = FormularioCampos::where(['form_types_id' => $ftId, 'etiqueta' => $pregunta])->first()
                ?? FormularioCampos::factory()->create([
                    'form_types_id' => $ftId,
                    'etiqueta'      => $pregunta,
                    'nombre_campo'  => 'campo_' . md5($pregunta . $ftId),
                ]);
            Answer::create([
                'form_types_id' => $campo->form_types_id, 'question_id' => $campo->id,
                'participante_id' => $asistente->id, 'value' => $respuesta,
            ]);
        }

        return LeadCapturado::create([
            'empresa_expositora_id' => $cuenta->id, 'participante_id' => $asistente->id, 'capturado_at' => now(),
        ]);
    }

    public function test_sin_configuracion_no_hay_graficos_y_la_bandera_es_falsa(): void
    {
        $cuenta = $this->crearCuenta($this->crearEvento());
        $this->leadConRespuesta($cuenta, 'Especialidad', 'Cardiología');

        $datos = LeadsCapturadosData::paraEmpresa($cuenta);

        $this->assertFalse($datos['especialidadConfigurada']);
        $this->assertFalse($datos['institucionConfigurada']);
        $this->assertSame([], $datos['porEspecialidad']);
        $this->assertSame([], $datos['porInstitucion']);
    }

    public function test_agrupa_variantes_ortograficas_y_muestra_la_grafia_mas_frecuente(): void
    {
        $evento = $this->crearEvento(['expositores_config' => ['especialidad_pregunta' => 'Especialidad']]);
        $cuenta = $this->crearCuenta($evento);
        foreach (['Anestesiólogo', 'anestesiologo ', 'Anestesiólogo', 'ANESTESIOLOGO'] as $v) {
            $this->leadConRespuesta($cuenta, 'Especialidad', $v);
        }
        $this->leadConRespuesta($cuenta, 'Especialidad', 'Cardiología');
        $this->leadConRespuesta($cuenta, 'Especialidad', null);          // no respondió
        $this->leadConRespuesta($cuenta, 'Especialidad', '   ');          // respuesta vacía

        $datos = LeadsCapturadosData::paraEmpresa($cuenta);

        $this->assertTrue($datos['especialidadConfigurada']);
        $this->assertFalse($datos['institucionConfigurada']);
        $this->assertSame([
            ['etiqueta' => 'Anestesiólogo', 'total' => 4],
            ['etiqueta' => 'Cardiología', 'total' => 1],
            ['etiqueta' => 'Sin dato', 'total' => 2],
        ], $datos['porEspecialidad']);
        $this->assertSame([], $datos['porInstitucion']);
    }

    public function test_la_etiqueta_configurada_se_compara_sin_acentos_ni_mayusculas(): void
    {
        $evento = $this->crearEvento(['expositores_config' => ['institucion_pregunta' => 'institución  o hospital']]);
        $cuenta = $this->crearCuenta($evento);
        // Cada asistente tiene su propio form_type con su propia pregunta (mismo nombre).
        $this->leadConRespuesta($cuenta, 'Institucion o Hospital', 'Hospital Japonés');
        $this->leadConRespuesta($cuenta, 'INSTITUCIÓN O HOSPITAL', 'Hospital Japonés');

        $datos = LeadsCapturadosData::paraEmpresa($cuenta);

        $this->assertSame([['etiqueta' => 'Hospital Japonés', 'total' => 2]], $datos['porInstitucion']);
    }

    public function test_top_10_y_otros(): void
    {
        $lista = [];
        for ($i = 1; $i <= 12; $i++) {
            $lista = array_merge($lista, array_fill(0, 13 - $i, "Esp{$i}"));   // Esp1 x12 … Esp12 x1
        }

        $filas = LeadsCapturadosData::agrupar($lista);

        $this->assertCount(11, $filas);
        $this->assertSame('Esp1', $filas[0]['etiqueta']);
        $this->assertSame('Otros', $filas[10]['etiqueta']);
        $this->assertSame(2 + 1, $filas[10]['total']);
    }

    public function test_los_leads_y_el_csv_incluyen_las_columnas_solo_si_estan_configuradas(): void
    {
        $evento = $this->crearEvento(['expositores_config' => ['especialidad_pregunta' => 'Especialidad']]);
        $cuenta = $this->crearCuenta($evento);
        $this->leadConRespuesta($cuenta, 'Especialidad', 'Pediatría', ['nombre' => 'Ana']);
        $this->comoExpositor($cuenta);

        $this->getJson('/api/v1/expositor/leads')
            ->assertOk()
            ->assertJsonPath('data.0.participante.especialidad', 'Pediatría')
            ->assertJsonPath('data.0.participante.institucion', null);

        $csv = $this->get('/api/v1/expositor/leads/exportar')->streamedContent();
        $cabecera = str_getcsv(strtok($csv, "\n"));
        $this->assertContains('Especialidad', $cabecera);
        $this->assertNotContains('Institución', $cabecera);
        $this->assertStringContainsString('Pediatría', $csv);

        // Sin configurar: mismas columnas de antes.
        $evento->update(['expositores_config' => null]);
        $cuenta->unsetRelation('evento');
        $this->comoExpositor($cuenta->fresh());
        $csv2 = $this->get('/api/v1/expositor/leads/exportar')->streamedContent();
        $this->assertNotContains('Especialidad', str_getcsv(strtok($csv2, "\n")));
    }

    public function test_el_csv_neutraliza_formulas_en_especialidad(): void
    {
        $evento = $this->crearEvento(['expositores_config' => ['especialidad_pregunta' => 'Especialidad']]);
        $cuenta = $this->crearCuenta($evento);
        $this->leadConRespuesta($cuenta, 'Especialidad', '=HYPERLINK("http://malo.test")');
        $this->comoExpositor($cuenta);

        $csv = $this->get('/api/v1/expositor/leads/exportar')->streamedContent();

        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_el_estado_del_seguimiento_aparece_en_cada_lead(): void
    {
        $cuenta = $this->crearCuenta($this->crearEvento());
        $lead = $this->leadConRespuesta($cuenta, 'Especialidad', null);
        $lead->update(['seguimiento_estado' => 'omitido', 'seguimiento_motivo' => 'baja']);
        $this->comoExpositor($cuenta);

        $this->getJson('/api/v1/expositor/leads')
            ->assertJsonPath('data.0.seguimiento.estado', 'omitido')
            ->assertJsonPath('data.0.seguimiento.motivo', 'baja');
    }
}
