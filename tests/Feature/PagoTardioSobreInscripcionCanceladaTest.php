<?php

namespace Tests\Feature;

use App\Models\Evento;
use App\Models\FormType;
use App\Models\Participante;
use App\Models\Registration;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Bug real (12/09/2026, caso real LA-6EF627D3, evento Naranjillo Ultra
 * Trail) — un webhook de pasarela (Multipago) llegando DESPUÉS de que
 * ExpirarInscripcionesPendientesAction ya canceló la inscripción (cupo
 * revertido + Participante purgado) terminaba marcando 'paid' una
 * inscripción sin ningún participante. Ver el fix en
 * RegistrationService::updatePaymentStatus().
 */
class PagoTardioSobreInscripcionCanceladaTest extends TestCase
{
    use RefreshDatabase;

    private function crearRegistroCancelado(string $tipoPago = 'multipago'): Registration
    {
        $evento = Evento::factory()->create(['mantener_datos_persona' => false]);
        $formType = FormType::factory()->create(['event_id' => $evento->id]);

        return Registration::factory()->create([
            'referencia'       => 'LA-' . Str::upper(Str::random(8)),
            'fecha'            => now(),
            'evento_id'        => $evento->id,
            'form_types_id'    => $formType->id,
            'evento_nombre'    => $evento->nombre,
            'tipo_pago'        => $tipoPago,
            'pago_status'      => 'cancelled',
            'pay_order_number' => '196574485',
            // Sin Participante — mismo estado que deja
            // PurgarDatosPersonaCanceladaAction después de cancelar.
        ]);
    }

    public function test_webhook_tardio_no_revive_una_inscripcion_cancelada_como_paid(): void
    {
        $registration = $this->crearRegistroCancelado();

        $resultado = app(RegistrationService::class)->updatePaymentStatus($registration->referencia, 'paid');

        $this->assertSame('cancelled', $resultado->pago_status);
        $this->assertSame('cancelled', $registration->fresh()->pago_status);
    }

    public function test_webhook_tardio_sobre_inscripcion_fallida_tampoco_la_revive(): void
    {
        $registration = $this->crearRegistroCancelado();
        $registration->update(['pago_status' => 'failed']);

        app(RegistrationService::class)->updatePaymentStatus($registration->referencia, 'paid');

        $this->assertSame('failed', $registration->fresh()->pago_status);
    }

    public function test_bloqueo_queda_registrado_en_el_log_para_reconciliacion_manual(): void
    {
        Log::spy();

        $registration = $this->crearRegistroCancelado();

        app(RegistrationService::class)->updatePaymentStatus($registration->referencia, 'paid');

        Log::shouldHaveReceived('critical')
            ->once()
            ->with('pago-tardio-sobre-inscripcion-ya-cancelada', \Mockery::on(
                fn ($contexto) => $contexto['referencia'] === $registration->referencia
                    && $contexto['estado_anterior'] === 'cancelled'
                    && $contexto['pay_order_number'] === '196574485'
            ));
    }

    public function test_transicion_normal_pending_a_paid_sigue_funcionando(): void
    {
        $evento = Evento::factory()->create();
        $formType = FormType::factory()->create(['event_id' => $evento->id]);
        $registration = Registration::factory()->create([
            'referencia'    => 'LA-' . Str::upper(Str::random(8)),
            'fecha'         => now(),
            'evento_id'     => $evento->id,
            'form_types_id' => $formType->id,
            'evento_nombre' => $evento->nombre,
            'tipo_pago'     => 'QR',
            'pago_status'   => 'pending',
        ]);

        $resultado = app(RegistrationService::class)->updatePaymentStatus($registration->referencia, 'paid');

        $this->assertSame('paid', $resultado->pago_status);
    }

    public function test_el_endpoint_de_webhook_real_no_rompe_devuelve_200_sobre_una_cancelada(): void
    {
        $registration = $this->crearRegistroCancelado();

        // Mismo endpoint que usan las pasarelas (RegistrationController::
        // updatePayment(), sin auth propia) — confirma que además del
        // guardia interno, la respuesta HTTP sigue siendo un 200 "éxito"
        // (no dispara reintentos infinitos de la pasarela por un 4xx/5xx),
        // aunque el estado real no haya cambiado.
        $response = $this->patchJson("/api/v1/registrations/{$registration->referencia}/payment", [
            'pago_status' => 'paid',
        ]);

        $response->assertOk();
        $this->assertSame('cancelled', $registration->fresh()->pago_status);
    }
}
