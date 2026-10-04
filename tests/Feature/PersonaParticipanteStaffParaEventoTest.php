<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Participante;
use App\Models\Persona;
use App\Models\Registration;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * App de staff offline (02/10/2026) — Persona::participanteStaffParaEvento().
 * Sin FK directa Persona→Participante, se resuelve por email (único)
 * primero, numero_documento como fallback — nunca `orWhere` (podría
 * matchear el participante equivocado si 2 personas comparten documento,
 * mismo criterio anti-colisión que RegistrationService::syncPersonas()).
 */
class PersonaParticipanteStaffParaEventoTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private Category $categoria;

    protected function setUp(): void
    {
        parent::setUp();

        $pais = Pais::factory()->create();
        $ciudad = Ciudad::factory()->create(['pais_id' => $pais->id]);
        $organizador = Organizador::factory()->create();
        $tipoEvento = TipoEvento::factory()->create();
        $subtipoEvento = SubtipoEvento::factory()->create(['tipo_evento_id' => $tipoEvento->id]);

        $this->evento = Evento::factory()->create([
            'organizador_id' => $organizador->id, 'tipo_evento_id' => $tipoEvento->id,
            'subtipo_evento_id' => $subtipoEvento->id, 'pais_id' => $pais->id, 'ciudad_id' => $ciudad->id,
        ]);
        $this->categoria = Category::factory()->create(['event_id' => $this->evento->id, 'price' => 100]);
    }

    private function crearParticipanteStaff(array $overrides = []): Participante
    {
        $formType = FormType::factory()->create(['event_id' => $this->evento->id, 'es_staff' => true, 'requiere_categoria' => false]);
        $registration = Registration::factory()->create([
            'evento_id' => $this->evento->id, 'form_types_id' => $formType->id,
            'referencia' => 'LA-STAFF-' . uniqid(), 'fecha' => now(), 'evento_nombre' => $this->evento->nombre,
            'tipo_pago' => 'gratis', 'pago_status' => 'paid',
        ]);

        return Participante::create(array_merge([
            'registration_id' => $registration->id,
            'nombre' => 'Staff', 'apellido' => 'Prueba', 'genero' => 'Femenino',
            'tipo_documento' => 'DNI', 'numero_documento' => '99998888',
            'fecha_nacimiento' => '1990-01-01', 'edad' => 34, 'correo' => 'staff@test.net',
            'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => 'Staff', 'subtotal' => 0,
        ], $overrides));
    }

    public function test_matchea_por_email_primero(): void
    {
        $this->crearParticipanteStaff();
        $persona = Persona::factory()->make(['email' => 'staff@test.net', 'numero_documento' => '00000000']);

        $resultado = $persona->participanteStaffParaEvento($this->evento);

        $this->assertNotNull($resultado);
        $this->assertSame('99998888', $resultado->numero_documento);
    }

    public function test_cae_a_numero_documento_si_el_email_no_matchea(): void
    {
        $this->crearParticipanteStaff();
        $persona = Persona::factory()->make(['email' => 'otro@test.net', 'numero_documento' => '99998888']);

        $resultado = $persona->participanteStaffParaEvento($this->evento);

        $this->assertNotNull($resultado);
        $this->assertSame('staff@test.net', $resultado->correo);
    }

    public function test_null_si_no_es_staff_de_este_evento(): void
    {
        $persona = Persona::factory()->make(['email' => 'nadie@test.net', 'numero_documento' => '00000000']);

        $this->assertNull($persona->participanteStaffParaEvento($this->evento));
    }

    public function test_null_si_el_registro_de_staff_no_esta_pagado(): void
    {
        $this->crearParticipanteStaff(['correo' => 'pendiente@test.net', 'numero_documento' => '77776666']);
        Registration::query()->update(['pago_status' => 'pending']);
        $persona = Persona::factory()->make(['email' => 'pendiente@test.net', 'numero_documento' => '77776666']);

        $this->assertNull($persona->participanteStaffParaEvento($this->evento));
    }

    public function test_null_si_el_form_type_no_es_staff(): void
    {
        $formType = FormType::factory()->create(['event_id' => $this->evento->id, 'requiere_categoria' => true, 'es_staff' => false]);
        $registration = Registration::factory()->create([
            'evento_id' => $this->evento->id, 'form_types_id' => $formType->id,
            'referencia' => 'LA-NOSTAFF-' . uniqid(), 'fecha' => now(), 'evento_nombre' => $this->evento->nombre,
            'tipo_pago' => 'pendiente', 'pago_status' => 'paid',
        ]);
        Participante::create([
            'registration_id' => $registration->id,
            'nombre' => 'Normal', 'apellido' => 'Prueba', 'genero' => 'Femenino',
            'tipo_documento' => 'DNI', 'numero_documento' => '55554444',
            'fecha_nacimiento' => '1990-01-01', 'edad' => 34, 'correo' => 'normal@test.net',
            'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => (string) $this->categoria->id, 'subtotal' => 100,
        ]);
        $persona = Persona::factory()->make(['email' => 'normal@test.net', 'numero_documento' => '55554444']);

        $this->assertNull($persona->participanteStaffParaEvento($this->evento));
    }
}
