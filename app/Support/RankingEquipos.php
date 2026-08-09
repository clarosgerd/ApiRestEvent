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
     *
     * `$categoria`: si se pasa, la suma de cada equipo solo cuenta a los
     * integrantes que corrieron esa categoría/distancia — evita que un
     * equipo "gane" el ranking general solo por haber corrido una
     * distancia más corta que otros equipos mezclados en el mismo evento
     * (ver [[project_demo_eventos_equipos_delivery]]). `null` (default)
     * mantiene el comportamiento histórico sin filtrar — lo sigue usando
     * `ClubController`, que no tiene la categoría de un participante
     * concreto para acotar.
     */
    public static function paraEvento(int $eventoId, ?string $categoria = null): Collection
    {
        return Equipo::where('event_id', $eventoId)
            ->get()
            ->map(function (Equipo $equipo) use ($eventoId, $categoria) {
                $segundos = Resultado::where('event_id', $eventoId)
                    ->where('estado', 'finisher')
                    ->whereHas('participante', function ($q) use ($equipo, $categoria) {
                        $q->where('equipo_id', $equipo->id);
                        if ($categoria !== null) {
                            $q->where('categoria', $categoria);
                        }
                    })
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
