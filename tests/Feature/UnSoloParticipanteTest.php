<?php

namespace Tests\Feature;

use App\Actions\ActualizarInscripcionAction;
use App\Models\Category;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * "Solo un participante por inscripción" (26/09/2026) — form_types.un_solo_participante,
 * para empresa expositora / staff / ponente. `permite_inscripcion_grupal` no sirve para
 * esto: solo fija un tope cuando está activo y, apagado, no limita nada.
 */
class UnSoloParticipanteTest extends TestCase
{
    use RefreshDatabase;

    private Evento $event;
    private Category $categoria;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->actingAsPersona();
        $this->event = Evento::factory()->create();
        $this->categoria = Category::factory()->create(['event_id' => $this->event->id, 'price' => 100]);
    }

    private function formType(array $attrs = []): FormType
    {
        return FormType::factory()->create(array_merge([
            'event_id' => $this->event->id, 'cupo_total' => 100, 'activo' => true, 'requiere_categoria' => true,
        ], $attrs));
    }

    private function participante(string $doc, string $correo): array
    {
        return [
            'nombre' => 'Ana', 'apellido' => 'Garcia', 'alias' => '', 'genero' => 'Femenino',
            'tipoDocumento' => 'CI', 'numeroDocumento' => $doc, 'polera' => 'M', 'precioPolera' => 0,
            'nacimiento' => ['dia' => 10, 'mes' => 5, 'anio' => 1995], 'edad' => 31,
            'correo' => $correo, 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => $this->categoria->id, 'precioCategoria' => 100,
            'donacion' => 0, 'promoDescuento' => 0, 'promoCodigo' => '', 'subtotal' => 100,
            'contacto_emergencia' => ['nombre' => 'X', 'celular' => '123', 'relacion' => 'Padre'],
            'souvenirs' => [],
        ];
    }

    /** @param  list<array>  $participantes */
    private function payload(FormType $ft, array $participantes): array
    {
        $n = count($participantes);

        return [[
            'referencia' => 'REF-' . Str::random(8),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->event->id,
            'form_types_id' => $ft->id,
            'evento_nombre' => $this->event->nombre,
            'tipo_pago' => 'QR',
            'pago_status' => 'pending',
            // fee_pct del evento = 5%: CrearInscripcionAction::validateFeePct() lo recalcula.
            'totales' => [
                'inscripcion' => 100 * $n, 'donacion' => 0, 'souvenirs' => 0, 'fee' => 5 * $n,
                'descuento' => 0, 'grand_total' => 105 * $n,
            ],
            'participantes' => $participantes,
        ]];
    }

    private function dos(): array
    {
        return [$this->participante('11111111', 'uno@test.net'), $this->participante('22222222', 'dos@test.net')];
    }

    public function test_alta_con_dos_participantes_en_un_tipo_con_el_flag_da_422(): void
    {
        $ft = $this->formType(['un_solo_participante' => true]);

        $this->postJson('/api/v1/registrations', $this->payload($ft, $this->dos()))->assertStatus(422);

        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_alta_con_un_participante_en_un_tipo_con_el_flag_da_201(): void
    {
        $ft = $this->formType(['un_solo_participante' => true]);

        $this->postJson('/api/v1/registrations', $this->payload($ft, [$this->participante('11111111', 'uno@test.net')]))
            ->assertCreated();
    }

    /** Regresión: por defecto (flag apagado) nada cambia y siguen permitiéndose varios. */
    public function test_alta_con_dos_participantes_en_un_tipo_sin_el_flag_sigue_dando_201(): void
    {
        $ft = $this->formType();

        $this->postJson('/api/v1/registrations', $this->payload($ft, $this->dos()))->assertCreated();
    }

    /**
     * Un tipo que HOY permite varios (incluso con inscripción grupal habilitada) deja de permitirlo en cuanto se
     * tilda el flag: se evalúa al inscribir, no al crear el tipo, y gana sobre `permite_inscripcion_grupal`.
     */
    public function test_tildar_el_flag_en_un_tipo_existente_con_grupal_habilitado_corta_los_varios_participantes(): void
    {
        $ft = $this->formType(['permite_inscripcion_grupal' => true, 'max_integrantes_grupo' => 5]);
        $this->postJson('/api/v1/registrations', $this->payload($ft, $this->dos()))->assertCreated();

        $this->actingAsAdmin();
        $this->putJson('/api/v1/form-type/' . $ft->id, ['un_solo_participante' => true])->assertOk();

        $otros = [$this->participante('33333333', 'tres@test.net'), $this->participante('44444444', 'cuatro@test.net')];
        $this->actingAsPersona();
        $this->postJson('/api/v1/registrations', $this->payload($ft, $otros))->assertStatus(422);
        // Y con uno solo, ese mismo tipo sigue inscribiendo.
        $this->postJson('/api/v1/registrations', $this->payload($ft, [$this->participante('55555555', 'cinco@test.net')]))->assertCreated();
    }

    public function test_editar_una_pendiente_agregando_un_segundo_participante_da_error_con_el_flag(): void
    {
        $ft = $this->formType(['un_solo_participante' => true]);
        $this->postJson('/api/v1/registrations', $this->payload($ft, [$this->participante('11111111', 'uno@test.net')]))
            ->assertCreated();
        $registration = Registration::firstOrFail();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('un solo participante');

        app(ActualizarInscripcionAction::class)->handle($registration->referencia, [
            'participantes' => $this->dos(),
            'totales' => ['inscripcion' => 200, 'donacion' => 0, 'souvenirs' => 0, 'fee' => 10, 'descuento' => 0, 'grand_total' => 210],
        ]);
    }

    public function test_el_flag_se_guarda_y_se_expone_como_un_solo_participante(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/form-type', [
            'event_id' => $this->event->id, 'name' => 'Empresa expositora', 'icon' => '🏢', 'description' => 'x',
            'cupo_total' => 10, 'precio_base' => 50, 'costo_edicion' => 0, 'tiempo_expiracion_min' => 30,
            'color' => '#00bad2', 'un_solo_participante' => true,
        ])->assertCreated()->assertJsonPath('formType.unSoloParticipante', true);

        $ft = FormType::where('event_id', $this->event->id)->firstOrFail();
        $this->assertTrue($ft->un_solo_participante);

        $this->putJson('/api/v1/form-type/' . $ft->id, ['un_solo_participante' => false])
            ->assertOk()->assertJsonPath('formType.unSoloParticipante', false);
        $this->assertFalse($ft->refresh()->un_solo_participante);

        $this->putJson('/api/v1/form-type/' . $ft->id, ['un_solo_participante' => true])->assertOk();
        $this->getJson('/api/v1/event/' . $this->event->id)
            ->assertOk()
            ->assertJsonPath('eventos.formTypes.0.unSoloParticipante', true);
    }
}
