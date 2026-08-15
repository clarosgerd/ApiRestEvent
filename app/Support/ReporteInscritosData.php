<?php

namespace App\Support;

use App\Models\Evento;
use App\Models\Participante;
use Illuminate\Support\Collection;

/**
 * Reporte de inscritos por modalidad (tipo de formulario) y por categoría,
 * con recaudación en dinero — más el desglose de poleras por sexo/talla.
 * Pedido por el usuario el 15/08/2026 a partir de un reporte de un sistema
 * legado (ver captura en la conversación).
 *
 * Archivo hermano de DashboardInscripcionesData (cuenta por estado) y
 * BalanceEventoData (suma dinero a nivel evento) — separado a propósito
 * porque este mezcla ambas cosas PERO agrupadas por categoría/tipo de
 * formulario, algo que ninguno de los otros 2 hace. Solo cuenta
 * inscripciones `paid` (mismo criterio que BalanceEventoData: "Recaudación"
 * es dinero efectivamente cobrado, no lo pendiente/cancelado).
 *
 * 2 decisiones tomadas con el usuario tras comparar contra datos reales
 * (no solo el esquema):
 * - El reporte legado tenía una tabla "Modalidad" (KIT, ej. "10K Kit
 *   Completo") y otra "Distancia" separadas. Acá NO existe ese concepto de
 *   "kit" — los souvenirs reales son ítems sueltos que un participante
 *   puede sumar libremente (0, 1 o varios: Camiseta/Gorra/Mochila/...), no
 *   una modalidad excluyente. Agrupar por souvenir rompe la propiedad de
 *   que todas las tablas sumen el mismo Total que tiene el reporte legado
 *   (alguien con 2 souvenirs se contaría 2 veces, alguien con 0 no
 *   aparecería). El único campo que reparte a cada inscrito en un solo
 *   grupo es `form_types.name` — se usa como "Modalidad" en su lugar.
 * - El reporte legado tenía tablas separadas "Distancia" y "Categoría".
 *   En este sistema son el mismo dato: `categories.name` YA es la
 *   distancia en los eventos reales ("5K", "10K", "21K", "7K"...) —
 *   confirmado contra datos reales, no es una convención nueva. No se
 *   duplica la tabla.
 * - "Reporte de Poleras" (sexo+talla) sale de los campos legacy
 *   `participantes.genero`/`polera` (los que de verdad tienen datos hoy:
 *   52/52 poblados en la BD real), no del sistema nuevo de souvenirs con
 *   talla/sexo genérico (casi sin uso real todavía — 2 filas, ninguna con
 *   talla cargada al momento de este reporte).
 */
class ReporteInscritosData
{
    public static function paraEvento(Evento $evento): array
    {
        $participantesPagados = Participante::whereHas(
            'registration',
            fn ($q) => $q->where('evento_id', $evento->id)->where('pago_status', 'paid')
        )->with('registration')->get();

        return [
            'porModalidad' => self::agruparPorFormulario($evento, $participantesPagados),
            'porCategoria' => self::agruparPorCategoria($evento, $participantesPagados),
            'poleras' => self::agruparPoleras($participantesPagados),
        ];
    }

    private static function agruparPorFormulario(Evento $evento, Collection $participantes): array
    {
        $nombres = $evento->formTypes()->pluck('name', 'id');
        $grupos = [];

        foreach ($participantes as $p) {
            $id = $p->registration->form_types_id ?: 'sin_especificar';
            $grupos[$id] ??= ['id' => $id, 'nombre' => $nombres[$id] ?? 'Sin especificar', 'cantidad' => 0, 'recaudacion' => 0.0];
            $grupos[$id]['cantidad']++;
            $grupos[$id]['recaudacion'] += (float) $p->subtotal;
        }

        return self::totalizar($grupos);
    }

    private static function agruparPorCategoria(Evento $evento, Collection $participantes): array
    {
        $nombres = $evento->categories()->pluck('name', 'id');
        $grupos = [];

        foreach ($participantes as $p) {
            $id = $p->categoria ?: 'sin_especificar';
            $grupos[$id] ??= ['id' => $id, 'nombre' => $nombres[$id] ?? 'Sin especificar', 'cantidad' => 0, 'recaudacion' => 0.0];
            $grupos[$id]['cantidad']++;
            $grupos[$id]['recaudacion'] += (float) $p->subtotal;
        }

        return self::totalizar($grupos);
    }

    private static function agruparPoleras(Collection $participantes): array
    {
        $grupos = [];

        foreach ($participantes as $p) {
            if (!$p->polera) {
                continue;
            }
            $sexo = $p->genero ?: 'Sin especificar';
            $talla = $p->polera;
            $key = $sexo . '|' . $talla;
            $grupos[$key] ??= ['sexo' => $sexo, 'talla' => $talla, 'cantidad' => 0];
            $grupos[$key]['cantidad']++;
        }

        ksort($grupos);
        $filas = array_values($grupos);

        return [
            'filas' => $filas,
            'total' => array_sum(array_column($filas, 'cantidad')),
        ];
    }

    private static function totalizar(array $grupos): array
    {
        foreach ($grupos as &$grupo) {
            $grupo['recaudacion'] = round($grupo['recaudacion'], 2);
        }
        unset($grupo);

        uasort($grupos, fn ($a, $b) => strcmp((string) $a['nombre'], (string) $b['nombre']));
        $filas = array_values($grupos);

        return [
            'filas' => $filas,
            'totalCantidad' => array_sum(array_column($filas, 'cantidad')),
            'totalRecaudacion' => round(array_sum(array_column($filas, 'recaudacion')), 2),
        ];
    }
}
