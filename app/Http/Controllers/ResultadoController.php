<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Models\Category;
use App\Models\Evento;
use App\Models\Participante;
use App\Models\Resultado;
use App\Services\ChronoTrackSyncService;
use App\Support\ProgresoHistorico;
use App\Support\RankingEquipos;
use App\Support\ResultadosBulkImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ResultadoController extends Controller
{
    use AuthorizesEventoScope;

    /**
     * Botón "Sincronizar ahora" del panel de administración — mismo camino
     * que el comando artisan `chronotrack:sincronizar`, expuesto como
     * endpoint autenticado para admin-eventos. Ver
     * brain/groovy-chasing-ladybug.md Parte B.
     */
    public function sincronizarChronoTrack(Evento $event, ChronoTrackSyncService $service): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);

        if (!$event->chronotrack_event_id) {
            return response()->json([
                'success' => false,
                'error'   => 'Este evento no tiene chronotrack_event_id configurado.',
            ], 422);
        }

        try {
            $resultado = $service->sincronizar($event);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Falló la sincronización con ChronoTrack: ' . $e->getMessage(),
            ], 502);
        }

        return response()->json(['success' => true] + $resultado);
    }

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

        $resultado = ResultadosBulkImporter::importar($event, $data['items']);

        return response()->json(['success' => true] + $resultado);
    }

    /**
     * Leaderboard completo de un evento, por categoría — no solo "mi
     * categoría" como `comparativoCategoria()`. Pensado para reemplazar la
     * tabla chica de "Mis Resultados" por una vista tipo ChronoTrack:
     * conteo Started/Finished/DNF/DNS + tabla completa con rank general y
     * rank de género, más el ranking de equipos de esa misma categoría.
     * Ver brain/groovy-chasing-ladybug.md (Parte C).
     */
    public function porEvento(Request $request, Evento $event): JsonResponse
    {
        $persona = $request->user();

        $misParticipanteIds = [];
        if ($persona) {
            $misParticipanteIds = Participante::whereHas('registration', fn ($q) => $q->where('evento_id', $event->id))
                ->where(function ($q) use ($persona) {
                    $q->where('numero_documento', $persona->numero_documento)
                      ->orWhere('correo', $persona->email);
                })
                ->pluck('id')
                ->all();
        }

        $resultados = Resultado::where('event_id', $event->id)
            ->with('participante')
            ->get()
            ->filter(fn ($r) => $r->participante !== null);

        $porCategoria = $event->categories->map(function ($categoria) use ($resultados, $event, $misParticipanteIds) {
            $deCategoria = $resultados->filter(fn ($r) => (string) $r->participante->categoria === (string) $categoria->id);

            $finishers = $deCategoria->filter(fn ($r) => $r->estado === 'finisher')
                ->sortBy(fn ($r) => RankingEquipos::tiempoASegundos($r->tiempo_oficial))
                ->values();

            $contadorGenero = [];
            $leaderboard = $finishers->map(function ($r, $i) use (&$contadorGenero, $misParticipanteIds) {
                $genero = $r->participante->genero;
                $contadorGenero[$genero] = ($contadorGenero[$genero] ?? 0) + 1;

                return [
                    'posicionGeneral' => $i + 1,
                    'posicionGenero'  => $contadorGenero[$genero],
                    'bib'             => $r->numero_corredor,
                    'nombre'          => trim($r->participante->nombre . ' ' . $r->participante->apellido),
                    'genero'          => $genero,
                    'tiempoOficial'   => $r->tiempo_oficial,
                    'esPropio'        => in_array($r->participante_id, $misParticipanteIds, true),
                ];
            })->values()->all();

            $equipos = RankingEquipos::paraEvento($event->id, (string) $categoria->id)
                ->map(fn ($e, $i) => [
                    'posicion'    => $i + 1,
                    'nombre'      => $e['nombre'],
                    'tiempoTotal' => RankingEquipos::segundosATiempo($e['segundos']),
                ])
                ->values()
                ->all();

            return [
                'categoriaId'      => $categoria->id,
                'categoriaNombre'  => $categoria->name,
                'inscritos'        => $deCategoria->count(),
                'finished'         => $finishers->count(),
                'dnf'              => $deCategoria->where('estado', 'dnf')->count(),
                'dns'              => $deCategoria->where('estado', 'dns')->count(),
                'leaderboard'      => $leaderboard,
                'equipos'          => $equipos,
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'data'    => [
                'eventoId'     => $event->id,
                'eventoNombre' => $event->nombre,
                'categorias'   => $porCategoria,
            ],
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
                // 'categoria' quedaba mostrando el id crudo en el frontend
                // (mismo bug ya corregido en DashboardInscripcionesData —
                // ver brain/groovy-chasing-ladybug.md). Se agrega el nombre
                // resuelto sin quitar el id (el frontend lo sigue usando
                // para matchear contra el leaderboard completo).
                'categoriaId'  => $participante->categoria,
                'categoria'    => Category::find($participante->categoria)?->name ?? $participante->categoria,
                'resultado'    => [
                    'tiempoOficial'     => $resultado->tiempo_oficial,
                    'posicionGeneral'   => $resultado->posicion_general,
                    'posicionCategoria' => $resultado->posicion_categoria,
                    'posicionGenero'    => $resultado->posicion_genero,
                    'estado'            => $resultado->estado,
                ],
                'comparativoCategoria' => $this->comparativoCategoria($eventoId, $participante),
                'progreso'             => ProgresoHistorico::paraIdentidad(
                    $participante->numero_documento,
                    $participante->correo,
                    $participante->categoria
                ),
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
            // 'finisher' explícito: desde que existen filas dns/dnf (sin
            // tiempo_oficial), ordenarlas por tiempoASegundos()=0 las
            // colaba en el primer puesto — ver ChronoTrackSyncService.
            ->where('estado', 'finisher')
            ->whereHas('participante', fn ($q) => $q->where('categoria', $participante->categoria))
            ->with('participante')
            ->get()
            ->filter(fn ($r) => $r->participante !== null)
            ->sortBy(fn ($r) => RankingEquipos::tiempoASegundos($r->tiempo_oficial))
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

        // Acotado a la categoría del participante — evita que equipos de otra
        // distancia "ganen" el ranking solo por correr menos (ver
        // RankingEquipos::paraEvento()).
        $ranking = RankingEquipos::paraEvento($eventoId, $participante->categoria);

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
                    'tiempoTotal'    => RankingEquipos::segundosATiempo($e['segundos']),
                    'esPropio'       => $e['equipoId'] === $participante->equipo_id,
                ])->values()->all(),
            ],
        ];
    }
}
