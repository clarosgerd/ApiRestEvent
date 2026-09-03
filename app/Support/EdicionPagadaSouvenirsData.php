<?php

namespace App\Support;

use App\Models\Souvenir;

/**
 * Resolver los souvenirs al editar una inscripción pagada (02/09/2026) —
 * ver PLAN-EDICION-PAGADA-CATEGORIA-SOUVENIRS-02092026.md. Antes de esto
 * no había NINGUNA validación real: `createParticipantFromData()` recrea
 * los souvenirs de un participante desde lo que mande el cliente, sin
 * comparar contra lo que ya tenía ni sumar nada a `costo_adicion` — el
 * único freno era el frontend (`restrictPaidEditToTalleresOnly()`
 * congelaba la sección). Mismo criterio que ya existe para talleres: lo
 * que ya está pagado no se puede quitar (no se hacen devoluciones), pero
 * sí se pueden agregar ítems nuevos.
 *
 * El precio de los ítems agregados se resuelve acá contra el catálogo
 * real (`Souvenir.price`), nunca contra lo que mande el cliente — a
 * diferencia de `createParticipantFromData()` (alta nueva/edición
 * pendiente), donde el precio persistido SÍ viene del cliente porque la
 * revalidación real ocurre antes, en el proxy de elascenso/event
 * (`_registro_validacion.php`). Acá el resultado puede ser dinero real
 * cobrado (SIP), así que se aplica el mismo criterio de no confiar en el
 * cliente que ya usa `EdicionPagadaCategoriaData`.
 */
class EdicionPagadaSouvenirsData
{
    /**
     * @param int[] $idsAnteriores souvenir_id que el participante ya tenía
     *   (incluye los invisibles — se filtran acá adentro)
     * @param int[] $idsNuevos souvenir_id que manda el cliente ahora
     * @return array{deltaSouvenirs: float, idsAgregados: int[]}
     *
     * @throws \DomainException
     */
    public static function resolver(int $formTypesId, array $idsAnteriores, array $idsNuevos): array
    {
        // Souvenirs invisibles para el participante (22/08/2026) — nunca
        // viajan en el payload del cliente (el frontend ni los conoce), así
        // que compararlos tal cual contra $idsNuevos los marcaría siempre
        // como "removidos". Se excluyen de este chequeo — igual se
        // re-inyectan solos en cada edición vía injectSouvenirsInvisibles().
        $idsInvisibles = Souvenir::where('form_types_id', $formTypesId)
            ->where('visible_participante', false)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $idsAnterioresVisibles = array_diff($idsAnteriores, $idsInvisibles);

        if (! empty(array_diff($idsAnterioresVisibles, $idsNuevos))) {
            throw new \DomainException('No se pueden quitar souvenirs que ya fueron pagados.');
        }

        $idsAgregados = array_values(array_diff($idsNuevos, $idsAnteriores));

        if (empty($idsAgregados)) {
            return ['deltaSouvenirs' => 0.0, 'idsAgregados' => []];
        }

        // whereIn + scoped a este form_type: si el cliente manda un id que
        // no pertenece al catálogo de este form_type, simplemente no suma
        // nada — la revalidación de "pertenece al evento" real ya la hace
        // el proxy de elascenso/event antes de llegar acá.
        $deltaSouvenirs = (float) Souvenir::where('form_types_id', $formTypesId)
            ->whereIn('id', $idsAgregados)
            ->sum('price');

        return ['deltaSouvenirs' => $deltaSouvenirs, 'idsAgregados' => $idsAgregados];
    }
}
