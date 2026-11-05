<?php

namespace App\Support;

use App\Models\Evento;
use App\Models\Participante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Reporte/dashboard de acreditaciones (check-in) de un evento — pedido
 * real del organizador (28/10/2026): saber cuántas acreditaciones se
 * hicieron por evento, contra cuántos participantes pagados (los únicos
 * acreditables, ver CheckinParticipanteAction), con desglose por
 * categoría, ritmo por hora y últimos acreditados.
 *
 * A propósito SOLO cubre la acreditación general del evento
 * (`participantes.checked_in_at`) — la asistencia por sesión de congreso
 * (tabla `asistencia_sesion`) queda fuera de este reporte (decisión del
 * usuario, 28/10/2026); esa ya tiene su propio reporte de concurrencia
 * (`AsistenciaSesionController::reporte` / `GET /event/{e}/sesiones-reporte`).
 */
class AcreditacionesReporteData
{
    public static function paraEvento(Evento $evento): array
    {
        $participantes = Participante::query()
            ->whereHas('registration', fn (Builder $q) => $q->where('evento_id', $evento->id))
            ->with('registration:id,referencia,pago_status')
            ->get();

        // Acreditables = solo pagados (mismo gate server-side que aplica
        // CheckinParticipanteAction al hacer el check-in) — un
        // pendiente/cancelado/fallido nunca va a tener checked_in_at, así
        // que contarlos como "pendientes de acreditar" solo ensuciaría el
        // porcentaje real de avance del día.
        $pagados = $participantes->filter(fn (Participante $p) => $p->registration->pago_status === 'paid');
        $acreditados = $pagados->filter(fn (Participante $p) => $p->checked_in_at !== null);

        return [
            'totales'            => self::totales($pagados, $acreditados, $participantes->count()),
            'porCategoria'       => self::porCategoria($pagados, $acreditados),
            'nombresCategorias'  => $evento->categories()->pluck('name', 'id'),
            'porHora'            => self::porHora($acreditados),
            'recientes'          => self::recientes($acreditados),
        ];
    }

    private static function totales(Collection $pagados, Collection $acreditados, int $total): array
    {
        return [
            // Inscritos del evento (todos los estados de pago) — contexto.
            'total'       => $total,
            // Acreditables: solo `pago_status=paid`.
            'pagados'     => $pagados->count(),
            'acreditados' => $acreditados->count(),
            'pendientes'  => $pagados->count() - $acreditados->count(),
            // El % de avance se calcula SOLO sobre los pagados (los únicos
            // que el gate de CheckinParticipanteAction dejaría acreditar).
            'porcentaje'  => $pagados->count() > 0
                ? round($acreditados->count() * 100 / $pagados->count(), 1)
                : 0.0,
        ];
    }

    /**
     * Mismo criterio que DashboardInscripcionesData: se agrupa por el id de
     * categoría (campo `participantes.categoria`, que es VARCHAR y puede
     * traer también texto libre de registros viejos/legado) y el nombre se
     * resuelve en la vista vía nombresCategorias.
     */
    private static function porCategoria(Collection $pagados, Collection $acreditados): array
    {
        $acreditadosPorCategoria = $acreditados->countBy(self::claveCategoria(...));

        $pagadosPorCategoria = $pagados->countBy(self::claveCategoria(...));

        $filas = [];
        foreach ($pagadosPorCategoria as $categoria => $totalPagados) {
            $acreditadosEnCategoria = $acreditadosPorCategoria[$categoria] ?? 0;

            $filas[$categoria] = [
                'pagados'     => $totalPagados,
                'acreditados' => $acreditadosEnCategoria,
                'pendientes'  => $totalPagados - $acreditadosEnCategoria,
                'porcentaje'  => $totalPagados > 0
                    ? round($acreditadosEnCategoria * 100 / $totalPagados, 1)
                    : 0.0,
            ];
        }
        ksort($filas);

        return $filas;
    }

    private static function claveCategoria(Participante $p): string
    {
        return (string) ($p->categoria ?: 'sin-categoria');
    }

    /**
     * Ritmo de acreditaciones por hora (agrupado por hora calendario,
     * más reciente primero) — pensado para el día del evento: muestra el
     * flujo en la puerta sin tener que scrollear la lista completa.
     */
    private static function porHora(Collection $acreditados): array
    {
        return $acreditados
            ->countBy(fn (Participante $p) => $p->checked_in_at->format('Y-m-d H:00'))
            ->sortKeysDesc()
            ->map(fn (int $cantidad, string $hora) => ['hora' => $hora, 'cantidad' => $cantidad])
            ->values()
            ->all();
    }

    /** Últimos acreditados (por fecha de check-in, descendente). */
    private static function recientes(Collection $acreditados, int $limite = 20): array
    {
        return $acreditados
            ->sortByDesc(fn (Participante $p) => $p->checked_in_at->getTimestamp())
            ->take($limite)
            ->map(fn (Participante $p) => [
                'participanteId' => $p->id,
                'nombre'         => trim($p->nombre . ' ' . $p->apellido),
                'categoria'      => $p->categoria,
                'referencia'     => $p->registration->referencia,
                'checkedInAt'    => $p->checked_in_at->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
