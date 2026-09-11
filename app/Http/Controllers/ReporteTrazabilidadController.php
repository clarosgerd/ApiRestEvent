<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Support\ReporteTrazabilidadData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reporte de trazabilidad de inscripciones (admin general, cross-evento,
 * 10/09/2026) — ver brain/api_rest_event/PLAN-REPORTE-TRAZABILIDAD-10092026.md
 * y ReporteTrazabilidadData (donde vive toda la lógica real).
 */
class ReporteTrazabilidadController extends Controller
{
    use AuthorizesEventoScope;

    public function index(Request $request): JsonResponse
    {
        $this->assertIsSuperAdmin();

        return response()->json(['success' => true] + ReporteTrazabilidadData::paginar($request->all()));
    }
}
