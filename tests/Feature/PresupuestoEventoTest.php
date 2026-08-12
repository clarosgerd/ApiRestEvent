<?php

namespace Tests\Feature;

use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\PresupuestoCategoria;
use App\Models\PresupuestoEvento;
use App\Models\Pais;
use App\Models\Registration;
use App\Models\RegistrationTotal;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Presupuesto de un evento — movimientos manuales de ingreso/gasto del
 * organizador (admin scoped a su evento, o super_admin). Ver
 * PresupuestoEventoController y BalanceEventoData.
 */
class PresupuestoEventoTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private PresupuestoCategoria $categoriaGasto;

    private PresupuestoCategoria $categoriaIngreso;

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

        $this->categoriaGasto = PresupuestoCategoria::factory()->create(['tipo' => 'gasto']);
        $this->categoriaIngreso = PresupuestoCategoria::factory()->create(['tipo' => 'ingreso']);
    }

    public function test_admin_scoped_a_su_evento_puede_registrar_gasto(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $this->evento->id]);

        $response = $this->postJson("/api/v1/event/{$this->evento->id}/presupuesto", [
            'presupuesto_categoria_id' => $this->categoriaGasto->id,
            'tipo' => 'gasto',
            'monto' => 150.50,
            'moneda' => 'BOB',
            'fecha' => '2026-08-01',
        ]);

        $response->assertStatus(201)->assertJson(['success' => true]);
        $this->assertDatabaseHas('presupuesto_evento', [
            'evento_id' => $this->evento->id,
            'monto' => 150.50,
            'admin_user_id' => $admin->id,
        ]);
    }

    public function test_admin_de_otro_evento_no_puede_registrar(): void
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

        $this->postJson("/api/v1/event/{$this->evento->id}/presupuesto", [
            'presupuesto_categoria_id' => $this->categoriaGasto->id,
            'tipo' => 'gasto',
            'monto' => 50,
            'fecha' => '2026-08-01',
        ])->assertStatus(403);
    }

    public function test_rechaza_si_el_tipo_no_coincide_con_la_categoria(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson("/api/v1/event/{$this->evento->id}/presupuesto", [
            'presupuesto_categoria_id' => $this->categoriaGasto->id, // es de tipo gasto
            'tipo' => 'ingreso', // pero se manda ingreso
            'monto' => 50,
            'fecha' => '2026-08-01',
        ])->assertStatus(422);

        $this->assertDatabaseCount('presupuesto_evento', 0);
    }

    public function test_no_permite_borrar_movimiento_de_otro_evento(): void
    {
        $otroEvento = Evento::factory()->create([
            'organizador_id' => $this->evento->organizador_id,
            'tipo_evento_id' => $this->evento->tipo_evento_id,
            'subtipo_evento_id' => $this->evento->subtipo_evento_id,
            'pais_id' => $this->evento->pais_id,
            'ciudad_id' => $this->evento->ciudad_id,
        ]);
        $movimiento = PresupuestoEvento::factory()->create([
            'evento_id' => $otroEvento->id,
            'presupuesto_categoria_id' => $this->categoriaGasto->id,
            'tipo' => 'gasto',
        ]);

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        // Ruta scoped al evento equivocado (this->evento, no $otroEvento) —
        // debe dar 404, no operar sobre un movimiento de otro evento.
        $this->deleteJson("/api/v1/event/{$this->evento->id}/presupuesto/{$movimiento->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('presupuesto_evento', ['id' => $movimiento->id]);
    }

    public function test_balance_excluye_el_fee_del_ingreso_por_inscripciones(): void
    {
        $formType = FormType::factory()->create(['event_id' => $this->evento->id]);
        $registration = Registration::factory()->create([
            'evento_id' => $this->evento->id,
            'form_types_id' => $formType->id,
            'referencia' => 'LA-PRES-'.uniqid(),
            'fecha' => now(),
            'evento_nombre' => $this->evento->nombre,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'paid',
        ]);
        RegistrationTotal::create([
            'registration_id' => $registration->id,
            'inscripcion' => 100,
            'donacion' => 20,
            'souvenirs' => 5,
            'fee' => 6.25, // 5% de 125 — NO debe contarse como ingreso del organizador
            'descuento' => 0,
            'descuento_registrante' => 0,
            'grand_total' => 131.25,
        ]);

        PresupuestoEvento::factory()->create([
            'evento_id' => $this->evento->id,
            'presupuesto_categoria_id' => $this->categoriaIngreso->id,
            'tipo' => 'ingreso',
            'monto' => 50,
        ]);
        PresupuestoEvento::factory()->create([
            'evento_id' => $this->evento->id,
            'presupuesto_categoria_id' => $this->categoriaGasto->id,
            'tipo' => 'gasto',
            'monto' => 30,
        ]);

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $response = $this->getJson("/api/v1/event/{$this->evento->id}/dashboard-inscripciones");

        $response->assertStatus(200);
        $balance = $response->json('balance');

        // inscripcion+donacion+souvenirs-descuentos = 100+20+5-0-0 = 125, SIN el fee de 6.25
        $this->assertEquals(125.0, $balance['ingresosInscripciones']);
        $this->assertEquals(50.0, $balance['ingresosManuales']);
        $this->assertEquals(30.0, $balance['gastosManuales']);
        $this->assertEquals(175.0, $balance['ingresosTotales']); // 125 + 50
        $this->assertEquals(145.0, $balance['utilidadNeta']); // 175 - 30
    }
}
