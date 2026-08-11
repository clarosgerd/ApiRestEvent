<?php

namespace App\Http\Controllers;

use App\Actions\LiquidarEventoAction;
use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Models\Evento;
use App\Models\Liquidacion;
use Illuminate\Http\JsonResponse;

/**
 * Consolidación financiera — liquidación de utilidades por evento. Solo
 * super_admin (ver LiquidarEventoAction para el cálculo real). Ver
 * elascenso/event/brain/ (sesión 11/08/2026).
 */
class LiquidacionController extends Controller
{
    use AuthorizesEventoScope;

    /**
     * Calcula (sin persistir) lo que se repartiría si se liquidara este
     * evento ahora — para que el panel muestre el preview antes de que el
     * superadmin confirme.
     */
    public function preview(Evento $event, LiquidarEventoAction $action): JsonResponse
    {
        $this->assertIsSuperAdmin();

        if (Liquidacion::where('evento_id', $event->id)->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'Este evento ya fue liquidado.',
            ], 409);
        }

        $calculo = $action->calcular($event);

        return response()->json([
            'success' => true,
            'evento_cerrado' => $event->estado_evento_id === 'closed',
            'data' => $calculo,
        ]);
    }

    /**
     * Devuelve la liquidación ya confirmada de este evento, si existe.
     */
    public function show(Evento $event): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $liquidacion = Liquidacion::with(['detalles', 'liquidadoPor'])
            ->where('evento_id', $event->id)
            ->first();

        if (! $liquidacion) {
            return response()->json([
                'success' => false,
                'error' => 'Este evento todavía no fue liquidado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $liquidacion,
        ]);
    }

    /**
     * Confirma y persiste la liquidación — no es reversible en esta fase
     * (ver plan: "deshacer una liquidación" queda explícitamente fuera de
     * alcance).
     */
    public function store(Evento $event, LiquidarEventoAction $action): JsonResponse
    {
        $this->assertIsSuperAdmin();

        // Mismo patrón que EventoController::despublicar(): el chequeo de
        // "ya existe" se hace acá en el controller (409), y el Action solo
        // valida las reglas de negocio del cálculo mismo (422).
        if (Liquidacion::where('evento_id', $event->id)->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'Este evento ya fue liquidado.',
            ], 409);
        }

        try {
            $liquidacion = $action->handle($event);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Evento liquidado correctamente.',
            'data' => $liquidacion,
        ], 201);
    }
}
