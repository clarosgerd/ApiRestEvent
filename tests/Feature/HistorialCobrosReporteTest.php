<?php

namespace Tests\Feature;

use App\Actions\ActualizarInscripcionPagadaAction;
use App\Actions\ConfirmarPagoAdicionalAction;
use App\Actions\CrearInscripcionAction;
use App\Actions\GenerarPagoAdicionalAction;
use App\DTOs\RegistrationDTO;
use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\PagoAdicionalInscripcion;
use App\Models\SesionCongreso;
use App\Models\SubtipoEvento;
use App\Models\Taller;
use App\Models\TipoEvento;
use App\Support\HistorialCobrosReporteData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reporte de historial de cobros por inscripción (22/10/2026, pedido real
 * del usuario) — ver App\Support\HistorialCobrosReporteData.
 */
class HistorialCobrosReporteTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private FormType $formType;

    private Category $categoria;

    private Taller $taller;

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
            'fee_pct' => 0.05,
        ]);
        $this->formType = FormType::factory()->create([
            'event_id' => $this->evento->id, 'requiere_categoria' => true, 'costo_edicion' => 10,
        ]);
        $this->categoria = Category::factory()->create(['event_id' => $this->evento->id, 'price' => 50]);
        $this->taller = Taller::factory()->create(['evento_id' => $this->evento->id, 'modalidad' => 'OPTIONAL', 'precio' => 30]);
        $this->sesion = SesionCongreso::factory()->create(['evento_id' => $this->evento->id, 'taller_id' => $this->taller->id, 'cupo' => 10]);
    }

    private function participanteData(string $numeroDocumento, array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'alias' => '', 'genero' => 'Femenino',
            'tipoDocumento' => 'DNI', 'numeroDocumento' => $numeroDocumento,
            'polera' => '', 'precioPolera' => 0,
            'nacimiento' => ['dia' => 1, 'mes' => 1, 'anio' => 1995], 'edad' => 30,
            'correo' => 'ana'.rand(1, 999999).'@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'contacto_emergencia' => ['nombre' => 'X', 'celular' => '123', 'relacion' => 'Madre'],
            'souvenirs' => [], 'answers' => [], 'talleres' => [],
            'categoria' => (string) $this->categoria->id, 'precioCategoria' => 50,
            'donacion' => 0, 'promoDescuento' => 0, 'promoCodigo' => '', 'subtotal' => 50,
        ], $overrides);
    }

    private function totalesData(array $overrides = []): array
    {
        return array_merge([
            'inscripcion' => 50, 'donacion' => 0, 'souvenirs' => 0, 'talleres' => 0, 'fee' => 2.5,
            'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 52.5,
        ], $overrides);
    }

    private function crearInscripcionPagadaSinTaller(string $numeroDocumento): \App\Models\Registration
    {
        $registration = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-TEST-'.uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => $this->totalesData(),
            'participantes' => [$this->participanteData($numeroDocumento)],
        ]));
        $registration->update(['pago_status' => 'paid']);

        return $registration;
    }

    public function test_inscripcion_sin_ediciones_usa_los_totales_exactos(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('70000001');

        $data = HistorialCobrosReporteData::paraEvento($this->evento);

        $filas = collect($data['filas'])->where('referenciaInscripcion', $registration->referencia);
        $this->assertCount(1, $filas);
        $fila = $filas->first();
        $this->assertSame('inicial', $fila['tipoMovimiento']);
        $this->assertFalse($fila['esEstimado']);
        $this->assertEquals(52.5, $fila['monto']);
        $this->assertEquals(2.5, $fila['fee']);
        $this->assertEquals(0.0, $fila['costoEdicion']);
        $this->assertEmpty($fila['anomalias']);
    }

    public function test_inscripcion_con_una_adicion_reconstruye_el_total_inicial_exacto(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('70000002');
        $participantes = [$this->participanteData('70000002', [
            'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
        ])];
        // costo_edicion (10) + delta de talleres (30) + fee de esa delta
        // (30*5%=1.5) = 41.5 — consistente con $totales (grand_total =
        // 52.5 original + 30 + 1.5 = 84, fee = 2.5 original + 1.5 = 4),
        // mismo cálculo real que haría CalcularCostoAdicionalAction.
        $totales = $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]);
        $pago = app(GenerarPagoAdicionalAction::class)->handle($registration, $participantes, $totales, 41.5);
        app(ConfirmarPagoAdicionalAction::class)->handle($pago->referencia);

        $data = HistorialCobrosReporteData::paraEvento($this->evento->fresh());

        $filas = collect($data['filas'])->where('referenciaInscripcion', $registration->referencia)->values();
        $this->assertCount(2, $filas);

        $inicial = $filas->firstWhere('tipoMovimiento', 'inicial');
        $this->assertTrue($inicial['esEstimado']);
        // grand_total inicial exacto por resta: 84 - (41.5 - costo_edicion 10) = 52.5
        // — coincide con el total real que tuvo la inscripcion antes de editarla.
        $this->assertEquals(52.5, $inicial['monto']);

        $adicion = $filas->firstWhere('tipoMovimiento', 'adicion');
        // Es la PRIMERA (y única) adición de esta inscripción — mismo
        // reparto estimado que la fila inicial (ver docblock de la clase).
        $this->assertTrue($adicion['esEstimado']);
        $this->assertEquals(41.5, $adicion['monto']);
        $this->assertEquals(10.0, $adicion['costoEdicion']);
        $this->assertEquals(84.0, $adicion['grandTotalAcumulado']);

        // Suma de los 2 "monto" de movimiento = lo realmente cobrado en las
        // 2 transacciones (52.5 de la inscripcion original + 41.5 del adicional).
        $this->assertEquals(52.5 + 41.5, $inicial['monto'] + $adicion['monto']);
    }

    public function test_una_segunda_adicion_tiene_el_desglose_exacto_no_estimado(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('70000003');
        $participantes1 = [$this->participanteData('70000003', [
            'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
        ])];
        $totales1 = $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]);
        $pago1 = app(GenerarPagoAdicionalAction::class)->handle($registration, $participantes1, $totales1, 40.0);
        app(ConfirmarPagoAdicionalAction::class)->handle($pago1->referencia);

        $tallerB = Taller::factory()->create(['evento_id' => $this->evento->id, 'modalidad' => 'OPTIONAL', 'precio' => 20]);
        $sesionB = SesionCongreso::factory()->create([
            'evento_id' => $this->evento->id, 'taller_id' => $tallerB->id, 'cupo' => 10,
            'hora_inicio' => '11:00:00', 'hora_fin' => '12:00:00',
        ]);
        $participantes2 = [$this->participanteData('70000003', [
            'talleres' => [
                ['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id],
                ['taller_id' => $tallerB->id, 'sesion_congreso_id' => $sesionB->id],
            ],
        ])];
        $totales2 = $this->totalesData(['talleres' => 50, 'fee' => 5, 'grand_total' => 105]);
        $pago2 = app(GenerarPagoAdicionalAction::class)->handle($registration->fresh(), $participantes2, $totales2, 30.0);
        app(ConfirmarPagoAdicionalAction::class)->handle($pago2->referencia);

        $data = HistorialCobrosReporteData::paraEvento($this->evento->fresh());
        $filas = collect($data['filas'])->where('referenciaInscripcion', $registration->referencia)
            ->where('tipoMovimiento', 'adicion')->values();
        $this->assertCount(2, $filas);

        $segunda = $filas->last();
        $this->assertFalse($segunda['esEstimado']);
        // fee exacto: fee acumulado tras la 2da (5) - fee acumulado tras la 1ra (4) = 1.
        $this->assertEquals(1.0, $segunda['fee']);
        $this->assertEquals(10.0, $segunda['costoEdicion']);
        // contenido = monto(30) - costo_edicion(10) - fee(1) = 19.
        $this->assertEquals(19.0, $segunda['contenidoNuevo']);
    }

    public function test_detecta_referencia_de_pago_adicional_no_estandar(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('70000004');
        $participantes = [$this->participanteData('70000004', [
            'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
        ])];
        $totales = $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]);
        $pago = app(GenerarPagoAdicionalAction::class)->handle($registration, $participantes, $totales, 40.0);
        app(ConfirmarPagoAdicionalAction::class)->handle($pago->referencia);
        // Simula un correctivo manual insertado directo en la BD — no sigue
        // el formato AD-XXXXXXXX que genera el sistema.
        $pago->update(['referencia' => 'AD-CORRECCIONMANUAL']);

        $data = HistorialCobrosReporteData::paraEvento($this->evento->fresh());
        $adicion = collect($data['filas'])->firstWhere('referenciaMovimiento', 'AD-CORRECCIONMANUAL');

        $this->assertNotNull($adicion);
        $this->assertContains('referencia_no_estandar', $adicion['anomalias']);
        $this->assertEquals(1, $data['resumen']['totalAnomalias']);
    }

    public function test_detecta_fecha_incoherente_cuando_la_adicion_es_anterior_al_alta(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('70000005');
        $registration->update(['fecha' => now()]);
        $participantes = [$this->participanteData('70000005', [
            'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
        ])];
        $totales = $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]);
        $pago = app(GenerarPagoAdicionalAction::class)->handle($registration->fresh(), $participantes, $totales, 40.0);
        app(ConfirmarPagoAdicionalAction::class)->handle($pago->referencia);
        // Simula el escenario real encontrado (fecha de la inscripción
        // sobrescrita por un backfill posterior al pago real).
        $pago->forceFill(['paid_at' => now()->subMonth()])->save();

        $data = HistorialCobrosReporteData::paraEvento($this->evento->fresh());
        $adicion = collect($data['filas'])->firstWhere('referenciaMovimiento', $pago->referencia);

        $this->assertNotNull($adicion);
        $this->assertContains('fecha_incoherente', $adicion['anomalias']);
    }

    public function test_endpoint_http_requiere_permiso_y_responde_el_reporte(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('70000006');

        // Sin loguearse: rechazado.
        $this->getJson("/api/v1/event/{$this->evento->id}/historial-cobros-reporte")
            ->assertStatus(401);

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $this->evento->id]);

        $response = $this->getJson("/api/v1/event/{$this->evento->id}/historial-cobros-reporte")
            ->assertOk()
            ->assertJson(['success' => true]);

        $referencias = collect($response->json('filas'))->pluck('referenciaInscripcion');
        $this->assertContains($registration->referencia, $referencias);
    }
}
