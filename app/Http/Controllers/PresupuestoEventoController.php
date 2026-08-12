<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\StorePresupuestoEventoRequest;
use App\Http\Requests\UpdatePresupuestoEventoRequest;
use App\Models\Evento;
use App\Models\PresupuestoCategoria;
use App\Models\PresupuestoEvento;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * Presupuesto de un evento — movimientos manuales de ingreso/gasto del
 * organizador. Ver PRD-presupuesto_de_un_evento.md y
 * elascenso/event/brain/ (sesión 11/08/2026). A diferencia de
 * SocioController/LiquidacionController (solo super_admin), acá el admin
 * scoped a su propio evento también puede operar — mismo criterio que
 * ParticipanteController/CategoryController.
 */
class PresupuestoEventoController extends Controller
{
    use AuthorizesEventoScope;

    public function index(Evento $event): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);

        $movimientos = PresupuestoEvento::with(['categoria', 'registradoPor'])
            ->where('evento_id', $event->id)
            ->orderByDesc('fecha')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $movimientos,
        ]);
    }

    public function store(StorePresupuestoEventoRequest $request, Evento $event): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);

        $data = $request->validated();

        $categoria = PresupuestoCategoria::find($data['presupuesto_categoria_id']);
        if ($categoria->tipo !== $data['tipo']) {
            return response()->json([
                'success' => false,
                'error' => "La categoría \"{$categoria->nombre}\" es de tipo \"{$categoria->tipo}\", no coincide con el tipo enviado (\"{$data['tipo']}\").",
            ], 422);
        }

        $data['evento_id'] = $event->id;
        // Quién lo registró se captura server-side, nunca viene del
        // request — mismo criterio que liquidado_por_admin_user_id en
        // LiquidarEventoAction.
        $data['admin_user_id'] = auth('admins')->id();

        $movimiento = PresupuestoEvento::create($data);

        AdminAuditLogger::log('create', 'PresupuestoEvento', $movimiento->id, $event->id, null, $movimiento->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Movimiento registrado correctamente.',
            'data' => $movimiento->load(['categoria', 'registradoPor']),
        ], 201);
    }

    public function update(UpdatePresupuestoEventoRequest $request, Evento $event, PresupuestoEvento $presupuesto): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);
        abort_if($presupuesto->evento_id !== $event->id, 404);

        $data = $request->validated();

        $tipo = $data['tipo'] ?? $presupuesto->tipo;
        $categoriaId = $data['presupuesto_categoria_id'] ?? $presupuesto->presupuesto_categoria_id;
        $categoria = PresupuestoCategoria::find($categoriaId);
        if ($categoria && $categoria->tipo !== $tipo) {
            return response()->json([
                'success' => false,
                'error' => "La categoría \"{$categoria->nombre}\" es de tipo \"{$categoria->tipo}\", no coincide con el tipo enviado (\"{$tipo}\").",
            ], 422);
        }

        $before = $presupuesto->toArray();
        $presupuesto->update($data);

        AdminAuditLogger::log('update', 'PresupuestoEvento', $presupuesto->id, $event->id, $before, $presupuesto->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Movimiento actualizado correctamente.',
            'data' => $presupuesto->load(['categoria', 'registradoPor']),
        ]);
    }

    public function destroy(Evento $event, PresupuestoEvento $presupuesto): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);
        abort_if($presupuesto->evento_id !== $event->id, 404);

        $before = $presupuesto->toArray();
        $presupuesto->delete();

        AdminAuditLogger::log('delete', 'PresupuestoEvento', $presupuesto->id, $event->id, $before, null);

        return response()->json([
            'success' => true,
            'message' => 'Movimiento eliminado correctamente.',
        ]);
    }
}
