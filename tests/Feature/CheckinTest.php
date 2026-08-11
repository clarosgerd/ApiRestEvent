<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Participante;
use App\Models\Registration;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Acreditación (check-in) escaneando el QR de referencia — panel de
 * administración. GET /event/{event}/checkin/{reference} (lookup) +
 * PATCH /participantes/{participante}/checkin (marcar presente). El QR ya
 * existía (ReferenceQrService) pero no tenía consumidor — ver
 * elascenso/event/brain/ (sesión 10/08/2026).
 *
 * `ParticipanteFactory` no sirve para crear un `Participante` válido acá
 * (tiene campos de otro modelo — bug preexistente, fuera de alcance) —
 * se construye directo con `Participante::create()`.
 */
class CheckinTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private Category $categoria;

    private Registration $registration;

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
        $this->categoria = Category::factory()->create(['event_id' => $this->evento->id, 'name' => '10K']);
        $formType = FormType::factory()->create(['event_id' => $this->evento->id]);

        $this->registration = Registration::factory()->create([
            'evento_id' => $this->evento->id,
            'form_types_id' => $formType->id,
            'referencia' => 'LA-CHECKIN-'.uniqid(),
            'fecha' => now(),
            'evento_nombre' => $this->evento->nombre,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'paid',
        ]);
    }

    private function crearParticipante(array $overrides = []): Participante
    {
        return Participante::create(array_merge([
            'registration_id' => $this->registration->id,
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'genero' => 'Femenino',
            'tipo_documento' => 'DNI', 'numero_documento' => '12345678',
            'fecha_nacimiento' => '1995-01-01', 'edad' => 30, 'correo' => 'ana@test.net',
            'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => (string) $this->categoria->id, 'subtotal' => 50,
        ], $overrides));
    }

    public function test_lookup_devuelve_participantes_con_categoria_resuelta(): void
    {
        $participante = $this->crearParticipante();
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $response = $this->getJson("/api/v1/event/{$this->evento->id}/checkin/{$this->registration->referencia}");

        $response->assertStatus(200)->assertJson([
            'success' => true,
            'pagoStatus' => 'paid',
        ]);
        $this->assertSame('10K', $response->json('participantes.0.categoria'));
        $this->assertNull($response->json('participantes.0.checkedInAt'));
    }

    public function test_lookup_404_si_la_referencia_es_de_otro_evento(): void
    {
        $this->crearParticipante();
        $otroEvento = Evento::factory()->create([
            'organizador_id' => $this->evento->organizador_id,
            'tipo_evento_id' => $this->evento->tipo_evento_id,
            'subtipo_evento_id' => $this->evento->subtipo_evento_id,
            'pais_id' => $this->evento->pais_id,
            'ciudad_id' => $this->evento->ciudad_id,
        ]);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->getJson("/api/v1/event/{$otroEvento->id}/checkin/{$this->registration->referencia}")
            ->assertStatus(404);
    }

    public function test_checkin_rechaza_con_422_si_no_esta_pagado(): void
    {
        $this->registration->update(['pago_status' => 'pending']);
        $participante = $this->crearParticipante();
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->patchJson("/api/v1/participantes/{$participante->id}/checkin")
            ->assertStatus(422)
            ->assertJson(['success' => false, 'error' => 'No se puede acreditar: el pago no está confirmado.']);

        $this->assertNull($participante->refresh()->checked_in_at);
    }

    public function test_checkin_marca_presente(): void
    {
        $participante = $this->crearParticipante();
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $response = $this->patchJson("/api/v1/participantes/{$participante->id}/checkin");

        $response->assertStatus(200)->assertJson(['success' => true, 'alreadyCheckedIn' => false]);
        $this->assertNotNull($participante->refresh()->checked_in_at);
    }

    public function test_reescanear_no_pisa_el_timestamp_original(): void
    {
        $participante = $this->crearParticipante();
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->patchJson("/api/v1/participantes/{$participante->id}/checkin")->assertStatus(200);
        $primerTimestamp = $participante->refresh()->checked_in_at;

        sleep(1);
        $response = $this->patchJson("/api/v1/participantes/{$participante->id}/checkin");

        $response->assertStatus(200)->assertJson(['success' => true, 'alreadyCheckedIn' => true]);
        $this->assertEquals($primerTimestamp, $participante->refresh()->checked_in_at);
    }

    public function test_admin_scoped_a_otro_evento_no_puede_acreditar(): void
    {
        $participante = $this->crearParticipante();
        $otroEvento = Evento::factory()->create([
            'organizador_id' => $this->evento->organizador_id,
            'tipo_evento_id' => $this->evento->tipo_evento_id,
            'subtipo_evento_id' => $this->evento->subtipo_evento_id,
            'pais_id' => $this->evento->pais_id,
            'ciudad_id' => $this->evento->ciudad_id,
        ]);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $otroEvento->id]);

        $this->patchJson("/api/v1/participantes/{$participante->id}/checkin")
            ->assertStatus(403);

        $this->getJson("/api/v1/event/{$this->evento->id}/checkin/{$this->registration->referencia}")
            ->assertStatus(403);
    }
}
