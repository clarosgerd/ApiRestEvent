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
 * `PATCH /participantes/{participante}` — habilitar edición de
 * `numero_documento` desde el panel de admin (04/09/2026). Antes estaba
 * excluido a propósito ("identidad, anti-fraude") — pedido explícito del
 * usuario: un admin necesita poder corregir un documento mal cargado.
 */
class ParticipanteUpdateNumeroDocumentoTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private Participante $participante;

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

        $formType = FormType::factory()->create(['event_id' => $this->evento->id]);
        $categoria = Category::factory()->create(['event_id' => $this->evento->id, 'price' => 50]);

        $registration = Registration::create([
            'referencia' => 'REF' . rand(100000, 999999),
            'fecha' => now(),
            'evento_id' => $this->evento->id,
            'form_types_id' => $formType->id,
            'evento_nombre' => $this->evento->nombre,
            'tipo_pago' => 'EFECTIVO',
            'pago_status' => 'paid',
        ]);

        $this->participante = Participante::create([
            'registration_id' => $registration->id,
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'genero' => 'Femenino',
            'tipo_documento' => 'DNI', 'numero_documento' => '11111111',
            'fecha_nacimiento' => '1995-01-01', 'edad' => 30,
            'correo' => 'ana@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => $categoria->id, 'precio_categoria' => 50, 'subtotal' => 50,
        ]);
    }

    public function test_admin_puede_corregir_numero_documento(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $this->evento->id]);

        $this->patchJson("/api/v1/participantes/{$this->participante->id}", [
            'numero_documento' => '22222222',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('participantes', [
            'id' => $this->participante->id,
            'numero_documento' => '22222222',
        ]);
    }

    public function test_corregir_numero_documento_queda_auditado(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $this->evento->id]);

        $this->patchJson("/api/v1/participantes/{$this->participante->id}", [
            'numero_documento' => '33333333',
        ])->assertOk();

        $this->assertDatabaseHas('admin_audit_logs', [
            'accion' => 'update',
            'entidad' => 'participante',
            'entidad_id' => $this->participante->id,
        ]);
    }

    public function test_admin_de_otro_evento_no_puede_corregir_numero_documento(): void
    {
        $otroEvento = Evento::factory()->create();
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $otroEvento->id]);

        $this->patchJson("/api/v1/participantes/{$this->participante->id}", [
            'numero_documento' => '44444444',
        ])->assertStatus(403);

        $this->assertDatabaseHas('participantes', [
            'id' => $this->participante->id,
            'numero_documento' => '11111111',
        ]);
    }
}
