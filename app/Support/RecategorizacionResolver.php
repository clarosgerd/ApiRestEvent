<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Evento;
use App\Models\Genero;
use App\Models\NumeracionRango;
use App\Models\Participante;
use Illuminate\Support\Collection;

/**
 * Recategorización visual por edad/género (23/09/2026) — a diferencia de
 * NumeracionRangoChecker (que solo AVISA si el bib no corresponde al
 * bloque de la PROPIA categoría), esto busca en TODAS las categorías del
 * evento cuál rango le correspondería a un participante por su género/edad
 * real, sin importar qué categoría haya elegido al inscribirse.
 *
 * Solo visual — confirmado con el usuario: nunca escribe nada, no toca
 * `participantes.categoria` ni `precio_categoria`, no afecta resultados ni
 * rankings (`ResultadoController` sigue agrupando por la categoría
 * elegida). Se usa en el CSV del organizador (`OrganizadorDashboardController::exportCsv`,
 * consumido por `elascenso/delivery`) y en `ParticipanteController::porEvento()`
 * (consumido por `admin-eventos/ChronoTrackExportController`).
 *
 * `numero_min`/`numero_max` del rango matcheado son irrelevantes acá — a
 * diferencia del aviso de bib, la recategorización solo necesita
 * género+edad, por eso esos 2 campos son opcionales en el catálogo.
 *
 * Los 3 parámetros opcionales al final existen para evitar N+1 cuando se
 * llama en un loop sobre todos los participantes de un evento (ambos
 * callers reales lo hacen) — sin ellos, cada llamada hace sus propias
 * consultas, útil para uso puntual (ej. tests).
 */
class RecategorizacionResolver
{
    /**
     * @param  ?Collection<int, NumeracionRango>  $numeracionRangos  Todos los rangos del evento, precargados (evita N+1).
     * @param  ?Collection<string, Genero>  $generosPorNombre  Keyed por `nombre`.
     * @param  ?Collection<string, Category>  $categoriasPorId  Keyed por `(string) id` — categorías del evento.
     * @return array{category: Category, color: ?string}|null
     */
    public static function paraParticipante(
        Participante $participante,
        Evento $evento,
        ?Collection $numeracionRangos = null,
        ?Collection $generosPorNombre = null,
        ?Collection $categoriasPorId = null,
    ): ?array {
        $generosPorNombre ??= Genero::all()->keyBy('nombre');
        $genero = $generosPorNombre->get($participante->genero);
        if (! $genero) {
            return null;
        }

        // Edad: se resuelve con el método de cálculo de la PROPIA
        // categoría (si resuelve) — evita el problema de "para buscar la
        // categoría real necesito su edad, pero la edad depende de la
        // categoría" cayendo directo a la edad cruda cuando no hay
        // categoría propia resoluble (ej. texto libre de un sync externo).
        $categoriaPropia = $categoriasPorId
            ? $categoriasPorId->get((string) $participante->categoria)
            : ($participante->categoria ? Category::find($participante->categoria) : null);
        $edad = $categoriaPropia
            ? CalculoEdadResolver::para($participante, $categoriaPropia, $evento)
            : (int) $participante->edad;

        $numeracionRangos ??= NumeracionRango::whereHas(
            'category',
            fn ($q) => $q->where('event_id', $evento->id)
        )->with('category')->get();

        $rango = $numeracionRangos->first(
            fn (NumeracionRango $r) => (int) $r->genero_id === (int) $genero->id
                && (int) $r->edad_min <= $edad && (int) $r->edad_max >= $edad
        );

        if (! $rango || ! $rango->category) {
            return null;
        }

        return ['category' => $rango->category, 'color' => $rango->color];
    }
}
