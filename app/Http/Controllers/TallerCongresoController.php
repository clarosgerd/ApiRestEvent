<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\StoreTallerRequest;
use App\Http\Requests\UpdateTallerRequest;
use App\Models\Evento;
use App\Models\Taller;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * CRUD de talleres de congreso (sesiones agrupadas con modalidad y
 * precio). Ver
 * brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md. Mismo
 * criterio de scoping que `SesionCongresoController`: admin scoped a
 * su propio evento, o super_admin — el gating "solo congresos" lo hace
 * el panel, no la API.
 */
class TallerCongresoController extends Controller
{
    use AuthorizesEventoScope;

    public function index(Evento $event): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);

        return response()->json([
            'success' => true,
            'data'    => Taller::where('evento_id', $event->id)
                ->orderBy('orden')
                ->orderBy('id')
                ->with(['sesiones' => fn ($q) => $q->orderBy('fecha')->orderBy('hora_inicio')])
                ->get(),
        ]);
    }

    public function store(StoreTallerRequest $request, Evento $event): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);

        $data = $request->validated();
        $data['evento_id'] = $event->id;
        $data['activo'] = $data['activo'] ?? true;
        $data['permite_inscripcion'] = $data['permite_inscripcion'] ?? true;
        $data['orden'] = $data['orden'] ?? 0;

        $taller = Taller::create($data);

        AdminAuditLogger::log('create', 'Taller', $taller->id, $event->id, null, $taller->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Taller creado correctamente.',
            'data'    => $taller,
        ], 201);
    }

    public function show(Evento $event, Taller $taller): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);
        abort_if($taller->evento_id !== $event->id, 404);

        return response()->json([
            'success' => true,
            'data'    => $taller->load('sesiones'),
        ]);
    }

    public function update(UpdateTallerRequest $request, Evento $event, Taller $taller): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);
        abort_if($taller->evento_id !== $event->id, 404);

        $before = $taller->toArray();
        $taller->update($request->validated());

        AdminAuditLogger::log('update', 'Taller', $taller->id, $event->id, $before, $taller->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Taller actualizado correctamente.',
            'data'    => $taller,
        ]);
    }

    public function destroy(Evento $event, Taller $taller): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);
        abort_if($taller->evento_id !== $event->id, 404);

        // El FK de sesiones_congreso.taller_id es nullOnDelete: borrar el
        // taller deja a sus sesiones como ponencias sueltas (no
        // seleccionables en inscripción). Las selecciones en
        // `participante_taller_sesion` también se borran en cascada.
        $before = $taller->toArray();
        $taller->delete();

        AdminAuditLogger::log('delete', 'Taller', $taller->id, $event->id, $before, null);

        return response()->json([
            'success' => true,
            'message' => 'Taller eliminado correctamente.',
        ]);
    }
}