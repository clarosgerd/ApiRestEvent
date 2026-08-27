<?php

namespace Tests\Feature;

use App\Actions\ActualizarInscripcionPagadaAction;
use App\Actions\CrearInscripcionAction;
use App\DTOs\RegistrationDTO;
use App\Models\CajaMovimiento;
use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Persona;
use App\Models\SesionCongreso;
use App\Models\Taller;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Agregar talleres a una inscripción pagada (autoservicio + Caja) + cambio
 * de categoría solo en Caja (25/08/2026) — ver
 * PLAN-EDICION-PAGADA-TALLERES-CATEGORIA-25082026.md. Caso real reportado:
 * personas inscritas al congreso sin haber elegido taller, que necesitan
 * agregarlo después de haber pagado.
 *
 * Antes de este cambio, ActualizarInscripcionPagadaAction cobraba siempre
 * un monto FIJO (form_types.costo_edicion) sin relación con lo que
 * realmente cambió, y no bloqueaba ni cambio de categoría ni remoción de
 * talleres ya pagados en ningún flujo.
 */
class EditarInscripcionPagadaTallerCategoriaTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private FormType $formType;

    private Category $categoria;

    private Category $categoriaCara;

    private Taller $taller;

    private SesionCongreso $sesion;

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
            // Sin esto, ResolverPrecioTallerData cobra $0 por cualquier
            // taller sin importar su precio cargado (default false).
            'talleres_con_costo' => true,
        ]);

        $this->formType = FormType::factory()->create([
            'event_id' => $this->evento->id,
            'cupo_total' => 100,
            'activo' => true,
            'requiere_categoria' => true,
            'costo_edicion' => 10,
        ]);

        $this->categoria = Category::factory()->create([
            'event_id' => $this->evento->id,
            'price' => 50,
        ]);
        $this->categoriaCara = Category::factory()->create([
            'event_id' => $this->evento->id,
            'price' => 120,
        ]);

        $this->taller = Taller::factory()->create([
            'evento_id' => $this->evento->id,
            'modalidad' => 'OPTIONAL',
            'precio' => 30,
        ]);
        $this->sesion = SesionCongreso::factory()->create([
            'evento_id' => $this->evento->id,
            'taller_id' => $this->taller->id,
            'cupo' => 10,
        ]);
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
            'referencia' => 'LA-TEST-' . uniqid(),
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

    // ── Autoservicio (permiteCambioCategoria=false, default) ──────────

    public function test_autoservicio_agrega_taller_nuevo_y_cobra_flat_mas_precio_real(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('20000001');

        $result = app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000001', [
                'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
            ])],
            'totales' => $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]),
            '_usuario' => 'participante@test.net',
        ]);

        // costo_edicion (10) + precio real del taller (30) = 40.
        $this->assertEquals(40.0, $result['costo_adicion']);
        $this->assertDatabaseHas('participante_taller_sesion', [
            'sesion_congreso_id' => $this->sesion->id,
        ]);
    }

    /**
     * Reporte de talleres confiable (27/08/2026) — cuando el participante
     * elige "pagar en el evento" (RegistrationController::updatePaid()
     * llama con requierePagoEnSitio: true), el taller nuevo queda marcado
     * pago_pendiente=true — el reporte de talleres necesita esto para no
     * contarlo como plata ya cobrada.
     */
    public function test_autoservicio_con_pago_en_sitio_marca_el_taller_nuevo_como_pendiente(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('20000010');

        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000010', [
                'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
            ])],
            'totales' => $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]),
            '_usuario' => 'participante@test.net',
        ], requierePagoEnSitio: true);

        $this->assertDatabaseHas('participante_taller_sesion', [
            'sesion_congreso_id' => $this->sesion->id,
            'pago_pendiente' => true,
        ]);
    }

    /**
     * Reverso del test anterior — Caja cobra en el momento
     * (permiteCambioCategoria: true, requierePagoEnSitio en su default
     * false), así que el taller nuevo NO queda pendiente.
     */
    public function test_caja_agrega_taller_y_no_queda_pendiente_de_cobro(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('20000011');

        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000011', [
                'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
            ])],
            'totales' => $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]),
            '_usuario' => 'cajero@test.net',
        ], permiteCambioCategoria: true);

        $this->assertDatabaseHas('participante_taller_sesion', [
            'sesion_congreso_id' => $this->sesion->id,
            'pago_pendiente' => false,
        ]);
    }

    /**
     * Un taller marcado pago_pendiente=true no debe perder esa marca en
     * una edición posterior que no lo toca — createParticipantFromData()
     * recrea la fila desde cero en cada edición (borra y vuelve a crear
     * TODOS los participantes), así que sin el snapshot/restauración
     * explícita el flag se resetearía a false en cualquier edición
     * siguiente, aunque nadie haya cobrado nada todavía.
     */
    public function test_pago_pendiente_se_mantiene_en_una_edicion_posterior_que_no_lo_toca(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('20000012');

        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000012', [
                'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
            ])],
            'totales' => $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]),
            '_usuario' => 'participante@test.net',
        ], requierePagoEnSitio: true);

        // Segunda edición (ej. Caja corrigiendo un dato personal cualquiera)
        // que manda el mismo taller sin cambios — no debe resetear el flag.
        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000012', [
                'apellido' => 'Corregida',
                'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
            ])],
            'totales' => $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]),
            '_usuario' => 'cajero@test.net',
        ], permiteCambioCategoria: true);

        $this->assertDatabaseHas('participante_taller_sesion', [
            'sesion_congreso_id' => $this->sesion->id,
            'pago_pendiente' => true,
        ]);
    }

    public function test_autoservicio_no_puede_cambiar_categoria(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('20000002');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('No se puede cambiar de categoría');

        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000002', [
                'categoria' => (string) $this->categoriaCara->id,
                'precioCategoria' => 120,
            ])],
            'totales' => $this->totalesData(['inscripcion' => 120, 'fee' => 6, 'grand_total' => 126]),
            '_usuario' => 'participante@test.net',
        ]);
    }

    public function test_ningun_flujo_puede_quitar_un_taller_ya_pagado(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('20000003');
        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000003', [
                'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
            ])],
            'totales' => $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]),
            '_usuario' => 'participante@test.net',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('No se pueden quitar talleres');

        // Reintenta editar sin mandar el taller que ya tenía.
        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000003', ['talleres' => []])],
            'totales' => $this->totalesData(),
            '_usuario' => 'participante@test.net',
        ]);
    }

    public function test_edicion_sin_cambios_de_categoria_ni_talleres_solo_cobra_el_flat(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('20000004');

        $result = app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000004', ['telefono' => '999999'])],
            'totales' => $this->totalesData(),
            '_usuario' => 'participante@test.net',
        ]);

        $this->assertEquals(10.0, $result['costo_adicion']);
    }

    // ── Caja (permiteCambioCategoria=true) ─────────────────────────────

    public function test_caja_puede_cambiar_a_categoria_mas_cara_y_cobra_diferencia_real(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('20000005');

        // precioCategoria mandado por el cliente (999) se ignora a propósito
        // — el delta se calcula contra el precio real de la BD (120).
        $result = app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000005', [
                'categoria' => (string) $this->categoriaCara->id,
                'precioCategoria' => 999,
            ])],
            'totales' => $this->totalesData(['inscripcion' => 120, 'fee' => 6, 'grand_total' => 126]),
            '_usuario' => 'cajero@test.net',
        ], permiteCambioCategoria: true);

        // costo_edicion (10) + diferencia real (120 - 50 = 70) = 80.
        $this->assertEquals(80.0, $result['costo_adicion']);
    }

    public function test_caja_puede_cambiar_a_categoria_mas_barata_y_da_costo_adicion_negativo(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('20000006');
        // Empieza en la categoría cara (120), baja a la barata (50).
        \App\Models\Participante::where('registration_id', $registration->id)
            ->update(['categoria' => (string) $this->categoriaCara->id, 'precio_categoria' => 120]);

        $result = app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000006', [
                'categoria' => (string) $this->categoria->id,
                'precioCategoria' => 50,
            ])],
            'totales' => $this->totalesData(),
            '_usuario' => 'cajero@test.net',
        ], permiteCambioCategoria: true);

        // costo_edicion (10) + diferencia real (50 - 120 = -70) = -60.
        $this->assertEquals(-60.0, $result['costo_adicion']);
    }

    public function test_caja_registra_movimiento_negativo_al_editar_pagada_con_desembolso(): void
    {
        $cajero = $this->actingAsAdmin();
        $cajero->update(['rol' => 'cajero', 'evento_id' => $this->evento->id]);
        $this->postJson("/api/v1/event/{$this->evento->id}/caja/turno/abrir", ['fondo_inicial' => 0]);

        $registration = $this->crearInscripcionPagadaSinTaller('20000007');
        \App\Models\Participante::where('registration_id', $registration->id)
            ->update(['categoria' => (string) $this->categoriaCara->id, 'precio_categoria' => 120]);

        $this->patchJson("/api/v1/registrations/{$registration->referencia}/caja/editar-pagada", [
            'confirmacion' => true,
            'participantes' => [$this->participanteData('20000007', [
                'categoria' => (string) $this->categoria->id,
                'precioCategoria' => 50,
            ])],
            'totales' => $this->totalesData(),
        ])->assertStatus(200)->assertJson(['success' => true, 'costo_adicion' => -60]);

        // Antes del fix (`> 0`), un monto negativo no generaba ningún
        // CajaMovimiento — quedaba sin registrar la devolución.
        $this->assertDatabaseHas('caja_movimientos', [
            'registration_id' => $registration->id,
            'tipo' => 'edicion_pagada',
            'monto' => -60,
            'admin_user_id' => $cajero->id,
        ]);
    }

    public function test_taller_nuevo_que_choca_con_uno_ya_pagado_rechaza(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('20000008');
        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000008', [
                'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
            ])],
            'totales' => $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]),
            '_usuario' => 'participante@test.net',
        ]);

        $tallerB = Taller::factory()->create(['evento_id' => $this->evento->id, 'modalidad' => 'OPTIONAL', 'precio' => 20]);
        $sesionSolapada = SesionCongreso::factory()->create([
            'evento_id' => $this->evento->id,
            'taller_id' => $tallerB->id,
            'cupo' => 10,
            'fecha' => $this->sesion->fecha,
            'hora_inicio' => $this->sesion->hora_inicio,
            'hora_fin' => $this->sesion->hora_fin,
        ]);

        $this->expectException(\DomainException::class);

        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000008', [
                'talleres' => [
                    ['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id],
                    ['taller_id' => $tallerB->id, 'sesion_congreso_id' => $sesionSolapada->id],
                ],
            ])],
            'totales' => $this->totalesData(['talleres' => 50, 'fee' => 5, 'grand_total' => 105]),
            '_usuario' => 'participante@test.net',
        ]);
    }

    // ── Prepoblado del taller ya elegido (bug real 26/08/2026) ─────────

    /**
     * Bug real 26/08/2026 (reportado por el usuario: "también debería
     * prepopular el taller escogido") — ni RegistrationController::mine()
     * ni RegistrationService::lookupRegistration() eager-cargaban
     * participants.talleresSesiones, así que ParticipanteResource::talleres
     * (que usa whenLoaded()) quedaba ausente del JSON — el frontend nunca
     * podía saber qué taller ya tenía el participante al reingresar por
     * "Mis inscripciones".
     */
    public function test_registrations_mine_incluye_talleres_del_participante(): void
    {
        $persona = Persona::factory()->create(['numero_documento' => '30000001']);
        $this->actingAsPersona($persona);

        $registration = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-TEST-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]),
            'participantes' => [$this->participanteData('30000001', [
                'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
            ])],
        ]));
        $registration->update(['pago_status' => 'paid']);

        $response = $this->getJson('/api/v1/registrations/mine')->assertOk();

        $talleres = $response->json('data.0.participantes.0.talleres');
        $this->assertNotEmpty($talleres, 'ParticipanteResource.talleres vino vacío/ausente — falta el eager-load.');
        $this->assertEquals($this->sesion->id, $talleres[0]['sesionCongresoId']);
    }

    public function test_lookup_registration_incluye_talleres_del_participante(): void
    {
        // CrearInscripcionAction::syncPersonas() auto-crea una Persona con
        // password = Hash::make(numero_documento) por cada participante —
        // no se crea una Persona propia acá, se usa esa (más real).
        $registration = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-TEST-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]),
            'participantes' => [$this->participanteData('30000002', [
                'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
            ])],
        ]));
        $registration->update(['pago_status' => 'paid']);

        $persona = Persona::where('numero_documento', '30000002')->firstOrFail();

        $result = app(RegistrationService::class)->lookupRegistration(
            $persona->email, '30000002', $this->evento->id, $this->formType->id
        );

        $this->assertSame('registration', $result['type']);
        $talleresSesiones = $result['data']->participants->first()->talleresSesiones;
        $this->assertNotEmpty($talleresSesiones, 'talleresSesiones vino vacío — falta el eager-load en lookupRegistration().');
        $this->assertEquals($this->sesion->id, $talleresSesiones->first()->sesion_congreso_id);
    }
}
