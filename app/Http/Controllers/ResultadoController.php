<?php

namespace App\Http\Controllers;

use App\Models\Equipo;
use App\Models\Evento;
use App\Models\Participante;
use App\Models\Resultado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ResultadoController extends Controller
{
    /**
     * Carga masiva de resultados de carrera, matcheando por chip, número de
     * corredor o número de documento (en ese orden de prioridad) dentro del
     * evento indicado — ver brain/PLAN-RESULTADOS-EQUIPOS-31072026.md §2.
     *
     * Si ninguno matchea un participante inscrito, el resultado se guarda
     * igual con participante_id null (corredor "bandit" o numeración mal
     * cargada) y se reporta en `no_vinculados` para resolver a mano.
     *
     * Body: { "items": [ { "chip", "numero_corredor", "numero_documento",
     *   "tiempo_oficial", "tiempo_chip", "posicion_general",
     *   "posicion_categoria", "posicion_genero", "estado" }, ... ] }
     */
    public function bulk(Request $request, Evento $event): JsonResponse
    {
        $data = $request->validate([
            'items'                          => ['required', 'array', 'min:1'],
            'items.*.chip'                   => ['nullable', 'string', 'max:50'],
            'items.*.numero_corredor'        => ['nullable', 'string', 'max:50'],
            'items.*.numero_documento'       => ['nullable', 'string', 'max:50'],
            'items.*.tiempo_oficial'         => ['nullable', 'string', 'max:20'],
            'items.*.tiempo_chip'            => ['nullable', 'string', 'max:20'],
            'items.*.posicion_general'       => ['nullable', 'integer', 'min:1'],
            'items.*.posicion_categoria'     => ['nullable', 'integer', 'min:1'],
            'items.*.posicion_genero'        => ['nullable', 'integer', 'min:1'],
            'items.*.estado'                 => ['nullable', Rule::in(['finisher', 'dns', 'dnf', 'dsq'])],
        ]);

        $procesados   = 0;
        $noVinculados = [];

        foreach ($data['items'] as $item) {
            $participante = $this->resolverParticipante($event, $item);

            $llave = $participante
                ? ['event_id' => $event->id, 'participante_id' => $participante->id]
                : [
                    'event_id'         => $event->id,
                    'participante_id'  => null,
                    'chip'             => $item['chip'] ?? null,
                    'numero_corredor'  => $item['numero_corredor'] ?? null,
                    'numero_documento' => $item['numero_documento'] ?? null,
                ];

            Resultado::updateOrCreate($llave, [
                'chip'               => $item['chip'] ?? null,
                'numero_corredor'    => $item['numero_corredor'] ?? null,
                'numero_documento'   => $item['numero_documento'] ?? null,
                'tiempo_oficial'     => $item['tiempo_oficial'] ?? null,
                'tiempo_chip'        => $item['tiempo_chip'] ?? null,
                'posicion_general'   => $item['posicion_general'] ?? null,
                'posicion_categoria' => $item['posicion_categoria'] ?? null,
                'posicion_genero'    => $item['posicion_genero'] ?? null,
                'estado'             => $item['estado'] ?? 'finisher',
            ]);

            $procesados++;
            if (!$participante) {
                $noVinculados[] = $item;
            }
        }

        return response()->json([
            'success'       => true,
            'procesados'    => $procesados,
            'no_vinculados' => $noVinculados,
        ]);
    }

    /**
     * Resultados del participante logueado, para cada evento donde ya tiene
     * un resultado registrado — ver
     * brain/PLAN-RESULTADOS-EQUIPOS-31072026.md §4 y §5.
     *
     * Reusa el mismo matching que RegistrationController::mine() (documento
     * o correo del Persona autenticado contra participantes).
     */
    public function mios(Request $request): JsonResponse
    {
        $persona = $request->user();

        $participantes = Participante::with(['resultado', 'registration', 'equipo'])
            ->where(function ($q) use ($persona) {
                $q->where('numero_documento', $persona->numero_documento)
                  ->orWhere('correo', $persona->email);
            })
            ->whereHas('resultado')
            ->get();

        $data = $participantes->map(function (Participante $participante) {
            $eventoId = $participante->registration->evento_id;
            $resultado = $participante->resultado;

            return [
                'eventoId'     => $eventoId,
                'eventoNombre' => $participante->registration->evento_nombre,
                'categoria'    => $participante->categoria,
                'resultado'    => [
                    'tiempoOficial'     => $resultado->tiempo_oficial,
                    'posicionGeneral'   => $resultado->posicion_general,
                    'posicionCategoria' => $resultado->posicion_categoria,
                    'posicionGenero'    => $resultado->posicion_genero,
                    'estado'            => $resultado->estado,
                ],
                'comparativoCategoria' => $this->comparativoCategoria($eventoId, $participante),
                'equipo'               => $participante->equipo_id
                    ? $this->resultadosEquipo($eventoId, $participante)
                    : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $data->values(),
        ]);
    }

    /**
     * Ranking de resultados dentro del mismo evento+categoría que el
     * participante — "en qué puesto quedaste dentro de tu categoría, y
     * quién más corrió en ella" (interpretación del PRD, req. 4).
     */
    private function comparativoCategoria(int $eventoId, Participante $participante): array
    {
        return Resultado::where('event_id', $eventoId)
            ->whereHas('participante', fn ($q) => $q->where('categoria', $participante->categoria))
            ->with('participante')
            ->get()
            ->filter(fn ($r) => $r->participante !== null)
            ->sortBy(fn ($r) => $this->tiempoASegundos($r->tiempo_oficial))
            ->values()
            ->map(fn ($r, $i) => [
                'nombre'        => trim($r->participante->nombre . ' ' . $r->participante->apellido),
                'tiempoOficial' => $r->tiempo_oficial,
                'posicion'      => $i + 1,
                'esPropio'      => $r->participante_id === $participante->id,
            ])
            ->all();
    }

    /**
     * Compañeros de equipo (con su resultado individual) + ranking agregado
     * del equipo contra los demás equipos del evento — suma de tiempos de
     * los integrantes `finisher` (req. 5).
     */
    private function resultadosEquipo(int $eventoId, Participante $participante): array
    {
        $companeros = Resultado::where('event_id', $eventoId)
            ->whereHas('participante', fn ($q) => $q->where('equipo_id', $participante->equipo_id))
            ->with('participante')
            ->get()
            ->filter(fn ($r) => $r->participante !== null);

        $ranking = Equipo::where('event_id', $eventoId)
            ->get()
            ->map(function (Equipo $equipo) use ($eventoId) {
                $segundos = Resultado::where('event_id', $eventoId)
                    ->where('estado', 'finisher')
                    ->whereHas('participante', fn ($q) => $q->where('equipo_id', $equipo->id))
                    ->get()
                    ->sum(fn ($r) => $this->tiempoASegundos($r->tiempo_oficial));

                return ['equipoId' => $equipo->id, 'nombre' => $equipo->nombre, 'segundos' => $segundos];
            })
            ->filter(fn ($e) => $e['segundos'] > 0)
            ->sortBy('segundos')
            ->values();

        $posicion = $ranking->search(fn ($e) => $e['equipoId'] === $participante->equipo_id);

        return [
            'nombre'      => $participante->equipo->nombre,
            'integrantes' => $companeros->map(fn ($r) => [
                'nombre'        => trim($r->participante->nombre . ' ' . $r->participante->apellido),
                'tiempoOficial' => $r->tiempo_oficial,
                'estado'        => $r->estado,
                'esPropio'      => $r->participante_id === $participante->id,
            ])->values()->all(),
            'ranking' => [
                'posicion'    => $posicion === false ? null : $posicion + 1,
                'totalEquipos' => $ranking->count(),
                'tabla'       => $ranking->map(fn ($e) => [
                    'nombre'         => $e['nombre'],
                    'tiempoTotal'    => $this->segundosATiempo($e['segundos']),
                    'esPropio'       => $e['equipoId'] === $participante->equipo_id,
                ])->values()->all(),
            ],
        ];
    }

    private function tiempoASegundos(?string $tiempo): int
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

    private function segundosATiempo(int $segundos): string
    {
        return sprintf('%02d:%02d:%02d', intdiv($segundos, 3600), intdiv($segundos % 3600, 60), $segundos % 60);
    }

    private function resolverParticipante(Evento $event, array $item): ?Participante
    {
        $base = fn () => Participante::whereHas('registration', function ($q) use ($event) {
            $q->where('evento_id', $event->id);
        });

        if (!empty($item['chip'])) {
            $p = $base()->where('chip', $item['chip'])->first();
            if ($p) return $p;
        }

        if (!empty($item['numero_corredor'])) {
            $p = $base()->where('numero_corredor', $item['numero_corredor'])->first();
            if ($p) return $p;
        }

        if (!empty($item['numero_documento'])) {
            $p = $base()->where('numero_documento', $item['numero_documento'])->first();
            if ($p) return $p;
        }

        return null;
    }
}
