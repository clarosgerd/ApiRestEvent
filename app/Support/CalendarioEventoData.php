<?php

namespace App\Support;

use App\Models\Evento;

/**
 * Calendario del evento (05/10/2026): une los items de agenda y las sesiones de
 * congreso en una sola lista ordenada por fecha y hora, para la pantalla de
 * calendario de admin-eventos. Sin HTTP, testeable directamente.
 */
class CalendarioEventoData
{
    /**
     * @return array{fecha_inicio: ?string, fecha_fin: ?string, bloques: list<array<string, mixed>>}
     */
    public static function paraEvento(Evento $evento): array
    {
        $bloques = $evento->agendaItems->map(fn ($item) => [
            'tipo' => 'agenda',
            'id' => $item->id,
            'titulo' => $item->titulo,
            'fecha' => self::fechaYmd($item->fecha),
            'hora_inicio' => $item->hora_inicio,
            'hora_fin' => $item->hora_fin,
            'sala' => $item->sala,
            'ponente' => $item->ponente,
        ])->concat($evento->sesionesCongreso->map(fn ($sesion) => [
            'tipo' => 'sesion',
            'id' => $sesion->id,
            'titulo' => $sesion->titulo,
            'fecha' => self::fechaYmd($sesion->fecha),
            'hora_inicio' => $sesion->hora_inicio,
            'hora_fin' => $sesion->hora_fin,
            'sala' => $sesion->sala,
            'ponente' => $sesion->ponente,
        ]))
            // Bloques sin fecha no se pueden ubicar en el calendario.
            ->filter(fn (array $bloque) => $bloque['fecha'] !== null)
            ->sortBy([['fecha', 'asc'], ['hora_inicio', 'asc'], ['tipo', 'asc']])
            ->values()
            ->all();

        return [
            'fecha_inicio' => self::fechaYmd($evento->fecha_inicio),
            'fecha_fin' => self::fechaYmd($evento->fecha_fin),
            'bloques' => $bloques,
        ];
    }

    /** Fecha como YYYY-MM-DD; acepta texto (sin cast en el modelo) o null. */
    private static function fechaYmd(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return \Carbon\Carbon::parse($valor)->format('Y-m-d');
    }
}
