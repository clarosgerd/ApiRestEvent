<?php

namespace App\Support;

use App\Models\Category;

/**
 * Resolver el cambio de categoría al editar una inscripción pagada
 * (02/09/2026) — ver PLAN-EDICION-PAGADA-CATEGORIA-SOUVENIRS-02092026.md.
 * Antes esto era un booleano en ActualizarInscripcionPagadaAction
 * (permiteCambioCategoria: false=autoservicio nunca puede, true=Caja
 * cualquier dirección) duplicado tal cual en CalcularCostoAdicionalAction
 * — extraído acá para no mantener la misma regla escrita dos veces
 * (el mismo patrón de bug que ya costó caro esta semana con
 * ValidarSeleccionesTallerAction).
 *
 * $modoCategoria:
 * - 'bloqueado': no se permite ningún cambio de categoría (no usado por
 *   ningún caller hoy, disponible para el futuro).
 * - 'solo_subida': autoservicio (elascenso/event) — solo se permite
 *   cambiar a una categoría de precio igual o mayor. Nunca hay
 *   devolución fuera de caja.
 * - 'libre': Caja — puede cambiar en cualquier dirección; una categoría
 *   más barata da un costo_adicion negativo (desembolso en efectivo, ver
 *   CajaController::editarPagada()).
 */
class EdicionPagadaCategoriaData
{
    /**
     * @return array{delta: float, categoriaModel: ?Category}
     *
     * @throws \DomainException
     */
    public static function resolver(
        string $categoriaAnteriorId,
        float $precioAnterior,
        string $categoriaNuevaId,
        int $eventoId,
        int $formTypesId,
        string $modoCategoria,
    ): array {
        if ($categoriaNuevaId === $categoriaAnteriorId) {
            return ['delta' => 0.0, 'categoriaModel' => null];
        }

        if ($modoCategoria === 'bloqueado') {
            throw new \DomainException(
                'No se puede cambiar de categoría desde tu cuenta — esa diferencia se resuelve en caja el día del evento.'
            );
        }

        // No se confía en el precio que manda el cliente para este cálculo
        // (a diferencia del alta nueva, acá el resultado puede ser dinero
        // real cobrado o desembolsado) — se resuelve el precio vigente
        // real de la categoría nueva server-side, mismo helper que ya usa
        // el resto del sistema para "Precios por período".
        //
        // Categorías por form_type — exige mismo evento y, si la
        // categoría tiene `formulario_id`, mismo form_type que esta
        // inscripción (null = compartida, sin cambios).
        $categoriaModel = Category::where('id', (int) $categoriaNuevaId)
            ->where('event_id', $eventoId)
            ->where(fn ($q) => $q->whereNull('formulario_id')->orWhere('formulario_id', $formTypesId))
            ->first();

        if (! $categoriaModel) {
            throw new \DomainException(
                "La categoría '{$categoriaNuevaId}' no es válida para este evento/tipo de formulario."
            );
        }

        $precioNuevo = PrecioVigenteData::paraCategoria($categoriaModel)['precio'];

        if ($modoCategoria === 'solo_subida' && $precioNuevo < $precioAnterior) {
            throw new \DomainException(
                'No podés cambiar a una categoría más barata desde tu cuenta — no se hacen devoluciones. Elegí una categoría de igual o mayor precio, o resolvé el cambio en caja el día del evento.'
            );
        }

        return ['delta' => $precioNuevo - $precioAnterior, 'categoriaModel' => $categoriaModel];
    }
}
