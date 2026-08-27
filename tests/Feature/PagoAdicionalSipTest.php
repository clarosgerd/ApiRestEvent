<?php

namespace Tests\Feature;

use App\Actions\ConfirmarPagoAdicionalAction;
use App\Actions\CrearInscripcionAction;
use App\Actions\ExpirarPagosAdicionalesAction;
use App\Actions\GenerarPagoAdicionalAction;
use App\DTOs\RegistrationDTO;
use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\PagoAdicionalInscripcion;
use App\Models\SesionCongreso;
use App\Models\Taller;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Cobro real por SIP del monto adicional al agregar un taller a una
 * inscripción pagada (26/08/2026) — ver PLAN-COBRO-SIP-ADICIONAL-26082026.md.
 *
 * El diseño clave a verificar: el taller NO se agrega a la inscripción
 * hasta que el pago adicional queda 'paid' — si nunca se paga (se
 * abandona, se cae la conexión), no debe quedar ningún rastro en la
 * inscripción real ni en participante_taller_sesion.
 */
class PagoAdicionalSipTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private FormType $formType;

    private Category $categoria;

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

        $this->taller = Taller::factory()->create([
            'evento_id' => $this->evento->id,
            'modalidad' => 'OPTIONAL',
            'precio' => 30,
        ]);
        $this->sesion = SesionCongreso::factory()->create([
            'evento_id' => $this->evento->id,
            'taller_id' => $this->taller->id,
            'cupo' => 1, // a propósito chico, para el test de cupo lleno
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

    public function test_generar_pago_adicional_no_toca_la_inscripcion_todavia(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('40000001');

        $participantes = [$this->participanteData('40000001', [
            'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
        ])];
        $totales = $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]);

        $pago = app(GenerarPagoAdicionalAction::class)->handle($registration, $participantes, $totales, 40.0);

        $this->assertEquals('pending', $pago->pago_status);
        $this->assertStringStartsWith('AD-', $pago->referencia);
        $this->assertNotEquals($registration->referencia, $pago->referencia);
        // Todavía nada agregado a la inscripción real.
        $this->assertDatabaseMissing('participante_taller_sesion', [
            'sesion_congreso_id' => $this->sesion->id,
        ]);
    }

    public function test_confirmar_pago_adicional_agrega_el_taller_recien_ahi(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('40000002');

        $participantes = [$this->participanteData('40000002', [
            'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
        ])];
        $totales = $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]);

        $pago = app(GenerarPagoAdicionalAction::class)->handle($registration, $participantes, $totales, 40.0);
        $this->assertDatabaseMissing('participante_taller_sesion', ['sesion_congreso_id' => $this->sesion->id]);

        $result = app(ConfirmarPagoAdicionalAction::class)->handle($pago->referencia);

        $this->assertEquals('paid', $result['pago']->pago_status);
        $this->assertNotNull($result['pago']->paid_at);
        $this->assertDatabaseHas('participante_taller_sesion', [
            'sesion_congreso_id' => $this->sesion->id,
        ]);
    }

    public function test_confirmar_pago_adicional_es_idempotente(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('40000003');
        $participantes = [$this->participanteData('40000003', [
            'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
        ])];
        $totales = $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]);
        $pago = app(GenerarPagoAdicionalAction::class)->handle($registration, $participantes, $totales, 40.0);

        app(ConfirmarPagoAdicionalAction::class)->handle($pago->referencia);
        // SIP reintenta el callback — no debe duplicar el taller ni fallar.
        $result = app(ConfirmarPagoAdicionalAction::class)->handle($pago->referencia);

        $this->assertEquals('paid', $result['pago']->pago_status);
        $this->assertEquals(1, \App\Models\ParticipanteTallerSesion::where('sesion_congreso_id', $this->sesion->id)->count());
    }

    public function test_si_el_cupo_se_llena_antes_de_confirmar_el_pago_queda_en_error_sin_tocar_la_inscripcion(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('40000004');
        $participantes = [$this->participanteData('40000004', [
            'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
        ])];
        $totales = $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]);
        $pago = app(GenerarPagoAdicionalAction::class)->handle($registration, $participantes, $totales, 40.0);

        // Otro participante ocupa el único cupo mientras el primero todavía
        // no confirmó el pago SIP.
        $otraInscripcion = $this->crearInscripcionPagadaSinTaller('40000005');
        app(\App\Actions\ActualizarInscripcionPagadaAction::class)->handle($otraInscripcion->referencia, [
            'participantes' => [$this->participanteData('40000005', [
                'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
            ])],
            'totales' => $totales,
            '_usuario' => 'test@test.net',
        ]);

        $this->expectException(\DomainException::class);
        try {
            app(ConfirmarPagoAdicionalAction::class)->handle($pago->referencia);
        } finally {
            $this->assertEquals('error', $pago->fresh()->pago_status);
            // La inscripción original del primer participante sigue sin el taller.
            $this->assertDatabaseMissing('participante_taller_sesion', [
                'participante_id' => $registration->participants->first()->id,
                'sesion_congreso_id' => $this->sesion->id,
            ]);
        }
    }

    public function test_expirar_pagos_adicionales_marca_expired_sin_tocar_nada(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('40000006');
        $participantes = [$this->participanteData('40000006', [
            'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
        ])];
        $totales = $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]);
        $pago = app(GenerarPagoAdicionalAction::class)->handle($registration, $participantes, $totales, 40.0);
        // created_at no está en $fillable (no debería estarlo en
        // producción) — forceFill() para simular una fila vieja en el test.
        $pago->forceFill(['created_at' => now()->subHours(2)])->save();

        $expirados = app(ExpirarPagosAdicionalesAction::class)->handle();

        $this->assertEquals(1, $expirados);
        $this->assertEquals('expired', $pago->fresh()->pago_status);
        $this->assertDatabaseMissing('participante_taller_sesion', ['sesion_congreso_id' => $this->sesion->id]);
    }

    public function test_el_monto_nunca_se_confia_del_cliente_se_recalcula_server_side(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('40000008');

        $response = $this->postJson("/api/v1/registrations/{$registration->referencia}/pagos-adicionales", [
            'confirmacion' => true,
            'participantes' => [$this->participanteData('40000008', [
                'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
            ])],
            'totales' => $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]),
            // Intento de manipular el monto — se ignora por completo.
            'monto' => 1,
        ])->assertCreated();

        // costo_edicion (10) + precio real del taller (30) = 40, no 1.
        $this->assertEquals(40.0, (float) $response->json('monto'));
        $this->assertDatabaseHas('pagos_adicionales_inscripcion', [
            'referencia' => $response->json('referencia_adicional'),
            'monto' => 40,
        ]);
    }

    /**
     * Cobro SIP del adicional no soporta eventos USD fijo todavía
     * (27/08/2026) — decisión explícita del usuario: por ahora solo se
     * permite modificar y pagar en efectivo en el evento, sin cambio
     * profundo. Ver CalcularCostoAdicionalAction::handle().
     */
    public function test_evento_usd_fijo_rechaza_el_pago_adicional_por_sip(): void
    {
        $this->evento->update(['usd_precio_fijo' => true, 'acepta_usd' => true]);
        $registration = $this->crearInscripcionPagadaSinTaller('40000009');

        $response = $this->postJson("/api/v1/registrations/{$registration->referencia}/pagos-adicionales", [
            'confirmacion' => true,
            'participantes' => [$this->participanteData('40000009', [
                'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
            ])],
            'totales' => $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]),
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
        $this->assertStringContainsString('USD', $response->json('error'));
        $this->assertDatabaseMissing('pagos_adicionales_inscripcion', [
            'registration_id' => $registration->id,
        ]);
    }

    public function test_flujo_http_completo_crear_y_confirmar_pago_adicional(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('40000007');

        $response = $this->postJson("/api/v1/registrations/{$registration->referencia}/pagos-adicionales", [
            'confirmacion' => true,
            'participantes' => [$this->participanteData('40000007', [
                'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
            ])],
            'totales' => $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]),
            'monto' => 40,
            'moneda_pago' => 'BOB',
        ])->assertCreated();

        $referenciaAdicional = $response->json('referencia_adicional');
        $this->assertStringStartsWith('AD-', $referenciaAdicional);

        $this->getJson("/api/v1/pagos-adicionales/{$referenciaAdicional}")
            ->assertOk()->assertJson(['pago_status' => 'pending']);

        $this->patchJson("/api/v1/pagos-adicionales/{$referenciaAdicional}/confirmar")
            ->assertOk()->assertJson(['success' => true, 'pago_status' => 'paid']);

        $this->assertDatabaseHas('participante_taller_sesion', ['sesion_congreso_id' => $this->sesion->id]);
    }
}
