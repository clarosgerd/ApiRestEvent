<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Grilla del calendario de un evento (05/10/2026): vistas de mes, semana y día.
 * Lógica pura, sin HTTP: recibe los bloques ya normalizados por la API
 * (ApiRestEvent GET /event/{id}/calendario) y devuelve la estructura para la vista.
 * Las semanas empiezan en lunes.
 */
class CalendarioGrilla
{
    public const VISTAS = ['mes', 'semana', 'dia'];

    /**
     * @param  list<array<string, mixed>>  $bloques  con 'fecha' (Y-m-d) y 'hora_inicio'
     * @return array{semanas: list<list<array<string, mixed>>>}
     */
    public static function mes(Carbon $ancla, array $bloques, ?string $inicio, ?string $fin): array
    {
        $primero = $ancla->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $ultimo = $ancla->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $semanas = intdiv((int) $primero->diffInDays($ultimo) + 1, 7);

        $porFecha = self::agruparPorFecha($bloques);
        $filas = [];
        for ($s = 0; $s < $semanas; $s++) {
            $fila = [];
            for ($d = 0; $d < 7; $d++) {
                $dia = $primero->copy()->addDays($s * 7 + $d);
                $fila[] = self::celda($dia, $ancla, $porFecha, $inicio, $fin);
            }
            $filas[] = $fila;
        }

        return ['semanas' => $filas];
    }

    /**
     * @param  list<array<string, mixed>>  $bloques
     * @return array{dias: list<array<string, mixed>>}
     */
    public static function semana(Carbon $ancla, array $bloques, ?string $inicio, ?string $fin): array
    {
        $lunes = $ancla->copy()->startOfWeek(Carbon::MONDAY);
        $porFecha = self::agruparPorFecha($bloques);

        $dias = [];
        for ($d = 0; $d < 7; $d++) {
            $dias[] = self::celda($lunes->copy()->addDays($d), $ancla, $porFecha, $inicio, $fin);
        }

        return ['dias' => $dias];
    }

    /**
     * @param  list<array<string, mixed>>  $bloques
     * @return array<string, mixed>
     */
    public static function dia(Carbon $ancla, array $bloques, ?string $inicio, ?string $fin): array
    {
        $porFecha = self::agruparPorFecha($bloques);

        return self::celda($ancla->copy(), $ancla, $porFecha, $inicio, $fin);
    }

    /**
     * Fecha anterior y siguiente para la navegación de cada vista.
     *
     * @return array{anterior: string, siguiente: string}
     */
    public static function navegacion(string $vista, Carbon $ancla): array
    {
        [$anterior, $siguiente] = match ($vista) {
            'semana' => [$ancla->copy()->subWeek(), $ancla->copy()->addWeek()],
            'dia' => [$ancla->copy()->subDay(), $ancla->copy()->addDay()],
            default => [$ancla->copy()->subMonthNoOverflow(), $ancla->copy()->addMonthNoOverflow()],
        };

        return ['anterior' => $anterior->toDateString(), 'siguiente' => $siguiente->toDateString()];
    }

    /**
     * @param  list<array<string, mixed>>  $bloques
     * @return array<string, list<array<string, mixed>>>
     */
    private static function agruparPorFecha(array $bloques): array
    {
        $grupos = [];
        foreach ($bloques as $bloque) {
            $grupos[$bloque['fecha']][] = $bloque;
        }

        // Dentro de cada día, por hora de inicio (los bloques sin hora van al final).
        foreach ($grupos as $fecha => $items) {
            usort($items, fn (array $a, array $b) => strcmp((string) ($a['hora_inicio'] ?? '99'), (string) ($b['hora_inicio'] ?? '99')));
            $grupos[$fecha] = $items;
        }

        return $grupos;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $porFecha
     * @return array<string, mixed>
     */
    private static function celda(Carbon $dia, Carbon $ancla, array $porFecha, ?string $inicio, ?string $fin): array
    {
        $fecha = $dia->toDateString();
        $enEvento = $inicio !== null && $fin !== null
            && $fecha >= Carbon::parse($inicio)->toDateString()
            && $fecha <= Carbon::parse($fin)->toDateString();

        return [
            'fecha' => $fecha,
            'numero' => $dia->day,
            'enMes' => $dia->month === $ancla->month,
            'hoy' => $dia->isToday(),
            'enEvento' => $enEvento,
            'bloques' => $porFecha[$fecha] ?? [],
        ];
    }
}
