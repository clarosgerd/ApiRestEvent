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

        // 2 pagados en Individual/5K, con polera.
        $this->crearInscripcion($individual, $cinco_k, ['genero' => 'Femenino', 'polera' => 'M']);
        $this->crearInscripcion($individual, $cinco_k, ['genero' => 'Masculino', 'polera' => 'L']);
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
        $poleras = collect($reporte['poleras']['filas']);
        $this->assertTrue($poleras->contains(fn ($f) => $f['sexo'] === 'Femenino' && $f['talla'] === 'M' && $f['cantidad'] === 1));
        $this->assertTrue($poleras->contains(fn ($f) => $f['sexo'] === 'Masculino' && $f['talla'] === 'L' && $f['cantidad'] === 1));
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
