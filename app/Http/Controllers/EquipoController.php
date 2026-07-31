<?php

namespace App\Http\Controllers;

use App\Http\Resources\EquipoResource;
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
            ->map(fn ($nombre) => Equipo::firstOrCreate([
                'event_id' => $event->id,
                'nombre'   => $nombre,
            ]))
            ->values();

        return response()->json([
            'success' => true,
            'data'    => EquipoResource::collection($creados),
        ], 201);
    }
}
