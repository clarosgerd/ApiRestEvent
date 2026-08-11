<?php

namespace Tests\Feature;

use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Registration;
use App\Models\RegistrationTotal;
use App\Models\Socio;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Consolidación financiera — liquidación de utilidades entre socios. Ver
 * LiquidarEventoAction y elascenso/event/brain/ (sesión 11/08/2026).
 */
class LiquidacionTest extends TestCase
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
            'estado_evento_id' => 'closed',
        ]);

        // 4 socios de fábrica (40/35/15/10) ya vienen del seed de la
        // migración — este test los reemplaza por su propio set controlado
        // para no depender de datos de seed en un test unitario.
        Socio::query()->delete();
        Socio::factory()->create(['nombre' => 'Socio A', 'porcentaje' => 60, 'activo' => true]);
        Socio::factory()->create(['nombre' => 'Socio B', 'porcentaje' => 40, 'activo' => true]);
    }

    private function crearInscripcionPagada(float $fee): Registration
    {
        $formType = FormType::factory()->create(['event_id' => $this->evento->id]);

        $registration = Registration::factory()->create([
            'evento_id' => $this->evento->id,
            'form_types_id' => $formType->id,
            'referencia' => 'LA-LIQ-'.uniqid(),
            'fecha' => now(),
            'evento_nombre' => $this->evento->nombre,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'paid',
        ]);

        RegistrationTotal::create([
            'registration_id' => $registration->id,
            'inscripcion' => 100,
            'fee' => $fee,
            'grand_total' => 100 + $fee,
        ]);

        return $registration;
    }

    public function test_preview_no_persiste_nada(): void
    {
        $this->crearInscripcionPagada(10.00);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $response = $this->getJson("/api/v1/event/{$this->evento->id}/liquidacion/preview");

        $response->assertStatus(200)->assertJson([
            'success' => true,
            'evento_cerrado' => true,
            'data' => [
                'monto_base' => 10.0,
                'cantidad_inscripciones' => 1,
                'porcentaje_total' => 100.0,
            ],
        ]);
        $this->assertDatabaseCount('liquidaciones', 0);
    }

    public function test_liquidar_reparte_exacto_al_centavo_con_redondeo(): void
    {
        // 33.33 * 60% = 19.998 -> redondea a 20.00; el resto (13.33) le
        // queda al segundo socio por absorción de residuo, no 13.332.
        $this->crearInscripcionPagada(33.33);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $response = $this->postJson("/api/v1/event/{$this->evento->id}/liquidacion");

        $response->assertStatus(201)->assertJson(['success' => true]);

        $liquidacion = $response->json('data');
        $sumaDetalles = array_sum(array_column($liquidacion['detalles'], 'monto'));

        $this->assertEqualsWithDelta(33.33, $sumaDetalles, 0.001);
        $this->assertDatabaseCount('liquidaciones', 1);
        $this->assertDatabaseCount('liquidacion_detalles', 2);
    }

    public function test_no_se_puede_liquidar_dos_veces_el_mismo_evento(): void
    {
        $this->crearInscripcionPagada(10.00);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson("/api/v1/event/{$this->evento->id}/liquidacion")->assertStatus(201);
        $this->postJson("/api/v1/event/{$this->evento->id}/liquidacion")
            ->assertStatus(409)
            ->assertJson(['success' => false, 'error' => 'Este evento ya fue liquidado.']);

        $this->assertDatabaseCount('liquidaciones', 1);
    }

    public function test_no_se_puede_liquidar_un_evento_no_cerrado(): void
    {
        $this->evento->update(['estado_evento_id' => 'open']);
        $this->crearInscripcionPagada(10.00);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson("/api/v1/event/{$this->evento->id}/liquidacion")
            ->assertStatus(422)
            ->assertJson(['success' => false, 'error' => 'El evento debe estar cerrado antes de poder liquidarlo.']);
    }

    public function test_bloquea_si_los_porcentajes_de_socios_no_suman_100(): void
    {
        Socio::query()->update(['porcentaje' => 30]); // 30+30 = 60, no 100
        $this->crearInscripcionPagada(10.00);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson("/api/v1/event/{$this->evento->id}/liquidacion")
            ->assertStatus(422);

        $this->assertDatabaseCount('liquidaciones', 0);
    }

    public function test_solo_super_admin_puede_liquidar(): void
    {
        $this->crearInscripcionPagada(10.00);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $this->evento->id]);

        $this->postJson("/api/v1/event/{$this->evento->id}/liquidacion")->assertStatus(403);
        $this->getJson("/api/v1/event/{$this->evento->id}/liquidacion/preview")->assertStatus(403);
    }

    public function test_editar_un_socio_despues_no_reinterpreta_liquidaciones_viejas(): void
    {
        $this->crearInscripcionPagada(10.00);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson("/api/v1/event/{$this->evento->id}/liquidacion")->assertStatus(201);

        $socioA = Socio::where('nombre', 'Socio A')->first();
        $socioA->update(['nombre' => 'Socio A Renombrado', 'porcentaje' => 99]);

        $detalle = $this->getJson("/api/v1/event/{$this->evento->id}/liquidacion")
            ->json('data.detalles.0');

        $this->assertSame('Socio A', $detalle['socio_nombre']);
        $this->assertEquals(60.0, $detalle['porcentaje']);
    }
}
