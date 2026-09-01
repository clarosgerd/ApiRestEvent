<?php

namespace Tests\Feature;

use App\Models\ContactoEmergenciaParticipante;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Participante;
use App\Models\Persona;
use App\Models\Registration;
use App\Models\Resultado;
use App\Models\SouvenirParticipante;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Purgar datos de Persona/Participante en inscripciones canceladas
 * (01/09/2026) — ver PLAN-PURGAR-DATOS-PERSONA-CANCELADA-01092026.md.
 * Cobertura de PurgarDatosPersonaCanceladaAction, disparado desde
 * RegistrationService::updatePaymentStatus() al pasar a `cancelled`.
 */
class PurgarDatosPersonaCanceladaTest extends TestCase
{
    use RefreshDatabase;

    private function crearRegistroCon(Evento $evento, string $documento, string $correo, string $pagoStatus): Registration
    {
        $formType = FormType::factory()->create(['event_id' => $evento->id]);

        $registration = Registration::factory()->create([
            'referencia'    => 'LA-' . Str::upper(Str::random(8)),
            'fecha'         => now(),
            'evento_id'     => $evento->id,
            'form_types_id' => $formType->id,
            'evento_nombre' => $evento->nombre,
            'tipo_pago'     => 'QR',
            'pago_status'   => $pagoStatus,
        ]);

        Participante::create([
            'registration_id'  => $registration->id,
            'nombre'           => 'Ana',
            'apellido'         => 'Test',
            'genero'           => 'Femenino',
            'tipo_documento'   => 'DNI',
            'numero_documento' => $documento,
            'fecha_nacimiento' => '1990-01-01',
            'edad'             => 36,
            'correo'           => $correo,
            'direccion'        => 'x',
            'ciudad'           => 'x',
            'telefono'         => '1',
            'categoria'        => 'General',
            'subtotal'         => 100,
        ]);

        return $registration;
    }

    public function test_flag_apagado_sin_otra_inscripcion_vigente_borra_participante_y_persona(): void
    {
        $evento = Evento::factory()->create(['mantener_datos_persona' => false]);
        $documento = '11111111';
        $correo = 'ana.purga1@test.net';

        $registration = $this->crearRegistroCon($evento, $documento, $correo, 'pending');
        $participante = $registration->participants()->first();
        Persona::factory()->create(['numero_documento' => $documento, 'email' => $correo]);

        // Hijos del participante — deben desaparecer con el cascade al
        // borrar el participante (contacto de emergencia, souvenir).
        ContactoEmergenciaParticipante::create([
            'participante_id' => $participante->id,
            'nombre' => 'Contacto', 'celular' => '2', 'relacion' => 'Padre',
        ]);
        SouvenirParticipante::create([
            'participante_id' => $participante->id, 'souvenir_id' => 1,
            'nombre' => 'Camiseta', 'precio' => 0,
        ]);

        app(RegistrationService::class)->updatePaymentStatus($registration->referencia, 'cancelled');

        $this->assertDatabaseMissing('participantes', ['id' => $participante->id]);
        $this->assertDatabaseMissing('personas', ['numero_documento' => $documento]);
        $this->assertDatabaseMissing('contacto_emergencia_participantes', ['participante_id' => $participante->id]);
        $this->assertDatabaseMissing('souvenir_participantes', ['participante_id' => $participante->id]);
    }

    public function test_flag_apagado_con_otra_inscripcion_vigente_en_otro_evento_conserva_persona(): void
    {
        $eventoA = Evento::factory()->create(['mantener_datos_persona' => false]);
        $eventoB = Evento::factory()->create();
        $documento = '22222222';
        $correo = 'ana.purga2@test.net';

        // Inscripción PAGADA y vigente en otro evento — no debe perderse
        // el acceso a la cuenta por cancelar una inscripción distinta.
        $this->crearRegistroCon($eventoB, $documento, $correo, 'paid');
        $registrationCancelada = $this->crearRegistroCon($eventoA, $documento, $correo, 'pending');
        $participanteCancelado = $registrationCancelada->participants()->first();
        Persona::factory()->create(['numero_documento' => $documento, 'email' => $correo]);

        app(RegistrationService::class)->updatePaymentStatus($registrationCancelada->referencia, 'cancelled');

        $this->assertDatabaseMissing('participantes', ['id' => $participanteCancelado->id]);
        $this->assertDatabaseHas('personas', ['numero_documento' => $documento]);
    }

    public function test_flag_encendido_default_no_borra_nada(): void
    {
        $evento = Evento::factory()->create(); // mantener_datos_persona default true
        $documento = '33333333';
        $correo = 'ana.purga3@test.net';

        $registration = $this->crearRegistroCon($evento, $documento, $correo, 'pending');
        $participante = $registration->participants()->first();
        Persona::factory()->create(['numero_documento' => $documento, 'email' => $correo]);

        app(RegistrationService::class)->updatePaymentStatus($registration->referencia, 'cancelled');

        $this->assertDatabaseHas('participantes', ['id' => $participante->id]);
        $this->assertDatabaseHas('personas', ['numero_documento' => $documento]);
    }

    public function test_transicion_a_paid_nunca_dispara_el_purge(): void
    {
        $evento = Evento::factory()->create(['mantener_datos_persona' => false]);
        $documento = '44444444';
        $correo = 'ana.purga4@test.net';

        $registration = $this->crearRegistroCon($evento, $documento, $correo, 'pending');
        $participante = $registration->participants()->first();
        Persona::factory()->create(['numero_documento' => $documento, 'email' => $correo]);

        app(RegistrationService::class)->updatePaymentStatus($registration->referencia, 'paid');

        $this->assertDatabaseHas('participantes', ['id' => $participante->id]);
        $this->assertDatabaseHas('personas', ['numero_documento' => $documento]);
    }

    public function test_participante_con_resultado_cargado_no_se_borra(): void
    {
        $evento = Evento::factory()->create(['mantener_datos_persona' => false]);
        $documento = '55555555';
        $correo = 'ana.purga5@test.net';

        $registration = $this->crearRegistroCon($evento, $documento, $correo, 'pending');
        $participante = $registration->participants()->first();
        Resultado::create(['event_id' => $evento->id, 'participante_id' => $participante->id]);

        app(RegistrationService::class)->updatePaymentStatus($registration->referencia, 'cancelled');

        $this->assertDatabaseHas('participantes', ['id' => $participante->id]);
    }
}
