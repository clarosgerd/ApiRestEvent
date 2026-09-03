<?php

namespace Tests\Feature;

use App\Actions\CrearInscripcionAction;
use App\DTOs\RegistrationDTO;
use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Registration;
use App\Models\Souvenir;
use App\Models\SouvenirParticipante;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Cobro en sitio — push-back desde el POS de retiro en sitio
 * (elascenso/delivery) cuando un form_type sin categoría (que ahora cobra
 * `precio_base` de verdad, ver PRD-precios-periodos-fechas.md sección 0)
 * llega pendiente de pago al mostrador. Ver
 * OrganizadorDashboardController::confirmarPagoSitio().
 */
class ConfirmarPagoSitioTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

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
    }

    /**
     * Crea una inscripción pendiente real (vía el mismo Action que usa el
     * registro online). `$categoriaValue` default null → usa el nombre del
     * form_type (caso `requiere_categoria=false`); pasar el id real de una
     * `Category` para el caso `requiere_categoria=true` — si no,
     * `CrearInscripcionAction::validatePrecioCategoria()` rechaza el fixture
     * antes de crearlo, igual que rechazaría un request real inconsistente.
     */
    private function crearInscripcionPendiente(FormType $formType, float $precio, string $documento, $categoriaValue = null): Registration
    {
        $participante = [
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'alias' => '', 'genero' => 'Femenino',
            'tipoDocumento' => 'DNI', 'numeroDocumento' => $documento,
            'polera' => '', 'precioPolera' => 0,
            'nacimiento' => ['dia' => 1, 'mes' => 1, 'anio' => 1995], 'edad' => 30,
            'correo' => 'ana'.rand(1, 999999).'@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'contacto_emergencia' => ['nombre' => 'X', 'celular' => '123', 'relacion' => 'Madre'],
            'souvenirs' => [], 'answers' => [],
            'categoria' => $categoriaValue ?? $formType->name, 'precioCategoria' => $precio,
            'donacion' => 0, 'promoDescuento' => 0, 'promoCodigo' => '',
            'subtotal' => $precio,
        ];
        $fee = round($precio * 0.05, 2);

        $dto = RegistrationDTO::fromArray([
            'referencia' => 'LA-SITIO-'.uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => [
                'inscripcion' => $precio, 'donacion' => 0, 'souvenirs' => 0, 'fee' => $fee,
                'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => $precio + $fee,
            ],
            'participantes' => [$participante],
        ]);

        return app(CrearInscripcionAction::class)->handle($dto);
    }

    public function test_confirma_pago_de_un_form_type_sin_categoria_pendiente(): void
    {
        $formType = FormType::factory()->create([
            'event_id' => $this->evento->id, 'activo' => true,
            'requiere_categoria' => false, 'precio_base' => 30,
        ]);
        $this->crearInscripcionPendiente($formType, 30, '11112222');

        $url = URL::signedRoute('organizador.dashboard.confirmar-pago-sitio', [
            'evento' => $this->evento->id, 'documento' => '11112222',
        ]);

        $this->getJson($url)->assertOk()->assertJson(['success' => true, 'pagoStatus' => 'paid']);

        $this->assertDatabaseHas('registrations', [
            'evento_id' => $this->evento->id, 'pago_status' => 'paid',
        ]);
        Mail::assertSent(\App\Mail\PagoConfirmadoMail::class);
    }

    public function test_rechaza_firma_invalida(): void
    {
        $formType = FormType::factory()->create([
            'event_id' => $this->evento->id, 'activo' => true,
            'requiere_categoria' => false, 'precio_base' => 30,
        ]);
        $this->crearInscripcionPendiente($formType, 30, '33334444');

        $this->getJson("/organizador/evento/{$this->evento->id}/participantes/33334444/confirmar-pago-sitio")
            ->assertStatus(403);
    }

    public function test_rechaza_form_type_que_requiere_categoria(): void
    {
        $formType = FormType::factory()->create([
            'event_id' => $this->evento->id, 'activo' => true,
            'requiere_categoria' => true,
        ]);
        $categoria = Category::factory()->create(['event_id' => $this->evento->id, 'price' => 80]);
        $registration = $this->crearInscripcionPendiente($formType, 80, '55556666', $categoria->id);

        $url = URL::signedRoute('organizador.dashboard.confirmar-pago-sitio', [
            'evento' => $this->evento->id, 'documento' => '55556666',
        ]);

        $this->getJson($url)->assertStatus(422)->assertJson(['success' => false]);
        $this->assertDatabaseHas('registrations', ['id' => $registration->id, 'pago_status' => 'pending']);
    }

    public function test_idempotente_si_ya_estaba_pagado(): void
    {
        $formType = FormType::factory()->create([
            'event_id' => $this->evento->id, 'activo' => true,
            'requiere_categoria' => false, 'precio_base' => 30,
        ]);
        $registration = $this->crearInscripcionPendiente($formType, 30, '77778888');
        $registration->update(['pago_status' => 'paid']);

        $url = URL::signedRoute('organizador.dashboard.confirmar-pago-sitio', [
            'evento' => $this->evento->id, 'documento' => '77778888',
        ]);

        $this->getJson($url)->assertOk()->assertJson(['success' => true, 'pagoStatus' => 'paid']);
    }

    public function test_404_si_el_documento_no_pertenece_al_evento(): void
    {
        $url = URL::signedRoute('organizador.dashboard.confirmar-pago-sitio', [
            'evento' => $this->evento->id, 'documento' => '00000000',
        ]);

        $this->getJson($url)->assertStatus(404);
    }

    public function test_csv_export_incluye_monto_y_url_solo_para_elegibles(): void
    {
        $formTypeSinCategoria = FormType::factory()->create([
            'event_id' => $this->evento->id, 'activo' => true,
            'requiere_categoria' => false, 'precio_base' => 30,
        ]);
        $this->crearInscripcionPendiente($formTypeSinCategoria, 30, '10101010');

        $formTypeConCategoria = FormType::factory()->create([
            'event_id' => $this->evento->id, 'activo' => true,
            'requiere_categoria' => true,
        ]);
        $categoria = Category::factory()->create(['event_id' => $this->evento->id, 'price' => 80]);
        $this->crearInscripcionPendiente($formTypeConCategoria, 80, '20202020', $categoria->id);

        $url = URL::signedRoute('organizador.dashboard.export', ['evento' => $this->evento->id]);
        $response = $this->get($url)->assertOk();
        $csv = $response->streamedContent();

        $lines = array_map('str_getcsv', explode("\n", trim($csv)));
        $header = $lines[0];
        $montoIdx = array_search('MontoPendiente', $header);
        $urlIdx = array_search('ConfirmarPagoSitioUrl', $header);
        $docIdx = array_search('Documento', $header);

        $this->assertNotFalse($montoIdx);
        $this->assertNotFalse($urlIdx);

        $filaSinCategoria = collect($lines)->first(fn ($l) => str_contains($l[$docIdx] ?? '', '10101010'));
        $filaConCategoria = collect($lines)->first(fn ($l) => str_contains($l[$docIdx] ?? '', '20202020'));

        $this->assertNotEmpty($filaSinCategoria[$urlIdx]);
        $this->assertEquals('31.50', $filaSinCategoria[$montoIdx]); // 30 + 5% fee, decimal(10,2)

        $this->assertEmpty($filaConCategoria[$urlIdx]);
        $this->assertEmpty($filaConCategoria[$montoIdx]);
    }

    /**
     * Bug real (02/09/2026, reportado por el usuario revisando el CSV
     * exportado): la columna "Categoría" mostraba el ID crudo guardado en
     * `participantes.categoria` (ej. "6", "90028") en vez del nombre real
     * (ej. "15K") — mismo mapeo que ya usa la pantalla del dashboard
     * (`nombresCategorias`), ahora también en el CSV. Un form_type sin
     * categoría real (requiere_categoria=false) sigue mostrando su propio
     * nombre tal cual (ya era legible, no hace falta mapeo).
     */
    public function test_csv_export_muestra_el_nombre_de_la_categoria_no_el_id(): void
    {
        $formTypeSinCategoria = FormType::factory()->create([
            'event_id' => $this->evento->id, 'activo' => true,
            'requiere_categoria' => false, 'precio_base' => 30, 'name' => 'Sin categoría X',
        ]);
        $this->crearInscripcionPendiente($formTypeSinCategoria, 30, '30303030');

        $formTypeConCategoria = FormType::factory()->create([
            'event_id' => $this->evento->id, 'activo' => true,
            'requiere_categoria' => true,
        ]);
        $categoria = Category::factory()->create(['event_id' => $this->evento->id, 'price' => 80, 'name' => '15K']);
        $this->crearInscripcionPendiente($formTypeConCategoria, 80, '40404040', $categoria->id);

        $url = URL::signedRoute('organizador.dashboard.export', ['evento' => $this->evento->id]);
        $csv = $this->get($url)->assertOk()->streamedContent();

        $lines = array_map('str_getcsv', explode("\n", trim($csv)));
        $header = $lines[0];
        $catIdx = array_search('Categoría', $header);
        $docIdx = array_search('Documento', $header);

        $filaSinCategoria = collect($lines)->first(fn ($l) => str_contains($l[$docIdx] ?? '', '30303030'));
        $filaConCategoria = collect($lines)->first(fn ($l) => str_contains($l[$docIdx] ?? '', '40404040'));

        $this->assertSame('15K', $filaConCategoria[$catIdx]);
        $this->assertSame('Sin categoría X', $filaSinCategoria[$catIdx]);
    }

    /**
     * "Monto categoría"/"Monto souvenir" (02/09/2026) — mismo pedido que
     * el nombre de categoría de arriba: el CSV mostraba los nombres pero
     * no cuánto pagó cada participante por cada uno.
     */
    public function test_csv_export_incluye_monto_categoria_y_monto_souvenir(): void
    {
        $formType = FormType::factory()->create([
            'event_id' => $this->evento->id, 'activo' => true, 'requiere_categoria' => true,
        ]);
        $categoria = Category::factory()->create(['event_id' => $this->evento->id, 'price' => 100, 'name' => '15K']);
        $souvenir = \App\Models\Souvenir::factory()->create([
            'form_types_id' => $formType->id, 'name' => 'Polera', 'price' => 40,
        ]);

        $participante = [
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'alias' => '', 'genero' => 'Femenino',
            'tipoDocumento' => 'DNI', 'numeroDocumento' => '50505050',
            'polera' => '', 'precioPolera' => 0,
            'nacimiento' => ['dia' => 1, 'mes' => 1, 'anio' => 1995], 'edad' => 30,
            'correo' => 'ana'.rand(1, 999999).'@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'contacto_emergencia' => ['nombre' => 'X', 'celular' => '123', 'relacion' => 'Madre'],
            'souvenirs' => [['id' => $souvenir->id, 'nombre' => 'Polera', 'precio' => 40]],
            'answers' => [],
            'categoria' => (string) $categoria->id, 'precioCategoria' => 100,
            'donacion' => 0, 'promoDescuento' => 0, 'promoCodigo' => '',
            'subtotal' => 140,
        ];
        $dto = RegistrationDTO::fromArray([
            'referencia' => 'LA-SITIO-'.uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => [
                // souvenir default aplica_cargo_servicio=false -> el fee
                // solo mira inscripción (100 * 5% = 5), no la polera.
                'inscripcion' => 100, 'donacion' => 0, 'souvenirs' => 40, 'fee' => 5,
                'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 145,
            ],
            'participantes' => [$participante],
        ]);
        app(CrearInscripcionAction::class)->handle($dto);

        $url = URL::signedRoute('organizador.dashboard.export', ['evento' => $this->evento->id]);
        $csv = $this->get($url)->assertOk()->streamedContent();

        $lines = array_map('str_getcsv', explode("\n", trim($csv)));
        $header = $lines[0];
        $docIdx = array_search('Documento', $header);
        $montoCatIdx = array_search('Monto categoría', $header);
        $montoSvIdx = array_search('Monto souvenir', $header);

        $fila = collect($lines)->first(fn ($l) => str_contains($l[$docIdx] ?? '', '50505050'));

        $this->assertEquals('100.00', $fila[$montoCatIdx]);
        $this->assertEquals('40.00', $fila[$montoSvIdx]);
    }

    /**
     * Inscritos por categoría/distancia con recaudación (03/09/2026,
     * pedido del usuario) — el dashboard del organizador (link firmado,
     * sin login) ahora también muestra este reporte, antes solo vivía en
     * el panel autenticado (EventoController::dashboardInscripciones).
     * Distinto del "Por categoría" ya existente en esta misma página (ese
     * cuenta por estado de pago, sin dinero) — ver
     * ReporteInscritosData::agruparPorCategoria().
     */
    public function test_dashboard_incluye_reporte_de_categoria_con_recaudacion(): void
    {
        $formType = FormType::factory()->create(['event_id' => $this->evento->id, 'requiere_categoria' => true]);
        $categoria = Category::factory()->create(['event_id' => $this->evento->id, 'name' => '15K', 'price' => 100]);

        $registration = $this->crearInscripcionPendiente($formType, 100, '60606060', (string) $categoria->id);
        $registration->update(['pago_status' => 'paid']);

        $url = URL::signedRoute('organizador.dashboard', ['evento' => $this->evento->id]);
        $response = $this->get($url)->assertOk();

        $response->assertSee('Inscritos por categoría / distancia');
        $response->assertSee('15K');
        $response->assertSee('100.00');
    }

    /**
     * Reporte de poleras (03/09/2026) — pedido del usuario: faltaba en
     * esta misma página, ver ReporteInscritosData::agruparPoleras().
     */
    public function test_dashboard_incluye_reporte_de_poleras(): void
    {
        $formType = FormType::factory()->create(['event_id' => $this->evento->id, 'requiere_categoria' => true]);
        $categoria = Category::factory()->create(['event_id' => $this->evento->id, 'price' => 100]);
        $polera = Souvenir::factory()->create([
            'form_types_id' => $formType->id, 'requiere_talla' => true, 'es_polera' => true,
        ]);

        $registration = $this->crearInscripcionPendiente($formType, 100, '70707070', (string) $categoria->id);
        $registration->update(['pago_status' => 'paid']);
        $participante = $registration->participants()->first();
        SouvenirParticipante::create([
            'participante_id' => $participante->id, 'souvenir_id' => $polera->id,
            'nombre' => $polera->name, 'precio' => $polera->price, 'talla' => 'M',
        ]);

        $url = URL::signedRoute('organizador.dashboard', ['evento' => $this->evento->id]);
        $response = $this->get($url)->assertOk();

        $response->assertSee('Reporte de poleras');
        $response->assertSee('Femenino');
        $response->assertSee('M');
    }
}
