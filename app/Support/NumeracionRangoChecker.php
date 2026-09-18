<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Evento;
use App\Models\Genero;
use App\Models\NumeracionRango;
use App\Models\Participante;

/**
 * Aviso de numeración vs. género/edad real en entrega de kit (16/09/2026).
 *
 * Nunca escribe nada, solo lee. Devuelve `null` (sin aviso) apenas falta
 * cualquier pieza — sin `numero_corredor`, sin categoría resoluble, sin
 * fila de `numeracion_rangos` configurada para el género/edad real del
 * participante en su categoría — para que un evento/categoría que no
 * cargó nada de esto se comporte exactamente igual que hoy (ver garantía
 * de no-regresión del plan).
 */
class NumeracionRangoChecker
{
    public static function alertaPara(Participante $participante, ?Evento $evento = null): ?string
    {
        if (! $participante->numero_corredor || ! is_numeric($participante->numero_corredor)) {
            return null;
        }

        if (! $participante->categoria) {
            return null;
        }

        $category = Category::find($participante->categoria);
        if (! $category) {
            return null;
        }

        $genero = Genero::where('nombre', $participante->genero)->first();
        if (! $genero) {
            return null;
        }

        $edad = CalculoEdadResolver::para($participante, $category, $evento);

        $esperado = NumeracionRango::where('category_id', $category->id)
            ->where('genero_id', $genero->id)
            ->where('edad_min', '<=', $edad)
            ->where('edad_max', '>=', $edad)
            ->first();

        // Sin ninguna fila configurada para este género/edad en esta
        // categoría — el organizador no cargó el catálogo (todavía), no es
        // un error, simplemente no hay nada contra qué comparar.
        if (! $esperado) {
            return null;
        }

        $numero = (int) $participante->numero_corredor;

        if ($numero >= $esperado->numero_min && $numero <= $esperado->numero_max) {
            return null;
        }

        return sprintf(
            'Por género/edad le correspondería el color %s (N°%d-%d), tiene asignado el N°%s.',
            $esperado->color,
            $esperado->numero_min,
            $esperado->numero_max,
            $participante->numero_corredor,
        );
    }
}
