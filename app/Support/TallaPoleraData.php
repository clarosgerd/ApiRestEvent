<?php

namespace App\Support;

use App\Models\Participante;
use App\Models\Souvenir;

/**
 * Resolver la talla real de la polera de un participante (03/09/2026) —
 * bug real reportado por el usuario: varios lugares (CSV del organizador,
 * CSV/JSON de delivery, correo de confirmación, "Detalle de inscritos")
 * leían `participantes.polera` directo — un campo legacy que queda
 * siempre en el string sentinel `'No shirt'` (ver elascenso/event
 * index.php, `saveParticipant()`) para eventos que ya modelan la polera
 * como un souvenir normal en vez del flujo viejo (`form_types.hasshirt`).
 *
 * Mismo mecanismo que `ReporteInscritosData::agruparPoleras()` (que
 * también usa esta clase): el organizador marca `souvenirs.es_polera=true`
 * en el ítem correcto (admin-eventos, requiere `requiere_talla=true`) —
 * un form_type puede tener más de un souvenir con talla (ej. una
 * mochila), no hay otra forma de saber cuál es la polera de verdad.
 *
 * Con fallback a `participantes.polera` cuando no hay ningún souvenir
 * `es_polera` marcado — eventos que todavía usan el flujo legacy
 * (`hasshirt=true`) siguen teniendo datos reales ahí, sin cambios.
 */
class TallaPoleraData
{
    /**
     * @param int|int[] $formTypesId un solo id o varios (ej. todos los
     *   form_types de un evento, para resolver varios participantes de
     *   distintos tipos con una sola consulta)
     * @return int[] ids de souvenir marcados es_polera=true
     */
    public static function souvenirIdsPolera(int|array $formTypesId): array
    {
        return Souvenir::whereIn('form_types_id', (array) $formTypesId)
            ->where('es_polera', true)
            ->pluck('id')
            ->all();
    }

    /**
     * Requiere `$participante->souvenirParticipante` ya eager-cargado —
     * no dispara una query nueva por participante (evita N+1).
     *
     * @param int[] $souvenirIdsPolera ver souvenirIdsPolera()
     */
    public static function resolver(Participante $participante, array $souvenirIdsPolera): ?string
    {
        return $participante->souvenirParticipante
            ->first(fn ($sp) => in_array($sp->souvenir_id, $souvenirIdsPolera, true))
            ?->talla ?? $participante->polera;
    }
}
