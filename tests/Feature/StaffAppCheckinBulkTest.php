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
 * App de staff offline (02/10/2026) — sync-back de check-ins hechos sin
 * conexión. Ver StaffAppController::checkinBulk() + CheckinParticipanteAction.
 */
class StaffAppCheckinBulkTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private Category $categoria;

    private Persona $staffPersona;

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
    }

    private function crearParticipante(FormType $formType, array $overrides = [], string $pagoStatus = 'paid', ?Evento $evento = null): Participante
    {
        $evento ??= $this->evento;
        $registration = Registration::factory()->create([
            'evento_id' => $evento->id, 'form_types_id' => $formType->id,
            'referencia' => 'LA-STAFF-' . uniqid(), 'fecha' => now(), 'evento_nombre' => $evento->nombre,
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

    public function test_acredita_un_participante_pagado(): void
    {
        $formType = FormType::factory()->create(['event_id' => $this->evento->id, 'requiere_categoria' => true]);
        $participante = $this->crearParticipante($formType);

        $response = $this->postJson("/api/v1/persona/eventos/{$this->evento->id}/checkin-bulk", [
            'checkins' => [['participanteId' => $participante->id, 'checkedInAt' => now()->subHours(2)->toIso8601String()]],
        ]);

        $response->assertOk()->assertJson(['success' => true, 'acreditados' => [$participante->id], 'yaAcreditados' => [], 'rechazados' => []]);
        $this->assertNotNull($participante->fresh()->checked_in_at);
        $this->assertDatabaseHas('admin_audit_logs', [
            'entidad' => 'participante', 'entidad_id' => $participante->id,
            'persona_id' => $this->staffPersona->id, 'admin_user_id' => null,
        ]);
    }

    public function test_reescanear_offline_no_pisa_el_timestamp_original_gana_el_primero(): void
    {
        $formType = FormType::factory()->create(['event_id' => $this->evento->id, 'requiere_categoria' => true]);
        $participante = $this->crearParticipante($formType);
        $primerTimestamp = now()->subHours(3);
        $participante->update(['checked_in_at' => $primerTimestamp]);

        $response = $this->postJson("/api/v1/persona/eventos/{$this->evento->id}/checkin-bulk", [
            'checkins' => [['participanteId' => $participante->id, 'checkedInAt' => now()->subHour()->toIso8601String()]],
        ]);

        $response->assertOk()->assertJson(['success' => true, 'acreditados' => [], 'yaAcreditados' => [$participante->id]]);
        $this->assertEquals($primerTimestamp->timestamp, $participante->fresh()->checked_in_at->timestamp);
    }

    public function test_rechaza_participante_no_pagado(): void
    {
        $formType = FormType::factory()->create(['event_id' => $this->evento->id, 'requiere_categoria' => true]);
        $participante = $this->crearParticipante($formType, [], 'pending');

        $response = $this->postJson("/api/v1/persona/eventos/{$this->evento->id}/checkin-bulk", [
            'checkins' => [['participanteId' => $participante->id, 'checkedInAt' => now()->toIso8601String()]],
        ]);

        $response->assertOk()->assertJson(['success' => true, 'acreditados' => []]);
        $this->assertCount(1, $response->json('rechazados'));
        $this->assertNull($participante->fresh()->checked_in_at);
    }

    public function test_rechaza_timestamp_futuro(): void
    {
        $formType = FormType::factory()->create(['event_id' => $this->evento->id, 'requiere_categoria' => true]);
        $participante = $this->crearParticipante($formType);

        $response = $this->postJson("/api/v1/persona/eventos/{$this->evento->id}/checkin-bulk", [
            'checkins' => [['participanteId' => $participante->id, 'checkedInAt' => now()->addDay()->toIso8601String()]],
        ]);

        $response->assertOk()->assertJson(['success' => true, 'acreditados' => []]);
        $this->assertCount(1, $response->json('rechazados'));
        $this->assertNull($participante->fresh()->checked_in_at);
    }

    public function test_rechaza_participante_de_otro_evento_sin_abortar_el_resto_del_lote(): void
    {
        $otroEvento = Evento::factory()->create([
            'organizador_id' => $this->evento->organizador_id, 'tipo_evento_id' => $this->evento->tipo_evento_id,
            'subtipo_evento_id' => $this->evento->subtipo_evento_id, 'pais_id' => $this->evento->pais_id, 'ciudad_id' => $this->evento->ciudad_id,
        ]);
        $formTypeOtro = FormType::factory()->create(['event_id' => $otroEvento->id, 'requiere_categoria' => true]);
        $deOtroEvento = $this->crearParticipante($formTypeOtro, [], 'paid', $otroEvento);

        $formType = FormType::factory()->create(['event_id' => $this->evento->id, 'requiere_categoria' => true]);
        $valido = $this->crearParticipante($formType);

        $response = $this->postJson("/api/v1/persona/eventos/{$this->evento->id}/checkin-bulk", [
            'checkins' => [
                ['participanteId' => $deOtroEvento->id, 'checkedInAt' => now()->toIso8601String()],
                ['participanteId' => $valido->id, 'checkedInAt' => now()->toIso8601String()],
            ],
        ]);

        $response->assertOk()->assertJson(['success' => true, 'acreditados' => [$valido->id]]);
        $this->assertCount(1, $response->json('rechazados'));
        $this->assertNull($deOtroEvento->fresh()->checked_in_at);
        $this->assertNotNull($valido->fresh()->checked_in_at);
    }

    public function test_rechaza_con_403_si_no_es_staff_del_evento(): void
    {
        $this->actingAsPersona(Persona::factory()->create());
        $formType = FormType::factory()->create(['event_id' => $this->evento->id, 'requiere_categoria' => true]);
        $participante = $this->crearParticipante($formType);

        $this->postJson("/api/v1/persona/eventos/{$this->evento->id}/checkin-bulk", [
            'checkins' => [['participanteId' => $participante->id, 'checkedInAt' => now()->toIso8601String()]],
        ])->assertStatus(403);
    }
}
