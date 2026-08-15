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
 * `GET /event/{event}/participantes` (ParticipanteController::porEvento) —
 * cubre la extensión del 15/08/2026 para la pantalla nueva "Detalle de
 * inscritos" en admin-eventos: filtro por pago_status, campos
 * importe/fechaInscripcion nuevos, y paginación opt-in vía `per_page`
 * (regresión explícita: sin `per_page`, el comportamiento debe ser
 * idéntico al de siempre — NumeracionController/ParticipantesController en
 * admin-eventos no mandan ese parámetro).
 */
class ParticipantesPorEventoTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private FormType $formType;

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
            'organizador_id' => $organizador->id,
            'tipo_evento_id' => $tipoEvento->id,
            'subtipo_evento_id' => $subtipoEvento->id,
            'pais_id' => $pais->id,
            'ciudad_id' => $ciudad->id,
        ]);

        $this->formType = FormType::factory()->create(['event_id' => $this->evento->id]);
        $this->categoria = Category::factory()->create(['event_id' => $this->evento->id, 'name' => '5K', 'price' => 50]);
    }

    private function crearInscripcion(array $overrides = []): Participante
    {
        $registration = Registration::create([
            'referencia' => 'REF' . rand(100000, 999999),
            'fecha' => now(),
            'evento_id' => $this->evento->id,
            'form_types_id' => $this->formType->id,
            'evento_nombre' => $this->evento->nombre,
            'tipo_pago' => 'EFECTIVO',
            'pago_status' => $overrides['pago_status'] ?? 'paid',
        ]);
        unset($overrides['pago_status']);

        return Participante::create(array_merge([
            'registration_id' => $registration->id,
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'genero' => 'Femenino',
            'tipo_documento' => 'DNI', 'numero_documento' => (string) rand(1000000, 9999999),
            'fecha_nacimiento' => '1995-01-01', 'edad' => 30,
            'correo' => 'ana' . rand(1, 999999) . '@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => $this->categoria->id,
            'precio_categoria' => 50, 'subtotal' => 50,
        ], $overrides));
    }

    public function test_sin_per_page_mantiene_el_comportamiento_de_siempre(): void
    {
        $this->crearInscripcion();
        $this->crearInscripcion(['pago_status' => 'pending']);

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $this->evento->id]);

        $response = $this->getJson("/api/v1/event/{$this->evento->id}/participantes")
            ->assertStatus(200)
            ->assertJsonMissingPath('meta');

        $this->assertCount(2, $response->json('participantes'));
    }

    public function test_filtra_por_pago_status(): void
    {
        $this->crearInscripcion(['pago_status' => 'paid']);
        $this->crearInscripcion(['pago_status' => 'pending']);
        $this->crearInscripcion(['pago_status' => 'pending']);

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $this->evento->id]);

        $response = $this->getJson("/api/v1/event/{$this->evento->id}/participantes?pago_status=pending")
            ->assertStatus(200);

        $participantes = $response->json('participantes');
        $this->assertCount(2, $participantes);
        foreach ($participantes as $p) {
            $this->assertSame('pending', $p['pagoStatus']);
        }
    }

    public function test_expone_importe_y_fecha_inscripcion(): void
    {
        $this->crearInscripcion(['subtotal' => 75]);

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $this->evento->id]);

        $response = $this->getJson("/api/v1/event/{$this->evento->id}/participantes")
            ->assertStatus(200);

        $p = $response->json('participantes.0');
        $this->assertEquals(75.0, $p['importe']);
        $this->assertNotNull($p['fechaInscripcion']);
    }

    public function test_pagina_cuando_se_pide_per_page(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->crearInscripcion();
        }

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $this->evento->id]);

        $response = $this->getJson("/api/v1/event/{$this->evento->id}/participantes?per_page=2&page=2")
            ->assertStatus(200);

        $this->assertCount(2, $response->json('participantes'));
        $this->assertSame(2, $response->json('meta.currentPage'));
        $this->assertSame(3, $response->json('meta.lastPage'));
        $this->assertSame(5, $response->json('meta.total'));
    }

    public function test_admin_de_otro_evento_no_ve_los_participantes(): void
    {
        $otroEvento = Evento::factory()->create([
            'organizador_id' => $this->evento->organizador_id,
            'tipo_evento_id' => $this->evento->tipo_evento_id,
            'subtipo_evento_id' => $this->evento->subtipo_evento_id,
            'pais_id' => $this->evento->pais_id,
            'ciudad_id' => $this->evento->ciudad_id,
        ]);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $otroEvento->id]);

        $this->getJson("/api/v1/event/{$this->evento->id}/participantes")->assertStatus(403);
    }
}
