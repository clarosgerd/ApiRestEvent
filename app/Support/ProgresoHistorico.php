<?php

namespace App\Support;

use App\Models\Participante;

class ProgresoHistorico
{
    /**
     * Historial cronológico (por fecha real del evento) de resultados de la
     * misma persona —matcheada por `numero_documento` o `correo`— en la
     * misma categoría/distancia exacta. Comparar tiempos entre distancias
     * distintas (10K vs 5K) no tiene sentido, así que solo entran
     * participaciones con la misma `categoria`. Cada entrada trae `mejora`:
     * `null` en la primera (no hay con qué comparar), `true`/`false` contra
     * la entrada cronológicamente anterior.
     *
     * @return array<int, array{eventoId:int,eventoNombre:string,fecha:?string,tiempoOficial:?string,mejora:?bool,diferenciaSegundos:?int}>
     */
    public static function paraIdentidad(?string $numeroDocumento, ?string $correo, string $categoria): array
    {
        if (!$numeroDocumento && !$correo) {
            return [];
        }

        $participantes = Participante::query()
            ->where('categoria', $categoria)
            ->where(function ($q) use ($numeroDocumento, $correo) {
                if ($numeroDocumento) $q->orWhere('numero_documento', $numeroDocumento);
                if ($correo) $q->orWhere('correo', $correo);
            })
            ->whereHas('resultado')
            ->with(['resultado', 'registration.evento'])
            ->get()
            ->filter(fn (Participante $p) => $p->registration && $p->registration->evento)
            ->sortBy(fn (Participante $p) => $p->registration->evento->fecha_inicio)
            ->values();

        $segundosAnterior = null;

        return $participantes->map(function (Participante $p) use (&$segundosAnterior) {
            $segundos = RankingEquipos::tiempoASegundos($p->resultado->tiempo_oficial);
            $mejora   = $segundosAnterior === null ? null : ($segundos < $segundosAnterior);
            $diferencia = $segundosAnterior === null ? null : ($segundosAnterior - $segundos); // positivo = mejoró

            $entry = [
                'eventoId'           => $p->registration->evento_id,
                'eventoNombre'       => $p->registration->evento_nombre,
                'fecha'              => $p->registration->evento->fecha_inicio,
                'tiempoOficial'      => $p->resultado->tiempo_oficial,
                'mejora'             => $mejora,
                'diferenciaSegundos' => $diferencia,
            ];

            $segundosAnterior = $segundos;

            return $entry;
        })->values()->all();
    }
}
