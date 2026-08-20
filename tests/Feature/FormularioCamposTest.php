<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\FormularioCampos;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Participante;
use App\Models\QuestionOptions;
use App\Models\Registration;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRUD de preguntas adicionales del formulario de inscripción — admin
 * scoped a su evento, o super_admin. Ver FormularioCamposController
 * (20/08/2026). El consumo público (renderizado + guardado de
 * respuestas) ya tenía cobertura indirecta vía CrearInscripcionAction;
 * este test cubre solo la administración, que no existía.
 */
class FormularioCamposTest extends TestCase
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
        ]);
        $this->formType = FormType::factory()->create(['event_id' => $this->evento->id]);
    }

    public function test_admin_scoped_a_su_evento_puede_crear_pregunta_de_texto(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $this->evento->id]);

        $response = $this->postJson("/api/v1/form-type/{$this->formType->id}/preguntas", [
            'seccion' => 'personal',
            'nombre_campo' => 'alergias',
            'etiqueta' => '¿Tenés alguna alergia?',
            'tipo_input' => 'text',
            'obligatorio' => true,
        ]);

        $response->assertStatus(201)->assertJson(['success' => true]);
        $this->assertDatabaseHas('questions', [
            'form_types_id' => $this->formType->id,
            'etiqueta' => '¿Tenés alguna alergia?',
        ]);
    }

    public function test_admin_de_otro_evento_no_puede_crear_pregunta(): void
    {
        $otroEvento = Evento::factory()->create([
            'organizador_id' => $this->evento->organizador_id,
            'tipo_evento_id' => $this->evento->tipo_evento_id,
            'subtipo_evento_id' => $this->evento->subtipo_evento_id,
            'pais_id' => $this->evento->pais_id,
            'ciudad_id' => $this->evento->ciudad_id,
        ]);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $otroEvento->id]);

        $this->postJson("/api/v1/form-type/{$this->formType->id}/preguntas", [
            'seccion' => 'personal', 'nombre_campo' => 'x', 'etiqueta' => 'X', 'tipo_input' => 'text',
        ])->assertStatus(403);
    }

    public function test_tipo_select_sin_opciones_es_rechazado(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson("/api/v1/form-type/{$this->formType->id}/preguntas", [
            'seccion' => 'kit', 'nombre_campo' => 'talla', 'etiqueta' => 'Talla', 'tipo_input' => 'select',
        ])->assertStatus(422);
    }

    public function test_tipo_file_es_rechazado(): void
    {
        // El formulario público (elascenso/event/index.php) omite en
        // silencio tipo_input=file — no hay endpoint de subida todavía.
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson("/api/v1/form-type/{$this->formType->id}/preguntas", [
            'seccion' => 'kit', 'nombre_campo' => 'foto', 'etiqueta' => 'Foto', 'tipo_input' => 'file',
        ])->assertStatus(422);
    }

    public function test_crea_pregunta_select_con_opciones(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $response = $this->postJson("/api/v1/form-type/{$this->formType->id}/preguntas", [
            'seccion' => 'kit',
            'nombre_campo' => 'talla',
            'etiqueta' => 'Talla',
            'tipo_input' => 'select',
            'options' => [
                ['option_text' => 'S', 'order' => 0],
                ['option_text' => 'M', 'order' => 1],
                ['option_text' => 'L', 'order' => 2],
            ],
        ]);

        $response->assertStatus(201);
        $pregunta = FormularioCampos::where('etiqueta', 'Talla')->firstOrFail();
        $this->assertCount(3, $pregunta->options);
        $this->assertDatabaseHas('question_options', ['question_id' => $pregunta->id, 'option_text' => 'M']);
    }

    public function test_update_reemplaza_las_opciones_viejas(): void
    {
        $pregunta = FormularioCampos::factory()->create(['form_types_id' => $this->formType->id, 'tipo_input' => 'radio']);
        QuestionOptions::factory()->create(['question_id' => $pregunta->id, 'option_text' => 'Vieja']);

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->putJson("/api/v1/pregunta/{$pregunta->id}", [
            'options' => [['option_text' => 'Nueva', 'order' => 0]],
        ])->assertStatus(200);

        $this->assertDatabaseMissing('question_options', ['question_id' => $pregunta->id, 'option_text' => 'Vieja']);
        $this->assertDatabaseHas('question_options', ['question_id' => $pregunta->id, 'option_text' => 'Nueva']);
    }

    public function test_no_permite_editar_pregunta_de_otro_evento(): void
    {
        $otroEvento = Evento::factory()->create([
            'organizador_id' => $this->evento->organizador_id,
            'tipo_evento_id' => $this->evento->tipo_evento_id,
            'subtipo_evento_id' => $this->evento->subtipo_evento_id,
            'pais_id' => $this->evento->pais_id,
            'ciudad_id' => $this->evento->ciudad_id,
        ]);
        $otroFormType = FormType::factory()->create(['event_id' => $otroEvento->id]);
        $pregunta = FormularioCampos::factory()->create(['form_types_id' => $otroFormType->id]);

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $this->evento->id]);

        $this->putJson("/api/v1/pregunta/{$pregunta->id}", ['etiqueta' => 'Nuevo'])
            ->assertStatus(403);
    }

    public function test_destroy_elimina_la_pregunta_y_sus_respuestas_en_cascada(): void
    {
        $pregunta = FormularioCampos::factory()->create(['form_types_id' => $this->formType->id]);
        $registration = Registration::factory()->create([
            'evento_id' => $this->evento->id,
            'form_types_id' => $this->formType->id,
            'referencia' => 'TEST-REF-1',
            'fecha' => now(),
            'evento_nombre' => $this->evento->nombre,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'paid',
        ]);
        // Participante::factory() tiene un campo 'email' que no existe en la
        // tabla real (usa 'correo') — pre-existente, no relacionado a este
        // feature; mismo esquive que ya usa EnviarCertificadosCongresoTest.
        $participante = Participante::create([
            'registration_id' => $registration->id,
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'genero' => 'Femenino',
            'tipo_documento' => 'DNI', 'numero_documento' => (string) rand(10000000, 99999999),
            'fecha_nacimiento' => '1995-01-01', 'edad' => 30, 'correo' => 'ana'.rand(1, 99999).'@test.net',
            'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => '1', 'subtotal' => 50,
        ]);
        $answer = Answer::factory()->create([
            'form_types_id' => $this->formType->id,
            'question_id' => $pregunta->id,
            'participante_id' => $participante->id,
            'value' => 'Sí, penicilina',
        ]);

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->deleteJson("/api/v1/pregunta/{$pregunta->id}")->assertStatus(200);

        $this->assertDatabaseMissing('questions', ['id' => $pregunta->id]);
        // Documenta el comportamiento de cascada (FK de la migración) —
        // no es un bug, es la misma decisión ya aceptada en SesionCongreso.
        $this->assertDatabaseMissing('answers', ['id' => $answer->id]);
    }
}
