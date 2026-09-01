<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Resources\EquipoResource;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\Evento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipoController extends Controller
{
    use AuthorizesEventoScope;

    /**
     * Catálogo de equipos de un evento (precargado por el organizador) —
     * ver brain/PLAN-RESULTADOS-EQUIPOS-31072026.md §3. Pantalla de gestión
     * en admin-eventos desde 01/09/2026 (antes solo se podía cargar
     * pegándole directo a la API).
     */
    public function index(Evento $event): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);

        return response()->json([
            'success' => true,
            'data'    => EquipoResource::collection($event->equipos),
        ]);
    }

    /**
     * Crea uno o varios equipos de una sola vez (para precargar el
     * catálogo completo antes de que arranquen las inscripciones).
     * Body: { "equipos": ["Team A", "Team B", ...] }
     */
    public function store(Request $request, Evento $event): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);

        $data = $request->validate([
            'equipos'   => ['required', 'array', 'min:1'],
            'equipos.*' => ['required', 'string', 'max:150'],
        ]);

        $creados = collect($data['equipos'])
            ->unique()
            ->map(function ($nombre) use ($event) {
                $equipo = Equipo::firstOrCreate([
                    'event_id' => $event->id,
                    'nombre'   => $nombre,
                ]);

                // Si el nombre matchea un club del catálogo global, se
                // vincula automáticamente para que el club vea este evento
                // en su historial — ver PLAN-CLUBES-31072026.md §1.
                if (!$equipo->club_id) {
                    $club = Club::whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])->first();
                    if ($club) {
                        $equipo->update(['club_id' => $club->id]);
                    }
                }

                return $equipo;
            })
            ->values();

        return response()->json([
            'success' => true,
            'data'    => EquipoResource::collection($creados),
        ], 201);
    }

    /**
     * Corrige el nombre de un equipo ya cargado (01/09/2026).
     */
    public function update(Request $request, Equipo $equipo): JsonResponse
    {
        $this->assertCanWriteEvento($equipo->event_id);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
        ]);

        $equipo->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Equipo actualizado correctamente.',
            'data'    => new EquipoResource($equipo),
        ]);
    }

    /**
     * Elimina un equipo (01/09/2026) — bloqueado si ya tiene participantes
     * inscritos, mismo criterio que SouvenirController::destroy().
     */
    public function destroy(Equipo $equipo): JsonResponse
    {
        $this->assertCanWriteEvento($equipo->event_id);

        if ($equipo->participantes()->exists()) {
            return response()->json([
                'success' => false,
                'error'   => 'No se puede eliminar este equipo: ya tiene participantes inscritos.',
            ], 409);
        }

        $equipo->delete();

        return response()->json([
            'success' => true,
            'message' => 'Equipo eliminado correctamente.',
        ]);
    }
}
