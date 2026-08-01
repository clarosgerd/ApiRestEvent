<?php

namespace App\Support;

use App\Models\Equipo;
use App\Models\Resultado;
use Illuminate\Support\Collection;

class RankingEquipos
{
    /**
     * Tabla de equipos de un evento ordenada por tiempo total (suma de
     * tiempos de los integrantes `finisher`), de menor a mayor. Usado tanto
     * por "Mis Resultados" (ResultadoController) como por la landing del
     * club (ClubController).
     */
    public static function paraEvento(int $eventoId): Collection
    {
        return Equipo::where('event_id', $eventoId)
            ->get()
            ->map(function (Equipo $equipo) use ($eventoId) {
                $segundos = Resultado::where('event_id', $eventoId)
                    ->where('estado', 'finisher')
                    ->whereHas('participante', fn ($q) => $q->where('equipo_id', $equipo->id))
                    ->get()
                    ->sum(fn ($r) => self::tiempoASegundos($r->tiempo_oficial));

                return ['equipoId' => $equipo->id, 'nombre' => $equipo->nombre, 'segundos' => $segundos];
            })
            ->filter(fn ($e) => $e['segundos'] > 0)
            ->sortBy('segundos')
            ->values();
    }

    public static function tiempoASegundos(?string $tiempo): int
    {
        if (!$tiempo) {
            return 0;
        }

        $partes = array_map('intval', explode(':', $tiempo));
        while (count($partes) < 3) {
            array_unshift($partes, 0);
        }

        [$h, $m, $s] = $partes;

        return ($h * 3600) + ($m * 60) + $s;
    }

    public static function segundosATiempo(int $segundos): string
    {
        return sprintf('%02d:%02d:%02d', intdiv($segundos, 3600), intdiv($segundos % 3600, 60), $segundos % 60);
    }
}
