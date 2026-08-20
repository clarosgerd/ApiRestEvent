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
        )
            // Reporte de talleres (19/08/2026) — eager-cargado para no hacer
            // N+1 al armar porTaller() más abajo. Vacío para eventos sin
            // talleres (relación vacía, no rompe nada).
            ->with(['registration', 'talleresSesiones.taller', 'talleresSesiones.sesionCongreso'])
            ->get();

        $porTaller = self::agruparPorTaller($participantesPagados);
        // Detalle sin agrupar (20/08/2026) — pedido del usuario para poder
        // descargarlo en CSV: fila por cada selección de taller (participante
        // × sesión), ordenado por fecha/hora — a diferencia de `filas` de
        // arriba (agrupado por sesión, con conteo/recaudación).
        $porTaller['detalle'] = self::detalleTalleres($participantesPagados);

        return [
            'porModalidad' => self::agruparPorFormulario($evento, $participantesPagados),
            'porCategoria' => self::agruparPorCategoria($evento, $participantesPagados),
            'poleras' => self::agruparPoleras($participantesPagados),
            'porTaller' => $porTaller,
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

    /**
     * Reporte de talleres (19/08/2026) — pedido del usuario: "no tenemos
     * reporte de talleres". Se agrupa por SESIÓN (no solo por taller),
     * porque el cupo y el horario son por sesión — un mismo taller puede
     * repetirse en más de una sesión (ej. "REASE: Manejo de Paro
     * Intraoperatorio" dos veces en el evento real que motivó este
     * reporte). `disponible` sale de `sesiones_congreso.cupo` menos la
     * cantidad de PAGADOS acá — no es el mismo `disponibles` que expone
     * TallerSesionResource al público (ese cuenta cualquier estado no
     * cancelado/fallido, ver participanteSesiones() en SesionCongreso);
     * acá es a propósito solo lo efectivamente cobrado, mismo criterio de
     * "recaudación" que el resto de este reporte.
     */
    private static function agruparPorTaller(Collection $participantes): array
    {
        $grupos = [];

        foreach ($participantes as $p) {
            foreach ($p->talleresSesiones as $pts) {
                $sesion = $pts->sesionCongreso;
                $taller = $pts->taller;
                $id = $pts->sesion_congreso_id;

                $grupos[$id] ??= [
                    'sesionId'     => $id,
                    'tallerId'     => $pts->taller_id,
                    'tallerNombre' => $taller->nombre ?? 'Sin especificar',
                    'sesionTitulo' => $sesion->titulo ?? null,
                    'fecha'        => optional($sesion?->fecha)->format('Y-m-d'),
                    'horaInicio'   => $sesion ? substr((string) $sesion->hora_inicio, 0, 5) : null,
                    'horaFin'      => $sesion ? substr((string) $sesion->hora_fin, 0, 5) : null,
                    'cupo'         => $sesion?->cupo,
                    'cantidad'     => 0,
                    'recaudacion'  => 0.0,
                ];
                $grupos[$id]['cantidad']++;
                $grupos[$id]['recaudacion'] += (float) $pts->total;
            }
        }

        foreach ($grupos as &$grupo) {
            $grupo['recaudacion'] = round($grupo['recaudacion'], 2);
            $grupo['disponible']  = $grupo['cupo'] !== null ? max(0, $grupo['cupo'] - $grupo['cantidad']) : null;
        }
        unset($grupo);

        uasort($grupos, fn ($a, $b) => [$a['fecha'], $a['horaInicio']] <=> [$b['fecha'], $b['horaInicio']]);
        $filas = array_values($grupos);

        return [
            'filas' => $filas,
            'totalCantidad' => array_sum(array_column($filas, 'cantidad')),
            'totalRecaudacion' => round(array_sum(array_column($filas, 'recaudacion')), 2),
        ];
    }

    /**
     * Detalle sin agrupar de talleres (20/08/2026) — pedido del usuario:
     * "un reporte csv que también pueda bajarlo el cliente, este reporte
     * no debe estar agrupado pero ordenado por fecha". Una fila por cada
     * selección de taller (participante × sesión) — un participante con 2
     * talleres aparece 2 veces, a diferencia de `agruparPorTaller()` que
     * lo cuenta una vez por sesión. Mismo criterio de "solo pagados" que
     * el resto de este reporte. Ordenado por fecha/hora de la sesión, y a
     * igualdad de fecha/hora por apellido del participante (orden estable
     * y legible para el organizador, no por ID interno).
     */
    private static function detalleTalleres(Collection $participantes): array
    {
        $filas = [];

        foreach ($participantes as $p) {
            foreach ($p->talleresSesiones as $pts) {
                $sesion = $pts->sesionCongreso;
                $taller = $pts->taller;

                $filas[] = [
                    'fecha'                => optional($sesion?->fecha)->format('Y-m-d'),
                    'horaInicio'           => $sesion ? substr((string) $sesion->hora_inicio, 0, 5) : null,
                    'horaFin'              => $sesion ? substr((string) $sesion->hora_fin, 0, 5) : null,
                    'sala'                 => $sesion->sala ?? null,
                    'tallerNombre'         => $taller->nombre ?? 'Sin especificar',
                    'sesionTitulo'         => $sesion->titulo ?? null,
                    'participanteNombre'   => $p->nombre,
                    'participanteApellido' => $p->apellido,
                    'numeroDocumento'      => $p->numero_documento,
                    'correo'               => $p->correo,
                    'telefono'             => $p->telefono,
                    'referencia'           => $p->registration->referencia ?? null,
                    'precio'               => round((float) $pts->total, 2),
                ];
            }
        }

        usort($filas, fn ($a, $b) => [
            $a['fecha'] ?? '', $a['horaInicio'] ?? '', $a['participanteApellido'],
        ] <=> [
            $b['fecha'] ?? '', $b['horaInicio'] ?? '', $b['participanteApellido'],
        ]);

        return $filas;
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
