<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Models\Evento;
use App\Support\HistorialCobrosReporteData;
use Illuminate\Http\JsonResponse;

/**
 * Reporte de historial de cobros por inscripción (22/10/2026, pedido real
 * del usuario) — ver App\Support\HistorialCobrosReporteData. Mismo patrón
 * exacto que PromoCodeController::reporte().
 */
class HistorialCobrosController extends Controller
{
    use AuthorizesEventoScope;

    public function reporte(Evento $event): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);

        return response()->json(['success' => true] + HistorialCobrosReporteData::paraEvento($event));
    }
}
