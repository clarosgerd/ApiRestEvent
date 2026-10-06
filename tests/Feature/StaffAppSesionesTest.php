<?php

namespace Tests\Feature;

use App\Models\AsistenciaSesion;
use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Participante;
use App\Models\Persona;
use App\Models\Registration;
use App\Models\SesionCongreso;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Check-in por sesión desde la app de staff (05/10/2026): listar sesiones y
 * marcar asistentes en lote. Mismas reglas que el admin, autor = Persona.
 */
class StaffAppSesionesTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private Category $categoria;

    private Persona $staffPersona;

    private SesionCongreso $sesion;

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

        $formTypeStaff = FormType::factory()->create(['event_id' => $this->evento->id, 'es_staff' => true, 'requiere_categoria' => false]);
        $this->crearParticipante($formTypeStaff, ['categoria' => 'Staff', 'correo' => 'staff@test.net', 'numero_documento' => '11112222', 'subtotal' => 0]);
        $this->staffPersona = Persona::factory()->create(['email' => 'staff@test.net', 'numero_documento' => '11112222']);
        $this->actingAsPersona($this->staffPersona);

        $this->sesion = SesionCongreso::factory()->create([
            'evento_id' => $this->evento->id, 'titulo' => 'Charla de apertura', 'sala' => 'Sirion',
            'fecha' => '2026-10-08', 'hora_inicio' => '08:00:00', 'hora_fin' => '08:30:00', 'cupo' => null,
        ]);
    }

    private function crearParticipante(FormType $formType, array $overrides = [], string $pagoStatus = 'paid', ?Evento $evento = null): Participante
    {
        $evento ??= $this->evento;
        $registration = Registration::factory()->create([
            'evento_id' => $evento->id, 'form_types_id' => $formType->id,
            'referencia' => 'LA-SES-' . uniqid(), 'fecha' => now(), 'evento_nombre' => $evento->nombre,
            'tipo_pago' => 'pendiente', 'pago_status' => $pagoStatus,
        ]);

        return Participante::create(array_merge([
            'registration_id' => $registration->id,
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'genero' => 'Femenino',
            'tipo_documento' => 'DNI', 'numero_documento' => (string) rand(10000000, 99999999),
            'fecha_nacimiento' => '1995-01-01', 'edad' => 30, 'correo' => 'ana' . rand(1, 99999) . '@test.net',
            'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => (string) $this->categoria->id, 'subtotal' => 100,
        ], $overrides));
    }

    private function participantePagado(): Participante
    {
        return $this->crearParticipante(FormType::factory()->create(['event_id' => $this->evento->id, 'requiere_categoria' => true]));
    }

    public function test_lista_las_sesiones_del_evento_con_su_asistencia(): void
    {
        $this->getJson("/api/v1/persona/eventos/{$this->evento->id}/sesiones")
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('sesiones.0.id', $this->sesion->id)
            ->assertJsonPath('sesiones.0.titulo', 'Charla de apertura')
            ->assertJsonPath('sesiones.0.horaInicio', '08:00')
            ->assertJsonPath('sesiones.0.acreditados', 0);
    }

    public function test_lista_rechaza_con_403_si_no_es_staff_del_evento(): void
    {
        $this->actingAsPersona(Persona::factory()->create());

        $this->getJson("/api/v1/persona/eventos/{$this->evento->id}/sesiones")->assertStatus(403);
    }

    public function test_acredita_a_un_participante_pagado_y_guarda_a_la_persona_como_autor(): void
    {
        $participante = $this->participantePagado();

        $this->postJson("/api/v1/persona/eventos/{$this->evento->id}/sesiones/{$this->sesion->id}/checkin-bulk", [
            'participante_ids' => [$participante->id],
        ])->assertOk()->assertJson(['success' => true, 'acreditados' => [$participante->id], 'yaAcreditados' => [], 'rechazados' => []]);

        $this->assertDatabaseHas('asistencia_sesion', [
            'sesion_congreso_id' => $this->sesion->id,
            'participante_id' => $participante->id,
            'staff_persona_id' => $this->staffPersona->id,
            'staff_admin_user_id' => null,
        ]);
        $this->assertDatabaseHas('admin_audit_logs', ['accion' => 'checkin_sesion', 'persona_id' => $this->staffPersona->id]);
    }

    public function test_no_duplica_si_ya_estaba_acreditado(): void
    {
        $participante = $this->participantePagado();
        AsistenciaSesion::create(['sesion_congreso_id' => $this->sesion->id, 'participante_id' => $participante->id, 'checkin_at' => now()->subHour()]);

        $this->postJson("/api/v1/persona/eventos/{$this->evento->id}/sesiones/{$this->sesion->id}/checkin-bulk", [
            'participante_ids' => [$participante->id],
        ])->assertOk()->assertJson(['acreditados' => [], 'yaAcreditados' => [$participante->id]]);

        $this->assertSame(1, AsistenciaSesion::where('sesion_congreso_id', $this->sesion->id)->count());
    }

    public function test_rechaza_pago_no_confirmado_y_participante_de_otro_evento(): void
    {
        $pendiente = $this->crearParticipante(FormType::factory()->create(['event_id' => $this->evento->id, 'requiere_categoria' => true]), [], 'pending');
        $otro = Evento::factory()->create([
            'organizador_id' => $this->evento->organizador_id, 'tipo_evento_id' => $this->evento->tipo_evento_id,
            'subtipo_evento_id' => $this->evento->subtipo_evento_id, 'pais_id' => $this->evento->pais_id, 'ciudad_id' => $this->evento->ciudad_id,
        ]);
        $deOtro = $this->crearParticipante(FormType::factory()->create(['event_id' => $otro->id, 'requiere_categoria' => true]), [], 'paid', $otro);

        $response = $this->postJson("/api/v1/persona/eventos/{$this->evento->id}/sesiones/{$this->sesion->id}/checkin-bulk", [
            'participante_ids' => [$pendiente->id, $deOtro->id],
        ])->assertOk();

        $this->assertCount(2, $response->json('rechazados'));
        $this->assertSame(0, AsistenciaSesion::count());
    }

    public function test_respeta_el_cupo_de_la_sesion(): void
    {
        $this->sesion->update(['cupo' => 1]);
        $primero = $this->participantePagado();
        $segundo = $this->participantePagado();

        $response = $this->postJson("/api/v1/persona/eventos/{$this->evento->id}/sesiones/{$this->sesion->id}/checkin-bulk", [
            'participante_ids' => [$primero->id, $segundo->id],
        ])->assertOk();

        $this->assertSame([$primero->id], $response->json('acreditados'));
        $this->assertSame('Esta sesión llegó a su cupo máximo.', $response->json('rechazados.0.motivo'));
    }

    public function test_sesion_de_otro_evento_responde_404(): void
    {
        $otro = Evento::factory()->create([
            'organizador_id' => $this->evento->organizador_id, 'tipo_evento_id' => $this->evento->tipo_evento_id,
            'subtipo_evento_id' => $this->evento->subtipo_evento_id, 'pais_id' => $this->evento->pais_id, 'ciudad_id' => $this->evento->ciudad_id,
        ]);
        $sesionOtra = SesionCongreso::factory()->create(['evento_id' => $otro->id]);

        $this->postJson("/api/v1/persona/eventos/{$this->evento->id}/sesiones/{$sesionOtra->id}/checkin-bulk", [
            'participante_ids' => [$this->participantePagado()->id],
        ])->assertStatus(404);
    }

    public function test_check_in_de_sesion_rechaza_403_si_no_es_staff(): void
    {
        $this->actingAsPersona(Persona::factory()->create());

        $this->postJson("/api/v1/persona/eventos/{$this->evento->id}/sesiones/{$this->sesion->id}/checkin-bulk", [
            'participante_ids' => [$this->participantePagado()->id],
        ])->assertStatus(403);
    }
}
