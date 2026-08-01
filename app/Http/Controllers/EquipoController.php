<?php

namespace App\Http\Controllers;

use App\Http\Resources\EquipoResource;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\Evento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipoController extends Controller
{
    /**
     * Catálogo de equipos de un evento (precargado por el organizador) —
     * ver brain/PLAN-RESULTADOS-EQUIPOS-31072026.md §3.
     */
    public function index(Evento $event): JsonResponse
    {
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
}
