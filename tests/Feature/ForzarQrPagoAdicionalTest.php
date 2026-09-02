<?php

namespace Tests\Feature;

use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Pagar en el evento (efectivo)" al agregar un taller a una inscripción
 * pagada — configurable por evento (02/09/2026). Pedido del usuario: poder
 * sacar esa opción y dejar QR como única forma de pagar el adicional.
 * Mismo patrón que EventoFeePctTest, pero sin la restricción a
 * super_admin — es un flag operativo, no financiero-sensible, así que un
 * admin scoped al evento también puede tocarlo (igual que
 * feeIncluyeTalleres/usdPrecioFijo).
 */
class ForzarQrPagoAdicionalTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

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
    }

    public function test_evento_nuevo_tiene_false_por_default(): void
    {
        // Eventos existentes/nuevos sin tocar este flag siguen mostrando
        // ambas opciones de pago del adicional — comportamiento sin cambios.
        $this->assertFalse((bool) $this->evento->refresh()->forzar_qr_pago_adicional);
    }

    public function test_event_resource_expone_forzar_qr_pago_adicional(): void
    {
        $this->getJson("/api/v1/event/{$this->evento->id}")
            ->assertOk()
            ->assertJsonPath('eventos.forzarQrPagoAdicional', false);
    }

    public function test_admin_scoped_puede_activarlo(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $this->evento->id]);

        $this->putJson("/api/v1/event/{$this->evento->id}", ['forzarQrPagoAdicional' => true])
            ->assertOk();

        $this->assertTrue((bool) $this->evento->refresh()->forzar_qr_pago_adicional);

        $this->getJson("/api/v1/event/{$this->evento->id}")
            ->assertOk()
            ->assertJsonPath('eventos.forzarQrPagoAdicional', true);
    }

    public function test_admin_scoped_puede_actualizar_otros_campos_sin_tocar_este_flag(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $this->evento->id]);

        $this->putJson("/api/v1/event/{$this->evento->id}", ['description' => 'Nueva descripción'])
            ->assertOk();

        $this->evento->refresh();
        $this->assertSame('Nueva descripción', $this->evento->descripcion);
        $this->assertFalse((bool) $this->evento->forzar_qr_pago_adicional);
    }
}
