<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Participante;
use App\Models\ParticipanteTallerSesion;
use App\Models\Registration;
use App\Models\SesionCongreso;
use App\Models\Souvenir;
use App\Models\SouvenirParticipante;
use App\Models\SubtipoEvento;
use App\Models\Taller;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reporte de inscritos por modalidad/categoría + poleras — pedido por el
 * usuario el 15/08/2026, ver App\Support\ReporteInscritosData. Cuelga del
 * mismo endpoint que el Dashboard de inscripciones existente
 * (`GET /event/{event}/dashboard-inscripciones`), bajo la clave
 * `reporteInscritos`.
 */
class ReporteInscritosTest extends TestCase
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

    private function crearInscripcion(FormType $formType, Category $categoria, array $overrides = []): Participante
    {
        $registration = Registration::create([
            'referencia' => 'REF' . rand(100000, 999999),
            'fecha' => now(),
            'evento_id' => $this->evento->id,
            'form_types_id' => $formType->id,
            'evento_nombre' => $this->evento->nombre,
            'tipo_pago' => 'EFECTIVO',
            'pago_status' => $overrides['pago_status'] ?? 'paid',
        ]);

        return Participante::create(array_merge([
            'registration_id' => $registration->id,
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'genero' => 'Femenino',
            'tipo_documento' => 'DNI', 'numero_documento' => (string) rand(1000000, 9999999),
            'fecha_nacimiento' => '1995-01-01', 'edad' => 30,
            'correo' => 'ana' . rand(1, 999999) . '@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => $categoria->id,
            'precio_categoria' => $categoria->price,
            'subtotal' => $categoria->price,
        ], $overrides));
    }

    public function test_agrupa_por_modalidad_categoria_y_poleras_solo_pagadas(): void
    {
        $individual = FormType::factory()->create(['event_id' => $this->evento->id, 'name' => 'Individual']);
        $grupal = FormType::factory()->create(['event_id' => $this->evento->id, 'name' => 'Grupal']);
        $cinco_k = Category::factory()->create(['event_id' => $this->evento->id, 'name' => '5K', 'price' => 50]);
        $diez_k = Category::factory()->create(['event_id' => $this->evento->id, 'name' => '10K', 'price' => 80]);

        // Reporte de poleras (03/09/2026) — ya no sale de participantes.polera
        // (siempre queda en el sentinel 'No shirt' del frontend), sale de
        // souvenir_participantes.talla filtrado a souvenirs es_polera=true.
        // Ver ReporteInscritosData::agruparPoleras().
        $polera = Souvenir::factory()->create([
            'form_types_id' => $individual->id, 'requiere_talla' => true, 'es_polera' => true, 'price' => 45,
        ]);

        // 2 pagados en Individual/5K, con polera.
        $p1 = $this->crearInscripcion($individual, $cinco_k, ['genero' => 'Femenino']);
        SouvenirParticipante::create(['participante_id' => $p1->id, 'souvenir_id' => $polera->id, 'nombre' => $polera->name, 'precio' => $polera->price, 'talla' => 'M']);
        $p2 = $this->crearInscripcion($individual, $cinco_k, ['genero' => 'Masculino']);
        SouvenirParticipante::create(['participante_id' => $p2->id, 'souvenir_id' => $polera->id, 'nombre' => $polera->name, 'precio' => $polera->price, 'talla' => 'L']);
        // 1 pagado en Grupal/10K, sin polera.
        $this->crearInscripcion($grupal, $diez_k, ['subtotal' => 80, 'precio_categoria' => 80]);
        // 1 pendiente — no debe contarse en ninguna tabla (solo se cuenta lo pagado).
        $this->crearInscripcion($individual, $cinco_k, ['pago_status' => 'pending']);

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $this->evento->id]);

        $response = $this->getJson("/api/v1/event/{$this->evento->id}/dashboard-inscripciones")
            ->assertStatus(200);

        $reporte = $response->json('reporteInscritos');

        $this->assertSame(3, $reporte['porModalidad']['totalCantidad']);
        $this->assertEquals(180.0, $reporte['porModalidad']['totalRecaudacion']); // 50+50+80

        $modalidades = collect($reporte['porModalidad']['filas'])->keyBy('nombre');
        $this->assertSame(2, $modalidades['Individual']['cantidad']);
        $this->assertEquals(100.0, $modalidades['Individual']['recaudacion']);
        $this->assertSame(1, $modalidades['Grupal']['cantidad']);
        $this->assertEquals(80.0, $modalidades['Grupal']['recaudacion']);

        $categorias = collect($reporte['porCategoria']['filas'])->keyBy('nombre');
        $this->assertSame(2, $categorias['5K']['cantidad']);
        $this->assertSame(1, $categorias['10K']['cantidad']);

        // Solo 2 de los 3 pagados tienen polera cargada.
        $this->assertSame(2, $reporte['poleras']['total']);
        // Costo unitario/Total (03/09/2026, pedido aparte) — cada fila tiene
        // 1 sola selección a $45, así que costoUnitario == montoTotal acá.
        $this->assertEquals(90.0, $reporte['poleras']['totalMonto']); // 45 + 45
        $poleras = collect($reporte['poleras']['filas']);
        $this->assertTrue($poleras->contains(fn ($f) => $f['sexo'] === 'Femenino' && $f['talla'] === 'M' && $f['cantidad'] === 1 && (float) $f['costoUnitario'] === 45.0 && (float) $f['montoTotal'] === 45.0));
        $this->assertTrue($poleras->contains(fn ($f) => $f['sexo'] === 'Masculino' && $f['talla'] === 'L' && $f['cantidad'] === 1 && (float) $f['costoUnitario'] === 45.0 && (float) $f['montoTotal'] === 45.0));
    }

    /**
     * Costo unitario como PROMEDIO real cobrado (03/09/2026) — si dos
     * participantes de la misma fila (sexo+talla) pagaron precios
     * distintos por la polera (ej. el precio de catálogo cambió durante
     * el evento), costoUnitario debe ser el promedio real, no "el último
     * precio visto" ni un precio fijo asumido.
     */
    public function test_costo_unitario_de_poleras_es_el_promedio_real_por_fila(): void
    {
        $formType = FormType::factory()->create(['event_id' => $this->evento->id]);
        $categoria = Category::factory()->create(['event_id' => $this->evento->id, 'price' => 100]);
        $polera = Souvenir::factory()->create([
            'form_types_id' => $formType->id, 'requiere_talla' => true, 'es_polera' => true, 'price' => 50,
        ]);

        $p1 = $this->crearInscripcion($formType, $categoria, ['genero' => 'Masculino']);
        SouvenirParticipante::create(['participante_id' => $p1->id, 'souvenir_id' => $polera->id, 'nombre' => $polera->name, 'precio' => 40, 'talla' => 'M']);
        $p2 = $this->crearInscripcion($formType, $categoria, ['genero' => 'Masculino']);
        SouvenirParticipante::create(['participante_id' => $p2->id, 'souvenir_id' => $polera->id, 'nombre' => $polera->name, 'precio' => 50, 'talla' => 'M']);

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $this->evento->id]);

        $response = $this->getJson("/api/v1/event/{$this->evento->id}/dashboard-inscripciones")->assertStatus(200);
        $fila = collect($response->json('reporteInscritos.poleras.filas'))->first();

        $this->assertSame(2, $fila['cantidad']);
        $this->assertEquals(90.0, $fila['montoTotal']); // 40 + 50
        $this->assertEquals(45.0, $fila['costoUnitario']); // promedio, no 40 ni 50
    }

    /**
     * Bug real 03/09/2026 — antes de este fix, el reporte de poleras leía
     * `participantes.polera`, que queda siempre en el string sentinel
     * 'No shirt' para form_types que modelan la polera como un souvenir
     * normal (sin el flujo legacy hasshirt). Este test reproduce
     * exactamente ese escenario: un souvenir con talla marcado como la
     * polera, y confirma que el 'No shirt' legacy no contamina el
     * reporte.
     */
    public function test_no_confunde_el_sentinel_no_shirt_legacy_con_talla_real(): void
    {
        $formType = FormType::factory()->create(['event_id' => $this->evento->id]);
        $categoria = Category::factory()->create(['event_id' => $this->evento->id, 'price' => 100]);
        $polera = Souvenir::factory()->create([
            'form_types_id' => $formType->id, 'requiere_talla' => true, 'es_polera' => true,
        ]);

        // Mismo dato que manda el frontend real cuando el flujo legacy no
        // aplica (index.php: polera === 'con' ? shirtSize : 'No shirt').
        $p = $this->crearInscripcion($formType, $categoria, ['polera' => 'No shirt']);
        SouvenirParticipante::create(['participante_id' => $p->id, 'souvenir_id' => $polera->id, 'nombre' => $polera->name, 'precio' => $polera->price, 'talla' => 'S']);

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $this->evento->id]);

        $response = $this->getJson("/api/v1/event/{$this->evento->id}/dashboard-inscripciones")
            ->assertStatus(200);

        $poleras = $response->json('reporteInscritos.poleras');
        $this->assertSame(1, $poleras['total']);
        $this->assertSame('S', $poleras['filas'][0]['talla']);
        $this->assertNotContains('No shirt', array_column($poleras['filas'], 'talla'));
    }

    /**
     * Un form_type puede tener otro souvenir con talla (ej. una mochila)
     * que NO está marcado es_polera — no debe mezclarse en el reporte.
     */
    public function test_souvenir_con_talla_que_no_es_la_polera_no_aparece_en_el_reporte(): void
    {
        $formType = FormType::factory()->create(['event_id' => $this->evento->id]);
        $categoria = Category::factory()->create(['event_id' => $this->evento->id, 'price' => 100]);
        $mochila = Souvenir::factory()->create([
            'form_types_id' => $formType->id, 'requiere_talla' => true, 'es_polera' => false, 'name' => 'Mochila',
        ]);

        $p = $this->crearInscripcion($formType, $categoria);
        SouvenirParticipante::create(['participante_id' => $p->id, 'souvenir_id' => $mochila->id, 'nombre' => $mochila->name, 'precio' => $mochila->price, 'talla' => 'Única']);

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $this->evento->id]);

        $response = $this->getJson("/api/v1/event/{$this->evento->id}/dashboard-inscripciones")
            ->assertStatus(200);

        $poleras = $response->json('reporteInscritos.poleras');
        $this->assertSame(0, $poleras['total']);
        $this->assertEmpty($poleras['filas']);
    }

    /**
     * Reporte de talleres (19/08/2026) — pedido del usuario tras el bug
     * real de `participante_taller_sesion` (ver
     * brain/... si aplica). Se agrupa por sesión, solo cuenta lo pagado
     * (mismo criterio que el resto de este reporte).
     */
    public function test_agrupa_por_taller_sesion_solo_pagados(): void
    {
        $formType = FormType::factory()->create(['event_id' => $this->evento->id]);
        $categoria = Category::factory()->create(['event_id' => $this->evento->id, 'price' => 100]);

        $taller = Taller::factory()->create([
            'evento_id' => $this->evento->id,
            'nombre' => 'Bombas Elastoméricas',
            'modalidad' => 'OPTIONAL',
            'precio' => 800,
        ]);
        $sesion = SesionCongreso::factory()->create([
            'evento_id' => $this->evento->id,
            'taller_id' => $taller->id,
            'titulo' => '15 OCT · TARDE',
            'fecha' => '2026-10-15',
            'hora_inicio' => '13:00:00',
            'hora_fin' => '18:30:00',
            'cupo' => 20,
        ]);

        // 2 pagados con el taller.
        $p1 = $this->crearInscripcion($formType, $categoria, ['subtotal' => 900]);
        ParticipanteTallerSesion::create([
            'participante_id' => $p1->id, 'sesion_congreso_id' => $sesion->id, 'taller_id' => $taller->id,
            'unit_price' => 800, 'discount' => 0, 'total' => 800,
        ]);
        $p2 = $this->crearInscripcion($formType, $categoria, ['subtotal' => 900]);
        ParticipanteTallerSesion::create([
            'participante_id' => $p2->id, 'sesion_congreso_id' => $sesion->id, 'taller_id' => $taller->id,
            'unit_price' => 800, 'discount' => 0, 'total' => 800,
        ]);
        // 1 pendiente con el taller — no cuenta para cantidad/recaudación
        // (solo dinero cobrado), pero SÍ ocupa cupo (31/08/2026, mismo
        // criterio que FormType::inscritosVigentes(): un pago QR en curso
        // también reserva lugar) — ver disponible más abajo.
        $p3 = $this->crearInscripcion($formType, $categoria, ['subtotal' => 900, 'pago_status' => 'pending']);
        ParticipanteTallerSesion::create([
            'participante_id' => $p3->id, 'sesion_congreso_id' => $sesion->id, 'taller_id' => $taller->id,
            'unit_price' => 800, 'discount' => 0, 'total' => 800,
        ]);
        // 1 pagado SIN taller — no debe aparecer en porTaller.
        $this->crearInscripcion($formType, $categoria);

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $this->evento->id]);

        $response = $this->getJson("/api/v1/event/{$this->evento->id}/dashboard-inscripciones")
            ->assertStatus(200);

        $porTaller = $response->json('reporteInscritos.porTaller');

        $this->assertSame(2, $porTaller['totalCantidad']);
        $this->assertEquals(1600.0, $porTaller['totalRecaudacion']);
        $this->assertCount(1, $porTaller['filas']);

        $fila = $porTaller['filas'][0];
        $this->assertSame('Bombas Elastoméricas', $fila['tallerNombre']);
        $this->assertSame($sesion->id, $fila['sesionId']);
        $this->assertSame(2, $fila['cantidad']);
        $this->assertEquals(1600.0, $fila['recaudacion']);
        $this->assertSame(20, $fila['cupo']);
        // 20 - 2 pagados - 1 pendiente = 17 (el pendiente sí ocupa cupo).
        $this->assertSame(17, $fila['disponible']);
    }

    /**
     * Detalle sin agrupar de talleres (20/08/2026) — CSV descargable para
     * el organizador, no confundir con `porTaller.filas` (agrupado por
     * sesión, ver test de arriba). Una fila por cada selección de taller,
     * ordenado por fecha/hora.
     */
    public function test_detalle_de_talleres_sin_agrupar_ordenado_por_fecha(): void
    {
        $formType = FormType::factory()->create(['event_id' => $this->evento->id]);
        $categoria = Category::factory()->create(['event_id' => $this->evento->id, 'price' => 100]);

        $taller = Taller::factory()->create(['evento_id' => $this->evento->id, 'nombre' => 'Bombas Elastoméricas']);
        $sesionTemprano = SesionCongreso::factory()->create([
            'evento_id' => $this->evento->id, 'taller_id' => $taller->id,
            'fecha' => '2026-10-15', 'hora_inicio' => '08:00:00', 'hora_fin' => '10:00:00',
        ]);
        $sesionTarde = SesionCongreso::factory()->create([
            'evento_id' => $this->evento->id, 'taller_id' => $taller->id,
            'fecha' => '2026-10-16', 'hora_inicio' => '14:00:00', 'hora_fin' => '16:00:00',
        ]);

        // Participante con 2 talleres — debe aparecer 2 veces (sin agrupar).
        $p1 = $this->crearInscripcion($formType, $categoria, ['nombre' => 'Beto', 'apellido' => 'Zeta', 'subtotal' => 900]);
        ParticipanteTallerSesion::create([
            'participante_id' => $p1->id, 'sesion_congreso_id' => $sesionTarde->id, 'taller_id' => $taller->id,
            'unit_price' => 800, 'discount' => 0, 'total' => 800,
        ]);
        ParticipanteTallerSesion::create([
            'participante_id' => $p1->id, 'sesion_congreso_id' => $sesionTemprano->id, 'taller_id' => $taller->id,
            'unit_price' => 800, 'discount' => 0, 'total' => 800,
        ]);
        // Otro participante, pendiente — no debe aparecer.
        $p2 = $this->crearInscripcion($formType, $categoria, ['subtotal' => 900, 'pago_status' => 'pending']);
        ParticipanteTallerSesion::create([
            'participante_id' => $p2->id, 'sesion_congreso_id' => $sesionTemprano->id, 'taller_id' => $taller->id,
            'unit_price' => 800, 'discount' => 0, 'total' => 800,
        ]);

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $this->evento->id]);

        $response = $this->getJson("/api/v1/event/{$this->evento->id}/dashboard-inscripciones")
            ->assertStatus(200);

        $detalle = $response->json('reporteInscritos.porTaller.detalle');

        // Solo las 2 filas del participante pagado, no la del pendiente.
        $this->assertCount(2, $detalle);
        // Ordenado por fecha: la sesión temprana (15/10) va antes que la tarde (16/10).
        $this->assertSame('2026-10-15', $detalle[0]['fecha']);
        $this->assertSame('2026-10-16', $detalle[1]['fecha']);
        $this->assertSame('Beto', $detalle[0]['participanteNombre']);
        $this->assertSame('Bombas Elastoméricas', $detalle[0]['tallerNombre']);
        $this->assertEquals(800.0, $detalle[0]['precio']);
    }

    /**
     * Reporte de talleres confiable (27/08/2026) — un taller agregado
     * después con "pagar en el evento" (pago_pendiente=true) no debe
     * mezclarse bajo "recaudación" con lo ya cobrado. Ver
     * ParticipanteTallerSesion::pago_pendiente.
     */
    public function test_reporte_de_taller_separa_lo_cobrado_de_lo_pendiente(): void
    {
        $formType = FormType::factory()->create(['event_id' => $this->evento->id]);
        $categoria = Category::factory()->create(['event_id' => $this->evento->id, 'price' => 100]);

        $taller = Taller::factory()->create(['evento_id' => $this->evento->id, 'nombre' => 'Sutura Avanzada']);
        $sesion = SesionCongreso::factory()->create([
            'evento_id' => $this->evento->id, 'taller_id' => $taller->id, 'cupo' => 20,
        ]);

        $p1 = $this->crearInscripcion($formType, $categoria, ['subtotal' => 900]);
        ParticipanteTallerSesion::create([
            'participante_id' => $p1->id, 'sesion_congreso_id' => $sesion->id, 'taller_id' => $taller->id,
            'unit_price' => 800, 'discount' => 0, 'total' => 800, 'pago_pendiente' => false,
        ]);
        $p2 = $this->crearInscripcion($formType, $categoria, ['subtotal' => 900]);
        ParticipanteTallerSesion::create([
            'participante_id' => $p2->id, 'sesion_congreso_id' => $sesion->id, 'taller_id' => $taller->id,
            'unit_price' => 800, 'discount' => 0, 'total' => 800, 'pago_pendiente' => true,
        ]);

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $this->evento->id]);

        $response = $this->getJson("/api/v1/event/{$this->evento->id}/dashboard-inscripciones")
            ->assertStatus(200);

        $fila = $response->json('reporteInscritos.porTaller.filas.0');
        $this->assertSame(2, $fila['cantidad']);
        $this->assertEquals(1600.0, $fila['recaudacion']);
        $this->assertSame(1, $fila['cantidadPendiente']);
        $this->assertEquals(800.0, $fila['recaudacionPendiente']);
        $this->assertEquals(800.0, $fila['recaudacionCobrada']);

        $detalle = collect($response->json('reporteInscritos.porTaller.detalle'));
        $this->assertSame(1, $detalle->where('pagoPendiente', true)->count());
        $this->assertSame('Pendiente (efectivo en el evento)', $detalle->firstWhere('pagoPendiente', true)['estadoPago']);
        $this->assertSame('Pagado', $detalle->firstWhere('pagoPendiente', false)['estadoPago']);
    }

    public function test_admin_de_otro_evento_no_ve_el_reporte(): void
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

        $this->getJson("/api/v1/event/{$this->evento->id}/dashboard-inscripciones")
            ->assertStatus(403);
    }
}
