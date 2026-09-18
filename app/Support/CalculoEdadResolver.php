<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Evento;
use App\Models\Participante;
use Carbon\Carbon;

/**
 * Aviso de numeración vs. género/edad real en entrega de kit (16/09/2026).
 *
 * Resuelve la edad "real" de un participante según el método que eligió su
 * categoría (`categories.calculo_edad_id`, ver create_calculo_edades_table)
 * — las 3 formas de calcular edad confirmadas por el usuario. Compara por
 * `nombre` del catálogo, no por id, para no depender del orden de seed.
 *
 * Sin `calculo_edad_id` cargado (categorías existentes, la inmensa mayoría
 * hoy), o si falta cualquier dato necesario para las otras 2 fórmulas
 * (evento sin `fecha_inicio`, participante sin `fecha_nacimiento`), cae a
 * `participante.edad` (ya guardada al inscribirse) — nunca revienta.
 */
class CalculoEdadResolver
{
    // $evento opcional — cuando el caller ya lo tiene a mano (ej.
    // exportCsv(), que ya recorre participantes de UN evento conocido) se
    // pasa explícito para no pagar una query por participante; si no se
    // pasa, se resuelve por relación (más cómodo para otros callers, algo
    // más caro).
    public static function para(Participante $participante, Category $category, ?Evento $evento = null): int
    {
        $metodo = $category->calculoEdad;

        if (! $metodo || $metodo->nombre === 'Edad al inscribirse') {
            return (int) $participante->edad;
        }

        $evento ??= $participante->registration?->evento;
        $fechaNacimiento = $participante->fecha_nacimiento;

        if (! $evento || ! $evento->fecha_inicio || ! $fechaNacimiento) {
            return (int) $participante->edad;
        }

        $fechaEvento = Carbon::parse($evento->fecha_inicio);
        $fechaNac = Carbon::parse($fechaNacimiento);

        if ($metodo->nombre === 'Edad al día del evento') {
            return (int) $fechaNac->diffInYears($fechaEvento);
        }

        // "Edad que cumple en el año del evento" — edad de pista, común en
        // atletismo: año del evento menos año de nacimiento, sin importar
        // si el cumpleaños ya pasó ese año.
        return $fechaEvento->year - $fechaNac->year;
    }
}
