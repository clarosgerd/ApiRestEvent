<?php

namespace Tests\Feature;

use App\Actions\ActualizarInscripcionPagadaAction;
use App\Actions\CrearInscripcionAction;
use App\DTOs\RegistrationDTO;
use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * `ingresos:recomputar-costo-edicion` — ver
 * App\Console\Commands\RecomputarCostoEdicionAcumulado. Pedido del
 * usuario: que el reporte de "Ingreso por ediciones" sea fidedigno también
 * para ediciones anteriores al deploy de esa columna, reconstruyéndolas
 * desde `AuditLog` (fuente de verdad real de "cuántas veces se cobró").
 */
class RecomputarCostoEdicionAcumuladoTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private FormType $formType;

    private Category $categoria;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

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

        $this->formType = FormType::factory()->create([
            'event_id' => $this->evento->id,
            'requiere_categoria' => true,
            'costo_edicion' => 10,
        ]);

        $this->categoria = Category::factory()->create(['event_id' => $this->evento->id, 'price' => 50]);
    }

    private function participanteData(string $numeroDocumento, array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'alias' => '', 'genero' => 'Femenino',
            'tipoDocumento' => 'DNI', 'numeroDocumento' => $numeroDocumento,
            'polera' => '', 'precioPolera' => 0,
            'nacimiento' => ['dia' => 1, 'mes' => 1, 'anio' => 1995], 'edad' => 30,
            'correo' => 'ana' . rand(1, 999999) . '@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
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

    /**
     * Crea una inscripción pagada, la edita $vecesEditada veces (cada una
     * queda en AuditLog y acumula el fee de verdad vía el código nuevo),
     * y después BORRA el acumulado en registration_totals — simula
     * exactamente el escenario real: ediciones que ya pasaron ANTES de
     * que esta columna existiera, con su AuditLog real intacto.
     */
    private function crearInscripcionEditadaVariasVecesSinAcumulado(string $numeroDocumento, int $vecesEditada): \App\Models\Registration
    {
        $registration = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-TEST-' . uniqid(),
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

        for ($i = 0; $i < $vecesEditada; $i++) {
            app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
                'participantes' => [$this->participanteData($numeroDocumento, ['telefono' => (string) rand(100000, 999999)])],
                'totales' => $this->totalesData(),
                '_usuario' => 'participante@test.net',
            ]);
        }

        // Simula "esta columna todavía no existía" — el AuditLog de las
        // $vecesEditada ediciones reales queda intacto. Vía el query
        // builder de la relación (no $registration->totals->update()):
        // cada edición borra y recrea la fila de registration_totals, así
        // que la instancia de RegistrationTotal cacheada en $registration
        // (cargada antes del loop de ediciones) apunta a una fila ya
        // borrada — actualizarla silenciosamente no tocaría nada.
        $registration->totals()->update(['costo_edicion_acumulado' => 0]);

        return $registration->fresh('totals');
    }

    public function test_reconstruye_el_acumulado_desde_audit_log(): void
    {
        $registration = $this->crearInscripcionEditadaVariasVecesSinAcumulado('40000001', 3);

        $this->assertDatabaseHas('registration_totals', [
            'registration_id' => $registration->id,
            'costo_edicion_acumulado' => 0,
        ]);

        $this->artisan('ingresos:recomputar-costo-edicion')
            ->expectsOutputToContain('Registros actualizados: 1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('registration_totals', [
            'registration_id' => $registration->id,
            'costo_edicion_acumulado' => 30.0, // 3 ediciones x 10
        ]);
    }

    public function test_dry_run_no_escribe_nada(): void
    {
        $registration = $this->crearInscripcionEditadaVariasVecesSinAcumulado('40000002', 2);

        $this->artisan('ingresos:recomputar-costo-edicion', ['--dry-run' => true])
            ->expectsOutputToContain('20') // 2 x 10
            ->expectsOutputToContain('[dry-run] Registros a actualizar: 1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('registration_totals', [
            'registration_id' => $registration->id,
            'costo_edicion_acumulado' => 0,
        ]);
    }

    /**
     * Idempotente: correrlo una segunda vez (ya reconstruido) no duplica
     * nada — vuelve a calcular desde AuditLog, no incrementa sobre lo que
     * ya había.
     */
    public function test_correrlo_dos_veces_no_duplica(): void
    {
        $registration = $this->crearInscripcionEditadaVariasVecesSinAcumulado('40000003', 2);

        $this->artisan('ingresos:recomputar-costo-edicion')->assertExitCode(0);
        $this->artisan('ingresos:recomputar-costo-edicion')
            ->expectsOutputToContain('Registros actualizados: 0') // ya estaba correcto, nada que tocar
            ->assertExitCode(0);

        $this->assertDatabaseHas('registration_totals', [
            'registration_id' => $registration->id,
            'costo_edicion_acumulado' => 20.0,
        ]);
    }

    public function test_no_toca_inscripciones_sin_ediciones(): void
    {
        app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-TEST-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'paid',
            'pay_order_number' => null,
            'totales' => $this->totalesData(),
            'participantes' => [$this->participanteData('40000004')],
        ]));

        $this->artisan('ingresos:recomputar-costo-edicion')
            ->expectsOutputToContain('Registros actualizados: 0')
            ->assertExitCode(0);
    }
}
