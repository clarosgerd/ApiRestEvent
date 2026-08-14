<?php

namespace Tests\Feature;

use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Participante;
use App\Models\Registration;
use App\Models\SesionCongreso;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Asignación de participantes Staff/Ayudante a sesiones de congreso — ver
 * SesionCongresoStaffController y
 * brain/PLAN-ASIGNACION-STAFF-SESIONES-CONGRESO-13082026.md.
 */
class SesionCongresoStaffTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

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
        $this->sesion = SesionCongreso::factory()->create(['evento_id' => $this->evento->id]);
    }

    private function crearParticipante(array $formTypeOverrides = [], array $registrationOverrides = []): Participante
    {
        $formType = FormType::factory()->create(array_merge([
            'event_id' => $this->evento->id,
        ], $formTypeOverrides));

        $registration = Registration::factory()->create(array_merge([
            'evento_id' => $this->evento->id,
            'form_types_id' => $formType->id,
            'referencia' => 'LA-STAFF-'.uniqid(),
            'fecha' => now(),
            'evento_nombre' => $this->evento->nombre,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'paid',
        ], $registrationOverrides));

        return Participante::create([
            'registration_id' => $registration->id,
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'genero' => 'Femenino',
            'tipo_documento' => 'DNI', 'numero_documento' => (string) rand(10000000, 99999999),
            'fecha_nacimiento' => '1995-01-01', 'edad' => 30, 'correo' => 'ana'.rand(1, 99999).'@test.net',
            'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => 'N/A', 'subtotal' => 0,
        ]);
    }

    public function test_lista_solo_participantes_de_form_types_es_staff(): void
    {
        $staff = $this->crearParticipante(['es_staff' => true]);
        $this->crearParticipante(['es_staff' => false]);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $response = $this->getJson("/api/v1/event/{$this->evento->id}/staff-disponible");

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertEquals([$staff->id], $ids);
    }

    public function test_asigna_un_staff_a_una_sesion(): void
    {
        $staff = $this->crearParticipante(['es_staff' => true]);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $response = $this->postJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/staff", [
            'participante_id' => $staff->id,
        ]);

        $response->assertStatus(201)->assertJson(['success' => true, 'alreadyAssigned' => false]);
        $this->assertDatabaseHas('sesion_congreso_staff', [
            'sesion_congreso_id' => $this->sesion->id,
            'participante_id' => $staff->id,
            'asignado_por_admin_user_id' => $admin->id,
        ]);
    }

    public function test_rechaza_asignar_un_participante_que_no_es_staff(): void
    {
        $noStaff = $this->crearParticipante(['es_staff' => false]);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/staff", [
            'participante_id' => $noStaff->id,
        ])->assertStatus(422);

        $this->assertDatabaseCount('sesion_congreso_staff', 0);
    }

    public function test_asignar_dos_veces_no_duplica(): void
    {
        $staff = $this->crearParticipante(['es_staff' => true]);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/staff", [
            'participante_id' => $staff->id,
        ])->assertStatus(201);

        $this->postJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/staff", [
            'participante_id' => $staff->id,
        ])->assertStatus(200)->assertJson(['success' => true, 'alreadyAssigned' => true]);

        $this->assertDatabaseCount('sesion_congreso_staff', 1);
    }

    public function test_un_staff_puede_apoyar_varias_sesiones(): void
    {
        $staff = $this->crearParticipante(['es_staff' => true]);
        $otraSesion = SesionCongreso::factory()->create(['evento_id' => $this->evento->id]);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/staff", ['participante_id' => $staff->id])
            ->assertStatus(201);
        $this->postJson("/api/v1/event/{$this->evento->id}/sesiones/{$otraSesion->id}/staff", ['participante_id' => $staff->id])
            ->assertStatus(201);

        $this->assertDatabaseCount('sesion_congreso_staff', 2);
        $this->assertEquals(2, $staff->sesionesApoyadas()->count());
    }

    public function test_una_sesion_puede_tener_varios_staff(): void
    {
        $staff1 = $this->crearParticipante(['es_staff' => true]);
        $staff2 = $this->crearParticipante(['es_staff' => true]);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/staff", ['participante_id' => $staff1->id])
            ->assertStatus(201);
        $this->postJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/staff", ['participante_id' => $staff2->id])
            ->assertStatus(201);

        $this->assertEquals(2, $this->sesion->staffAsignado()->count());
    }

    public function test_desasigna_un_staff_de_una_sesion(): void
    {
        $staff = $this->crearParticipante(['es_staff' => true]);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/staff", ['participante_id' => $staff->id])
            ->assertStatus(201);

        $this->deleteJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/staff/{$staff->id}")
            ->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseCount('sesion_congreso_staff', 0);
    }

    public function test_rechaza_asignar_un_participante_de_otro_evento(): void
    {
        $otroEvento = Evento::factory()->create([
            'organizador_id' => $this->evento->organizador_id,
            'tipo_evento_id' => $this->evento->tipo_evento_id,
            'subtipo_evento_id' => $this->evento->subtipo_evento_id,
            'pais_id' => $this->evento->pais_id,
            'ciudad_id' => $this->evento->ciudad_id,
        ]);
        $formTypeOtroEvento = FormType::factory()->create(['event_id' => $otroEvento->id, 'es_staff' => true]);
        $registration = Registration::factory()->create([
            'evento_id' => $otroEvento->id,
            'form_types_id' => $formTypeOtroEvento->id,
            'referencia' => 'LA-STAFF-OTRO-'.uniqid(),
            'fecha' => now(),
            'evento_nombre' => $otroEvento->nombre,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'paid',
        ]);
        $staffDeOtroEvento = Participante::create([
            'registration_id' => $registration->id,
            'nombre' => 'Otro', 'apellido' => 'Evento', 'genero' => 'Masculino',
            'tipo_documento' => 'DNI', 'numero_documento' => (string) rand(10000000, 99999999),
            'fecha_nacimiento' => '1995-01-01', 'edad' => 30, 'correo' => 'otro'.rand(1, 99999).'@test.net',
            'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => 'N/A', 'subtotal' => 0,
        ]);

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/staff", [
            'participante_id' => $staffDeOtroEvento->id,
        ])->assertStatus(404);

        $this->assertDatabaseCount('sesion_congreso_staff', 0);
    }

    // ── Rol "ponente" (13/08/2026, mismo día) ──────────────────────────
    // Ver brain/PLAN-VINCULACION-PONENTES-SESIONES-CONGRESO-13082026.md.

    public function test_disponibles_filtra_por_rol_ponente(): void
    {
        $ponente = $this->crearParticipante(['es_ponente' => true]);
        $this->crearParticipante(['es_staff' => true]); // no debe aparecer al pedir rol=ponente
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $response = $this->getJson("/api/v1/event/{$this->evento->id}/staff-disponible?rol=ponente");

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertEquals([$ponente->id], $ids);
    }

    public function test_vincula_un_ponente_a_una_sesion(): void
    {
        $ponente = $this->crearParticipante(['es_ponente' => true]);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $response = $this->postJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/staff", [
            'participante_id' => $ponente->id,
            'rol' => 'ponente',
        ]);

        $response->assertStatus(201)->assertJson(['success' => true, 'alreadyAssigned' => false]);
        $this->assertDatabaseHas('sesion_congreso_staff', [
            'sesion_congreso_id' => $this->sesion->id,
            'participante_id' => $ponente->id,
            'rol' => 'ponente',
        ]);
        $this->assertEquals(1, $this->sesion->ponentesVinculados()->count());
        $this->assertEquals(0, $this->sesion->staffAsignado()->count());
    }

    public function test_rechaza_vincular_como_ponente_a_alguien_sin_flag_es_ponente(): void
    {
        $staff = $this->crearParticipante(['es_staff' => true]); // tiene es_staff, NO es_ponente
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/staff", [
            'participante_id' => $staff->id,
            'rol' => 'ponente',
        ])->assertStatus(422);

        $this->assertDatabaseCount('sesion_congreso_staff', 0);
    }

    public function test_un_participante_puede_ser_staff_y_ponente_de_la_misma_sesion_a_la_vez(): void
    {
        $persona = $this->crearParticipante(['es_staff' => true, 'es_ponente' => true]);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/staff", [
            'participante_id' => $persona->id, 'rol' => 'staff',
        ])->assertStatus(201);
        $this->postJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/staff", [
            'participante_id' => $persona->id, 'rol' => 'ponente',
        ])->assertStatus(201);

        $this->assertDatabaseCount('sesion_congreso_staff', 2);
        $this->assertEquals(1, $this->sesion->staffAsignado()->count());
        $this->assertEquals(1, $this->sesion->ponentesVinculados()->count());
    }

    public function test_desvincula_un_ponente_sin_afectar_su_rol_de_staff(): void
    {
        $persona = $this->crearParticipante(['es_staff' => true, 'es_ponente' => true]);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/staff", [
            'participante_id' => $persona->id, 'rol' => 'staff',
        ])->assertStatus(201);
        $this->postJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/staff", [
            'participante_id' => $persona->id, 'rol' => 'ponente',
        ])->assertStatus(201);

        $this->deleteJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/staff/{$persona->id}?rol=ponente")
            ->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseCount('sesion_congreso_staff', 1);
        $this->assertEquals(1, $this->sesion->staffAsignado()->count());
        $this->assertEquals(0, $this->sesion->ponentesVinculados()->count());
    }

    public function test_un_ponente_puede_exponer_en_varias_sesiones(): void
    {
        $ponente = $this->crearParticipante(['es_ponente' => true]);
        $otraSesion = SesionCongreso::factory()->create(['evento_id' => $this->evento->id]);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson("/api/v1/event/{$this->evento->id}/sesiones/{$this->sesion->id}/staff", ['participante_id' => $ponente->id, 'rol' => 'ponente'])
            ->assertStatus(201);
        $this->postJson("/api/v1/event/{$this->evento->id}/sesiones/{$otraSesion->id}/staff", ['participante_id' => $ponente->id, 'rol' => 'ponente'])
            ->assertStatus(201);

        $this->assertEquals(2, $ponente->sesionesExpuestas()->count());
    }
}
