<?php

namespace Tests\Feature;

use App\Actions\ActualizarInscripcionAction;
use App\Actions\ActualizarInscripcionPagadaAction;
use App\Actions\CrearInscripcionAction;
use App\DTOs\RegistrationDTO;
use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Souvenir;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Edición restringida a solo souvenirs/talleres (04/09/2026) — ver
 * App\Support\EdicionSoloExtrasData. Pedido de organizadores de congresos:
 * con `form_types.edicion_solo_extras=true`, el participante solo puede
 * agregar souvenirs/talleres al editar su inscripción — no puede tocar
 * datos personales ni categoría, sin importar lo que mande el cliente.
 */
class EdicionSoloExtrasTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private FormType $formType;

    private Category $categoria;

    private Category $categoriaCara;

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
        ]);

        $this->formType = FormType::factory()->create([
            'event_id' => $this->evento->id,
            'requiere_categoria' => true,
            'edicion_solo_extras' => true,
        ]);

        $this->categoria = Category::factory()->create(['event_id' => $this->evento->id, 'price' => 50]);
        $this->categoriaCara = Category::factory()->create(['event_id' => $this->evento->id, 'price' => 120]);

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

    private function crearInscripcionPendiente(string $numeroDocumento): \App\Models\Registration
    {
        return app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
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
    }

    // ── Edición PENDIENTE ──────────────────────────────────────────────

    public function test_edicion_pendiente_ignora_cambio_de_datos_personales_y_categoria(): void
    {
        $registration = $this->crearInscripcionPendiente('30000001');

        $editado = app(ActualizarInscripcionAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('30000001', [
                'nombre' => 'Otro Nombre',
                'correo' => 'otro@test.net',
                'categoria' => (string) $this->categoriaCara->id,
                'precioCategoria' => 120,
                'souvenirs' => [['id' => $this->souvenir->id, 'nombre' => $this->souvenir->name, 'precio' => 20]],
            ])],
            'totales' => $this->totalesData(['souvenirs' => 20, 'fee' => 3.5, 'grand_total' => 73.5]),
        ]);

        $participante = $editado->participants->first();
        $this->assertEquals('Ana', $participante->nombre);
        $this->assertNotEquals('otro@test.net', $participante->correo);
        $this->assertEquals((string) $this->categoria->id, $participante->categoria);
        $this->assertEquals(50.0, (float) $participante->precio_categoria);

        // El souvenir SÍ se agregó — eso es justamente lo que el flag permite.
        $this->assertDatabaseHas('souvenir_participantes', [
            'participante_id' => $participante->id,
            'souvenir_id' => $this->souvenir->id,
        ]);
    }

    public function test_edicion_pendiente_rechaza_agregar_un_participante_nuevo(): void
    {
        $registration = $this->crearInscripcionPendiente('30000002');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('no permite agregar ni quitar participantes');

        app(ActualizarInscripcionAction::class)->handle($registration->referencia, [
            'participantes' => [
                $this->participanteData('30000002'),
                $this->participanteData('30000003'),
            ],
            'totales' => $this->totalesData(['inscripcion' => 100, 'fee' => 5, 'grand_total' => 105]),
        ]);
    }

    /**
     * Sin regresión: un form_type SIN el flag (default false) sigue
     * permitiendo editar datos personales libremente, como siempre.
     */
    public function test_edicion_pendiente_sin_el_flag_permite_cambiar_datos_personales(): void
    {
        $this->formType->update(['edicion_solo_extras' => false]);
        $registration = $this->crearInscripcionPendiente('30000004');

        $editado = app(ActualizarInscripcionAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('30000004', ['nombre' => 'Cambiado'])],
            'totales' => $this->totalesData(),
        ]);

        $this->assertEquals('Cambiado', $editado->participants->first()->nombre);
    }

    // ── Edición PAGADA ─────────────────────────────────────────────────

    private function crearInscripcionPagada(string $numeroDocumento): \App\Models\Registration
    {
        $registration = $this->crearInscripcionPendiente($numeroDocumento);
        $registration->update(['pago_status' => 'paid']);

        return $registration;
    }

    public function test_edicion_pagada_ignora_cambio_de_datos_personales_y_categoria(): void
    {
        $registration = $this->crearInscripcionPagada('30000005');

        $result = app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [$this->participanteData('30000005', [
                'apellido' => 'Otro Apellido',
                'categoria' => (string) $this->categoriaCara->id,
                'precioCategoria' => 999,
                'souvenirs' => [['id' => $this->souvenir->id, 'nombre' => $this->souvenir->name, 'precio' => 20]],
            ])],
            'totales' => $this->totalesData(['souvenirs' => 20, 'fee' => 3.5, 'grand_total' => 73.5]),
            '_usuario' => 'participante@test.net',
        ]);

        $participante = $result['registration']->participants->first();
        $this->assertEquals('Prueba', $participante->apellido);
        $this->assertEquals((string) $this->categoria->id, $participante->categoria);
        // costo_edicion (default del factory, probablemente 0) + precio real del souvenir (20).
        $this->assertEquals((float) $this->formType->costo_edicion + 20.0, $result['costo_adicion']);
    }

    public function test_edicion_pagada_rechaza_agregar_un_participante_nuevo(): void
    {
        $registration = $this->crearInscripcionPagada('30000006');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('no permite agregar ni quitar participantes');

        app(ActualizarInscripcionPagadaAction::class)->handle($registration->referencia, [
            'participantes' => [
                $this->participanteData('30000006'),
                $this->participanteData('30000007'),
            ],
            'totales' => $this->totalesData(['inscripcion' => 100, 'fee' => 5, 'grand_total' => 105]),
            '_usuario' => 'participante@test.net',
        ]);
    }
}
