<?php

namespace Tests\Feature;

use App\Actions\ActualizarInscripcionAction;
use App\Actions\CrearInscripcionAction;
use App\DTOs\RegistrationDTO;
use App\Models\CajaMovimiento;
use App\Models\Category;
use App\Models\CategoryPricePeriod;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Registration;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use App\Services\RegistrationService;
use App\Support\PrecioVigenteData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Reserva de cupo con seña (21/10/2026, pedido real del usuario — ver
 * afiche "PARAPETÍ INDOMABLE"): un participante puede pagar solo una
 * reserva (seña) en vez del precio completo de su categoría, dejando el
 * resto como `registrations.saldo_pendiente` hasta que Caja lo cobre con
 * cobrarSaldo(). `pago_status` sigue siendo 'paid' desde el alta — no hay
 * un 5to estado.
 */
class ReservaCupoTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private FormType $formType;

    private Category $categoria;

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
        ]);

        $this->formType = FormType::factory()->create([
            'event_id' => $this->evento->id,
            'cupo_total' => 100,
            'activo' => true,
            'requiere_categoria' => true,
            'costo_edicion' => 10,
        ]);

        // 50K Jasy Porã del afiche: preventa 850, reserva 300.
        $this->categoria = Category::factory()->create([
            'event_id' => $this->evento->id,
            'price' => 850,
            'price_reserva' => 300,
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
            'souvenirs' => [], 'answers' => [],
            'categoria' => (string) $this->categoria->id, 'precioCategoria' => 850,
            'esReserva' => false,
            'donacion' => 0, 'promoDescuento' => 0, 'promoCodigo' => '', 'subtotal' => 850,
        ], $overrides);
    }

    // ── PrecioVigenteData::paraCategoria() ──────────────────────────────

    public function test_precio_vigente_resuelve_precio_reserva_de_la_categoria(): void
    {
        $vigente = PrecioVigenteData::paraCategoria($this->categoria);

        $this->assertSame(850.0, $vigente['precio']);
        $this->assertSame(300.0, $vigente['precio_reserva']);
    }

    public function test_precio_vigente_sin_price_reserva_devuelve_null(): void
    {
        $sinReserva = Category::factory()->create(['event_id' => $this->evento->id, 'price' => 100]);

        $vigente = PrecioVigenteData::paraCategoria($sinReserva);

        $this->assertNull($vigente['precio_reserva']);
    }

    public function test_precio_vigente_usa_la_reserva_del_periodo_vigente(): void
    {
        CategoryPricePeriod::create([
            'category_id' => $this->categoria->id,
            'nombre' => 'Último día',
            'price' => 1000,
            'price_reserva' => 400,
            'fecha_desde' => Carbon::today()->subDays(5),
            'fecha_hasta' => Carbon::today()->addDays(5),
        ]);

        $vigente = PrecioVigenteData::paraCategoria($this->categoria->fresh());

        $this->assertSame(1000.0, $vigente['precio']);
        $this->assertSame(400.0, $vigente['precio_reserva']);
    }

    public function test_precio_vigente_periodo_sin_price_reserva_cae_a_la_categoria(): void
    {
        CategoryPricePeriod::create([
            'category_id' => $this->categoria->id,
            'nombre' => 'Último día',
            'price' => 1000,
            'fecha_desde' => Carbon::today()->subDays(5),
            'fecha_hasta' => Carbon::today()->addDays(5),
        ]);

        $vigente = PrecioVigenteData::paraCategoria($this->categoria->fresh());

        $this->assertSame(1000.0, $vigente['precio']);
        $this->assertSame(300.0, $vigente['precio_reserva']); // cae a categories.price_reserva
    }

    // ── ValidarCategoriaAction / CrearInscripcionAction ─────────────────

    /**
     * Bug real 22/10/2026 (reportado por el usuario en vivo, UAT): toda
     * inscripción con reserva rechazaba con "El precio de la categoría no
     * coincide con el vigente" aunque el precio mandado fuera el correcto.
     * Causa: `StoreRegistrationRequest::rules()` nunca declaró una regla
     * para `esReserva` — `$request->validated()` descarta cualquier campo
     * sin regla, así que `ParticipantDTO` siempre lo recibía `false` sin
     * importar lo que mandara el cliente, y `ValidarCategoriaAction`
     * terminaba comparando contra el precio completo. Los tests de arriba
     * (`test_alta_con_reserva_persiste_el_saldo_pendiente` y otros) nunca
     * lo detectaron porque llaman a `CrearInscripcionAction` directo,
     * salteando la capa HTTP/FormRequest donde vivía el bug — este test sí
     * pasa por `postJson()` contra el endpoint real para cubrir ese gap.
     */
    public function test_alta_con_reserva_via_http_real_no_rechaza_por_falta_de_regla_esreserva(): void
    {
        $persona = $this->actingAsPersona();

        $response = $this->postJson('/api/v1/registrations', [[
            'referencia' => 'LA-HTTPRESERVA-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'QR',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => [
                'inscripcion' => 850, 'donacion' => 0, 'souvenirs' => 0, 'fee' => 42.5,
                'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 892.5,
            ],
            'participantes' => [$this->participanteData('10000099', [
                'polera' => 'No shirt', 'precioCategoria' => 300, 'esReserva' => true, 'subtotal' => 300,
            ])],
        ]]);

        $response->assertCreated();

        $registration = Registration::where('referencia', 'like', 'LA-HTTPRESERVA-%')->firstOrFail();
        $this->assertEquals(550.0, (float) $registration->saldo_pendiente);
    }

    public function test_alta_con_reserva_persiste_el_saldo_pendiente(): void
    {
        $registration = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-RESERVA-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'QR',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => [
                'inscripcion' => 850, 'donacion' => 0, 'souvenirs' => 0, 'fee' => 42.5,
                'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 892.5,
            ],
            'participantes' => [$this->participanteData('10000001', [
                'precioCategoria' => 300, 'esReserva' => true, 'subtotal' => 300,
            ])],
        ]));

        // grand_total (892.5) - saldo (550, = 850-300) = 342.5 cobrado ahora.
        $this->assertEquals(550.0, (float) $registration->saldo_pendiente);
        $this->assertEquals(892.5, (float) $registration->totals->grand_total);
    }

    public function test_alta_sin_reserva_deja_saldo_pendiente_en_cero(): void
    {
        $registration = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-SINRESERVA-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'QR',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => [
                'inscripcion' => 850, 'donacion' => 0, 'souvenirs' => 0, 'fee' => 42.5,
                'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 892.5,
            ],
            'participantes' => [$this->participanteData('10000002')],
        ]));

        $this->assertEquals(0.0, (float) $registration->saldo_pendiente);
    }

    public function test_reserva_rechaza_categoria_que_no_admite_reserva(): void
    {
        $sinReserva = Category::factory()->create(['event_id' => $this->evento->id, 'price' => 100]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('no admite reserva');

        app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-RECHAZO-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'QR',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => [
                'inscripcion' => 100, 'donacion' => 0, 'souvenirs' => 0, 'fee' => 5,
                'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 105,
            ],
            'participantes' => [$this->participanteData('10000003', [
                'categoria' => (string) $sinReserva->id, 'precioCategoria' => 30, 'esReserva' => true, 'subtotal' => 30,
            ])],
        ]));
    }

    public function test_reserva_rechaza_un_monto_que_no_coincide_con_el_precio_de_reserva_vigente(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('precio de reserva no coincide');

        app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-RECHAZO2-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'QR',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => [
                'inscripcion' => 850, 'donacion' => 0, 'souvenirs' => 0, 'fee' => 42.5,
                'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 892.5,
            ],
            'participantes' => [$this->participanteData('10000004', [
                'precioCategoria' => 999, 'esReserva' => true, 'subtotal' => 999,
            ])],
        ]));
    }

    // ── RegistrationService::updatePaymentStatus() ──────────────────────

    public function test_confirmar_pago_con_saldo_pendiente_deja_rastro_de_la_reserva(): void
    {
        $registration = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-RASTRO-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'QR',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => [
                'inscripcion' => 850, 'donacion' => 0, 'souvenirs' => 0, 'fee' => 42.5,
                'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 892.5,
            ],
            'participantes' => [$this->participanteData('50000001', [
                'precioCategoria' => 300, 'esReserva' => true, 'subtotal' => 300,
            ])],
        ]));

        app(RegistrationService::class)->updatePaymentStatus($registration->referencia, 'paid');

        $registration->refresh();
        $this->assertEquals(342.5, (float) $registration->monto_reserva_cobrado);
        $this->assertNotNull($registration->reserva_pagada_at);
        // saldo_pendiente no se toca al confirmar el pago de la reserva.
        $this->assertEquals(550.0, (float) $registration->saldo_pendiente);
    }

    public function test_confirmar_pago_sin_saldo_pendiente_no_setea_rastro_de_reserva(): void
    {
        $registration = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-SINRASTRO-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'QR',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => [
                'inscripcion' => 850, 'donacion' => 0, 'souvenirs' => 0, 'fee' => 42.5,
                'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 892.5,
            ],
            'participantes' => [$this->participanteData('50000002')],
        ]));

        app(RegistrationService::class)->updatePaymentStatus($registration->referencia, 'paid');

        $registration->refresh();
        $this->assertNull($registration->monto_reserva_cobrado);
        $this->assertNull($registration->reserva_pagada_at);
    }

    /**
     * Bug real encontrado al implementar (21/10/2026): cobrarPendiente()
     * siempre cobra el grand_total completo (Caja nunca ofrece reserva
     * presencial) — si Caja termina cobrando entera una inscripción que
     * había nacido 'pending' con saldo_pendiente>0 (reserva online que
     * nunca se confirmó por SIP), no debe quedar un saldo fantasma.
     */
    public function test_cobrar_pendiente_en_caja_limpia_un_saldo_pendiente_fantasma(): void
    {
        $this->actingAsCajero();
        $this->postJson("/api/v1/event/{$this->evento->id}/caja/turno/abrir", ['fondo_inicial' => 0]);

        $registration = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-FANTASMA-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => [
                'inscripcion' => 850, 'donacion' => 0, 'souvenirs' => 0, 'fee' => 42.5,
                'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 892.5,
            ],
            'participantes' => [$this->participanteData('60000001', [
                'precioCategoria' => 300, 'esReserva' => true, 'subtotal' => 300,
            ])],
        ]));
        $this->assertEquals(550.0, (float) $registration->saldo_pendiente);

        $this->postJson("/api/v1/registrations/{$registration->referencia}/caja/cobrar-pendiente")
            ->assertStatus(200);

        $this->assertDatabaseHas('registrations', [
            'id' => $registration->id, 'pago_status' => 'paid', 'saldo_pendiente' => 0,
        ]);
        $this->assertDatabaseHas('caja_movimientos', [
            'registration_id' => $registration->id, 'tipo' => 'cobro_pendiente', 'monto' => 892.5,
        ]);
    }

    // ── Caja: cobrarSaldo() / editarPagada() ────────────────────────────

    private function actingAsCajero(): \App\Models\AdminUser
    {
        $cajero = $this->actingAsAdmin();
        $cajero->update(['rol' => 'cajero', 'evento_id' => $this->evento->id]);

        return $cajero;
    }

    private function crearInscripcionConReserva(string $documento): Registration
    {
        return app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-CAJA-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'QR',
            'pago_status' => 'paid',
            'pay_order_number' => null,
            'totales' => [
                'inscripcion' => 850, 'donacion' => 0, 'souvenirs' => 0, 'fee' => 42.5,
                'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 892.5,
            ],
            'participantes' => [$this->participanteData($documento, [
                'precioCategoria' => 300, 'esReserva' => true, 'subtotal' => 300,
            ])],
        ]));
    }

    public function test_caja_cobra_el_saldo_pendiente(): void
    {
        $this->actingAsCajero();
        $this->postJson("/api/v1/event/{$this->evento->id}/caja/turno/abrir", ['fondo_inicial' => 0]);

        $registration = $this->crearInscripcionConReserva('20000001');

        $this->postJson("/api/v1/registrations/{$registration->referencia}/caja/cobrar-saldo", [
            'metodo_pago' => 'EFECTIVO',
        ])->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('registrations', ['id' => $registration->id, 'saldo_pendiente' => 0, 'pago_status' => 'paid']);
        $this->assertDatabaseHas('caja_movimientos', [
            'registration_id' => $registration->id,
            'tipo' => 'cobro_saldo',
            'monto' => 550,
        ]);
    }

    public function test_caja_rechaza_cobrar_saldo_si_no_hay_saldo_pendiente(): void
    {
        $this->actingAsCajero();
        $this->postJson("/api/v1/event/{$this->evento->id}/caja/turno/abrir", ['fondo_inicial' => 0]);

        $registration = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-SINSALDO-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'QR',
            'pago_status' => 'paid',
            'pay_order_number' => null,
            'totales' => [
                'inscripcion' => 850, 'donacion' => 0, 'souvenirs' => 0, 'fee' => 42.5,
                'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 892.5,
            ],
            'participantes' => [$this->participanteData('20000002')],
        ]));

        $this->postJson("/api/v1/registrations/{$registration->referencia}/caja/cobrar-saldo")
            ->assertStatus(422)->assertJsonFragment(['error' => 'Esta inscripción no tiene saldo pendiente.']);
    }

    public function test_caja_no_puede_cobrar_saldo_sin_turno_abierto(): void
    {
        $this->actingAsCajero();

        $registration = $this->crearInscripcionConReserva('20000003');

        $this->postJson("/api/v1/registrations/{$registration->referencia}/caja/cobrar-saldo")
            ->assertStatus(422)->assertJsonFragment(['error' => 'Abrí un turno de caja antes de cobrar.']);
    }

    public function test_editar_pagada_se_bloquea_mientras_hay_saldo_pendiente(): void
    {
        $this->actingAsCajero();
        $this->postJson("/api/v1/event/{$this->evento->id}/caja/turno/abrir", ['fondo_inicial' => 0]);

        $registration = $this->crearInscripcionConReserva('20000004');

        $this->patchJson("/api/v1/registrations/{$registration->referencia}/caja/editar-pagada", [
            'confirmacion' => true,
            'participantes' => [$this->participanteData('20000004', [
                'precioCategoria' => 300, 'esReserva' => true, 'subtotal' => 300,
            ])],
            'totales' => [
                'inscripcion' => 850, 'donacion' => 0, 'souvenirs' => 0, 'fee' => 42.5,
                'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 892.5,
            ],
        ])->assertStatus(422)->assertJsonFragment(['error' => 'Esta inscripción tiene un saldo pendiente — cobralo antes de editarla.']);
    }

    // ── ActualizarInscripcionAction (edición de una pendiente) ──────────

    public function test_editar_inscripcion_pendiente_recalcula_el_saldo_pendiente(): void
    {
        $registration = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-EDITPEND-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => [
                'inscripcion' => 850, 'donacion' => 0, 'souvenirs' => 0, 'fee' => 42.5,
                'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 892.5,
            ],
            'participantes' => [$this->participanteData('30000001')],
        ]));
        $this->assertEquals(0.0, (float) $registration->saldo_pendiente);

        app(ActualizarInscripcionAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('30000001', [
                'precioCategoria' => 300, 'esReserva' => true, 'subtotal' => 300,
            ])],
            'totales' => [
                'inscripcion' => 850, 'donacion' => 0, 'souvenirs' => 0, 'fee' => 42.5,
                'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 892.5,
            ],
        ]);

        $this->assertEquals(550.0, (float) $registration->fresh()->saldo_pendiente);
    }

    // ── Reportes: no sobrestimar lo recaudado ───────────────────────────

    public function test_balance_evento_resta_el_saldo_pendiente_de_lo_recaudado(): void
    {
        $this->crearInscripcionConReserva('40000001');

        $balance = \App\Support\BalanceEventoData::paraEvento($this->evento);

        // inscripcion(850) - saldo_pendiente(550) = 300 — BalanceEventoData
        // no incluye el fee en este neto (le queda al organizador sin el
        // service fee, ver docblock de la clase).
        $this->assertEquals(300.0, $balance['ingresosInscripciones']);
    }

    public function test_liquidar_evento_excluye_inscripciones_con_saldo_pendiente(): void
    {
        \App\Models\Socio::create(['nombre' => 'Socio Test', 'porcentaje' => 100, 'activo' => true]);
        $this->crearInscripcionConReserva('40000002');

        $calculo = app(\App\Actions\LiquidarEventoAction::class)->calcular($this->evento);

        $this->assertEquals(0.0, $calculo['monto_base']);
        $this->assertEquals(0, $calculo['cantidad_inscripciones']);
        $this->assertEquals(1, $calculo['excluidas_por_saldo_pendiente']);
    }
}
