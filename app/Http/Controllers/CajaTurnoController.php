<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\AbrirTurnoCajaRequest;
use App\Http\Requests\CerrarTurnoCajaRequest;
use App\Http\Resources\CajaTurnoResource;
use App\Models\CajaTurno;
use App\Models\Evento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Turnos de caja — ver PLAN-CAJA-COBRO-PRESENCIAL-14082026.md,
 * "Actualización 14/08/2026 — alto tráfico, control de cierre de caja".
 * Un cajero no puede cobrar sin turno abierto, ni tener dos turnos
 * abiertos a la vez.
 */
class CajaTurnoController extends Controller
{
    use AuthorizesEventoScope;

    /**
     * Turno abierto del cajero autenticado en este evento (o null) — para
     * la barra fija del panel (hora de apertura + total cobrado hasta el
     * momento). Cualquier rol que pueda operar la caja puede consultar el
     * suyo propio.
     */
    public function actual(Evento $event): JsonResponse
    {
        $admin = $this->assertCanOperarCaja((int) $event->id);

        $turno = CajaTurno::where('evento_id', $event->id)
            ->where('admin_user_id', $admin->id)
            ->where('estado', 'abierto')
            ->with('movimientos')
            ->first();

        return response()->json([
            'success' => true,
            'turno'   => $turno ? new CajaTurnoResource($turno) : null,
        ]);
    }

    public function abrir(AbrirTurnoCajaRequest $request, Evento $event): JsonResponse
    {
        $admin = $this->assertCanOperarCaja((int) $event->id);

        $yaAbierto = CajaTurno::where('evento_id', $event->id)
            ->where('admin_user_id', $admin->id)
            ->where('estado', 'abierto')
            ->exists();

        if ($yaAbierto) {
            return response()->json([
                'success' => false,
                'error'   => 'Ya tenés un turno de caja abierto — cerralo antes de abrir uno nuevo.',
            ], 422);
        }

        $turno = CajaTurno::create([
            'evento_id'      => $event->id,
            'admin_user_id'  => $admin->id,
            'fondo_inicial'  => $request->validated('fondo_inicial'),
            'estado'         => 'abierto',
            'abierto_at'     => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Turno de caja abierto.',
            'turno'   => new CajaTurnoResource($turno),
        ], 201);
    }

    public function cerrar(CerrarTurnoCajaRequest $request, CajaTurno $turno): JsonResponse
    {
        $admin = $this->assertCanOperarCaja((int) $turno->evento_id);

        // Un cajero solo cierra el suyo; admin/super_admin pueden forzar
        // el cierre de cualquier turno de su evento (ej. fin de jornada).
        if ($admin->rol === 'cajero' && (int) $turno->admin_user_id !== (int) $admin->id) {
            return response()->json([
                'success' => false,
                'error'   => 'No podés cerrar el turno de otro cajero.',
            ], 403);
        }

        if ($turno->estado === 'cerrado') {
            return response()->json([
                'success' => false,
                'error'   => 'Este turno ya está cerrado.',
            ], 422);
        }

        $montoEsperado = (float) $turno->fondo_inicial + $turno->totalMovimientos();
        $montoContado  = (float) $request->validated('monto_contado');

        $turno->update([
            'monto_esperado' => $montoEsperado,
            'monto_contado'  => $montoContado,
            'diferencia'     => round($montoContado - $montoEsperado, 2),
            'estado'         => 'cerrado',
            'notas'          => $request->validated('notas'),
            'cerrado_at'     => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Turno de caja cerrado.',
            'turno'   => new CajaTurnoResource($turno->fresh('movimientos')),
        ]);
    }

    /**
     * Listado de turnos del evento — el control de cierre de caja que
     * pidió el stakeholder. Solo admin/super_admin (no cajero: no debe
     * ver los turnos de sus compañeros).
     */
    public function index(Request $request, Evento $event): JsonResponse
    {
        $this->assertCanWriteEvento((int) $event->id);

        $query = CajaTurno::where('evento_id', $event->id)
            ->with(['cajero', 'movimientos'])
            ->orderByDesc('abierto_at');

        if ($request->filled('admin_user_id')) {
            $query->where('admin_user_id', (int) $request->input('admin_user_id'));
        }
        if ($request->filled('desde')) {
            $query->whereDate('abierto_at', '>=', $request->input('desde'));
        }
        if ($request->filled('hasta')) {
            $query->whereDate('abierto_at', '<=', $request->input('hasta'));
        }

        return response()->json([
            'success' => true,
            'turnos'  => CajaTurnoResource::collection($query->get()),
        ]);
    }

    /**
     * Detalle de un turno (27/08/2026) — drill-down pedido por el usuario
     * desde el reporte de cierres: la lista de movimientos que componen el
     * total, no solo el agregado que ya devuelve index(). Mismo scoping
     * que index() (solo admin/super_admin, no cajero).
     */
    public function show(Evento $event, CajaTurno $turno): JsonResponse
    {
        $this->assertCanWriteEvento((int) $event->id);

        if ((int) $turno->evento_id !== (int) $event->id) {
            abort(404);
        }

        $turno->load(['cajero', 'movimientos.registration']);

        return response()->json([
            'success' => true,
            'turno'   => new CajaTurnoResource($turno),
        ]);
    }
}
