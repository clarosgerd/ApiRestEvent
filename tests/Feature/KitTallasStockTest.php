<?php

namespace Tests\Feature;

use App\Actions\CrearInscripcionAction;
use App\DTOs\RegistrationDTO;
use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\ItemStock;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Souvenir;
use App\Models\SouvenirParticipante;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use App\Support\DisponibilidadItemData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Kit/tallas/stock — ver PRD-kit-tallas-stock-lista-espera.md. Cubre:
 * el arreglo del bug de `form_types.activo` (antes no bloqueaba nada,
 * ver el "Hallazgo adicional" del PRD), disponibilidad de stock por
 * talla/sexo, y la condición de carrera del último cupo/última talla.
 */
class KitTallasStockTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private FormType $formType;

    private Category $categoria;

    protected function setUp(): void
    {
        parent::setUp();

        // CrearInscripcionAction manda notificarInscripcionPendiente() de
        // forma síncrona cuando pago_status='pending' — este proyecto no
        // tiene sandbox de email por defecto en cualquier entorno que
        // corra estos tests, así que Mail::fake() es obligatorio, nunca
        // opcional acá.
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
            'cupo_total' => 100,
            'activo' => true,
            // Precios por período (12/08/2026) — pinneado a true
            // (FormTypeFactory lo randomiza con faker->boolean()): este
            // suite usa una categoría real de aquí en más, ver
            // PRD-precios-periodos-fechas.md, sección 0.
            'requiere_categoria' => true,
        ]);
        $this->categoria = Category::factory()->create([
            'event_id' => $this->evento->id,
            'price' => 50,
        ]);
    }

    private function dtoParaParticipante(array $overrides = [], array $souvenirs = []): RegistrationDTO
    {
        $participante = array_merge([
            'nombre' => 'Ana',
            'apellido' => 'Prueba',
            'alias' => '',
            'genero' => 'Femenino',
            'tipoDocumento' => 'DNI',
            'numeroDocumento' => (string) rand(10000000, 99999999),
            'polera' => '',
            'precioPolera' => 0,
            'nacimiento' => ['dia' => 1, 'mes' => 1, 'anio' => 1995],
            'edad' => 30,
            'correo' => 'ana'.rand(1, 999999).'@test.net',
            'direccion' => 'x',
            'ciudad' => 'x',
            'telefono' => '123',
            'contacto_emergencia' => ['nombre' => 'X', 'celular' => '123', 'relacion' => 'Madre'],
            'souvenirs' => $souvenirs,
            'answers' => [],
            'categoria' => (string) $this->categoria->id,
            'precioCategoria' => 50,
            'donacion' => 0,
            'promoDescuento' => 0,
            'promoCodigo' => '',
            'subtotal' => 50,
        ], $overrides);

        return RegistrationDTO::fromArray([
            'referencia' => 'LA-TEST-'.uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => [
                'inscripcion' => 50, 'donacion' => 0, 'souvenirs' => 0, 'fee' => 2.5,
                'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 52.5,
            ],
            'participantes' => [$participante],
        ]);
    }

    public function test_rechaza_inscripcion_si_el_form_type_esta_inactivo(): void
    {
        $this->formType->update(['activo' => false]);
        $action = app(CrearInscripcionAction::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('ya no tiene cupo disponible');

        $action->handle($this->dtoParaParticipante());
    }

    public function test_permite_inscripcion_si_el_form_type_esta_activo(): void
    {
        $action = app(CrearInscripcionAction::class);

        $registration = $action->handle($this->dtoParaParticipante());

        $this->assertDatabaseHas('registrations', ['id' => $registration->id]);
    }

    public function test_item_sin_fila_de_stock_tiene_disponibilidad_no_controlada(): void
    {
        $souvenir = Souvenir::factory()->create([
            'form_types_id' => $this->formType->id,
            'requiere_talla' => true,
        ]);

        $this->assertSame([], DisponibilidadItemData::paraSouvenir($souvenir));
        $this->assertNull(DisponibilidadItemData::disponibleParaCombinacion($souvenir, 'M', null));
    }

    public function test_rechaza_inscripcion_si_no_hay_stock_de_la_talla_pedida(): void
    {
        $souvenir = Souvenir::factory()->create(['form_types_id' => $this->formType->id, 'requiere_talla' => true]);
        ItemStock::create(['souvenir_id' => $souvenir->id, 'talla' => 'M', 'sexo' => null, 'cantidad_total' => 1]);

        // Ya hay 1 ocupado de M — no queda nada.
        $otroParticipanteDto = $this->dtoParaParticipante([], [
            ['id' => $souvenir->id, 'nombre' => $souvenir->name, 'precio' => 0, 'talla' => 'M'],
        ]);
        app(CrearInscripcionAction::class)->handle($otroParticipanteDto);

        $action = app(CrearInscripcionAction::class);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('No hay stock suficiente');

        $action->handle($this->dtoParaParticipante([], [
            ['id' => $souvenir->id, 'nombre' => $souvenir->name, 'precio' => 0, 'talla' => 'M'],
        ]));
    }

    /**
     * Bug real (01/09/2026, reportado por el usuario en vivo): un souvenir
     * SIN requiere_sexo (ej. "Polera Solo Varones", el sexo ya está
     * implícito en el nombre) nunca le pide sexo al participante — la
     * selección siempre llega con sexo null — pero el admin puede haber
     * cargado el stock CON un sexo propio en esas filas (ej.
     * sexo=masculino, 300 unidades reales). El match exacto contra `sexo`
     * nunca encontraba esa fila: bloqueaba la compra ("No hay stock
     * disponible...") pese a haber stock real cargado.
     */
    public function test_permite_inscripcion_cuando_stock_tiene_sexo_pero_souvenir_no_lo_pide(): void
    {
        $souvenir = Souvenir::factory()->create([
            'form_types_id' => $this->formType->id,
            'requiere_talla' => false,
            'requiere_sexo' => false,
        ]);
        ItemStock::create(['souvenir_id' => $souvenir->id, 'talla' => null, 'sexo' => 'masculino', 'cantidad_total' => 300]);

        $action = app(CrearInscripcionAction::class);
        $registration = $action->handle($this->dtoParaParticipante([], [
            ['id' => $souvenir->id, 'nombre' => $souvenir->name, 'precio' => 0],
        ]));

        $participante = $registration->participants->first();
        $this->assertDatabaseHas('souvenir_participantes', [
            'participante_id' => $participante->id,
            'souvenir_id' => $souvenir->id,
        ]);
        $this->assertSame(299, DisponibilidadItemData::disponibleParaCombinacion($souvenir, null, null));
    }

    public function test_permite_inscripcion_con_stock_disponible_y_guarda_talla(): void
    {
        $souvenir = Souvenir::factory()->create(['form_types_id' => $this->formType->id, 'requiere_talla' => true]);
        ItemStock::create(['souvenir_id' => $souvenir->id, 'talla' => 'L', 'sexo' => null, 'cantidad_total' => 5]);

        $action = app(CrearInscripcionAction::class);
        $registration = $action->handle($this->dtoParaParticipante([], [
            ['id' => $souvenir->id, 'nombre' => $souvenir->name, 'precio' => 0, 'talla' => 'L'],
        ]));

        $participante = $registration->participants->first();
        $this->assertDatabaseHas('souvenir_participantes', [
            'participante_id' => $participante->id,
            'souvenir_id' => $souvenir->id,
            'talla' => 'L',
        ]);
        $this->assertSame(4, DisponibilidadItemData::disponibleParaCombinacion($souvenir, 'L', null));
    }

    public function test_condicion_de_carrera_del_ultimo_stock_no_deja_pasar_dos(): void
    {
        $souvenir = Souvenir::factory()->create(['form_types_id' => $this->formType->id, 'requiere_talla' => true]);
        ItemStock::create(['souvenir_id' => $souvenir->id, 'talla' => 'S', 'sexo' => null, 'cantidad_total' => 1]);

        $dtoConSouvenir = fn () => $this->dtoParaParticipante([], [
            ['id' => $souvenir->id, 'nombre' => $souvenir->name, 'precio' => 0, 'talla' => 'S'],
        ]);

        // Primera pasa (consume la única unidad).
        app(CrearInscripcionAction::class)->handle($dtoConSouvenir());
        $this->assertSame(1, SouvenirParticipante::where('souvenir_id', $souvenir->id)->count());

        // Segunda debe rechazarse — no queda stock.
        $this->expectException(\DomainException::class);
        app(CrearInscripcionAction::class)->handle($dtoConSouvenir());
    }
}
