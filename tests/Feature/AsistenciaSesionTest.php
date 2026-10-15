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
use App\Models\Registration;
use App\Models\SesionCongreso;
use App\Models\SubtipoEvento;
use App\Models\Taller;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Check-in de staff por sesión de congreso (individual y masivo) +
 * reporte de asistencia. Ver AsistenciaSesionController y
 * elascenso/event/brain/ (sesión 11/08/2026).
 */
class AsistenciaSesionTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private Category $categoria;

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
            'organizador_id' => $organizador->id,
            'tipo_evento_id' => $tipoEvento->id,
            'subtipo_evento_id' => $subtipoEvento->id,
            'pais_id' => $pais->id,
            'ciudad_id' => $ciudad->id,
        ]);
        $this->categoria = Category::factory()->create(['event_id' => $this->evento->id, 'name' => 'General']);
        $this->sesion = SesionCongreso::factory()->create(['evento_id' => $this->evento->id]);
    }

    private function crearParticipantePagado(array $overrides = []): Participante
    {
        $formType = FormType::factory()->create(['event_id' => $this->evento->id]);
        $registration = Registration::factory()->create(array_merge([
            'evento_id' => $this->evento->id,
            'form_types_id' => $formType->id,
            'referencia' => 'LA-SES-'.uniqid(),
            'fecha' => now(),
            'evento_nombre' => $this->evento->nombre,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'paid',
        ], $overrides['registration'] ?? []));

        return Participante::create(array_merge([
            'registration_id' => $registration->id,
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'genero' => 'Femenino',
            'tipo_documento' => 'DNI', 'numero_documento' => (string) rand(10000000, 99999999),
            'fecha_nacimiento' => '1995-01-01', 'edad' => 30, 'correo' => 'ana'.rand(1, 99999).'@test.net',
            'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => (string) $this->categoria->id, 'subtotal' => 50,
        ], $overrides['participante'] ?? []));
    }

    public function test_checkin_individual_marca_asistencia(): void
    {
        $participante = $this->crearParticipantePagado();
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $response = $this->patchJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/participantes/{$participante->id}/checkin");

        $response->assertStatus(201)->assertJson(['success' => true, 'alreadyCheckedIn' => false]);
        $this->assertDatabaseHas('asistencia_sesion', [
            'sesion_congreso_id' => $this->sesion->id,
            'participante_id' => $participante->id,
            'staff_admin_user_id' => $admin->id,
        ]);
    }

    public function test_reescanear_no_duplica_ni_pisa_el_staff_original(): void
    {
        $participante = $this->crearParticipantePagado();
        $admin1 = $this->actingAsAdmin();
        $admin1->update(['rol' => 'super_admin']);

        $this->patchJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/participantes/{$participante->id}/checkin")
            ->assertStatus(201);

        $admin2 = $this->actingAsAdmin();
        $admin2->update(['rol' => 'super_admin']);

        $this->patchJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/participantes/{$participante->id}/checkin")
            ->assertStatus(200)
            ->assertJson(['success' => true, 'alreadyCheckedIn' => true]);

        $this->assertDatabaseCount('asistencia_sesion', 1);
        $this->assertDatabaseHas('asistencia_sesion', ['participante_id' => $participante->id, 'staff_admin_user_id' => $admin1->id]);
    }

    public function test_rechaza_checkin_si_no_esta_pagado(): void
    {
        $participante = $this->crearParticipantePagado(['registration' => ['pago_status' => 'pending']]);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->patchJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/participantes/{$participante->id}/checkin")
            ->assertStatus(422);

        $this->assertDatabaseCount('asistencia_sesion', 0);
    }

    public function test_rechaza_checkin_si_llego_al_cupo(): void
    {
        $this->sesion->update(['cupo' => 1]);
        $p1 = $this->crearParticipantePagado();
        $p2 = $this->crearParticipantePagado();
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->patchJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/participantes/{$p1->id}/checkin")
            ->assertStatus(201);

        $this->patchJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/participantes/{$p2->id}/checkin")
            ->assertStatus(409);
    }

    public function test_checkin_bulk_es_parcial_no_todo_o_nada(): void
    {
        $this->sesion->update(['cupo' => 2]);
        $pagado1 = $this->crearParticipantePagado();
        $pagado2 = $this->crearParticipantePagado();
        $pagado3 = $this->crearParticipantePagado(); // este se rechaza por cupo
        $pendiente = $this->crearParticipantePagado(['registration' => ['pago_status' => 'pending']]);

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $response = $this->postJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/checkin-bulk", [
            'participante_ids' => [$pagado1->id, $pagado2->id, $pagado3->id, $pendiente->id],
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $data = $response->json();

        $this->assertCount(2, $data['acreditados']);
        $this->assertContains($pagado1->id, $data['acreditados']);
        $this->assertContains($pagado2->id, $data['acreditados']);
        $this->assertCount(2, $data['rechazados']); // pagado3 (cupo) + pendiente (pago)
        $this->assertDatabaseCount('asistencia_sesion', 2);
    }

    public function test_checkin_bulk_reporta_ya_acreditados_sin_duplicar(): void
    {
        $participante = $this->crearParticipantePagado();
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->patchJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/participantes/{$participante->id}/checkin")
            ->assertStatus(201);

        $response = $this->postJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/checkin-bulk", [
            'participante_ids' => [$participante->id],
        ]);

        $response->assertJson(['success' => true, 'acreditados' => [], 'yaAcreditados' => [$participante->id]]);
        $this->assertDatabaseCount('asistencia_sesion', 1);
    }

    /**
     * Control de acceso por sesión (15/10/2026, pedido real del usuario) —
     * `requiere_inscripcion` es opt-in (default false, cero cambio para
     * sesiones existentes): con el flag prendido, el check-in exige que
     * el participante tenga una fila en `participante_taller_sesion` para
     * ESTA sesión (es decir, que la haya seleccionado/comprado al
     * inscribirse) — antes cualquier participante pagado del evento podía
     * ocupar el cupo de check-in de un taller que nunca compró.
     */
    private function inscribirParticipanteEnSesion(Participante $participante): void
    {
        $taller = Taller::factory()->create(['evento_id' => $this->evento->id]);
        ParticipanteTallerSesion::create([
            'participante_id' => $participante->id,
            'sesion_congreso_id' => $this->sesion->id,
            'taller_id' => $taller->id,
            'unit_price' => 50,
            'total' => 50,
        ]);
    }

    public function test_requiere_inscripcion_acredita_si_el_participante_tiene_la_sesion_seleccionada(): void
    {
        $this->sesion->update(['requiere_inscripcion' => true]);
        $participante = $this->crearParticipantePagado();
        $this->inscribirParticipanteEnSesion($participante);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->patchJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/participantes/{$participante->id}/checkin")
            ->assertStatus(201);
    }

    public function test_requiere_inscripcion_rechaza_si_el_participante_no_tiene_la_sesion_seleccionada(): void
    {
        $this->sesion->update(['requiere_inscripcion' => true]);
        $participante = $this->crearParticipantePagado();
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->patchJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/participantes/{$participante->id}/checkin")
            ->assertStatus(403)
            ->assertJson(['success' => false, 'error' => 'Este participante no está inscrito a esta sesión.']);

        $this->assertDatabaseCount('asistencia_sesion', 0);
    }

    public function test_requiere_inscripcion_bulk_rechaza_sin_romper_el_resto_del_lote(): void
    {
        $this->sesion->update(['requiere_inscripcion' => true]);
        $conSesion = $this->crearParticipantePagado();
        $this->inscribirParticipanteEnSesion($conSesion);
        $sinSesion = $this->crearParticipantePagado();

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $response = $this->postJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/checkin-bulk", [
            'participante_ids' => [$conSesion->id, $sinSesion->id],
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $data = $response->json();
        $this->assertContains($conSesion->id, $data['acreditados']);
        $this->assertCount(1, $data['rechazados']);
        $this->assertSame($sinSesion->id, $data['rechazados'][0]['participanteId']);
        $this->assertSame('Este participante no está inscrito a esta sesión.', $data['rechazados'][0]['motivo']);
    }

    public function test_requiere_inscripcion_false_sigue_libre_como_siempre(): void
    {
        // Default — ninguna sesión existente cambia de comportamiento.
        $this->assertFalse((bool) $this->sesion->requiere_inscripcion);
        $participante = $this->crearParticipantePagado(); // sin ninguna selección de taller
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->patchJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/participantes/{$participante->id}/checkin")
            ->assertStatus(201);
    }

    public function test_lookup_devuelve_estado_de_asistencia_por_sesion(): void
    {
        $participante = $this->crearParticipantePagado();
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $referencia = $participante->registration->referencia;

        $response = $this->getJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/lookup/{$referencia}");
        $response->assertStatus(200);
        $this->assertFalse($response->json('participantes.0.asistioSesion'));

        $this->patchJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/participantes/{$participante->id}/checkin");

        $response2 = $this->getJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/lookup/{$referencia}");
        $this->assertTrue($response2->json('participantes.0.asistioSesion'));
    }

    public function test_lookup_404_si_la_referencia_es_de_otro_evento(): void
    {
        $participante = $this->crearParticipantePagado();
        $otroEvento = Evento::factory()->create([
            'organizador_id' => $this->evento->organizador_id,
            'tipo_evento_id' => $this->evento->tipo_evento_id,
            'subtipo_evento_id' => $this->evento->subtipo_evento_id,
            'pais_id' => $this->evento->pais_id,
            'ciudad_id' => $this->evento->ciudad_id,
        ]);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $referencia = $participante->registration->referencia;

        $this->getJson("/api/v1/event/{$otroEvento->id}/sesiones/{$this->sesion->id}/lookup/{$referencia}")
            ->assertStatus(404);
    }

    public function test_reporte_calcula_el_porcentaje_de_concurrencia(): void
    {
        $p1 = $this->crearParticipantePagado();
        $this->crearParticipantePagado(); // pagado pero no asiste
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->patchJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/participantes/{$p1->id}/checkin");

        $response = $this->getJson("/api/v1/event/{$this->evento->id}/sesiones-reporte");

        $response->assertStatus(200)->assertJson(['success' => true, 'totalParticipantesPagados' => 2]);
        $this->assertEquals(1, $response->json('data.0.asistieron'));
        $this->assertEquals(50.0, $response->json('data.0.porcentajeConcurrencia'));
    }
}
