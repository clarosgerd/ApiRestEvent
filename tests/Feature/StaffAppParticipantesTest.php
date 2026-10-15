<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Participante;
use App\Models\ParticipanteTallerSesion;
use App\Models\Persona;
use App\Models\Registration;
use App\Models\SesionCongreso;
use App\Models\SubtipoEvento;
use App\Models\Taller;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * App de staff offline (02/10/2026) — descarga completa de participantes
 * del evento para una Persona staff autenticada. Ver StaffAppController.
 */
class StaffAppParticipantesTest extends TestCase
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
        $this->categoria = Category::factory()->create(['event_id' => $this->evento->id, 'name' => 'General', 'price' => 100]);
    }

    private function crearParticipante(FormType $formType, array $overrides = []): Participante
    {
        $registration = Registration::factory()->create(array_merge([
            'evento_id' => $this->evento->id, 'form_types_id' => $formType->id,
            'referencia' => 'LA-STAFF-' . uniqid(), 'fecha' => now(), 'evento_nombre' => $this->evento->nombre,
            'tipo_pago' => 'pendiente', 'pago_status' => 'paid',
        ], $overrides['registration'] ?? []));

        return Participante::create(array_merge([
            'registration_id' => $registration->id,
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'genero' => 'Femenino',
            'tipo_documento' => 'DNI', 'numero_documento' => (string) rand(10000000, 99999999),
            'fecha_nacimiento' => '1995-01-01', 'edad' => 30, 'correo' => 'ana' . rand(1, 99999) . '@test.net',
            'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => (string) $this->categoria->id, 'subtotal' => 100,
        ], $overrides['participante'] ?? []));
    }

    private function crearStaffAutenticado(): Persona
    {
        $formTypeStaff = FormType::factory()->create(['event_id' => $this->evento->id, 'es_staff' => true, 'requiere_categoria' => false]);
        $staff = $this->crearParticipante($formTypeStaff, ['participante' => [
            'categoria' => 'Staff', 'correo' => 'staff@test.net', 'numero_documento' => '11112222', 'subtotal' => 0,
        ]]);

        $persona = Persona::factory()->create(['email' => 'staff@test.net', 'numero_documento' => '11112222']);
        $this->actingAsPersona($persona);

        return $persona;
    }

    public function test_rechaza_con_403_si_la_persona_no_es_staff_del_evento(): void
    {
        $persona = Persona::factory()->create();
        $this->actingAsPersona($persona);

        $this->getJson("/api/v1/persona/eventos/{$this->evento->id}/participantes")
            ->assertStatus(403)
            ->assertJson(['success' => false]);
    }

    public function test_rechaza_sin_autenticar(): void
    {
        $this->getJson("/api/v1/persona/eventos/{$this->evento->id}/participantes")
            ->assertStatus(401);
    }

    public function test_staff_del_evento_descarga_todos_los_participantes_pagados(): void
    {
        $this->crearStaffAutenticado();
        $formTypeNormal = FormType::factory()->create(['event_id' => $this->evento->id, 'requiere_categoria' => true]);
        $pagado = $this->crearParticipante($formTypeNormal, ['participante' => ['nombre' => 'Pagado']]);
        $this->crearParticipante($formTypeNormal, ['participante' => ['nombre' => 'Pendiente'], 'registration' => ['pago_status' => 'pending']]);

        $response = $this->getJson("/api/v1/persona/eventos/{$this->evento->id}/participantes");

        $response->assertOk()->assertJson(['success' => true]);
        $nombres = collect($response->json('participantes'))->pluck('nombre');
        // El staff solo baja inscripciones pagadas — ni la pendiente, ni
        // (por diseño) a sí mismo le importa verse incluido o no, pero acá
        // confirmamos que "Pendiente" no aparece.
        $this->assertTrue($nombres->contains('Pagado'));
        $this->assertFalse($nombres->contains('Pendiente'));
    }

    public function test_el_shape_de_un_participante_coincide_con_porEvento(): void
    {
        $this->crearStaffAutenticado();
        $formTypeNormal = FormType::factory()->create(['event_id' => $this->evento->id, 'requiere_categoria' => true]);
        $this->crearParticipante($formTypeNormal);

        $response = $this->getJson("/api/v1/persona/eventos/{$this->evento->id}/participantes");

        $primero = collect($response->json('participantes'))->first(fn ($p) => $p['nombre'] === 'Ana');
        $this->assertNotNull($primero);
        foreach (['id', 'referencia', 'nombre', 'apellido', 'numeroDocumento', 'categoria', 'correo', 'telefono',
            'direccion', 'pagoStatus', 'checkedInAt', 'importe', 'importeTotal', 'fechaInscripcion', 'talleres'] as $campo) {
            $this->assertArrayHasKey($campo, $primero);
        }
    }

    /**
     * Talleres/sesiones asignados al participante (15/10/2026, pedido del
     * usuario) — hay congresos reales donde el participante elige talleres/
     * sesiones específicas al inscribirse; el staff necesita verlas para
     * orientarlo, aunque el check-in por sesión siga siendo libre (cualquier
     * participante pagado puede marcarse en cualquier sesión, eso no cambia
     * acá — ver StaffAppController::sesionCheckinBulk()).
     */
    public function test_incluye_los_talleres_y_sesiones_asignados_al_participante(): void
    {
        $this->crearStaffAutenticado();
        $formTypeNormal = FormType::factory()->create(['event_id' => $this->evento->id, 'requiere_categoria' => true]);
        $participante = $this->crearParticipante($formTypeNormal);

        $taller = Taller::factory()->create(['evento_id' => $this->evento->id, 'nombre' => 'Taller de Sutura']);
        $sesion = SesionCongreso::factory()->create([
            'evento_id' => $this->evento->id, 'taller_id' => $taller->id,
            'titulo' => 'Sutura avanzada', 'sala' => 'Sala B',
            'fecha' => '2026-11-10', 'hora_inicio' => '09:00:00', 'hora_fin' => '10:30:00',
        ]);
        ParticipanteTallerSesion::create([
            'participante_id' => $participante->id, 'sesion_congreso_id' => $sesion->id,
            'taller_id' => $taller->id, 'unit_price' => 50, 'discount' => 0, 'total' => 50,
        ]);

        $response = $this->getJson("/api/v1/persona/eventos/{$this->evento->id}/participantes");

        $fila = collect($response->json('participantes'))->first(fn ($p) => $p['nombre'] === 'Ana');
        $this->assertCount(1, $fila['talleres']);
        $this->assertSame($taller->id, $fila['talleres'][0]['tallerId']);
        $this->assertSame($sesion->id, $fila['talleres'][0]['sesionId']);
        $this->assertSame('Taller de Sutura', $fila['talleres'][0]['tallerNombre']);
        $this->assertSame('Sutura avanzada', $fila['talleres'][0]['titulo']);
        $this->assertSame('Sala B', $fila['talleres'][0]['sala']);
        $this->assertSame('2026-11-10', $fila['talleres'][0]['fecha']);
        $this->assertSame('09:00', $fila['talleres'][0]['horaInicio']);
        $this->assertSame('10:30', $fila['talleres'][0]['horaFin']);
    }

    /** Sin talleres asignados, la clave existe pero es un array vacío (no null/ausente). */
    public function test_talleres_viene_vacio_si_el_participante_no_tiene_ninguno(): void
    {
        $this->crearStaffAutenticado();
        $formTypeNormal = FormType::factory()->create(['event_id' => $this->evento->id, 'requiere_categoria' => true]);
        $this->crearParticipante($formTypeNormal);

        $response = $this->getJson("/api/v1/persona/eventos/{$this->evento->id}/participantes");

        $fila = collect($response->json('participantes'))->first(fn ($p) => $p['nombre'] === 'Ana');
        $this->assertSame([], $fila['talleres']);
    }
}
