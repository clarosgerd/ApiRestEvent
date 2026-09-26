<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\FormularioCampos;
use App\Models\Registration;
use App\Models\SesionCongreso;
use App\Models\Taller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Staff y ponente/expositor (26/09/2026): sin categoría, sin costo y sin talleres como asistentes; el ponente
 * indica qué va a dictar con dos preguntas adicionales. Distinto de la empresa expositora (`es_expositor`),
 * que sí paga una categoría (stand) pero tampoco se inscribe a talleres.
 */
class StaffPonenteFormTypeTest extends TestCase
{
    use RefreshDatabase;

    private Evento $event;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->event = Evento::factory()->create();
    }

    private function formType(array $attrs = []): FormType
    {
        return FormType::factory()->create(array_merge([
            'event_id' => $this->event->id, 'cupo_total' => 100, 'activo' => true, 'requiere_categoria' => true,
            'precio_base' => 0, 'costo_edicion' => 0, 'es_staff' => false, 'es_ponente' => false, 'es_expositor' => false,
            'permite_inscripcion_grupal' => false, 'un_solo_participante' => false,
        ], $attrs));
    }

    private function taller(): array
    {
        $taller = Taller::factory()->create(['evento_id' => $this->event->id, 'modalidad' => 'OPTIONAL', 'precio' => 0, 'activo' => true, 'permite_inscripcion' => true]);
        $sesion = SesionCongreso::factory()->create(['evento_id' => $this->event->id, 'taller_id' => $taller->id, 'cupo' => 50]);

        return [$taller, $sesion];
    }

    /** Inscripción de UN participante por la API pública; `$extra` se mezcla en el participante. */
    private function inscribir(FormType $ft, array $extra = [], float $total = 0): \Illuminate\Testing\TestResponse
    {
        $this->actingAsPersona();
        $sinCategoria = ! $ft->requiere_categoria;
        $categoria = $sinCategoria ? null : Category::factory()->create(['event_id' => $this->event->id, 'price' => 100]);

        return $this->postJson('/api/v1/registrations', [[
            'referencia' => 'REF-' . Str::random(8), 'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->event->id, 'form_types_id' => $ft->id, 'evento_nombre' => $this->event->nombre,
            'tipo_pago' => 'gratis', 'pago_status' => 'pending',
            'totales' => ['inscripcion' => $total, 'donacion' => 0, 'souvenirs' => 0, 'fee' => round($total * 0.05, 2), 'descuento' => 0, 'grand_total' => round($total * 1.05, 2)],
            'participantes' => [array_merge([
                'nombre' => 'Ana', 'apellido' => 'Garcia', 'alias' => '', 'genero' => 'Femenino', 'tipoDocumento' => 'CI',
                'numeroDocumento' => '20000001', 'polera' => 'M', 'precioPolera' => 0,
                'nacimiento' => ['dia' => 10, 'mes' => 5, 'anio' => 1995], 'edad' => 31,
                'correo' => 'ana@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
                'categoria' => $sinCategoria ? $ft->name : $categoria->id, 'precioCategoria' => $sinCategoria ? 0 : 100,
                'donacion' => 0, 'promoDescuento' => 0, 'promoCodigo' => '', 'subtotal' => $total,
                'contacto_emergencia' => ['nombre' => 'X', 'celular' => '123', 'relacion' => 'Padre'],
                'souvenirs' => [],
            ], $extra)],
        ]]);
    }

    // ── Hook: sin categoría y sin costo ──────────────────────────────

    public function test_al_marcar_staff_o_ponente_el_tipo_queda_sin_categoria_y_sin_costo(): void
    {
        foreach (['es_staff', 'es_ponente'] as $flag) {
            $ft = $this->formType([$flag => true, 'requiere_categoria' => true, 'precio_base' => 80, 'costo_edicion' => 15]);

            $ft->refresh();
            $this->assertSame(0, (int) $ft->requiere_categoria, "$flag: requiere_categoria");
            $this->assertEquals(0, $ft->precio_base, "$flag: precio_base");
            $this->assertEquals(0, $ft->costo_edicion, "$flag: costo_edicion");
        }
    }

    public function test_un_tipo_normal_y_la_empresa_expositora_conservan_su_categoria_y_precio(): void
    {
        $normal = $this->formType(['requiere_categoria' => true, 'precio_base' => 80, 'costo_edicion' => 15]);
        $expositor = $this->formType(['es_expositor' => true, 'requiere_categoria' => true, 'precio_base' => 300]);

        $this->assertSame(1, (int) $normal->refresh()->requiere_categoria);
        $this->assertEquals(80, $normal->precio_base);
        $this->assertSame(1, (int) $expositor->refresh()->requiere_categoria);
        $this->assertEquals(300, $expositor->precio_base);
    }

    public function test_tildar_staff_en_un_tipo_existente_por_la_api_normaliza_los_precios(): void
    {
        $this->actingAsAdmin();
        $ft = $this->formType(['requiere_categoria' => true, 'precio_base' => 80, 'costo_edicion' => 15]);

        $this->putJson('/api/v1/form-type/' . $ft->id, ['es_staff' => true])->assertOk();

        $ft->refresh();
        $this->assertSame(0, (int) $ft->requiere_categoria);
        $this->assertEquals(0, $ft->precio_base);
        $this->assertEquals(0, $ft->costo_edicion);
    }

    // ── Inscripción en $0 ────────────────────────────────────────────

    public function test_staff_se_inscribe_sin_categoria_y_queda_pagada_como_gratis(): void
    {
        $ft = $this->formType(['es_staff' => true]);

        $this->inscribir($ft)->assertCreated();

        $registration = Registration::firstOrFail();
        $this->assertSame('paid', $registration->pago_status);
        $this->assertSame('gratis', $registration->tipo_pago);
    }

    public function test_staff_y_ponente_con_total_mayor_a_cero_se_rechazan(): void
    {
        foreach (['es_staff', 'es_ponente'] as $flag) {
            $ft = $this->formType([$flag => true]);
            $this->inscribir($ft, [], 50)->assertStatus(422);
        }
        $this->assertDatabaseCount('registrations', 0);
    }

    // ── Talleres ─────────────────────────────────────────────────────

    public function test_staff_ponente_y_expositor_no_pueden_elegir_talleres(): void
    {
        [$taller, $sesion] = $this->taller();

        foreach (['es_staff', 'es_ponente', 'es_expositor'] as $flag) {
            $ft = $this->formType([$flag => true, 'requiere_categoria' => $flag === 'es_expositor']);
            $extra = ['talleres' => [['taller_id' => $taller->id, 'sesion_congreso_id' => $sesion->id]]];

            $this->inscribir($ft, $extra, $flag === 'es_expositor' ? 100 : 0)->assertStatus(422);
        }
        $this->assertDatabaseCount('registrations', 0);
    }

    /** Regresión: un tipo normal sigue pudiendo elegir talleres. */
    public function test_un_tipo_normal_si_puede_elegir_talleres(): void
    {
        [$taller, $sesion] = $this->taller();
        $ft = $this->formType(['requiere_categoria' => true]);

        $this->inscribir($ft, ['talleres' => [['taller_id' => $taller->id, 'sesion_congreso_id' => $sesion->id]]], 100)->assertCreated();
    }

    public function test_un_taller_obligatorio_del_evento_no_bloquea_a_staff_ni_a_ponente(): void
    {
        Taller::factory()->create(['evento_id' => $this->event->id, 'modalidad' => 'REQUIRED', 'activo' => true, 'permite_inscripcion' => true]);

        foreach (['es_staff', 'es_ponente'] as $i => $flag) {
            $ft = $this->formType([$flag => true]);
            $this->inscribir($ft, ['numeroDocumento' => '3000000' . $i, 'correo' => "p{$i}@test.net"])->assertCreated();
        }
    }

    // ── Ponente: preguntas adicionales ───────────────────────────────

    public function test_un_tipo_ponente_recibe_las_dos_preguntas_una_sola_vez(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/form-type', [
            'event_id' => $this->event->id, 'name' => 'Ponente', 'icon' => '🎤', 'description' => 'x', 'cupo_total' => 10,
            'precio_base' => 50, 'costo_edicion' => 5, 'tiempo_expiracion_min' => 30, 'color' => '#00bad2', 'es_ponente' => true,
        ])->assertCreated();

        $ft = FormType::where('event_id', $this->event->id)->firstOrFail();
        $this->assertEquals(0, $ft->precio_base);
        $this->assertEqualsCanonicalizing(
            ['taller_dictara', 'tema_charla'],
            FormularioCampos::where('form_types_id', $ft->id)->pluck('nombre_campo')->all()
        );

        // Editar el organizador la etiqueta y guardar de nuevo: no se duplica ni se pisa.
        FormularioCampos::where('form_types_id', $ft->id)->where('nombre_campo', 'tema_charla')->update(['etiqueta' => 'Título de su charla']);
        $this->putJson('/api/v1/form-type/' . $ft->id, ['name' => 'Ponentes'])->assertOk();

        $this->assertSame(2, FormularioCampos::where('form_types_id', $ft->id)->count());
        $this->assertSame('Título de su charla', FormularioCampos::where('form_types_id', $ft->id)->where('nombre_campo', 'tema_charla')->value('etiqueta'));
    }

    public function test_un_tipo_que_no_es_ponente_no_recibe_esas_preguntas(): void
    {
        $this->actingAsAdmin();
        $ft = $this->formType();

        $this->putJson('/api/v1/form-type/' . $ft->id, ['name' => 'Otro'])->assertOk();
        $this->putJson('/api/v1/form-type/' . $ft->id, ['es_staff' => true])->assertOk();

        $this->assertSame(0, FormularioCampos::where('form_types_id', $ft->id)->count());
    }

    public function test_al_vincular_un_ponente_se_ve_lo_que_dijo_que_va_a_dictar(): void
    {
        $this->actingAsAdmin();
        $ft = $this->formType(['es_ponente' => true]);
        $this->putJson('/api/v1/form-type/' . $ft->id, ['name' => 'Ponente'])->assertOk(); // crea las preguntas
        $tema = FormularioCampos::where('form_types_id', $ft->id)->where('nombre_campo', 'tema_charla')->firstOrFail();
        $taller = FormularioCampos::where('form_types_id', $ft->id)->where('nombre_campo', 'taller_dictara')->firstOrFail();

        $this->inscribir($ft, ['answers' => [
            ['form_types_id' => $ft->id, 'question_id' => $taller->id, 'value' => 'Taller de Ecografía'],
            ['form_types_id' => $ft->id, 'question_id' => $tema->id, 'value' => 'Ultrasonido en urgencias'],
        ]])->assertCreated();

        $this->actingAsAdmin();
        $this->getJson('/api/v1/event/' . $this->event->id . '/staff-disponible?rol=ponente')
            ->assertOk()
            ->assertJsonPath('data.0.taller_dictara', 'Taller de Ecografía')
            ->assertJsonPath('data.0.tema_charla', 'Ultrasonido en urgencias');
    }
}
