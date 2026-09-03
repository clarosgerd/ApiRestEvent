<?php

namespace Tests\Feature;

use App\Actions\CrearInscripcionAction;
use App\DTOs\RegistrationDTO;
use App\Mail\PagoAdicionalConfirmadoMail;
use App\Mail\PagoConfirmadoMail;
use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\PagoAdicionalInscripcion;
use App\Models\RegistrationNotification;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use App\Services\NotificacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Bug real en UAT (03/09/2026) — SQLSTATE[23000]: Duplicate entry
 * '90314-pago_confirmado-email' for key
 * registration_notifications_registration_id_tipo_canal_unique.
 *
 * Causa: `enviarEmailSiNoEnviado()` chequeaba `yaEnviado()` (SELECT) y
 * recién registraba el envío (INSERT) DESPUÉS de mandar el correo — dos
 * requests casi simultáneos para el mismo (registration_id, tipo, canal)
 * (típicamente el webhook de pago + el polling del frontend detectando
 * el mismo pago un instante después, o un reintento de webhook) podían
 * pasar el SELECT los dos ANTES de que cualquiera hiciera el INSERT — el
 * correo se mandaba DOS VECES y recién el segundo INSERT crasheaba,
 * demasiado tarde para evitar el duplicado.
 *
 * Fix: reservarNotificacion() usa insertOrIgnore() (email/WhatsApp,
 * tabla con UNIQUE) o un UPDATE condicional (pago adicional, columna
 * simple) como mutex atómico a nivel de BD — se reserva el lugar ANTES
 * de mandar nada, no después.
 */
class NotificacionServiceConcurrenciaTest extends TestCase
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
        ]);

        $this->formType = FormType::factory()->create([
            'event_id' => $this->evento->id,
            'cupo_total' => 100,
            'activo' => true,
            'requiere_categoria' => true,
        ]);

        $this->categoria = Category::factory()->create([
            'event_id' => $this->evento->id,
            'price' => 50,
        ]);
    }

    private function participanteData(string $numeroDocumento): array
    {
        return [
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'alias' => '', 'genero' => 'Femenino',
            'tipoDocumento' => 'DNI', 'numeroDocumento' => $numeroDocumento,
            'polera' => '', 'precioPolera' => 0,
            'nacimiento' => ['dia' => 1, 'mes' => 1, 'anio' => 1995], 'edad' => 30,
            'correo' => 'ana' . rand(1, 999999) . '@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'contacto_emergencia' => ['nombre' => 'X', 'celular' => '123', 'relacion' => 'Madre'],
            'souvenirs' => [], 'answers' => [], 'talleres' => [],
            'categoria' => (string) $this->categoria->id, 'precioCategoria' => 50,
            'donacion' => 0, 'promoDescuento' => 0, 'promoCodigo' => '', 'subtotal' => 50,
        ];
    }

    private function crearInscripcionPagada(string $numeroDocumento): \App\Models\Registration
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
            'totales' => ['inscripcion' => 50, 'donacion' => 0, 'souvenirs' => 0, 'talleres' => 0, 'fee' => 2.5, 'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 52.5],
            'participantes' => [$this->participanteData($numeroDocumento)],
        ]));
        $registration->update(['pago_status' => 'paid']);

        return $registration;
    }

    public function test_notificar_pago_confirmado_reserva_y_envia_normalmente(): void
    {
        $registration = $this->crearInscripcionPagada('50000001');
        // crearInscripcionPagada() ya crea la fila via el alta (pending) +
        // update manual a paid, sin pasar por updatePaymentStatus() —
        // este test llama la notificación directo para aislar el
        // escenario.
        RegistrationNotification::where('registration_id', $registration->id)->delete();

        app(NotificacionService::class)->notificarPagoConfirmado($registration);

        Mail::assertSent(PagoConfirmadoMail::class, 1);
        $this->assertDatabaseHas('registration_notifications', [
            'registration_id' => $registration->id,
            'tipo' => 'pago_confirmado',
            'canal' => 'email',
        ]);
    }

    /**
     * Reproduce la manifestación determinística del bug real: la
     * reserva YA existe (equivalente a "otro request ganó la carrera")
     * — no debe mandar el correo ni crashear.
     */
    public function test_notificar_pago_confirmado_no_manda_ni_crashea_si_ya_estaba_reservado(): void
    {
        $registration = $this->crearInscripcionPagada('50000002');
        RegistrationNotification::where('registration_id', $registration->id)->delete();
        RegistrationNotification::create([
            'registration_id' => $registration->id,
            'tipo' => 'pago_confirmado',
            'canal' => 'email',
            'enviado_at' => now(),
        ]);

        app(NotificacionService::class)->notificarPagoConfirmado($registration);

        Mail::assertNotSent(PagoConfirmadoMail::class);
        $this->assertEquals(1, RegistrationNotification::where('registration_id', $registration->id)
            ->where('tipo', 'pago_confirmado')->where('canal', 'email')->count());
    }

    public function test_notificar_pago_adicional_confirmado_no_manda_ni_crashea_si_ya_estaba_notificado(): void
    {
        $registration = $this->crearInscripcionPagada('50000003');
        $pago = PagoAdicionalInscripcion::create([
            'registration_id' => $registration->id,
            'referencia' => 'AD-' . strtoupper(uniqid()),
            'monto' => 10,
            'moneda_pago' => 'BOB',
            'participantes_payload' => [],
            'totales_payload' => [],
            'pago_status' => 'paid',
            'paid_at' => now(),
            'notificado_at' => now(), // ya notificado — simula la carrera ganada por otro request
        ]);

        app(NotificacionService::class)->notificarPagoAdicionalConfirmado($pago);

        Mail::assertNotSent(PagoAdicionalConfirmadoMail::class);
    }
}
