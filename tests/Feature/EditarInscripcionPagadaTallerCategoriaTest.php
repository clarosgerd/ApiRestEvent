<?php

namespace Tests\Feature;

use App\Actions\ActualizarInscripcionPagadaAction;
use App\Actions\CrearInscripcionAction;
use App\DTOs\RegistrationDTO;
use App\Models\CajaMovimiento;
use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Persona;
use App\Models\SesionCongreso;
use App\Models\Souvenir;
use App\Models\Taller;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Agregar talleres a una inscripción pagada (autoservicio + Caja) + cambio
 * de categoría solo en Caja (25/08/2026) — ver
 * PLAN-EDICION-PAGADA-TALLERES-CATEGORIA-25082026.md. Caso real reportado:
 * personas inscritas al congreso sin haber elegido taller, que necesitan
 * agregarlo después de haber pagado.
 *
 * Antes de este cambio, ActualizarInscripcionPagadaAction cobraba siempre
 * un monto FIJO (form_types.costo_edicion) sin relación con lo que
 * realmente cambió, y no bloqueaba ni cambio de categoría ni remoción de
 * talleres ya pagados en ningún flujo.
 */
class EditarInscripcionPagadaTallerCategoriaTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private FormType $formType;

    private Category $categoria;

    private Category $categoriaCara;

    private Taller $taller;

    private SesionCongreso $sesion;

    private Souvenir $souvenir;

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
            'fee_pct' => 0.05,
            // Sin esto, ResolverPrecioTallerData cobra $0 por cualquier
            // taller sin importar su precio cargado (default false).
            'talleres_con_costo' => true,
        ]);

        $this->formType = FormType::factory()->create([
            'event_id' => $this->evento->id,
            'cupo_total' => 100,
            'activo' => true,
            'requiere_categoria' => true,
            'costo_edicion' => 10,
        ]);

        $this->categoria = Category::factory()->create([
            'event_id' => $this->evento->id,
            'price' => 50,
        ]);
        $this->categoriaCara = Category::factory()->create([
            'event_id' => $this->evento->id,
            'price' => 120,
        ]);

        $this->taller = Taller::factory()->create([
            'evento_id' => $this->evento->id,
            'modalidad' => 'OPTIONAL',
            'precio' => 30,
        ]);
        $this->sesion = SesionCongreso::factory()->create([
            'evento_id' => $this->evento->id,
            'taller_id' => $this->taller->id,
            'cupo' => 10,
        ]);
        $this->souvenir = Souvenir::factory()->create([
            'form_types_id' => $this->formType->id,
            'price' => 20,
        ]);
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

    private function crearInscripcionPagadaSinTaller(string $numeroDocumento): \App\Models\Registration
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

        return $registration;
    }

    // ── Autoservicio (modoCategoria='solo_subida', default) ──────────

    public function test_autoservicio_agrega_taller_nuevo_y_cobra_flat_mas_precio_real(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('20000001');

        $result = app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000001', [
                'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
            ])],
            'totales' => $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]),
            '_usuario' => 'participante@test.net',
        ]);

        // costo_edicion (10) + precio real del taller (30) = 40.
        $this->assertEquals(40.0, $result['costo_adicion']);
        $this->assertDatabaseHas('participante_taller_sesion', [
            'sesion_congreso_id' => $this->sesion->id,
        ]);
    }

    /**
     * Reporte de talleres confiable (27/08/2026) — cuando el participante
     * elige "pagar en el evento" (RegistrationController::updatePaid()
     * llama con requierePagoEnSitio: true), el taller nuevo queda marcado
     * pago_pendiente=true — el reporte de talleres necesita esto para no
     * contarlo como plata ya cobrada.
     */
    public function test_autoservicio_con_pago_en_sitio_marca_el_taller_nuevo_como_pendiente(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('20000010');

        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000010', [
                'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
            ])],
            'totales' => $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]),
            '_usuario' => 'participante@test.net',
        ], requierePagoEnSitio: true);

        $this->assertDatabaseHas('participante_taller_sesion', [
            'sesion_congreso_id' => $this->sesion->id,
            'pago_pendiente' => true,
        ]);
    }

    /**
     * Reverso del test anterior — Caja cobra en el momento
     * (modoCategoria: 'libre', requierePagoEnSitio en su default
     * false), así que el taller nuevo NO queda pendiente.
     */
    public function test_caja_agrega_taller_y_no_queda_pendiente_de_cobro(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('20000011');

        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000011', [
                'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
            ])],
            'totales' => $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]),
            '_usuario' => 'cajero@test.net',
        ], modoCategoria: 'libre');

        $this->assertDatabaseHas('participante_taller_sesion', [
            'sesion_congreso_id' => $this->sesion->id,
            'pago_pendiente' => false,
        ]);
    }

    /**
     * Un taller marcado pago_pendiente=true no debe perder esa marca en
     * una edición posterior que no lo toca — createParticipantFromData()
     * recrea la fila desde cero en cada edición (borra y vuelve a crear
     * TODOS los participantes), así que sin el snapshot/restauración
     * explícita el flag se resetearía a false en cualquier edición
     * siguiente, aunque nadie haya cobrado nada todavía.
     */
    public function test_pago_pendiente_se_mantiene_en_una_edicion_posterior_que_no_lo_toca(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('20000012');

        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000012', [
                'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
            ])],
            'totales' => $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]),
            '_usuario' => 'participante@test.net',
        ], requierePagoEnSitio: true);

        // Segunda edición (ej. Caja corrigiendo un dato personal cualquiera)
        // que manda el mismo taller sin cambios — no debe resetear el flag.
        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000012', [
                'apellido' => 'Corregida',
                'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
            ])],
            'totales' => $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]),
            '_usuario' => 'cajero@test.net',
        ], modoCategoria: 'libre');

        $this->assertDatabaseHas('participante_taller_sesion', [
            'sesion_congreso_id' => $this->sesion->id,
            'pago_pendiente' => true,
        ]);
    }

    /**
     * Autoservicio 'solo_subida' (02/09/2026, ver EdicionPagadaCategoriaData)
     * — antes de este cambio el autoservicio nunca podía cambiar de
     * categoría en ninguna dirección; ahora sí, pero solo hacia una de
     * igual o mayor precio (no se hacen devoluciones).
     */
    public function test_autoservicio_puede_subir_de_categoria_y_cobra_diferencia_real(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('20000002');

        // precioCategoria mandado por el cliente (999) se ignora a
        // propósito — el delta se calcula contra el precio real de la BD.
        $result = app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000002', [
                'categoria' => (string) $this->categoriaCara->id,
                'precioCategoria' => 999,
            ])],
            'totales' => $this->totalesData(['inscripcion' => 120, 'fee' => 6, 'grand_total' => 126]),
            '_usuario' => 'participante@test.net',
        ]);

        // costo_edicion (10) + diferencia real (120 - 50 = 70) = 80.
        $this->assertEquals(80.0, $result['costo_adicion']);
    }

    public function test_autoservicio_no_puede_bajar_de_categoria(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('20000009');
        \App\Models\Participante::where('registration_id', $registration->id)
            ->update(['categoria' => (string) $this->categoriaCara->id, 'precio_categoria' => 120]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('no se hacen devoluciones');

        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000009', [
                'categoria' => (string) $this->categoria->id,
                'precioCategoria' => 50,
            ])],
            'totales' => $this->totalesData(),
            '_usuario' => 'participante@test.net',
        ]);
    }

    public function test_ningun_flujo_puede_quitar_un_taller_ya_pagado(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('20000003');
        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000003', [
                'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
            ])],
            'totales' => $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]),
            '_usuario' => 'participante@test.net',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('No se pueden quitar talleres');

        // Reintenta editar sin mandar el taller que ya tenía.
        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000003', ['talleres' => []])],
            'totales' => $this->totalesData(),
            '_usuario' => 'participante@test.net',
        ]);
    }

    public function test_edicion_sin_cambios_de_categoria_ni_talleres_solo_cobra_el_flat(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('20000004');

        $result = app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000004', ['telefono' => '999999'])],
            'totales' => $this->totalesData(),
            '_usuario' => 'participante@test.net',
        ]);

        $this->assertEquals(10.0, $result['costo_adicion']);
    }

    // ── Souvenirs (02/09/2026, ver EdicionPagadaSouvenirsData) ─────────
    // Antes de esto no había NINGUNA validación real del lado del backend
    // — createParticipantFromData() recreaba los souvenirs de lo que
    // mandara el cliente sin comparar contra lo que ya tenía ni sumar
    // nada a costo_adicion. Mismo criterio que talleres: agregar sí,
    // quitar lo ya pagado no.

    public function test_autoservicio_agrega_souvenir_nuevo_y_cobra_precio_real(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('20000015');

        // precio mandado por el cliente (1) se ignora a propósito — el
        // costo se calcula contra el precio real del catálogo (20).
        $result = app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000015', [
                'souvenirs' => [['id' => $this->souvenir->id, 'nombre' => $this->souvenir->name, 'precio' => 1]],
            ])],
            'totales' => $this->totalesData(['souvenirs' => 20, 'fee' => 3.5, 'grand_total' => 73.5]),
            '_usuario' => 'participante@test.net',
        ]);

        // costo_edicion (10) + precio real del souvenir (20) = 30.
        $this->assertEquals(30.0, $result['costo_adicion']);
        $this->assertDatabaseHas('souvenir_participantes', [
            'souvenir_id' => $this->souvenir->id,
        ]);
    }

    public function test_ningun_flujo_puede_quitar_un_souvenir_ya_pagado(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('20000016');
        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000016', [
                'souvenirs' => [['id' => $this->souvenir->id, 'nombre' => $this->souvenir->name, 'precio' => 20]],
            ])],
            'totales' => $this->totalesData(['souvenirs' => 20, 'fee' => 3.5, 'grand_total' => 73.5]),
            '_usuario' => 'participante@test.net',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('No se pueden quitar souvenirs');

        // Reintenta editar sin mandar el souvenir que ya tenía.
        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000016', ['souvenirs' => []])],
            'totales' => $this->totalesData(),
            '_usuario' => 'participante@test.net',
        ]);
    }

    /**
     * Souvenirs invisibles para el participante (22/08/2026) — nunca
     * viajan en el payload del cliente, así que no deben confundirse con
     * "un souvenir que se quitó". Se auto-asignan solos en cada edición
     * (injectSouvenirsInvisibles()), no deben bloquear ninguna edición.
     */
    public function test_souvenir_invisible_no_bloquea_la_edicion(): void
    {
        $invisible = Souvenir::factory()->create([
            'form_types_id' => $this->formType->id,
            'price' => 0,
            'visible_participante' => false,
        ]);
        $registration = $this->crearInscripcionPagadaSinTaller('20000017');
        $this->assertDatabaseHas('souvenir_participantes', ['souvenir_id' => $invisible->id]);

        $result = app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000017', ['telefono' => '999999'])],
            'totales' => $this->totalesData(),
            '_usuario' => 'participante@test.net',
        ]);

        $this->assertEquals(10.0, $result['costo_adicion']);
        $this->assertDatabaseHas('souvenir_participantes', ['souvenir_id' => $invisible->id]);
    }

    // ── Caja (modoCategoria='libre') ─────────────────────────────

    public function test_caja_puede_cambiar_a_categoria_mas_cara_y_cobra_diferencia_real(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('20000005');

        // precioCategoria mandado por el cliente (999) se ignora a propósito
        // — el delta se calcula contra el precio real de la BD (120).
        $result = app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000005', [
                'categoria' => (string) $this->categoriaCara->id,
                'precioCategoria' => 999,
            ])],
            'totales' => $this->totalesData(['inscripcion' => 120, 'fee' => 6, 'grand_total' => 126]),
            '_usuario' => 'cajero@test.net',
        ], modoCategoria: 'libre');

        // costo_edicion (10) + diferencia real (120 - 50 = 70) = 80.
        $this->assertEquals(80.0, $result['costo_adicion']);
    }

    public function test_caja_puede_cambiar_a_categoria_mas_barata_y_da_costo_adicion_negativo(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('20000006');
        // Empieza en la categoría cara (120), baja a la barata (50).
        \App\Models\Participante::where('registration_id', $registration->id)
            ->update(['categoria' => (string) $this->categoriaCara->id, 'precio_categoria' => 120]);

        $result = app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000006', [
                'categoria' => (string) $this->categoria->id,
                'precioCategoria' => 50,
            ])],
            'totales' => $this->totalesData(),
            '_usuario' => 'cajero@test.net',
        ], modoCategoria: 'libre');

        // costo_edicion (10) + diferencia real (50 - 120 = -70) = -60.
        $this->assertEquals(-60.0, $result['costo_adicion']);
    }

    /**
     * Categorías por form_type (27/08/2026) — ver
     * PLAN-CATEGORIAS-POR-FORM-TYPE-27082026.md. Antes de este cambio,
     * `ActualizarInscripcionPagadaAction` resolvía la categoría nueva con
     * `Category::findOrFail()` sin filtrar por evento — aceptaba la
     * categoría de CUALQUIER evento del sistema.
     */
    public function test_caja_no_puede_cambiar_a_una_categoria_de_otro_evento(): void
    {
        $otroEvento = Evento::factory()->create();
        $categoriaAjena = Category::factory()->create(['event_id' => $otroEvento->id, 'price' => 999]);

        $registration = $this->crearInscripcionPagadaSinTaller('20000007');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("La categoría '{$categoriaAjena->id}' no es válida para este evento/tipo de formulario.");

        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000007', [
                'categoria' => (string) $categoriaAjena->id,
                'precioCategoria' => 999,
            ])],
            'totales' => $this->totalesData(),
            '_usuario' => 'cajero@test.net',
        ], modoCategoria: 'libre');
    }

    /**
     * Categorías por form_type (27/08/2026) — una categoría con
     * `formulario_id` cargado solo es válida para ESE form_type; con
     * `formulario_id = null` (default, categoría compartida) el cambio
     * sigue funcionando exactamente igual, sin regresión.
     */
    public function test_caja_no_puede_cambiar_a_una_categoria_de_otro_form_type_del_mismo_evento(): void
    {
        $otroFormType = FormType::factory()->create(['event_id' => $this->evento->id]);
        $categoriaDeOtroFormType = Category::factory()->create([
            'event_id' => $this->evento->id,
            'formulario_id' => $otroFormType->id,
            'price' => 999,
        ]);

        $registration = $this->crearInscripcionPagadaSinTaller('20000008');

        $this->expectException(\DomainException::class);

        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000008', [
                'categoria' => (string) $categoriaDeOtroFormType->id,
                'precioCategoria' => 999,
            ])],
            'totales' => $this->totalesData(),
            '_usuario' => 'cajero@test.net',
        ], modoCategoria: 'libre');
    }

    public function test_caja_registra_movimiento_negativo_al_editar_pagada_con_desembolso(): void
    {
        $cajero = $this->actingAsAdmin();
        $cajero->update(['rol' => 'cajero', 'evento_id' => $this->evento->id]);
        $this->postJson("/api/v1/event/{$this->evento->id}/caja/turno/abrir", ['fondo_inicial' => 0]);

        $registration = $this->crearInscripcionPagadaSinTaller('20000007');
        \App\Models\Participante::where('registration_id', $registration->id)
            ->update(['categoria' => (string) $this->categoriaCara->id, 'precio_categoria' => 120]);

        $this->patchJson("/api/v1/registrations/{$registration->referencia}/caja/editar-pagada", [
            'confirmacion' => true,
            'participantes' => [$this->participanteData('20000007', [
                'categoria' => (string) $this->categoria->id,
                'precioCategoria' => 50,
            ])],
            'totales' => $this->totalesData(),
        ])->assertStatus(200)->assertJson(['success' => true, 'costo_adicion' => -60]);

        // Antes del fix (`> 0`), un monto negativo no generaba ningún
        // CajaMovimiento — quedaba sin registrar la devolución.
        $this->assertDatabaseHas('caja_movimientos', [
            'registration_id' => $registration->id,
            'tipo' => 'edicion_pagada',
            'monto' => -60,
            'admin_user_id' => $cajero->id,
        ]);
    }

    // ── Grandfather clause de disponibilidad (bug real UAT 02/09/2026) ──

    /**
     * Bug real reportado en UAT: SIP cobró un pago adicional real (agregar
     * un taller nuevo) y la aplicación falló de todos modos porque OTRO
     * taller — uno que el participante ya tenía pagado de antes — había
     * sido deshabilitado (`permite_inscripcion=false`) mientras tanto. Como
     * ese taller no se puede quitar (ver
     * test_ningun_flujo_puede_quitar_un_taller_ya_pagado), la edición
     * quedaba bloqueada para siempre sin ninguna forma de aplicar el
     * cambio que el dinero ya había pagado.
     */
    public function test_editar_pagada_no_bloquea_por_taller_ya_pagado_que_luego_se_deshabilita(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('20000020');
        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000020', [
                'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
            ])],
            'totales' => $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]),
            '_usuario' => 'participante@test.net',
        ]);

        // El organizador deshabilita el taller que este participante ya
        // tiene pagado (cupo lleno, lo que sea) — no afecta lo ya pagado.
        $this->taller->update(['permite_inscripcion' => false]);

        $tallerNuevo = Taller::factory()->create(['evento_id' => $this->evento->id, 'modalidad' => 'OPTIONAL', 'precio' => 15]);
        // Horario distinto al de $this->sesion (default de la factory:
        // 09:00-10:00) para que no choque con el solape.
        $sesionNueva = SesionCongreso::factory()->create([
            'evento_id' => $this->evento->id, 'taller_id' => $tallerNuevo->id, 'cupo' => 10,
            'hora_inicio' => '11:00:00', 'hora_fin' => '12:00:00',
        ]);

        // Agrega un taller NUEVO (distinto) sin tocar el ya deshabilitado —
        // no debe rechazar por el taller viejo, que sigue viajando en el
        // payload porque no se puede quitar.
        $result = app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000020', [
                'talleres' => [
                    ['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id],
                    ['taller_id' => $tallerNuevo->id, 'sesion_congreso_id' => $sesionNueva->id],
                ],
            ])],
            'totales' => $this->totalesData(['talleres' => 45, 'fee' => 4, 'grand_total' => 99]),
            '_usuario' => 'sip:AD-TEST',
        ]);

        // costo_edicion (10) + precio real del taller nuevo (15) = 25.
        $this->assertEquals(25.0, $result['costo_adicion']);
        $this->assertDatabaseHas('participante_taller_sesion', ['sesion_congreso_id' => $this->sesion->id]);
        $this->assertDatabaseHas('participante_taller_sesion', ['sesion_congreso_id' => $sesionNueva->id]);
    }

    /**
     * El fix de arriba NO relaja la regla para selecciones genuinamente
     * nuevas — solo exime a las que el participante ya tenía antes de esta
     * edición.
     */
    public function test_editar_pagada_sigue_rechazando_un_taller_nuevo_con_permite_inscripcion_false(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('20000021');

        $tallerDeshabilitado = Taller::factory()->create([
            'evento_id' => $this->evento->id, 'modalidad' => 'OPTIONAL', 'precio' => 15, 'permite_inscripcion' => false,
        ]);
        $sesionDeshabilitada = SesionCongreso::factory()->create(['evento_id' => $this->evento->id, 'taller_id' => $tallerDeshabilitado->id, 'cupo' => 10]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('no está disponible para inscripción en este momento');

        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000021', [
                'talleres' => [['taller_id' => $tallerDeshabilitado->id, 'sesion_congreso_id' => $sesionDeshabilitada->id]],
            ])],
            'totales' => $this->totalesData(['talleres' => 15, 'fee' => 4, 'grand_total' => 69]),
            '_usuario' => 'participante@test.net',
        ]);
    }

    /**
     * Misma categoría de bug que el de arriba, pero con cupo en vez de
     * permite_inscripcion: si el organizador reduce el cupo de una sesión
     * después de que el participante ya la pagó, y mientras tanto OTRA
     * inscripción también la ocupa, revalidar la capacidad de esa sesión
     * ya pagada en cada edición posterior bloquearía la edición para
     * siempre por algo que el participante no puede resolver.
     */
    public function test_editar_pagada_no_bloquea_por_cupo_lleno_de_taller_ya_pagado(): void
    {
        $sesionChica = SesionCongreso::factory()->create(['evento_id' => $this->evento->id, 'taller_id' => $this->taller->id, 'cupo' => 2]);

        $registration = $this->crearInscripcionPagadaSinTaller('20000022');
        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000022', [
                'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $sesionChica->id]],
            ])],
            'totales' => $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]),
            '_usuario' => 'participante@test.net',
        ]);

        // Otra inscripción distinta también ocupa esa misma sesión —
        // ahora el cupo (2) está completo entre las dos.
        $otraRegistration = $this->crearInscripcionPagadaSinTaller('20000023');
        app(ActualizarInscripcionPagadaAction::class)->handle($otraRegistration->referencia, [
            'participantes' => [$this->participanteData('20000023', [
                'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $sesionChica->id]],
            ])],
            'totales' => $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]),
            '_usuario' => 'participante@test.net',
        ]);

        // El organizador reduce el cupo (ej. cambio de sala más chica) DESPUÉS
        // de que ambas ya estaban pagadas — sin esto, el chequeo de cupo
        // (que excluye la propia inscripción) nunca llega a `>= cupo` y el
        // test no reproduce el bug real.
        $sesionChica->update(['cupo' => 1]);

        // El participante original edita algo sin relación con el taller
        // (ej. teléfono) — no debe rechazar por cupo de una sesión que ya
        // tenía pagada de antes.
        $result = app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000022', [
                'telefono' => '999999',
                'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $sesionChica->id]],
            ])],
            'totales' => $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]),
            '_usuario' => 'participante@test.net',
        ]);

        $this->assertEquals(10.0, $result['costo_adicion']);
    }

    public function test_taller_nuevo_que_choca_con_uno_ya_pagado_rechaza(): void
    {
        $registration = $this->crearInscripcionPagadaSinTaller('20000008');
        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000008', [
                'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
            ])],
            'totales' => $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]),
            '_usuario' => 'participante@test.net',
        ]);

        $tallerB = Taller::factory()->create(['evento_id' => $this->evento->id, 'modalidad' => 'OPTIONAL', 'precio' => 20]);
        $sesionSolapada = SesionCongreso::factory()->create([
            'evento_id' => $this->evento->id,
            'taller_id' => $tallerB->id,
            'cupo' => 10,
            'fecha' => $this->sesion->fecha,
            'hora_inicio' => $this->sesion->hora_inicio,
            'hora_fin' => $this->sesion->hora_fin,
        ]);

        $this->expectException(\DomainException::class);

        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('20000008', [
                'talleres' => [
                    ['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id],
                    ['taller_id' => $tallerB->id, 'sesion_congreso_id' => $sesionSolapada->id],
                ],
            ])],
            'totales' => $this->totalesData(['talleres' => 50, 'fee' => 5, 'grand_total' => 105]),
            '_usuario' => 'participante@test.net',
        ]);
    }

    // ── Prepoblado del taller ya elegido (bug real 26/08/2026) ─────────

    /**
     * Bug real 26/08/2026 (reportado por el usuario: "también debería
     * prepopular el taller escogido") — ni RegistrationController::mine()
     * ni RegistrationService::lookupRegistration() eager-cargaban
     * participants.talleresSesiones, así que ParticipanteResource::talleres
     * (que usa whenLoaded()) quedaba ausente del JSON — el frontend nunca
     * podía saber qué taller ya tenía el participante al reingresar por
     * "Mis inscripciones".
     */
    public function test_registrations_mine_incluye_talleres_del_participante(): void
    {
        $persona = Persona::factory()->create(['numero_documento' => '30000001']);
        $this->actingAsPersona($persona);

        $registration = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-TEST-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]),
            'participantes' => [$this->participanteData('30000001', [
                'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
            ])],
        ]));
        $registration->update(['pago_status' => 'paid']);

        $response = $this->getJson('/api/v1/registrations/mine')->assertOk();

        $talleres = $response->json('data.0.participantes.0.talleres');
        $this->assertNotEmpty($talleres, 'ParticipanteResource.talleres vino vacío/ausente — falta el eager-load.');
        $this->assertEquals($this->sesion->id, $talleres[0]['sesionCongresoId']);
    }

    public function test_lookup_registration_incluye_talleres_del_participante(): void
    {
        // CrearInscripcionAction::syncPersonas() auto-crea una Persona con
        // password = Hash::make(numero_documento) por cada participante —
        // no se crea una Persona propia acá, se usa esa (más real).
        $registration = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-TEST-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]),
            'participantes' => [$this->participanteData('30000002', [
                'talleres' => [['taller_id' => $this->taller->id, 'sesion_congreso_id' => $this->sesion->id]],
            ])],
        ]));
        $registration->update(['pago_status' => 'paid']);

        $persona = Persona::where('numero_documento', '30000002')->firstOrFail();

        $result = app(RegistrationService::class)->lookupRegistration(
            $persona->email, '30000002', $this->evento->id, $this->formType->id
        );

        $this->assertSame('registration', $result['type']);
        $talleresSesiones = $result['data']->participants->first()->talleresSesiones;
        $this->assertNotEmpty($talleresSesiones, 'talleresSesiones vino vacío — falta el eager-load en lookupRegistration().');
        $this->assertEquals($this->sesion->id, $talleresSesiones->first()->sesion_congreso_id);
    }

    /**
     * Bug real 02/09/2026 (encontrado armando la edición de souvenirs en
     * una inscripción pagada) — SouvenirParticipanteResource nunca exponía
     * `id`/`talla`/`sexo`, solo `nombre`/`precio`. El frontend
     * (`editParticipant()`) ya intentaba restaurar la tarjeta marcada
     * comparando `s.id` contra el id real del souvenir — con `id` siempre
     * ausente, reabrir CUALQUIER inscripción existente para editar (no
     * solo pagada) nunca marcaba los souvenirs que el participante ya
     * había elegido.
     */
    public function test_registrations_mine_incluye_id_talla_sexo_del_souvenir(): void
    {
        $persona = Persona::factory()->create(['numero_documento' => '30000003']);
        $this->actingAsPersona($persona);

        $souvenirConTalla = Souvenir::factory()->create([
            'form_types_id' => $this->formType->id,
            'price' => 20,
            'requiere_talla' => true,
        ]);

        $registration = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-TEST-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => $this->totalesData(['souvenirs' => 20, 'grand_total' => 72.5]),
            'participantes' => [$this->participanteData('30000003', [
                'souvenirs' => [['id' => $souvenirConTalla->id, 'nombre' => $souvenirConTalla->name, 'precio' => 20, 'talla' => 'M']],
            ])],
        ]));
        $registration->update(['pago_status' => 'paid']);

        $response = $this->getJson('/api/v1/registrations/mine')->assertOk();

        $souvenirs = $response->json('data.0.participantes.0.souvenirs');
        $this->assertNotEmpty($souvenirs, 'ParticipanteResource.souvenirs vino vacío/ausente.');
        $this->assertEquals($souvenirConTalla->id, $souvenirs[0]['id']);
        $this->assertEquals('M', $souvenirs[0]['talla']);
    }
}
