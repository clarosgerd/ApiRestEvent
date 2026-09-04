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
use App\Models\ItemStock;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Souvenir;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Revalidación de stock al EDITAR una inscripción existente — ver
 * PLAN-STOCK-SOUVENIRS-SIMPLES-13082026.md, punto 2. Antes de este
 * cambio, App\Actions\CrearInscripcionAction revalidaba stock al crear,
 * pero App\Services\RegistrationService::createParticipantFromData()
 * (usado por ActualizarInscripcionAction/ActualizarInscripcionPagadaAction
 * para recrear participantes al editar) no lo hacía — gap documentado
 * explícitamente en el propio código.
 */
class RegistrationUpdateStockTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private FormType $formType;

    private Category $categoria;

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
            'cupo_total' => 100,
            'activo' => true,
            'requiere_categoria' => true,
        ]);
        $this->categoria = Category::factory()->create([
            'event_id' => $this->evento->id,
            'price' => 50,
        ]);

        // Ítem simple, sin talla/sexo (ej. una medalla) — el mismo gap que
        // reportó el usuario: una sola fila null/null con cantidad_total=1.
        $this->souvenir = Souvenir::factory()->create([
            'form_types_id' => $this->formType->id,
            'requiere_talla' => false,
            'requiere_sexo' => false,
        ]);
        ItemStock::create(['souvenir_id' => $this->souvenir->id, 'talla' => null, 'sexo' => null, 'cantidad_total' => 1]);
    }

    private function dtoParaParticipante(array $souvenirs = []): RegistrationDTO
    {
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
            'participantes' => [[
                'nombre' => 'Ana', 'apellido' => 'Prueba', 'alias' => '', 'genero' => 'Femenino',
                'tipoDocumento' => 'DNI', 'numeroDocumento' => (string) rand(10000000, 99999999),
                'polera' => '', 'precioPolera' => 0,
                'nacimiento' => ['dia' => 1, 'mes' => 1, 'anio' => 1995], 'edad' => 30,
                'correo' => 'ana'.rand(1, 999999).'@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
                'contacto_emergencia' => ['nombre' => 'X', 'celular' => '123', 'relacion' => 'Madre'],
                'souvenirs' => $souvenirs, 'answers' => [],
                'categoria' => (string) $this->categoria->id, 'precioCategoria' => 50,
                'donacion' => 0, 'promoDescuento' => 0, 'promoCodigo' => '', 'subtotal' => 50,
            ]],
        ]);
    }

    private function datosParaEditar(string $numeroDocumento, array $souvenirs = []): array
    {
        return [
            'participantes' => [[
                'nombre' => 'Ana', 'apellido' => 'Prueba', 'alias' => '', 'genero' => 'Femenino',
                'tipoDocumento' => 'DNI', 'numeroDocumento' => $numeroDocumento,
                'polera' => '', 'precioPolera' => 0,
                'nacimiento' => ['dia' => 1, 'mes' => 1, 'anio' => 1995], 'edad' => 30,
                'correo' => 'ana@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
                'contacto_emergencia' => ['nombre' => 'X', 'celular' => '123', 'relacion' => 'Madre'],
                'souvenirs' => $souvenirs, 'answers' => [],
                'categoria' => (string) $this->categoria->id, 'precioCategoria' => 50,
                'donacion' => 0, 'promoDescuento' => 0, 'promoCodigo' => '', 'subtotal' => 50,
            ]],
            'totales' => [
                'inscripcion' => 50, 'donacion' => 0, 'souvenirs' => 0, 'fee' => 2.5,
                'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 52.5,
            ],
        ];
    }

    public function test_editar_inscripcion_rechaza_si_ya_no_hay_stock_del_item(): void
    {
        // La única unidad la consume OTRA inscripción.
        app(CrearInscripcionAction::class)->handle($this->dtoParaParticipante([
            ['id' => $this->souvenir->id, 'nombre' => $this->souvenir->name, 'precio' => 0, 'talla' => null, 'sexo' => null],
        ]));

        // Esta inscripción no tenía el ítem — al editarla para agregarlo,
        // debe rechazarse: antes de este fix, createParticipantFromData()
        // la habría dejado pasar sin chequear nada.
        $sinItem = app(CrearInscripcionAction::class)->handle($this->dtoParaParticipante([]));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('No hay stock suficiente');

        app(ActualizarInscripcionAction::class)->handle($sinItem->referencia, $this->datosParaEditar(
            $sinItem->participants->first()->numero_documento,
            [['id' => $this->souvenir->id, 'nombre' => $this->souvenir->name, 'precio' => 0, 'talla' => null, 'sexo' => null]]
        ));
    }

    public function test_editar_inscripcion_manteniendo_su_propio_item_no_se_autobloquea(): void
    {
        // Esta misma inscripción ya consumió la única unidad que existe.
        $registration = app(CrearInscripcionAction::class)->handle($this->dtoParaParticipante([
            ['id' => $this->souvenir->id, 'nombre' => $this->souvenir->name, 'precio' => 0, 'talla' => null, 'sexo' => null],
        ]));

        // Editarla sin cambiar nada del ítem (mismo souvenir) no debe
        // autobloquearse contra su propio consumo — antes de este fix no
        // se revalidaba nada; el punto de esta prueba es que, ahora que sí
        // se revalida, seguir teniendo el ítem propio no cuenta como "ya
        // ocupado por otro" (se borra antes de revalidar, ver
        // RegistrationService::validateStockForParticipants()).
        $editada = app(ActualizarInscripcionAction::class)->handle($registration->referencia, $this->datosParaEditar(
            $registration->participants->first()->numero_documento,
            [['id' => $this->souvenir->id, 'nombre' => $this->souvenir->name, 'precio' => 0, 'talla' => null, 'sexo' => null]]
        ));

        $this->assertDatabaseHas('souvenir_participantes', [
            'participante_id' => $editada->participants->first()->id,
            'souvenir_id' => $this->souvenir->id,
        ]);
    }

    public function test_actualizar_inscripcion_pagada_tambien_revalida_stock(): void
    {
        app(CrearInscripcionAction::class)->handle($this->dtoParaParticipante([
            ['id' => $this->souvenir->id, 'nombre' => $this->souvenir->name, 'precio' => 0, 'talla' => null, 'sexo' => null],
        ]));

        $sinItem = app(CrearInscripcionAction::class)->handle($this->dtoParaParticipante([]));
        $sinItem->update(['pago_status' => 'paid']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('No hay stock suficiente');

        app(ActualizarInscripcionPagadaAction::class)->handle($sinItem->referencia, $this->datosParaEditar(
            $sinItem->participants->first()->numero_documento,
            [['id' => $this->souvenir->id, 'nombre' => $this->souvenir->name, 'precio' => 0, 'talla' => null, 'sexo' => null]]
        ) + ['_usuario' => 'test@test.net']);
    }

    /**
     * Deshabilitar una categoría sin ocultarla (04/09/2026) — antes de este
     * cambio, ActualizarInscripcionAction no revalidaba categoría en
     * absoluto (ni existencia ni precio, ver
     * App\Support\Categoria\ValidarCategoriaAction). Editar hacia una
     * categoría deshabilitada debe rechazarse igual que al dar de alta.
     */
    public function test_editar_inscripcion_pendiente_rechaza_categoria_deshabilitada(): void
    {
        $registration = app(CrearInscripcionAction::class)->handle($this->dtoParaParticipante([]));

        $categoriaDeshabilitada = Category::factory()->create([
            'event_id' => $this->evento->id,
            'price' => 80,
            'permite_inscripcion' => false,
        ]);

        $datos = $this->datosParaEditar($registration->participants->first()->numero_documento);
        $datos['participantes'][0]['categoria'] = (string) $categoriaDeshabilitada->id;
        $datos['participantes'][0]['precioCategoria'] = 80;
        $datos['totales']['inscripcion'] = 80;
        $datos['totales']['grand_total'] = 84;

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("La categoría '{$categoriaDeshabilitada->name}' no está disponible para inscripción en este momento.");

        app(ActualizarInscripcionAction::class)->handle($registration->referencia, $datos);
    }

    /**
     * Sin "grandfather clause" en edición pendiente (04/09/2026) — a
     * diferencia de la edición YA PAGADA (EdicionPagadaCategoriaData, que sí
     * exime "sin cambios"), acá se revalida completo, mismo criterio que ya
     * usan los talleres en este mismo flujo
     * (ValidarSeleccionesTallerAction::run() sin exenciones). Si el
     * organizador deshabilita la categoría que el participante ya tenía
     * mientras la inscripción sigue pendiente (nada pagado todavía), la
     * edición se rechaza igual y el participante tiene que elegir otra.
     */
    public function test_editar_inscripcion_pendiente_rechaza_mantener_categoria_que_se_deshabilito(): void
    {
        $registration = app(CrearInscripcionAction::class)->handle($this->dtoParaParticipante([]));

        $this->categoria->update(['permite_inscripcion' => false]);

        $datos = $this->datosParaEditar($registration->participants->first()->numero_documento);
        // Mismo dato personal (ej. teléfono) sin tocar la categoría.
        $datos['participantes'][0]['telefono'] = '999';

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("La categoría '{$this->categoria->name}' no está disponible para inscripción en este momento.");

        app(ActualizarInscripcionAction::class)->handle($registration->referencia, $datos);
    }
}
