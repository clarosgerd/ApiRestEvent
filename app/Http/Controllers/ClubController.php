<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Equipo;
use App\Models\Participante;
use App\Support\ProgresoHistorico;
use App\Support\RankingEquipos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ClubController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $club = Club::where('email', $request->email)->first();

        if (!$club || !Hash::check($request->password, $club->password)) {
            return response()->json([
                'success' => false,
                'error'   => 'Las credenciales proporcionadas son incorrectas.',
            ], 401);
        }

        $token = $club->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login exitoso',
            'data'    => [
                'club'  => ['id' => $club->id, 'nombre' => $club->nombre, 'email' => $club->email],
                'token' => $token,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $club = auth('clubes')->user();

        if ($club) {
            $club->currentAccessToken()->delete();

            return response()->json(['success' => true, 'message' => 'Sesión cerrada con éxito']);
        }

        return response()->json(['success' => false, 'error' => 'No autorizado'], 401);
    }

    public function me(Request $request): JsonResponse
    {
        $club = $request->user();

        return response()->json([
            'success' => true,
            'data'    => ['id' => $club->id, 'nombre' => $club->nombre, 'email' => $club->email],
        ]);
    }

    /**
     * Historial de eventos del club: cada `equipo` (por-evento) vinculado a
     * este club, con el ranking de ese equipo en su evento y quiénes
     * corrieron por el club ahí — ver
     * elascenso/event/brain/PLAN-CLUBES-31072026.md §3.
     */
    public function landing(Request $request): JsonResponse
    {
        $club = $request->user();

        $equipos = Equipo::where('club_id', $club->id)->with('evento')->get();

        $historial = $equipos
            ->filter(fn (Equipo $equipo) => $equipo->evento !== null)
            ->map(function (Equipo $equipo) {
                $evento   = $equipo->evento;
                $ranking  = RankingEquipos::paraEvento($evento->id);
                $posicion = $ranking->search(fn ($e) => $e['equipoId'] === $equipo->id);

                $integrantes = Participante::where('equipo_id', $equipo->id)
                    ->with('resultado')
                    ->get()
                    ->map(fn (Participante $p) => [
                        'nombre'        => trim($p->nombre . ' ' . $p->apellido),
                        'tiempoOficial' => $p->resultado->tiempo_oficial ?? null,
                        'estado'        => $p->resultado->estado ?? null,
                        'progreso'      => $p->resultado
                            ? ProgresoHistorico::paraIdentidad($p->numero_documento, $p->correo, $p->categoria)
                            : [],
                    ])
                    ->values();

                return [
                    'eventoId'     => $evento->id,
                    'eventoNombre' => $evento->nombre,
                    'fechaInicio'  => $evento->fecha_inicio,
                    'equipoNombre' => $equipo->nombre,
                    'ranking'      => [
                        'posicion'     => $posicion === false ? null : $posicion + 1,
                        'totalEquipos' => $ranking->count(),
                    ],
                    'integrantes'  => $integrantes,
                ];
            })
            ->sortByDesc('fechaInicio')
            ->values();

        return response()->json([
            'success'   => true,
            'club'      => ['id' => $club->id, 'nombre' => $club->nombre],
            'historial' => $historial,
        ]);
    }
}
