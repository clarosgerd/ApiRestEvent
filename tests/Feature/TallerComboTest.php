<?php

namespace Tests\Feature;

use App\Actions\ActualizarInscripcionAction;
use App\Actions\ActualizarInscripcionPagadaAction;
use App\Actions\CrearInscripcionAction;
use App\DTOs\RegistrationDTO;
use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\SesionCongreso;
use App\Models\SubtipoEvento;
use App\Models\Taller;
use App\Models\TallerCombo;
use App\Models\TallerComboPrecio;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Combos de talleres (12/10/2026, pedido del usuario) — cubre el punto más
 * delicado del plan: un combo nunca se aplica retroactivo sobre un taller
 * ya persistido (pagado o no) de una edición anterior. Ver
 * ResolverCombosTallerAction (matching/reparto) y
 * RegistrationService::createParticipantFromData() (snapshot-lock).
 */
class TallerComboTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private FormType $formType;

    private Category $categoria;

    private Taller $tallerA;

    private Taller $tallerB;

    private SesionCongreso $sesionA;

    private SesionCongreso $sesionB;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

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
            'fee_pct' => 0.05,
            'talleres_con_costo' => true,
        ]);

        $this->formType = FormType::factory()->create([
            'event_id' => $this->evento->id,
            'cupo_total' => 100,
            'activo' => true,
            'requiere_categoria' => true,
            'costo_edicion' => 0,
        ]);

        $this->categoria = Category::factory()->create([
            'event_id' => $this->evento->id,
            'price' => 50,
        ]);

        $this->tallerA = Taller::factory()->create([
            'evento_id' => $this->evento->id, 'modalidad' => 'OPTIONAL', 'precio' => 100,
        ]);
        $this->tallerB = Taller::factory()->create([
            'evento_id' => $this->evento->id, 'modalidad' => 'OPTIONAL', 'precio' => 100,
        ]);
        $this->sesionA = SesionCongreso::factory()->create([
            'evento_id' => $this->evento->id, 'taller_id' => $this->tallerA->id, 'cupo' => 10,
            'hora_inicio' => '09:00:00', 'hora_fin' => '10:00:00',
        ]);
        $this->sesionB = SesionCongreso::factory()->create([
            'evento_id' => $this->evento->id, 'taller_id' => $this->tallerB->id, 'cupo' => 10,
            // Horario distinto al de A — sin esto, ValidarSeleccionesTallerAction
            // rechaza la combinación por solape antes de llegar al combo.
            'hora_inicio' => '11:00:00', 'hora_fin' => '12:00:00',
        ]);

        $combo = TallerCombo::create([
            'evento_id' => $this->evento->id,
            'nombre' => 'Combo A+B',
            'activo' => true,
        ]);
        $combo->talleres()->sync([$this->tallerA->id, $this->tallerB->id]);
        // Precio por categoría (13/10/2026) — fila default (sin category_id),
        // aplica a cualquier categoría que no tenga su propia fila. La
        // mayoría de los tests de este archivo no les importa la categoría
        // en sí, solo el matching/snapshot-lock de talleres.
        TallerComboPrecio::create(['taller_combo_id' => $combo->id, 'precio' => 100]);
    }

    private function participanteData(string $numeroDocumento, array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'alias' => '', 'genero' => 'Femenino',
            'tipoDocumento' => 'DNI', 'numeroDocumento' => $numeroDocumento,
            'polera' => '', 'precioPolera' => 0,
            'nacimiento' => ['dia' => 1, 'mes' => 1, 'anio' => 1995], 'edad' => 30,
            'correo' => 'ana' . rand(1, 999999) . '@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'contacto_emergencia' => ['nombre' => 'X', 'celular' => '123', 'relacion' => 'Madre'],
            'souvenirs' => [], 'answers' => [], 'talleres' => [],
            'categoria' => (string) $this->categoria->id, 'precioCategoria' => 50,
            'donacion' => 0, 'promoDescuento' => 0, 'promoCodigo' => '', 'subtotal' => 50,
        ], $overrides);
    }

    private function totalesData(array $overrides = []): array
    {
        return array_merge([
            'inscripcion' => 50, 'donacion' => 0, 'souvenirs' => 0, 'talleres' => 0, 'fee' => 2.5,
            'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 52.5,
        ], $overrides);
    }

    private function crearInscripcionPagadaSinTaller(string $numeroDocumento): \App\Models\Registration
    {
        $registration = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-COMBO-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => $this->totalesData(),
            'participantes' => [$this->participanteData($numeroDocumento)],
        ]));
        $registration->update(['pago_status' => 'paid']);

        return $registration;
    }

    public function test_alta_nueva_con_los_2_talleres_del_combo_cobra_el_precio_combo(): void
    {
        $registration = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-COMBO-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => $this->totalesData(['talleres' => 100, 'fee' => 7.5, 'grand_total' => 157.5]),
            'participantes' => [$this->participanteData('30000001', [
                'talleres' => [
                    ['taller_id' => $this->tallerA->id, 'sesion_congreso_id' => $this->sesionA->id],
                    ['taller_id' => $this->tallerB->id, 'sesion_congreso_id' => $this->sesionB->id],
                ],
            ])],
        ]));

        $participante = $registration->participants()->first();
        $this->assertEquals(100.0, $participante->talleresSesiones->sum('total'));
        $this->assertNotNull($participante->talleresSesiones->first()->taller_combo_id);
    }

    public function test_caja_agrega_los_2_talleres_juntos_en_la_misma_edicion_aplica_el_combo(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('30000002');

        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('30000002', [
                'talleres' => [
                    ['taller_id' => $this->tallerA->id, 'sesion_congreso_id' => $this->sesionA->id],
                    ['taller_id' => $this->tallerB->id, 'sesion_congreso_id' => $this->sesionB->id],
                ],
            ])],
            'totales' => $this->totalesData(['talleres' => 100, 'fee' => 7.5, 'grand_total' => 157.5]),
            '_usuario' => 'cajero@test.net',
        ], modoCategoria: 'libre');

        $participante = $registration->participants()->first();
        $this->assertEquals(100.0, $participante->talleresSesiones->sum('total'));
    }

    /**
     * El caso crítico del plan: Taller A ya pagado de una edición anterior
     * + Taller B agregado ahora — aunque juntos formarían el combo, NO se
     * aplica. A conserva su total viejo intacto, B entra a precio normal.
     */
    public function test_combo_no_se_aplica_retroactivo_sobre_un_taller_ya_pagado(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('30000003');

        // 1ra edición: solo el Taller A, a precio normal (100).
        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('30000003', [
                'talleres' => [['taller_id' => $this->tallerA->id, 'sesion_congreso_id' => $this->sesionA->id]],
            ])],
            'totales' => $this->totalesData(['talleres' => 100, 'fee' => 7.5, 'grand_total' => 157.5]),
            '_usuario' => 'cajero@test.net',
        ], modoCategoria: 'libre');

        $totalAAntes = $registration->participants()->first()->talleresSesiones()->sum('total');
        $this->assertEquals(100.0, $totalAAntes);

        // 2da edición (días después): se agrega el Taller B. Si el combo
        // se aplicara retroactivo, A+B costarían 100 en total — NO debe
        // pasar: A sigue en 100, B entra normal en 100 (200 en total).
        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('30000003', [
                'talleres' => [
                    ['taller_id' => $this->tallerA->id, 'sesion_congreso_id' => $this->sesionA->id],
                    ['taller_id' => $this->tallerB->id, 'sesion_congreso_id' => $this->sesionB->id],
                ],
            ])],
            'totales' => $this->totalesData(['talleres' => 200, 'fee' => 12.5, 'grand_total' => 262.5]),
            '_usuario' => 'cajero@test.net',
        ], modoCategoria: 'libre');

        $participante = $registration->participants()->first();
        $this->assertEquals(200.0, $participante->talleresSesiones->sum('total'));

        $filaA = $participante->talleresSesiones->firstWhere('sesion_congreso_id', $this->sesionA->id);
        $filaB = $participante->talleresSesiones->firstWhere('sesion_congreso_id', $this->sesionB->id);
        $this->assertEquals(100.0, $filaA->total);
        $this->assertEquals(100.0, $filaB->total);
        $this->assertNull($filaA->taller_combo_id);
        $this->assertNull($filaB->taller_combo_id);
    }

    /**
     * Inscripción PENDIENTE (nada cobrado todavía) — acá sí se puede
     * recalcular libremente: agregar el 2do taller en una edición
     * posterior SÍ activa el combo, porque no hay nada "ya pagado" que
     * proteger.
     */
    public function test_inscripcion_pendiente_si_aplica_el_combo_al_completar_la_seleccion_despues(): void
    {
        $registration = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-COMBO-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => $this->totalesData(['talleres' => 100, 'fee' => 7.5, 'grand_total' => 157.5]),
            'participantes' => [$this->participanteData('30000004', [
                'talleres' => [['taller_id' => $this->tallerA->id, 'sesion_congreso_id' => $this->sesionA->id]],
            ])],
        ]));

        app(ActualizarInscripcionAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('30000004', [
                'talleres' => [
                    ['taller_id' => $this->tallerA->id, 'sesion_congreso_id' => $this->sesionA->id],
                    ['taller_id' => $this->tallerB->id, 'sesion_congreso_id' => $this->sesionB->id],
                ],
            ])],
            'totales' => $this->totalesData(['talleres' => 100, 'fee' => 7.5, 'grand_total' => 157.5]),
        ]);

        $participante = $registration->participants()->first();
        $this->assertEquals(100.0, $participante->talleresSesiones->sum('total'));
    }

    // ── Autoservicio (13/10/2026, pedido del usuario) ──────────────────

    public function test_autoservicio_agrega_los_2_talleres_del_combo_juntos_y_recibe_el_precio_combo(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('30000005');

        // Defaults de ActualizarInscripcionPagadaAction = autoservicio
        // (modoCategoria='solo_subida', permiteQuitarTalleres=false).
        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('30000005', [
                'talleres' => [
                    ['taller_id' => $this->tallerA->id, 'sesion_congreso_id' => $this->sesionA->id],
                    ['taller_id' => $this->tallerB->id, 'sesion_congreso_id' => $this->sesionB->id],
                ],
            ])],
            'totales' => $this->totalesData(['talleres' => 100, 'fee' => 7.5, 'grand_total' => 157.5]),
            '_usuario' => 'participante@test.net',
        ]);

        $participante = $registration->participants()->first();
        $this->assertEquals(100.0, $participante->talleresSesiones->sum('total'));
    }

    public function test_autoservicio_agrega_el_segundo_taller_del_combo_a_precio_normal_sin_devolucion(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('30000006');

        // 1ra edición (autoservicio): solo el Taller A, precio normal.
        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('30000006', [
                'talleres' => [['taller_id' => $this->tallerA->id, 'sesion_congreso_id' => $this->sesionA->id]],
            ])],
            'totales' => $this->totalesData(['talleres' => 100, 'fee' => 7.5, 'grand_total' => 157.5]),
            '_usuario' => 'participante@test.net',
        ]);

        // 2da edición (autoservicio): agrega el Taller B sin tocar el A ya
        // pagado — no recibe el combo (sin devoluciones desde autoservicio).
        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('30000006', [
                'talleres' => [
                    ['taller_id' => $this->tallerA->id, 'sesion_congreso_id' => $this->sesionA->id],
                    ['taller_id' => $this->tallerB->id, 'sesion_congreso_id' => $this->sesionB->id],
                ],
            ])],
            'totales' => $this->totalesData(['talleres' => 200, 'fee' => 12.5, 'grand_total' => 262.5]),
            '_usuario' => 'participante@test.net',
        ]);

        $participante = $registration->participants()->first();
        $this->assertEquals(200.0, $participante->talleresSesiones->sum('total'));
    }

    public function test_autoservicio_no_puede_cambiar_talleres_ya_pagados_por_los_del_combo(): void
    {
        $tallerC = Taller::factory()->create([
            'evento_id' => $this->evento->id, 'modalidad' => 'OPTIONAL', 'precio' => 60,
        ]);
        $sesionC = SesionCongreso::factory()->create([
            'evento_id' => $this->evento->id, 'taller_id' => $tallerC->id, 'cupo' => 10,
            'fecha' => now()->addDays(5)->toDateString(), 'hora_inicio' => '09:00:00', 'hora_fin' => '10:00:00',
        ]);
        $registration = $this->crearInscripcionPagadaSinTaller('30000007');

        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('30000007', [
                'talleres' => [['taller_id' => $tallerC->id, 'sesion_congreso_id' => $sesionC->id]],
            ])],
            'totales' => $this->totalesData(['talleres' => 60, 'fee' => 5.5, 'grand_total' => 115.5]),
            '_usuario' => 'participante@test.net',
        ]);

        // Intenta cambiar el Taller C (ya pagado) por los 2 del combo —
        // debe bloquearse por completo, nunca "sin descuento".
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/Caja|soporte/i');

        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('30000007', [
                'talleres' => [
                    ['taller_id' => $this->tallerA->id, 'sesion_congreso_id' => $this->sesionA->id],
                    ['taller_id' => $this->tallerB->id, 'sesion_congreso_id' => $this->sesionB->id],
                ],
            ])],
            'totales' => $this->totalesData(['talleres' => 100, 'fee' => 7.5, 'grand_total' => 157.5]),
            '_usuario' => 'participante@test.net',
        ]);
    }

    // ── Precio por categoría (13/10/2026, pedido del usuario) ──────────

    public function test_combo_no_aplica_a_una_categoria_sin_precio_propio_ni_default(): void
    {
        // Reemplaza la fila default del setUp por una específica de
        // $this->categoria únicamente.
        TallerComboPrecio::query()->delete();
        $combo = TallerCombo::where('evento_id', $this->evento->id)->first();
        TallerComboPrecio::create([
            'taller_combo_id' => $combo->id, 'category_id' => $this->categoria->id, 'precio' => 100,
        ]);
        $otraCategoria = Category::factory()->create(['event_id' => $this->evento->id, 'price' => 80]);

        $registration = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-COMBO-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => $this->totalesData([
                'inscripcion' => 80, 'talleres' => 200, 'fee' => 14, 'grand_total' => 294,
            ]),
            'participantes' => [$this->participanteData('30000008', [
                'categoria' => (string) $otraCategoria->id, 'precioCategoria' => 80, 'subtotal' => 80,
                'talleres' => [
                    ['taller_id' => $this->tallerA->id, 'sesion_congreso_id' => $this->sesionA->id],
                    ['taller_id' => $this->tallerB->id, 'sesion_congreso_id' => $this->sesionB->id],
                ],
            ])],
        ]));

        $participante = $registration->participants()->first();
        // Sin combo para esta categoría: paga la suma normal (100+100=200).
        $this->assertEquals(200.0, $participante->talleresSesiones->sum('total'));
        $this->assertNull($participante->talleresSesiones->first()->taller_combo_id);
    }

    // ── Cupo (13/10/2026) — los combos nunca lo saltean ────────────────

    public function test_combo_no_saltea_la_validacion_de_cupo(): void
    {
        $this->sesionA->update(['cupo' => 1]);

        app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-COMBO-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => $this->totalesData(['talleres' => 100, 'fee' => 7.5, 'grand_total' => 157.5]),
            'participantes' => [$this->participanteData('30000009', [
                'talleres' => [
                    ['taller_id' => $this->tallerA->id, 'sesion_congreso_id' => $this->sesionA->id],
                    ['taller_id' => $this->tallerB->id, 'sesion_congreso_id' => $this->sesionB->id],
                ],
            ])],
        ]));

        // El cupo de la sesión A ya se agotó con el participante anterior —
        // un segundo participante no puede entrar al combo saltando el cupo.
        $this->expectException(\DomainException::class);

        app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-COMBO-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => $this->totalesData(['talleres' => 100, 'fee' => 7.5, 'grand_total' => 157.5]),
            'participantes' => [$this->participanteData('30000010', [
                'talleres' => [
                    ['taller_id' => $this->tallerA->id, 'sesion_congreso_id' => $this->sesionA->id],
                    ['taller_id' => $this->tallerB->id, 'sesion_congreso_id' => $this->sesionB->id],
                ],
            ])],
        ]));
    }
}
