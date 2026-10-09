<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsappOficialMessageJob;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Participante;
use App\Models\Registration;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use App\Models\WhatsappCuenta;
use App\Services\NotificacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * WhatsApp Business API oficial por organizador (08/10/2026) — cubre el
 * canal 'oficial' nuevo en NotificacionService::notificarWhatsappSiNoEnviado()
 * y que pago_confirmado ahora también manda WhatsApp (antes solo
 * recordatorios/pendiente/reversión/kit). SIEMPRE Mail::fake() — este
 * proyecto no tiene sandbox de email.
 */
class NotificacionServiceWhatsappTest extends TestCase
{
    use RefreshDatabase;

    private Organizador $organizador;

    private Evento $evento;

    private FormType $formType;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Queue::fake();

        $pais = Pais::factory()->create();
        $ciudad = Ciudad::factory()->create(['pais_id' => $pais->id]);
        $this->organizador = Organizador::factory()->create();
        $tipoEvento = TipoEvento::factory()->create();
        $subtipoEvento = SubtipoEvento::factory()->create(['tipo_evento_id' => $tipoEvento->id]);

        $this->evento = Evento::factory()->create([
            'organizador_id' => $this->organizador->id,
            'tipo_evento_id' => $tipoEvento->id,
            'subtipo_evento_id' => $subtipoEvento->id,
            'pais_id' => $pais->id,
            'ciudad_id' => $ciudad->id,
        ]);

        $this->formType = FormType::factory()->create(['event_id' => $this->evento->id, 'es_staff' => false]);
    }

    private function crearRegistrationConParticipante(array $participanteOverrides = []): Registration
    {
        $registration = Registration::factory()->create([
            'evento_id' => $this->evento->id,
            'form_types_id' => $this->formType->id,
            'referencia' => 'LA-WA-' . uniqid(),
            'fecha' => now(),
            'evento_nombre' => $this->evento->nombre,
            'tipo_pago' => 'EFECTIVO',
            'pago_status' => 'paid',
        ]);

        Participante::create(array_merge([
            'registration_id' => $registration->id,
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'genero' => 'Femenino',
            'tipo_documento' => 'DNI', 'numero_documento' => (string) rand(10000000, 99999999),
            'fecha_nacimiento' => '1995-01-01', 'edad' => 30, 'correo' => 'ana' . rand(1, 99999) . '@test.net',
            'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '77712345',
            'categoria' => '1', 'subtotal' => 50,
        ], $participanteOverrides));

        return $registration;
    }

    public function test_pago_confirmado_con_canal_oficial_despacha_un_job_por_participante_con_telefono(): void
    {
        $this->organizador->update(['whatsapp_canal' => 'oficial']);
        $cuenta = WhatsappCuenta::create([
            'organizador_id' => $this->organizador->id,
            'nombre' => 'Cuenta Prueba',
            'phone_number_id' => '123456789',
            'access_token' => 'token-test',
            'activo' => true,
        ]);
        $registration = $this->crearRegistrationConParticipante();

        app(NotificacionService::class)->notificarPagoConfirmado($registration);

        Queue::assertPushed(SendWhatsappOficialMessageJob::class, function ($job) use ($cuenta) {
            $ref = new \ReflectionClass($job);
            $cuentaId = $ref->getProperty('cuentaId');
            $cuentaId->setAccessible(true);
            $telefono = $ref->getProperty('telefonoDigitos');
            $telefono->setAccessible(true);

            return $cuentaId->getValue($job) === $cuenta->id && $telefono->getValue($job) === '77712345';
        });
    }

    public function test_pago_confirmado_con_canal_ninguno_no_despacha_nada(): void
    {
        // whatsapp_canal queda en su default 'ninguno'.
        $registration = $this->crearRegistrationConParticipante();

        app(NotificacionService::class)->notificarPagoConfirmado($registration);

        Queue::assertNothingPushed();
    }

    public function test_canal_oficial_sin_cuenta_activa_no_despacha_nada(): void
    {
        // whatsapp_canal='oficial' pero nadie cargó una WhatsappCuenta
        // todavía — no hay a quién avisarle que falta configurar.
        $this->organizador->update(['whatsapp_canal' => 'oficial']);
        $registration = $this->crearRegistrationConParticipante();

        app(NotificacionService::class)->notificarPagoConfirmado($registration);

        Queue::assertNothingPushed();
    }

    public function test_canal_oficial_con_cuenta_inactiva_no_despacha_nada(): void
    {
        $this->organizador->update(['whatsapp_canal' => 'oficial']);
        WhatsappCuenta::create([
            'organizador_id' => $this->organizador->id,
            'nombre' => 'Cuenta Inactiva',
            'phone_number_id' => '123456789',
            'access_token' => 'token-test',
            'activo' => false,
        ]);
        $registration = $this->crearRegistrationConParticipante();

        app(NotificacionService::class)->notificarPagoConfirmado($registration);

        Queue::assertNothingPushed();
    }

    public function test_pago_confirmado_no_reenvia_whatsapp_dos_veces(): void
    {
        $this->organizador->update(['whatsapp_canal' => 'oficial']);
        WhatsappCuenta::create([
            'organizador_id' => $this->organizador->id,
            'nombre' => 'Cuenta Prueba',
            'phone_number_id' => '123456789',
            'access_token' => 'token-test',
            'activo' => true,
        ]);
        $registration = $this->crearRegistrationConParticipante();

        $service = app(NotificacionService::class);
        $service->notificarPagoConfirmado($registration);
        $service->notificarPagoConfirmado($registration);

        Queue::assertPushed(SendWhatsappOficialMessageJob::class, 1);
    }

    public function test_pendiente_creada_tambien_funciona_por_canal_oficial(): void
    {
        // Confirma que el case 'oficial' del match es genérico, no algo
        // hardcodeado solo para pago_confirmado.
        $this->organizador->update(['whatsapp_canal' => 'oficial']);
        WhatsappCuenta::create([
            'organizador_id' => $this->organizador->id,
            'nombre' => 'Cuenta Prueba',
            'phone_number_id' => '123456789',
            'access_token' => 'token-test',
            'activo' => true,
        ]);
        $registration = $this->crearRegistrationConParticipante();

        app(NotificacionService::class)->notificarInscripcionPendiente($registration);

        Queue::assertPushed(SendWhatsappOficialMessageJob::class, 1);
    }

    public function test_participante_sin_telefono_no_genera_job(): void
    {
        $this->organizador->update(['whatsapp_canal' => 'oficial']);
        WhatsappCuenta::create([
            'organizador_id' => $this->organizador->id,
            'nombre' => 'Cuenta Prueba',
            'phone_number_id' => '123456789',
            'access_token' => 'token-test',
            'activo' => true,
        ]);
        $registration = $this->crearRegistrationConParticipante(['telefono' => '']);

        app(NotificacionService::class)->notificarPagoConfirmado($registration);

        Queue::assertNothingPushed();
    }
}
