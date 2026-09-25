<?php

namespace Tests\Feature;

use App\Actions\ProvisionarCuentaExpositorAction;
use App\Mail\ExpositorCredencialesMail;
use App\Models\Answer;
use App\Models\EmpresaExpositora;
use App\Models\FormularioCampos;
use App\Services\NotificacionService;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\Support\ArmaExpositores;
use Tests\TestCase;

/**
 * SmartStand (25/09/2026) — alta automática de la cuenta de expositor al
 * confirmarse el pago de una inscripción con form_type `es_expositor`.
 */
class ProvisionarCuentaExpositorTest extends TestCase
{
    use RefreshDatabase, ArmaExpositores;

    private function notificarPago($registration): void
    {
        app(NotificacionService::class)->notificarPagoConfirmado($registration);
    }

    public function test_al_confirmarse_el_pago_se_crea_la_cuenta_y_se_manda_el_correo(): void
    {
        Mail::fake();
        $evento = $this->crearEvento(['expositores_config' => [
            'app_url_android' => 'https://play.google.com/store/apps/details?id=x',
            'app_url_ios'     => 'https://apps.apple.com/app/x',
        ]]);
        $ft = $this->crearFormType($evento);
        $categoria = $this->crearCategoria($evento, $ft, 'Stand 6x3');
        $registration = $this->crearInscripcion($evento, $ft, [
            'nombre' => 'Marta', 'apellido' => 'Rojas', 'correo' => 'Marta@Empresa.test', 'categoria' => (string) $categoria->id,
        ]);

        $this->notificarPago($registration);

        $cuenta = EmpresaExpositora::where('registration_id', $registration->id)->firstOrFail();
        $this->assertSame($evento->id, $cuenta->evento_id);
        $this->assertSame('marta@empresa.test', $cuenta->email);
        $this->assertSame($categoria->id, $cuenta->categoria_id);
        $this->assertSame('Marta Rojas', $cuenta->nombre, 'Sin respuesta razon_social, usa el nombre del representante.');
        $this->assertNotNull($cuenta->credenciales_enviadas_at);
        $this->assertNull($cuenta->stand, 'El número de stand lo asigna el organizador después.');

        Mail::assertSent(ExpositorCredencialesMail::class, function (ExpositorCredencialesMail $mail) use ($cuenta) {
            return $mail->hasTo('marta@empresa.test')
                && $mail->cuenta->is($cuenta)
                && $mail->passwordPlano !== ''
                && Hash::check($mail->passwordPlano, $cuenta->fresh()->password);
        });
    }

    public function test_el_correo_incluye_usuario_contrasena_y_links_de_la_app(): void
    {
        $evento = $this->crearEvento(['expositores_config' => [
            'app_url_android' => 'https://play.google.com/store/apps/details?id=x',
            'app_url_ios'     => 'https://apps.apple.com/app/x',
            'instrucciones'   => 'Retira tu credencial en la mesa 2.',
        ]]);
        $cuenta = $this->crearCuenta($evento, ['email' => 'e@empresa.test', 'nombre' => 'Farma Sur']);

        $html = (new ExpositorCredencialesMail($cuenta, 'Abc123xyz789'))->render();

        $this->assertStringContainsString('e@empresa.test', $html);
        $this->assertStringContainsString('Abc123xyz789', $html);
        $this->assertStringContainsString('https://play.google.com/store/apps/details?id=x', $html);
        $this->assertStringContainsString('https://apps.apple.com/app/x', $html);
        $this->assertStringContainsString('Retira tu credencial en la mesa 2.', $html);
    }

    public function test_sin_links_de_app_el_correo_avisa_que_los_enviara_el_organizador(): void
    {
        $evento = $this->crearEvento();
        $cuenta = $this->crearCuenta($evento);

        $html = (new ExpositorCredencialesMail($cuenta, 'Abc123xyz789'))->render();

        $this->assertStringContainsString('El organizador te enviará el link de descarga', $html);
    }

    public function test_usa_la_razon_social_de_las_preguntas_custom(): void
    {
        Mail::fake();
        $evento = $this->crearEvento();
        $ft = $this->crearFormType($evento);
        $pregunta = FormularioCampos::factory()->create([
            'form_types_id' => $ft->id, 'nombre_campo' => 'razon_social', 'etiqueta' => 'Razón social',
        ]);
        $registration = $this->crearInscripcion($evento, $ft);
        Answer::create([
            'form_types_id' => $ft->id, 'question_id' => $pregunta->id,
            'participante_id' => $registration->participants->first()->id, 'value' => 'Laboratorios Andes S.A.',
        ]);

        $this->notificarPago($registration);

        $this->assertSame('Laboratorios Andes S.A.', EmpresaExpositora::where('registration_id', $registration->id)->value('nombre'));
    }

    /** El webhook/polling llega varias veces: no se crea 2 veces ni se manda 2 contraseñas. */
    public function test_es_idempotente_ante_reintentos_del_pago(): void
    {
        Mail::fake();
        $evento = $this->crearEvento();
        $ft = $this->crearFormType($evento);
        $registration = $this->crearInscripcion($evento, $ft);

        $this->notificarPago($registration);
        $this->notificarPago($registration);
        $this->notificarPago($registration);

        $this->assertSame(1, EmpresaExpositora::where('registration_id', $registration->id)->count());
        Mail::assertSent(ExpositorCredencialesMail::class, 1);
    }

    public function test_un_form_type_que_no_es_expositor_no_crea_cuenta(): void
    {
        Mail::fake();
        $evento = $this->crearEvento();
        $ft = $this->crearFormType($evento, esExpositor: false);
        $registration = $this->crearInscripcion($evento, $ft);

        $this->notificarPago($registration);

        $this->assertSame(0, EmpresaExpositora::count());
        Mail::assertNotSent(ExpositorCredencialesMail::class);
    }

    public function test_no_crea_cuenta_si_el_pago_no_esta_confirmado(): void
    {
        Mail::fake();
        $evento = $this->crearEvento();
        $ft = $this->crearFormType($evento);
        $registration = $this->crearInscripcion($evento, $ft, [], 'pending');

        $this->notificarPago($registration);

        $this->assertSame(0, EmpresaExpositora::count());
    }

    /** Ruta real de producción: PATCH de la pasarela -> updatePaymentStatus() -> gancho. */
    public function test_se_dispara_desde_el_flujo_real_de_confirmacion_de_pago(): void
    {
        Mail::fake();
        $evento = $this->crearEvento();
        $ft = $this->crearFormType($evento);
        $registration = $this->crearInscripcion($evento, $ft, [], 'pending');

        app(RegistrationService::class)->updatePaymentStatus($registration->referencia, 'paid');

        $this->assertSame(1, EmpresaExpositora::where('registration_id', $registration->id)->count());
        Mail::assertSent(ExpositorCredencialesMail::class, 1);
    }

    /** Un SMTP caído no rompe la confirmación del pago ni pierde la cuenta. */
    public function test_si_el_correo_falla_la_cuenta_queda_y_se_puede_reenviar(): void
    {
        $evento = $this->crearEvento();
        $ft = $this->crearFormType($evento);
        $registration = $this->crearInscripcion($evento, $ft);

        // Un transporte que siempre falla, como un SMTP caído de verdad.
        Mail::extend('fallido', fn () => new class extends AbstractTransport {
            protected function doSend(SentMessage $message): void
            {
                throw new \RuntimeException('SMTP caído');
            }

            public function __toString(): string
            {
                return 'fallido';
            }
        });
        config(['mail.mailers.fallido' => ['transport' => 'fallido'], 'mail.default' => 'fallido']);

        $this->notificarPago($registration); // no debe lanzar

        $cuenta = EmpresaExpositora::where('registration_id', $registration->id)->firstOrFail();
        $this->assertNull($cuenta->credenciales_enviadas_at);
        $hashAntes = $cuenta->password;

        // El SMTP "vuelve": mailer normal de tests (array).
        config(['mail.default' => 'array']);
        Mail::purge('fallido');
        $ok = app(ProvisionarCuentaExpositorAction::class)->reenviarCredenciales($cuenta);

        $this->assertTrue($ok);
        $cuenta->refresh();
        $this->assertNotNull($cuenta->credenciales_enviadas_at);
        $this->assertNotSame($hashAntes, $cuenta->password, 'El reenvío regenera la contraseña.');
    }

    public function test_el_comando_de_reconciliacion_reintenta_solo_las_cuentas_sin_enviar(): void
    {
        Mail::fake();
        $evento = $this->crearEvento();
        $sinEnviar = $this->crearCuenta($evento, ['credenciales_enviadas_at' => null]);
        $sinEnviar->forceFill(['created_at' => now()->subHour()])->save();
        $yaEnviada = $this->crearCuenta($evento, ['credenciales_enviadas_at' => now()]);
        $recien = $this->crearCuenta($evento, ['credenciales_enviadas_at' => null]); // creada hace segundos

        $this->artisan('expositores:reenviar-credenciales-faltantes')->assertSuccessful();

        $this->assertNotNull($sinEnviar->fresh()->credenciales_enviadas_at);
        $this->assertNull($recien->fresh()->credenciales_enviadas_at, 'No pisa un primer envío en curso.');
        Mail::assertSent(ExpositorCredencialesMail::class, 1);
    }

    public function test_dos_expositores_con_el_mismo_correo_en_el_mismo_evento_no_rompen_el_pago(): void
    {
        Mail::fake();
        $evento = $this->crearEvento();
        $ft = $this->crearFormType($evento);
        $this->crearCuenta($evento, ['email' => 'dup@empresa.test']);
        $registration = $this->crearInscripcion($evento, $ft, ['correo' => 'dup@empresa.test']);

        $this->notificarPago($registration); // UNIQUE(evento_id,email): se omite sin lanzar

        $this->assertSame(1, EmpresaExpositora::count());
    }
}
