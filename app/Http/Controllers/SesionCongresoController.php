<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\StoreSesionCongresoRequest;
use App\Http\Requests\UpdateSesionCongresoRequest;
use App\Models\Evento;
use App\Models\SesionCongreso;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * Agenda y sesiones de congreso — configuración estructural (ponente/
 * sala/horario/cupo). Ver PRD-Agenda-sessiones-onlycongresos.md y
 * elascenso/event/brain/ (sesión 11/08/2026). Mismo criterio de permisos
 * que Presupuesto: el admin scoped a su propio evento también puede
 * operar, no solo super_admin. La API no bloquea por tipo de evento — el
 * gating "solo congresos" es de visibilidad en el panel, no una regla de
 * negocio acá.
 */
class SesionCongresoController extends Controller
{
    use AuthorizesEventoScope;

    public function index(Evento $event): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);

        return response()->json([
            'success' => true,
            'data' => SesionCongreso::where('evento_id', $event->id)
                ->orderBy('fecha')->orderBy('hora_inicio')
                ->get(),
        ]);
    }

    public function store(StoreSesionCongresoRequest $request, Evento $event): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);

        $data = $request->validated();
        $data['evento_id'] = $event->id;

        $sesion = SesionCongreso::create($data);

        AdminAuditLogger::log('create', 'SesionCongreso', $sesion->id, $event->id, null, $sesion->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Sesión creada correctamente.',
            'data' => $sesion,
        ], 201);
    }

    public function update(UpdateSesionCongresoRequest $request, Evento $event, SesionCongreso $sesion): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);
        abort_if($sesion->evento_id !== $event->id, 404);

        $before = $sesion->toArray();
        $sesion->update($request->validated());

        AdminAuditLogger::log('update', 'SesionCongreso', $sesion->id, $event->id, $before, $sesion->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Sesión actualizada correctamente.',
            'data' => $sesion,
        ]);
    }

    public function destroy(Evento $event, SesionCongreso $sesion): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);
        abort_if($sesion->evento_id !== $event->id, 404);

        // No bloqueamos por asistencia ya registrada (a diferencia de
        // Socios/PresupuestoCategoria) — borrar una sesión en cascada se
        // lleva su asistencia (FK cascadeOnDelete), aceptable porque no
        // es un registro financiero. Si hace falta preservar historial de
        // asistencia de una sesión borrada, se resuelve en una fase aparte.
        $before = $sesion->toArray();
        $sesion->delete();

        AdminAuditLogger::log('delete', 'SesionCongreso', $sesion->id, $event->id, $before, null);

        return response()->json([
            'success' => true,
            'message' => 'Sesión eliminada correctamente.',
        ]);
    }
}
