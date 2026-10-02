<?php

namespace Tests\Feature;

use App\Actions\CrearInscripcionAction;
use App\DTOs\RegistrationDTO;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\CajaMovimiento;
use App\Models\CajaTurno;
use App\Models\Category;
use App\Models\CategoryPricePeriod;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Participante;
use App\Models\Souvenir;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Reparación del bug real de snapshot corrompido (30/09/2026) — ver
 * App\Support\SnapshotInscripcionPagadaData y
 * App\Console\Commands\ReconciliarSnapshotInscripcionesPagadas.
 */
class ReconciliarSnapshotInscripcionesPagadasTest extends TestCase
{
    use RefreshDatabase;

    private function crearInscripcionPagadaCorrupta(string $numeroDocumento): \App\Models\Registration
    {
        Mail::fake();

        $pais = Pais::factory()->create();
        $ciudad = Ciudad::factory()->create(['pais_id' => $pais->id]);
        $organizador = Organizador::factory()->create();
        $tipoEvento = TipoEvento::factory()->create();
        $subtipoEvento = SubtipoEvento::factory()->create(['tipo_evento_id' => $tipoEvento->id]);
        $evento = Evento::factory()->create([
            'organizador_id' => $organizador->id, 'tipo_evento_id' => $tipoEvento->id,
            'subtipo_evento_id' => $subtipoEvento->id, 'pais_id' => $pais->id, 'ciudad_id' => $ciudad->id,
            'fee_pct' => 0.05,
        ]);
        $formType = FormType::factory()->create(['event_id' => $evento->id, 'requiere_categoria' => true]);
        $categoria = Category::factory()->create(['event_id' => $evento->id, 'price' => 50]);

        $registration = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-TEST-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $evento->id,
            'evento_nombre' => $evento->nombre,
            'form_types_id' => $formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => ['inscripcion' => 50, 'donacion' => 0, 'souvenirs' => 0, 'fee' => 2.5, 'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 52.5],
            'participantes' => [[
                'nombre' => 'Ana', 'apellido' => 'Prueba', 'alias' => '', 'genero' => 'Femenino',
                'tipoDocumento' => 'DNI', 'numeroDocumento' => $numeroDocumento,
                'polera' => '', 'precioPolera' => 0,
                'nacimiento' => ['dia' => 1, 'mes' => 1, 'anio' => 1995], 'edad' => 30,
                'correo' => 'ana' . rand(1, 999999) . '@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
                'souvenirs' => [], 'answers' => [], 'talleres' => [],
                'categoria' => (string) $categoria->id, 'precioCategoria' => 50,
                'donacion' => 0, 'promoDescuento' => 0, 'promoCodigo' => '', 'subtotal' => 50,
            ]],
        ]));
        $registration->update(['pago_status' => 'paid']);

        // Simula el bug ya aplicado: el snapshot quedó con una diferencia
        // (-9999, un valor imposible para una categoría de 50, para que no
        // haya dudas de que el comando lo corrigió de verdad) en vez del
        // precio real — y una fila en audit_logs, que es lo que el comando
        // usa para encontrar "esta inscripción fue editada alguna vez".
        Participante::where('registration_id', $registration->id)->update([
            'precio_categoria' => -9999, 'subtotal' => -9999,
        ]);
        $registration->totals()->update(['inscripcion' => -9999, 'grand_total' => -9999]);
        AuditLog::create(['registration_id' => $registration->id, 'usuario' => 'test', 'costo_adicion' => -9999]);

        // Un `caja_movimientos` tipo=edicion_pagada real (02/10/2026) — es la
        // evidencia que el comando exige antes de tocar precio_categoria
        // contra el catálogo; sin esto, el comando ya no "corregiría" nada
        // acá (ver el nuevo criterio en ReconciliarSnapshotInscripcionesPagadas).
        $admin = AdminUser::factory()->create();
        $turno = CajaTurno::create([
            'evento_id' => $evento->id, 'admin_user_id' => $admin->id, 'estado' => 'abierto',
            'fondo_inicial' => 0, 'abierto_at' => now(),
        ]);
        CajaMovimiento::create([
            'caja_turno_id' => $turno->id, 'evento_id' => $evento->id, 'registration_id' => $registration->id,
            'admin_user_id' => $admin->id, 'tipo' => 'edicion_pagada', 'monto' => -9999, 'metodo_pago' => 'EFECTIVO',
        ]);

        return $registration;
    }

    public function test_dry_run_lista_la_diferencia_sin_aplicar_nada(): void
    {
        $registration = $this->crearInscripcionPagadaCorrupta('30000001');

        $this->artisan('caja:reconciliar-snapshot-inscripciones-pagadas', ['--dry-run' => true])
            ->assertSuccessful();

        // Nada cambió — sigue corrupto.
        $this->assertDatabaseHas('participantes', [
            'registration_id' => $registration->id, 'precio_categoria' => -9999,
        ]);
        $this->assertDatabaseHas('registration_totals', [
            'registration_id' => $registration->id, 'grand_total' => -9999,
        ]);
    }

    public function test_modo_real_corrige_el_snapshot_sin_tocar_el_cobro_real(): void
    {
        $registration = $this->crearInscripcionPagadaCorrupta('30000002');

        $this->artisan('caja:reconciliar-snapshot-inscripciones-pagadas')->assertSuccessful();

        $participante = Participante::where('registration_id', $registration->id)->first();
        $this->assertEquals(50.0, (float) $participante->precio_categoria);
        $this->assertEquals(50.0, (float) $participante->subtotal);

        $totals = $registration->totals()->first();
        $this->assertEquals(50.0, (float) $totals->inscripcion);
        $this->assertEquals(52.5, (float) $totals->grand_total);

        // El comando no toca audit_logs/caja_movimientos — solo el snapshot.
        $this->assertDatabaseHas('audit_logs', [
            'registration_id' => $registration->id, 'costo_adicion' => -9999,
        ]);
    }

    public function test_no_falla_si_no_hay_ninguna_inscripcion_editada(): void
    {
        $this->artisan('caja:reconciliar-snapshot-inscripciones-pagadas')->assertSuccessful();
    }

    /**
     * Falso positivo real encontrado en UAT (02/10/2026): una categoría con
     * precios por período (ver PrecioVigenteData) tiene un precio distinto
     * HOY del que tenía cuando la inscripción se pagó — eso es normal, no
     * corrupción. El comando no debe "corregir" precio_categoria/subtotal
     * contra el precio vigente de hoy cuando el valor guardado coincide con
     * un período YA VENCIDO de esa misma categoría.
     */
    public function test_no_toca_precio_categoria_de_un_periodo_ya_vencido_aunque_difiera_del_vigente_hoy(): void
    {
        Mail::fake();

        $pais = Pais::factory()->create();
        $ciudad = Ciudad::factory()->create(['pais_id' => $pais->id]);
        $organizador = Organizador::factory()->create();
        $tipoEvento = TipoEvento::factory()->create();
        $subtipoEvento = SubtipoEvento::factory()->create(['tipo_evento_id' => $tipoEvento->id]);
        $evento = Evento::factory()->create([
            'organizador_id' => $organizador->id, 'tipo_evento_id' => $tipoEvento->id,
            'subtipo_evento_id' => $subtipoEvento->id, 'pais_id' => $pais->id, 'ciudad_id' => $ciudad->id,
            'fee_pct' => 0.05,
        ]);
        $formType = FormType::factory()->create(['event_id' => $evento->id, 'requiere_categoria' => true]);
        $categoria = Category::factory()->create(['event_id' => $evento->id, 'price' => 1400]);
        CategoryPricePeriod::create([
            'category_id' => $categoria->id, 'nombre' => 'Preventa', 'price' => 1000,
            'fecha_desde' => now()->subMonth()->toDateString(), 'fecha_hasta' => now()->subDays(28)->toDateString(),
        ]);
        CategoryPricePeriod::create([
            'category_id' => $categoria->id, 'nombre' => 'Vigente', 'price' => 1400,
            'fecha_desde' => now()->subDays(27)->toDateString(), 'fecha_hasta' => now()->addMonth()->toDateString(),
        ]);

        $registration = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-TEST-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $evento->id,
            'evento_nombre' => $evento->nombre,
            'form_types_id' => $formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => ['inscripcion' => 1400, 'donacion' => 0, 'souvenirs' => 0, 'fee' => 70, 'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 1470],
            'participantes' => [[
                'nombre' => 'Ana', 'apellido' => 'Prueba', 'alias' => '', 'genero' => 'Femenino',
                'tipoDocumento' => 'DNI', 'numeroDocumento' => '30000003',
                'polera' => '', 'precioPolera' => 0,
                'nacimiento' => ['dia' => 1, 'mes' => 1, 'anio' => 1995], 'edad' => 30,
                'correo' => 'ana' . rand(1, 999999) . '@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
                'souvenirs' => [], 'answers' => [], 'talleres' => [],
                'categoria' => (string) $categoria->id, 'precioCategoria' => 1400,
                'donacion' => 0, 'promoDescuento' => 0, 'promoCodigo' => '', 'subtotal' => 1400,
            ]],
        ]));
        $registration->update(['pago_status' => 'paid']);

        // Simula que se pagó de verdad durante la Preventa (hoy ya vencida):
        // precio_categoria/subtotal/registration_totals con el precio de
        // ESE momento (1000), real y correcto, aunque el período vigente
        // hoy sea otro (1400). Una edición cualquiera después (sin cambiar
        // categoría) deja la fila en audit_logs que dispara la reconciliación.
        Participante::where('registration_id', $registration->id)->update([
            'precio_categoria' => 1000, 'subtotal' => 1000,
        ]);
        $registration->totals()->update(['inscripcion' => 1000, 'fee' => 50, 'grand_total' => 1050]);
        AuditLog::create(['registration_id' => $registration->id, 'usuario' => 'test', 'costo_adicion' => 0]);

        $this->artisan('caja:reconciliar-snapshot-inscripciones-pagadas')->assertSuccessful();

        $participante = Participante::where('registration_id', $registration->id)->first();
        $this->assertEquals(1000.0, (float) $participante->precio_categoria);
        $this->assertEquals(1000.0, (float) $participante->subtotal);

        $totals = $registration->totals()->first();
        $this->assertEquals(1000.0, (float) $totals->inscripcion);
        $this->assertEquals(1050.0, (float) $totals->grand_total);
    }

    /**
     * Falso positivo real encontrado en UAT (02/10/2026): `subtotal` del
     * participante NO es solo el precio de categoría — el JS lo calcula como
     * precioCategoria + polera + souvenirs + donación - promo (ver
     * index.php, const subtotal). Con un souvenir de por medio, subtotal
     * nunca va a coincidir con el catálogo de precios de la categoría aunque
     * todo esté perfecto — el comando no debe tocar precio_categoria en ese
     * caso.
     */
    public function test_no_toca_precio_categoria_cuando_el_subtotal_incluye_souvenirs(): void
    {
        Mail::fake();

        $pais = Pais::factory()->create();
        $ciudad = Ciudad::factory()->create(['pais_id' => $pais->id]);
        $organizador = Organizador::factory()->create();
        $tipoEvento = TipoEvento::factory()->create();
        $subtipoEvento = SubtipoEvento::factory()->create(['tipo_evento_id' => $tipoEvento->id]);
        $evento = Evento::factory()->create([
            'organizador_id' => $organizador->id, 'tipo_evento_id' => $tipoEvento->id,
            'subtipo_evento_id' => $subtipoEvento->id, 'pais_id' => $pais->id, 'ciudad_id' => $ciudad->id,
            'fee_pct' => 0.05,
        ]);
        $formType = FormType::factory()->create(['event_id' => $evento->id, 'requiere_categoria' => true]);
        $categoria = Category::factory()->create(['event_id' => $evento->id, 'price' => 280]);
        $souvenir = Souvenir::factory()->create(['form_types_id' => $formType->id]);

        $registration = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-TEST-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $evento->id,
            'evento_nombre' => $evento->nombre,
            'form_types_id' => $formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => ['inscripcion' => 280, 'donacion' => 0, 'souvenirs' => 150, 'fee' => 14, 'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 444],
            'participantes' => [[
                'nombre' => 'Ana', 'apellido' => 'Prueba', 'alias' => '', 'genero' => 'Femenino',
                'tipoDocumento' => 'DNI', 'numeroDocumento' => '30000005',
                'polera' => '', 'precioPolera' => 0,
                'nacimiento' => ['dia' => 1, 'mes' => 1, 'anio' => 1995], 'edad' => 30,
                'correo' => 'ana' . rand(1, 999999) . '@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
                'souvenirs' => [['id' => $souvenir->id, 'nombre' => $souvenir->name, 'precio' => 150]],
                'answers' => [], 'talleres' => [],
                'categoria' => (string) $categoria->id, 'precioCategoria' => 280,
                'donacion' => 0, 'promoDescuento' => 0, 'promoCodigo' => '', 'subtotal' => 430,
            ]],
        ]));
        $registration->update(['pago_status' => 'paid']);

        AuditLog::create(['registration_id' => $registration->id, 'usuario' => 'test', 'costo_adicion' => 0]);

        $this->artisan('caja:reconciliar-snapshot-inscripciones-pagadas')->assertSuccessful();

        $participante = Participante::where('registration_id', $registration->id)->first();
        $this->assertEquals(280.0, (float) $participante->precio_categoria);
        $this->assertEquals(430.0, (float) $participante->subtotal);

        $totals = $registration->totals()->first();
        $this->assertEquals(280.0, (float) $totals->inscripcion);
        $this->assertEquals(150.0, (float) $totals->souvenirs);
    }

    /**
     * Caso real encontrado en UAT (02/10/2026, LA-D25BDF15): llegó por carga
     * masiva/autoservicio (nunca por Caja) con `subtotal`/registration_totals
     * en 0 pero `precio_categoria` y los talleres con su valor real intacto
     * — sin ningún `caja_movimientos` tipo=edicion_pagada de por medio. El
     * comando debe recomponer el agregado desde lo ya persistido (talleres,
     * precio_categoria) SIN tocar precio_categoria contra el catálogo de
     * hoy, aunque ya no coincida con el precio vigente actual.
     */
    public function test_recompone_el_agregado_sin_tocar_categoria_cuando_no_hay_evidencia_de_caja(): void
    {
        Mail::fake();

        $pais = Pais::factory()->create();
        $ciudad = Ciudad::factory()->create(['pais_id' => $pais->id]);
        $organizador = Organizador::factory()->create();
        $tipoEvento = TipoEvento::factory()->create();
        $subtipoEvento = SubtipoEvento::factory()->create(['tipo_evento_id' => $tipoEvento->id]);
        $evento = Evento::factory()->create([
            'organizador_id' => $organizador->id, 'tipo_evento_id' => $tipoEvento->id,
            'subtipo_evento_id' => $subtipoEvento->id, 'pais_id' => $pais->id, 'ciudad_id' => $ciudad->id,
            'fee_pct' => 0.05,
        ]);
        $formType = FormType::factory()->create(['event_id' => $evento->id, 'requiere_categoria' => true]);
        // Precio base subió después (1400 hoy) sin dejar ningún historial —
        // a propósito, para probar que el comando NO lo usa sin evidencia.
        $categoria = Category::factory()->create(['event_id' => $evento->id, 'price' => 1400]);

        $registration = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-TEST-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $evento->id,
            'evento_nombre' => $evento->nombre,
            'form_types_id' => $formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => ['inscripcion' => 1400, 'donacion' => 0, 'souvenirs' => 0, 'fee' => 70, 'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 1470],
            'participantes' => [[
                'nombre' => 'Ana', 'apellido' => 'Prueba', 'alias' => '', 'genero' => 'Femenino',
                'tipoDocumento' => 'DNI', 'numeroDocumento' => '30000006',
                'polera' => '', 'precioPolera' => 0,
                'nacimiento' => ['dia' => 1, 'mes' => 1, 'anio' => 1995], 'edad' => 30,
                'correo' => 'ana' . rand(1, 999999) . '@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
                'souvenirs' => [], 'answers' => [], 'talleres' => [],
                'categoria' => (string) $categoria->id, 'precioCategoria' => 1400,
                'donacion' => 0, 'promoDescuento' => 0, 'promoCodigo' => '', 'subtotal' => 1400,
            ]],
        ]));
        $registration->update(['pago_status' => 'paid']);

        // Simula el bug real: categoría intacta con su precio real de
        // entonces (1000, ya no coincide con el catálogo de hoy ni con
        // ningún período — no hay historial), pero subtotal/totales en 0.
        Participante::where('registration_id', $registration->id)->update([
            'precio_categoria' => 1000, 'subtotal' => 0,
        ]);
        $registration->totals()->update(['inscripcion' => 0, 'fee' => 0, 'grand_total' => 0]);
        AuditLog::create(['registration_id' => $registration->id, 'usuario' => 'test', 'costo_adicion' => 0]);

        $this->artisan('caja:reconciliar-snapshot-inscripciones-pagadas')->assertSuccessful();

        // precio_categoria NUNCA se toca sin evidencia de Caja, aunque no
        // coincida con el catálogo de hoy.
        $participante = Participante::where('registration_id', $registration->id)->first();
        $this->assertEquals(1000.0, (float) $participante->precio_categoria);

        // El agregado SÍ se recompone desde lo real ya persistido.
        $totals = $registration->totals()->first();
        $this->assertEquals(1000.0, (float) $totals->inscripcion);
        $this->assertEquals(50.0, (float) $totals->fee);
        $this->assertEquals(1050.0, (float) $totals->grand_total);
    }
}
