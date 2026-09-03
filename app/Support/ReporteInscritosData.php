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
 * - "Reporte de Poleras" (sexo+talla) — DECISIÓN REVERTIDA el 03/09/2026
 *   (bug real reportado por el usuario: la columna Talla siempre mostraba
 *   "No shirt"). En 15/08 se eligió leer los campos legacy
 *   `participantes.genero`/`polera` porque en ese momento tenían datos
 *   reales y el sistema de souvenirs casi no se usaba — esa base quedó
 *   obsoleta: eventos como los que motivaron el reporte ya modelan la
 *   polera como un souvenir normal (`requiere_talla=true`), y
 *   `participantes.polera` queda siempre en el string sentinel 'No shirt'
 *   que manda el frontend cuando el flujo legacy no aplica. Ahora sale de
 *   `souvenir_participantes.talla`, filtrado a los souvenirs marcados
 *   `es_polera=true` (flag nuevo, opt-in por ítem — un form_type puede
 *   tener más de un souvenir con talla, ej. una mochila, y no hay otra
 *   forma de saber cuál es la polera de verdad). `genero` sigue siendo el
 *   de `participantes` (eso sí sigue poblado siempre, sin cambios).
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
            // souvenirParticipante (03/09/2026) — para agruparPoleras(), ver
            // docblock de la clase.
            ->with(['registration', 'talleresSesiones.taller', 'talleresSesiones.sesionCongreso', 'souvenirParticipante'])
            ->get();

        // Cupo de talleres (31/08/2026) — universo aparte, VIGENTES (paid +
        // pending, no cancelled/failed), solo para que `disponible` en
        // agruparPorTaller() refleje el cupo real (un pago QR en curso
        // también reserva lugar). El resto del reporte (modalidad,
        // categoría, poleras, recaudación, detalle CSV) sigue usando
        // exclusivamente $participantesPagados — ver docblock de la clase.
        $participantesVigentesParaTalleres = Participante::whereHas(
            'registration',
            fn ($q) => $q->where('evento_id', $evento->id)->whereNotIn('pago_status', ['cancelled', 'failed'])
        )
            ->with(['registration', 'talleresSesiones.taller', 'talleresSesiones.sesionCongreso'])
            ->get();

        $porTaller = self::agruparPorTaller($participantesVigentesParaTalleres);
        // Detalle sin agrupar (20/08/2026) — pedido del usuario para poder
        // descargarlo en CSV: fila por cada selección de taller (participante
        // × sesión), ordenado por fecha/hora — a diferencia de `filas` de
        // arriba (agrupado por sesión, con conteo/recaudación). Sigue
        // "solo pagados" (ver docblock de detalleTalleres()) — no confundir
        // con $participantesVigentesParaTalleres de arriba.
        $porTaller['detalle'] = self::detalleTalleres($participantesPagados);

        return [
            'porModalidad' => self::agruparPorFormulario($evento, $participantesPagados),
            'porCategoria' => self::agruparPorCategoria($evento, $participantesPagados),
            'poleras' => self::agruparPoleras($evento, $participantesPagados),
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

    /**
     * Bug real 03/09/2026 — ver docblock de la clase. `souvenirIdsPolera`
     * puede tener 0, 1 o varios ids (varios solo si el organizador marcó
     * más de un ítem como "es la polera" — ej. corte hombre/mujer como
     * catálogo separado); se cuenta cada SELECCIÓN que matchea, mismo
     * criterio que agruparPorTaller() (por selección, no por participante
     * único) — un participante sin ninguna selección marcada simplemente
     * no aporta ninguna fila.
     *
     * Costo unitario/total (03/09/2026, pedido del usuario) — `montoTotal`
     * suma el `souvenir_participantes.precio` REAL de cada selección (el
     * precio de catálogo al momento de inscribirse, ya sea $0 si el ítem
     * es `incluido=true` o el precio real si no — mismo valor que ya usa
     * CrearInscripcionAction al crear la fila, no se recalcula acá).
     * `costoUnitario` es el promedio (`montoTotal / cantidad`) en vez de
     * asumir un precio fijo — si el precio del souvenir cambió durante el
     * evento (o hay más de un souvenir `es_polera` con precios distintos
     * mezclados en la misma talla/sexo), sigue siendo el número
     * matemáticamente correcto en vez de mostrar "el último precio visto".
     */
    private static function agruparPoleras(Evento $evento, Collection $participantes): array
    {
        $souvenirIdsPolera = TallaPoleraData::souvenirIdsPolera($evento->formTypes()->pluck('id')->all());

        $grupos = [];

        if (!empty($souvenirIdsPolera)) {
            foreach ($participantes as $p) {
                foreach ($p->souvenirParticipante as $sp) {
                    if (!in_array($sp->souvenir_id, $souvenirIdsPolera, true) || !$sp->talla) {
                        continue;
                    }
                    $sexo = $p->genero ?: 'Sin especificar';
                    $talla = $sp->talla;
                    $key = $sexo . '|' . $talla;
                    $grupos[$key] ??= ['sexo' => $sexo, 'talla' => $talla, 'cantidad' => 0, 'montoTotal' => 0.0];
                    $grupos[$key]['cantidad']++;
                    $grupos[$key]['montoTotal'] += (float) $sp->precio;
                }
            }
        }

        ksort($grupos);
        $filas = array_values(array_map(function ($fila) {
            $fila['costoUnitario'] = $fila['cantidad'] > 0 ? round($fila['montoTotal'] / $fila['cantidad'], 2) : 0.0;
            $fila['montoTotal'] = round($fila['montoTotal'], 2);

            return $fila;
        }, $grupos));

        return [
            'filas' => $filas,
            'total' => array_sum(array_column($filas, 'cantidad')),
            'totalMonto' => round(array_sum(array_column($filas, 'montoTotal')), 2),
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
    /**
     * @param Collection $participantes Inscripciones VIGENTES (paid + pending,
     *   no cancelled/failed — mismo criterio que FormType::inscritosVigentes()),
     *   NO solo pagadas. `cantidad`/`recaudacion` siguen contando solo lo
     *   `paid` (dinero efectivamente cobrado, ver docblock de la clase);
     *   `disponible` cuenta TODO lo vigente, porque un pago en curso
     *   (`pending`) también reserva el cupo real — ver
     *   ValidarSeleccionesTallerAction::runCapacidad, que aplica el mismo
     *   criterio del lado de la validación. Antes esto recibía
     *   $participantesPagados y `disponible` = cupo - cantidad (solo
     *   pagados), lo que mostraba MÁS cupo disponible del que en realidad
     *   había apenas alguien tenía un pago QR en curso — bug real
     *   reportado por el usuario (31/08/2026): "los cupos muestran
     *   distinta información" entre elascenso/event y admin-eventos.
     */
    private static function agruparPorTaller(Collection $participantes): array
    {
        $grupos = [];

        foreach ($participantes as $p) {
            $pagado = $p->registration?->pago_status === 'paid';

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
                    // Reporte de talleres confiable (27/08/2026) — separa lo
                    // ya cobrado (inscripción original, Caja, o SIP
                    // confirmado) de lo que el participante eligió "pagar
                    // en el evento" y todavía no se cobró, para que
                    // "recaudación" no mezcle las dos cosas. Ver
                    // ParticipanteTallerSesion::pago_pendiente.
                    'cantidadPendiente'    => 0,
                    'recaudacionPendiente' => 0.0,
                    // ocupadosVigentes (31/08/2026) — paid + pending, solo
                    // para calcular `disponible` más abajo; no se expone
                    // tal cual (ver unset() después del loop).
                    'ocupadosVigentes' => 0,
                ];

                $grupos[$id]['ocupadosVigentes']++;

                // `cantidad`/`recaudacion` (y su desglose `Pendiente`, que es
                // sobre un DETALLE de facturación distinto — ver
                // ParticipanteTallerSesion::pago_pendiente) siguen siendo
                // exclusivamente sobre inscripciones ya `paid`, sin cambios
                // de comportamiento respecto a antes de este fix.
                if (!$pagado) {
                    continue;
                }

                $grupos[$id]['cantidad']++;
                $grupos[$id]['recaudacion'] += (float) $pts->total;
                if ($pts->pago_pendiente) {
                    $grupos[$id]['cantidadPendiente']++;
                    $grupos[$id]['recaudacionPendiente'] += (float) $pts->total;
                }
            }
        }

        foreach ($grupos as &$grupo) {
            $grupo['recaudacion'] = round($grupo['recaudacion'], 2);
            $grupo['recaudacionPendiente'] = round($grupo['recaudacionPendiente'], 2);
            $grupo['recaudacionCobrada']   = round($grupo['recaudacion'] - $grupo['recaudacionPendiente'], 2);
            $grupo['disponible']  = $grupo['cupo'] !== null ? max(0, $grupo['cupo'] - $grupo['ocupadosVigentes']) : null;
            unset($grupo['ocupadosVigentes']);
        }
        unset($grupo);

        uasort($grupos, fn ($a, $b) => [$a['fecha'], $a['horaInicio']] <=> [$b['fecha'], $b['horaInicio']]);
        $filas = array_values($grupos);

        return [
            'filas' => $filas,
            'totalCantidad' => array_sum(array_column($filas, 'cantidad')),
            'totalRecaudacion' => round(array_sum(array_column($filas, 'recaudacion')), 2),
            // Reporte de talleres confiable (27/08/2026).
            'totalCantidadPendiente'    => array_sum(array_column($filas, 'cantidadPendiente')),
            'totalRecaudacionPendiente' => round(array_sum(array_column($filas, 'recaudacionPendiente')), 2),
            'totalRecaudacionCobrada'   => round(array_sum(array_column($filas, 'recaudacionCobrada')), 2),
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
                    // Título académico de la persona (25/08/2026) — reusa el
                    // campo `alias` (mismo mecanismo que index.php
                    // toggleAliasTituloMode(): para form_types tipo
                    // 'congreso' el participante elige Dr./Lic./PhD./etc. en
                    // vez de un alias libre, pero se guarda en esta misma
                    // columna). Se expone tal cual, sin filtrar por tipo de
                    // form_type — para eventos no-congreso puede venir un
                    // alias real en vez de un título, es el mismo dato que
                    // ya vive ahí hoy.
                    'participanteAlias'    => $p->alias,
                    'participanteNombre'   => $p->nombre,
                    'participanteApellido' => $p->apellido,
                    'numeroDocumento'      => $p->numero_documento,
                    'correo'               => $p->correo,
                    'telefono'             => $p->telefono,
                    'referencia'           => $p->registration->referencia ?? null,
                    'precio'               => round((float) $pts->total, 2),
                    // Reporte de talleres confiable (27/08/2026) — ver
                    // ParticipanteTallerSesion::pago_pendiente. Texto ya
                    // resuelto acá (no solo el booleano) para que tanto el
                    // CSV como cualquier otra vista lo muestren igual sin
                    // reinventar el texto en cada lugar.
                    'pagoPendiente'        => (bool) $pts->pago_pendiente,
                    'estadoPago'           => $pts->pago_pendiente ? 'Pendiente (efectivo en el evento)' : 'Pagado',
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
