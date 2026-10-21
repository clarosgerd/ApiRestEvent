<?php

namespace Tests\Feature;

use App\Actions\ConfirmarPagoSaldoAction;
use App\Actions\CrearInscripcionAction;
use App\Actions\ExpirarPagosSaldoAction;
use App\Actions\GenerarPagoSaldoAction;
use App\DTOs\RegistrationDTO;
use App\Mail\PagoSaldoConfirmadoMail;
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
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Pago online del saldo pendiente — autoservicio (22/10/2026, pedido real
 * del usuario). A diferencia del pago adicional (agregar un taller), acá
 * no se edita nada al confirmarse: solo se deja
 * registrations.saldo_pendiente en 0. Fee SOLO en este canal online
 * (cubre el costo real de la pasarela) — Caja (efectivo) sigue sin fee,
 * ver ReservaCupoTest::test_caja_cobra_el_saldo_pendiente().
 */
class PagoSaldoInscripcionTest extends TestCase
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
        ]);

        // 50K Jasy Porã del afiche: preventa 850, reserva 300 → saldo 550.
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
            'categoria' => (string) $this->categoria->id, 'precioCategoria' => 300,
            'esReserva' => true,
            'donacion' => 0, 'promoDescuento' => 0, 'promoCodigo' => '', 'subtotal' => 300,
        ], $overrides);
    }

    private function crearInscripcionConReserva(string $documento): Registration
    {
        return app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-SALDO-' . uniqid(),
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
            'participantes' => [$this->participanteData($documento)],
        ]));
    }

    // ── GenerarPagoSaldoAction ───────────────────────────────────────────

    public function test_generar_pago_saldo_calcula_fee_sobre_el_saldo(): void
    {
        $registration = $this->crearInscripcionConReserva('60000001');

        $pago = app(GenerarPagoSaldoAction::class)->handle($registration);

        $this->assertEquals('pending', $pago->pago_status);
        $this->assertStringStartsWith('SA-', $pago->referencia);
        $this->assertNotEquals($registration->referencia, $pago->referencia);
        $this->assertEquals(550.0, (float) $pago->saldo);
        $this->assertEquals(27.5, (float) $pago->fee); // 550 * 5% = 27.5
        $this->assertEquals(577.5, (float) $pago->monto);
        // Nada se toca todavía en la inscripción real.
        $this->assertEquals(550.0, (float) $registration->fresh()->saldo_pendiente);
    }

    public function test_generar_pago_saldo_rechaza_si_no_hay_saldo_pendiente(): void
    {
        $registration = $this->crearInscripcionConReserva('60000002');
        $registration->update(['saldo_pendiente' => 0]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('no tiene saldo pendiente');

        app(GenerarPagoSaldoAction::class)->handle($registration);
    }

    public function test_generar_pago_saldo_rechaza_si_la_inscripcion_no_esta_pagada(): void
    {
        $registration = $this->crearInscripcionConReserva('60000003');
        $registration->update(['pago_status' => 'pending']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('inscripciones pagadas');

        app(GenerarPagoSaldoAction::class)->handle($registration->fresh());
    }

    // ── ConfirmarPagoSaldoAction ──────────────────────────────────────────

    public function test_confirmar_pago_saldo_deja_el_saldo_pendiente_en_cero(): void
    {
        $registration = $this->crearInscripcionConReserva('60000004');
        $pago = app(GenerarPagoSaldoAction::class)->handle($registration);

        $result = app(ConfirmarPagoSaldoAction::class)->handle($pago->referencia);

        $this->assertEquals('paid', $result['pago']->pago_status);
        $this->assertNotNull($result['pago']->paid_at);
        $this->assertEquals(0.0, (float) $registration->fresh()->saldo_pendiente);
        $this->assertEquals('paid', $registration->fresh()->pago_status); // sin cambios, ya era paid
    }

    public function test_confirmar_pago_saldo_es_idempotente(): void
    {
        $registration = $this->crearInscripcionConReserva('60000005');
        $pago = app(GenerarPagoSaldoAction::class)->handle($registration);

        app(ConfirmarPagoSaldoAction::class)->handle($pago->referencia);
        // SIP/Multipago reintenta el callback — no debe fallar ni duplicar nada.
        $result = app(ConfirmarPagoSaldoAction::class)->handle($pago->referencia);

        $this->assertEquals('paid', $result['pago']->pago_status);
        $this->assertEquals(0.0, (float) $registration->fresh()->saldo_pendiente);
    }

    /**
     * Mutex contra Caja (22/10/2026) — dos canales pueden cobrar el mismo
     * saldo ahora. Si Caja lo cobró MIENTRAS el pago online seguía
     * pending, confirmar el pago online no debe volver a tocar
     * saldo_pendiente (ya en 0) ni fingir éxito — el dinero ya lo cobró
     * la pasarela y no hay reembolso automático, así que queda en
     * 'error' para revisión manual.
     */
    public function test_confirmar_pago_saldo_queda_en_error_si_caja_ya_lo_cobro_mientras_tanto(): void
    {
        $registration = $this->crearInscripcionConReserva('60000006');
        $pago = app(GenerarPagoSaldoAction::class)->handle($registration);

        // Caja cobra el saldo mientras el pago online sigue pending.
        $registration->update(['saldo_pendiente' => 0]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('ya fue cobrado por otro medio');

        try {
            app(ConfirmarPagoSaldoAction::class)->handle($pago->referencia);
        } finally {
            $this->assertEquals('error', $pago->fresh()->pago_status);
            $this->assertEquals(0.0, (float) $registration->fresh()->saldo_pendiente);
        }
    }

    // ── ExpirarPagosSaldoAction ───────────────────────────────────────────

    public function test_expirar_pagos_saldo_marca_expired_sin_tocar_la_inscripcion(): void
    {
        $registration = $this->crearInscripcionConReserva('60000007');
        $pago = app(GenerarPagoSaldoAction::class)->handle($registration);
        $pago->forceFill(['created_at' => now()->subHours(2)])->save();

        $expirados = app(ExpirarPagosSaldoAction::class)->handle();

        $this->assertEquals(1, $expirados);
        $this->assertEquals('expired', $pago->fresh()->pago_status);
        $this->assertEquals(550.0, (float) $registration->fresh()->saldo_pendiente);
    }

    public function test_expirar_pagos_saldo_no_toca_uno_reciente(): void
    {
        $registration = $this->crearInscripcionConReserva('60000008');
        $pago = app(GenerarPagoSaldoAction::class)->handle($registration);

        $expirados = app(ExpirarPagosSaldoAction::class)->handle();

        $this->assertEquals(0, $expirados);
        $this->assertEquals('pending', $pago->fresh()->pago_status);
    }

    // ── Correo de confirmación ────────────────────────────────────────────

    public function test_confirmar_pago_saldo_envia_correo_de_confirmacion(): void
    {
        $registration = $this->crearInscripcionConReserva('60000009');
        $pago = app(GenerarPagoSaldoAction::class)->handle($registration);
        $correo = $registration->participants->first()->correo;

        $result = app(ConfirmarPagoSaldoAction::class)->handle($pago->referencia);

        Mail::assertSent(PagoSaldoConfirmadoMail::class, function ($mail) use ($correo, $pago) {
            return $mail->hasTo($correo) && $mail->pago->referencia === $pago->referencia;
        });
        $this->assertNotNull($result['pago']->notificado_at);
    }

    public function test_confirmar_pago_saldo_no_reenvia_correo_en_reintento(): void
    {
        $registration = $this->crearInscripcionConReserva('60000010');
        $pago = app(GenerarPagoSaldoAction::class)->handle($registration);

        app(ConfirmarPagoSaldoAction::class)->handle($pago->referencia);
        app(ConfirmarPagoSaldoAction::class)->handle($pago->referencia);

        Mail::assertSent(PagoSaldoConfirmadoMail::class, 1);
    }

    public function test_pago_saldo_en_error_no_envia_correo(): void
    {
        $registration = $this->crearInscripcionConReserva('60000011');
        $pago = app(GenerarPagoSaldoAction::class)->handle($registration);
        $registration->update(['saldo_pendiente' => 0]);

        try {
            app(ConfirmarPagoSaldoAction::class)->handle($pago->referencia);
        } catch (\DomainException $e) {
            // esperado
        }

        Mail::assertNotSent(PagoSaldoConfirmadoMail::class);
    }

    // ── Flujo HTTP completo ────────────────────────────────────────────────

    public function test_flujo_http_completo_generar_y_confirmar_pago_saldo(): void
    {
        $registration = $this->crearInscripcionConReserva('60000012');

        $response = $this->postJson("/api/v1/registrations/{$registration->referencia}/pagos-saldo")
            ->assertCreated();

        $referenciaSaldo = $response->json('referencia_saldo');
        $this->assertStringStartsWith('SA-', $referenciaSaldo);
        $this->assertEquals(550.0, (float) $response->json('saldo'));
        $this->assertEquals(27.5, (float) $response->json('fee'));
        $this->assertEquals(577.5, (float) $response->json('monto'));

        $this->getJson("/api/v1/pagos-saldo/{$referenciaSaldo}")
            ->assertOk()->assertJson(['pago_status' => 'pending']);

        $this->patchJson("/api/v1/pagos-saldo/{$referenciaSaldo}/confirmar")
            ->assertOk()->assertJson(['success' => true, 'pago_status' => 'paid']);

        $this->assertDatabaseHas('registrations', [
            'id' => $registration->id, 'saldo_pendiente' => 0, 'pago_status' => 'paid',
        ]);
    }

    public function test_http_store_rechaza_sin_saldo_pendiente(): void
    {
        $registration = $this->crearInscripcionConReserva('60000013');
        $registration->update(['saldo_pendiente' => 0]);

        $this->postJson("/api/v1/registrations/{$registration->referencia}/pagos-saldo")
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseMissing('pagos_saldo_inscripcion', ['registration_id' => $registration->id]);
    }

    public function test_show_expone_tipo_pago_original_y_desglose(): void
    {
        $registration = $this->crearInscripcionConReserva('60000014');
        $registration->update(['tipo_pago' => 'multipago']);
        $pago = app(GenerarPagoSaldoAction::class)->handle($registration->fresh());

        $this->getJson("/api/v1/pagos-saldo/{$pago->referencia}")
            ->assertOk()
            ->assertJsonPath('tipoPagoOriginal', 'multipago')
            ->assertJsonPath('saldo', 550)
            ->assertJsonPath('fee', 27.5)
            ->assertJsonPath('monto', 577.5)
            ->assertJsonPath('payOrderNumber', null);
    }

    public function test_guardar_pay_order_number_lo_persiste(): void
    {
        $registration = $this->crearInscripcionConReserva('60000015');
        $pago = app(GenerarPagoSaldoAction::class)->handle($registration);

        $this->patchJson("/api/v1/pagos-saldo/{$pago->referencia}/pay-order", [
            'pay_order_number' => '555444',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('pagos_saldo_inscripcion', [
            'referencia' => $pago->referencia, 'pay_order_number' => '555444',
        ]);
    }

    public function test_find_by_pay_order_encuentra_el_pago_de_saldo(): void
    {
        $registration = $this->crearInscripcionConReserva('60000016');
        $pago = app(GenerarPagoSaldoAction::class)->handle($registration);
        $pago->update(['pay_order_number' => '777666']);

        $this->getJson('/api/v1/pagos-saldo/by-pay-order/777666')
            ->assertOk()
            ->assertJson(['success' => true, 'referencia' => $pago->referencia]);
    }

    public function test_find_by_pay_order_404_si_no_existe(): void
    {
        $this->getJson('/api/v1/pagos-saldo/by-pay-order/no-existe')
            ->assertStatus(404);
    }
}
