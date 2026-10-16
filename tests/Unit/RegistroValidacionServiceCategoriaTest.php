<?php

namespace Tests\Unit;

use App\Services\RegistroValidacionService;
use PHPUnit\Framework\TestCase;

/**
 * Auditoría monolito (16/10/2026, pedido del usuario) — 2 bugs reales
 * encontrados al comparar RegistroValidacionService (puerto a clase,
 * monolito-only) contra elascenso/event/api/_registro_validacion.php, el
 * original del que se portó: ambos ya estaban corregidos ahí pero nunca se
 * sincronizaron a este archivo. Ver [[project_auditoria_monolito_registrovalidacionservice]].
 */
class RegistroValidacionServiceCategoriaTest extends TestCase
{
    private function eventoBase(array $overrides = []): array
    {
        return array_merge([
            'talleresConCosto' => false,
            'usdPrecioFijo' => false,
            'fee_pct' => 0.05,
            'feeIncluyeTalleres' => true,
            'categories' => [
                ['id' => 1, 'price' => 0],
            ],
            'talleres' => [],
            'tallerCombos' => [],
            'promoCodes' => [],
        ], $overrides);
    }

    private function participanteBase(array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Juan', 'apellido' => 'Pérez', 'genero' => 'Masculino', 'correo' => 'juan@example.net',
            'categoria' => '1', 'precioCategoria' => 0,
            'talleres' => [],
        ], $overrides);
    }

    /** Bug real: `empty($p['precioCategoria'])` trataba 0 (categoría gratis) como "campo requerido". */
    public function test_categoria_de_precio_cero_no_se_rechaza_como_campo_requerido(): void
    {
        $evento = $this->eventoBase();
        $participantes = [$this->participanteBase()];

        $resultado = (new RegistroValidacionService())->validarYCalcularParticipantes($evento, null, $participantes);

        $this->assertArrayNotHasKey('error', $resultado);
        $this->assertSame(0.0, $resultado['totales']['inscripcion']);
    }

    /** Bug real: faltaba el condicional requiereCategoria — bloqueaba SIEMPRE a form_types sin categoría (staff/ponente/expositor). */
    public function test_form_type_sin_categoria_valida_contra_precio_base_no_contra_evento_categories(): void
    {
        $evento = $this->eventoBase(['categories' => []]);
        $formType = ['requiereCategoria' => false, 'precio_base' => 50.0];
        $participantes = [$this->participanteBase(['categoria' => 'Staff', 'precioCategoria' => 50.0])];

        $resultado = (new RegistroValidacionService())->validarYCalcularParticipantes($evento, $formType, $participantes);

        $this->assertArrayNotHasKey('error', $resultado);
        $this->assertSame(50.0, $resultado['totales']['inscripcion']);
    }

    /** Mismo escenario, pero con un precio que no coincide con precio_base — debe rechazar. */
    public function test_form_type_sin_categoria_rechaza_precio_que_no_coincide_con_precio_base(): void
    {
        $evento = $this->eventoBase(['categories' => []]);
        $formType = ['requiereCategoria' => false, 'precio_base' => 50.0];
        $participantes = [$this->participanteBase(['categoria' => 'Staff', 'precioCategoria' => 999.0])];

        $resultado = (new RegistroValidacionService())->validarYCalcularParticipantes($evento, $formType, $participantes);

        $this->assertSame('El precio de inscripción no coincide con el vigente. Recargá la página e intentá de nuevo.', $resultado['error']);
    }

    /** Regresión: un form_type CON categoría (default, sin el flag) sigue exigiendo que matchee evento.categories. */
    public function test_form_type_con_categoria_sigue_exigiendo_match_contra_evento_categories(): void
    {
        $evento = $this->eventoBase(['categories' => [['id' => 1, 'price' => 100]]]);
        $participantes = [$this->participanteBase(['categoria' => '999', 'precioCategoria' => 100])];

        $resultado = (new RegistroValidacionService())->validarYCalcularParticipantes($evento, null, $participantes);

        $this->assertSame("Categoría '999' no válida para este evento.", $resultado['error']);
    }
}
