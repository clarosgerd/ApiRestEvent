<?php

namespace Tests\Unit;

use App\Services\RegistroValidacionService;
use PHPUnit\Framework\TestCase;

/**
 * Combos de talleres + precios por período/categoría (12-13/10/2026, pedido
 * del usuario), capa de preview de RegistroValidacionService (proxy
 * in-process, portado 1:1 de elascenso/event api/_registro_validacion.php)
 * — réplica en PHP del mismo algoritmo que ResolverCombosTallerAction
 * (ApiRestEvent), que es quien decide y persiste de verdad qué se cobra.
 */
class RegistroValidacionServiceCombosTest extends TestCase
{
    private function eventoBase(array $overrides = []): array
    {
        return array_merge([
            'talleresConCosto' => true,
            'usdPrecioFijo' => false,
            'fee_pct' => 0.05,
            'feeIncluyeTalleres' => true,
            'categories' => [
                ['id' => 1, 'price' => 100],
            ],
            'talleres' => [],
            'tallerCombos' => [],
            'promoCodes' => [],
        ], $overrides);
    }

    private function participanteBase(array $talleres, array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Juan', 'apellido' => 'Pérez', 'genero' => 'Masculino', 'correo' => 'juan@example.net',
            'categoria' => '1', 'precioCategoria' => 100,
            'talleres' => $talleres,
        ], $overrides);
    }

    public function test_sin_combo_cobra_cada_taller_a_su_precio_normal(): void
    {
        $evento = $this->eventoBase([
            'talleres' => [
                ['id' => 10, 'nombre' => 'Taller A', 'precio' => 50, 'precioVigente' => 50.0, 'sesiones' => [['id' => 100, 'precio' => null]]],
                ['id' => 11, 'nombre' => 'Taller B', 'precio' => 60, 'precioVigente' => 60.0, 'sesiones' => [['id' => 101, 'precio' => null]]],
            ],
        ]);
        $participantes = [$this->participanteBase([
            ['taller_id' => 10, 'sesion_congreso_id' => 100],
            ['taller_id' => 11, 'sesion_congreso_id' => 101],
        ])];

        $resultado = (new RegistroValidacionService())->validarYCalcularParticipantes($evento, null, $participantes);

        $this->assertArrayNotHasKey('error', $resultado);
        $this->assertSame(110.0, $resultado['totales']['talleres']);
    }

    public function test_combo_vigente_aplica_descuento_y_marca_taller_combo_id(): void
    {
        $evento = $this->eventoBase([
            'talleres' => [
                ['id' => 10, 'nombre' => 'Taller A', 'precio' => 50, 'precioVigente' => 50.0, 'sesiones' => [['id' => 100, 'precio' => null]]],
                ['id' => 11, 'nombre' => 'Taller B', 'precio' => 60, 'precioVigente' => 60.0, 'sesiones' => [['id' => 101, 'precio' => null]]],
            ],
            'tallerCombos' => [
                [
                    'id' => 5, 'vigente' => true, 'tallerIds' => [10, 11],
                    'precios' => [['categoriaId' => null, 'precio' => 90.0, 'priceUsd' => null]],
                ],
            ],
        ]);
        $participantes = [$this->participanteBase([
            ['taller_id' => 10, 'sesion_congreso_id' => 100],
            ['taller_id' => 11, 'sesion_congreso_id' => 101],
        ])];

        $resultado = (new RegistroValidacionService())->validarYCalcularParticipantes($evento, null, $participantes);

        $this->assertArrayNotHasKey('error', $resultado);
        // Suma normal 110, combo a 90 -> ahorro 20.
        $this->assertSame(90.0, $resultado['totales']['talleres']);
        $talleres = $resultado['participantes'][0]['talleres'];
        $this->assertSame(5, $talleres[0]['taller_combo_id']);
        $this->assertSame(5, $talleres[1]['taller_combo_id']);
    }

    /** Un combo sin fila de precio aplicable a la categoría del participante no es candidato. */
    public function test_combo_sin_precio_para_la_categoria_no_aplica(): void
    {
        $evento = $this->eventoBase([
            'talleres' => [
                ['id' => 10, 'nombre' => 'Taller A', 'precio' => 50, 'precioVigente' => 50.0, 'sesiones' => [['id' => 100, 'precio' => null]]],
                ['id' => 11, 'nombre' => 'Taller B', 'precio' => 60, 'precioVigente' => 60.0, 'sesiones' => [['id' => 101, 'precio' => null]]],
            ],
            'tallerCombos' => [
                [
                    'id' => 5, 'vigente' => true, 'tallerIds' => [10, 11],
                    // Solo tiene precio para la categoría 2 — este participante es categoría 1.
                    'precios' => [['categoriaId' => 2, 'precio' => 1.0, 'priceUsd' => null]],
                ],
            ],
        ]);
        $participantes = [$this->participanteBase([
            ['taller_id' => 10, 'sesion_congreso_id' => 100],
            ['taller_id' => 11, 'sesion_congreso_id' => 101],
        ])];

        $resultado = (new RegistroValidacionService())->validarYCalcularParticipantes($evento, null, $participantes);

        $this->assertSame(110.0, $resultado['totales']['talleres']);
    }

    /** Precio vigente por categoría (13/10/2026) — una fila específica gana sobre la general. */
    public function test_precio_vigente_por_categoria_de_un_taller_individual(): void
    {
        $evento = $this->eventoBase([
            'talleres' => [
                [
                    'id' => 10, 'nombre' => 'Taller A', 'precio' => 50,
                    'sesiones' => [['id' => 100, 'precio' => null]],
                    'precioVigentePorCategoria' => [
                        ['categoriaId' => null, 'precio' => 50.0, 'priceUsd' => null],
                        ['categoriaId' => 1, 'precio' => 30.0, 'priceUsd' => null],
                    ],
                ],
            ],
        ]);
        $participantes = [$this->participanteBase([
            ['taller_id' => 10, 'sesion_congreso_id' => 100],
        ])];

        $resultado = (new RegistroValidacionService())->validarYCalcularParticipantes($evento, null, $participantes);

        $this->assertSame(30.0, $resultado['totales']['talleres']);
    }
}
