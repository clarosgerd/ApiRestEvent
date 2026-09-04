<?php

namespace Tests\Feature;

use App\Actions\CrearInscripcionAction;
use App\DTOs\RegistrationDTO;
use App\Mail\PagoConfirmadoMail;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * `notificaciones:pago-confirmado-faltante` — ver
 * App\Console\Commands\ReenviarPagoConfirmadoFaltante. Diagnóstico
 * 04/09/2026: `pago_status` actualizado por SQL directo (sin pasar por
 * `RegistrationService::updatePaymentStatus()`) nunca dispara el correo de
 * confirmación — este comando lo reconcilia.
 */
class ReenviarPagoConfirmadoFaltanteTest extends TestCase
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
            'requiere_categoria' => true,
        ]);

        $this->categoria = Category::factory()->create([
            'event_id' => $this->evento->id,
            'price' => 50,
        ]);
    }

    /**
     * Crea una inscripción y la marca `paid` con un UPDATE directo (no
     * `->update()` de Eloquent vía el service) — reproduce exactamente el
     * bug real: el SQL directo del proveedor externo no dispara
     * `notificarPagoConfirmado()`, así que nace `paid` sin la notificación.
     */
    private function crearRegistrationPagadaSinNotificacion(): Registration
    {
        $registration = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-TEST-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'multipago',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => [
                'inscripcion' => 50, 'donacion' => 0, 'souvenirs' => 0, 'fee' => 2.5,
                'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 52.5,
            ],
            'participantes' => [[
                'nombre' => 'Ana', 'apellido' => 'Prueba', 'alias' => '', 'genero' => 'Femenino',
                'tipoDocumento' => 'DNI', 'numeroDocumento' => (string) rand(10000000, 99999999),
                'polera' => '', 'precioPolera' => 0,
                'nacimiento' => ['dia' => 1, 'mes' => 1, 'anio' => 1995], 'edad' => 30,
                'correo' => 'ana' . rand(1, 999999) . '@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
                'contacto_emergencia' => ['nombre' => 'X', 'celular' => '123', 'relacion' => 'Madre'],
                'souvenirs' => [], 'answers' => [],
                'categoria' => (string) $this->categoria->id, 'precioCategoria' => 50,
                'donacion' => 0, 'promoDescuento' => 0, 'promoCodigo' => '', 'subtotal' => 50,
            ]],
        ]));

        // UPDATE directo por query builder — a propósito NO usa
        // $registration->update() ni el service: eso es justo lo que el
        // bug real NO hace (bypassea toda la app vía SQL directo).
        DB::table('registrations')->where('id', $registration->id)->update(['pago_status' => 'paid']);

        return $registration->fresh();
    }

    public function test_reenvia_correo_a_pagada_sin_notificacion(): void
    {
        $registration = $this->crearRegistrationPagadaSinNotificacion();

        $this->assertDatabaseMissing('registration_notifications', [
            'registration_id' => $registration->id,
            'tipo' => 'pago_confirmado',
        ]);

        $this->artisan('notificaciones:pago-confirmado-faltante')
            ->expectsOutputToContain('Correos de pago confirmado reenviados: 1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('registration_notifications', [
            'registration_id' => $registration->id,
            'tipo' => 'pago_confirmado',
            'canal' => 'email',
        ]);
        Mail::assertSent(PagoConfirmadoMail::class);
    }

    public function test_no_reenvia_si_ya_tiene_la_notificacion(): void
    {
        $registration = $this->crearRegistrationPagadaSinNotificacion();
        app(\App\Services\NotificacionService::class)->notificarPagoConfirmado($registration);
        Mail::fake(); // limpia el envío del setup para que el assert de abajo sea real

        $this->artisan('notificaciones:pago-confirmado-faltante')
            ->expectsOutputToContain('Correos de pago confirmado reenviados: 0')
            ->assertExitCode(0);

        Mail::assertNotSent(PagoConfirmadoMail::class);
    }

    public function test_no_reenvia_a_pendientes(): void
    {
        app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-TEST-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'qr',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => [
                'inscripcion' => 50, 'donacion' => 0, 'souvenirs' => 0, 'fee' => 2.5,
                'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 52.5,
            ],
            'participantes' => [[
                'nombre' => 'Ana', 'apellido' => 'Prueba', 'alias' => '', 'genero' => 'Femenino',
                'tipoDocumento' => 'DNI', 'numeroDocumento' => (string) rand(10000000, 99999999),
                'polera' => '', 'precioPolera' => 0,
                'nacimiento' => ['dia' => 1, 'mes' => 1, 'anio' => 1995], 'edad' => 30,
                'correo' => 'ana' . rand(1, 999999) . '@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
                'contacto_emergencia' => ['nombre' => 'X', 'celular' => '123', 'relacion' => 'Madre'],
                'souvenirs' => [], 'answers' => [],
                'categoria' => (string) $this->categoria->id, 'precioCategoria' => 50,
                'donacion' => 0, 'promoDescuento' => 0, 'promoCodigo' => '', 'subtotal' => 50,
            ]],
        ]));

        $this->artisan('notificaciones:pago-confirmado-faltante')
            ->expectsOutputToContain('Correos de pago confirmado reenviados: 0')
            ->assertExitCode(0);

        Mail::assertNotSent(PagoConfirmadoMail::class);
    }

    /**
     * ETL de datos históricos — inscripciones con `origen_legado` cargado
     * nunca tuvieron una notificación real, no corresponde mandarles un
     * correo de "pago confirmado" ahora.
     */
    public function test_no_reenvia_a_origen_legado(): void
    {
        $registration = $this->crearRegistrationPagadaSinNotificacion();
        DB::table('registrations')->where('id', $registration->id)->update(['origen_legado' => 'etl-2019']);

        $this->artisan('notificaciones:pago-confirmado-faltante')
            ->expectsOutputToContain('Correos de pago confirmado reenviados: 0')
            ->assertExitCode(0);

        Mail::assertNotSent(PagoConfirmadoMail::class);
    }

    public function test_respeta_ventana_de_dias(): void
    {
        $registration = $this->crearRegistrationPagadaSinNotificacion();
        DB::table('registrations')->where('id', $registration->id)
            ->update(['created_at' => now()->subDays(10)]);

        $this->artisan('notificaciones:pago-confirmado-faltante', ['--dias' => 5])
            ->expectsOutputToContain('Correos de pago confirmado reenviados: 0')
            ->assertExitCode(0);

        Mail::assertNotSent(PagoConfirmadoMail::class);
    }

    public function test_dry_run_no_envia_nada(): void
    {
        $registration = $this->crearRegistrationPagadaSinNotificacion();

        $this->artisan('notificaciones:pago-confirmado-faltante', ['--dry-run' => true])
            ->expectsOutputToContain($registration->referencia)
            ->assertExitCode(0);

        Mail::assertNotSent(PagoConfirmadoMail::class);
        $this->assertDatabaseMissing('registration_notifications', [
            'registration_id' => $registration->id,
            'tipo' => 'pago_confirmado',
        ]);
    }
}
