<?php

namespace App\Http\Controllers;

use App\Models\CalculoEdad;
use Illuminate\Http\JsonResponse;

/**
 * Aviso de numeración vs. género/edad real en entrega de kit (16/09/2026).
 * Catálogo fijo (3 filas sembradas, no editable desde ningún panel a
 * propósito, ver create_calculo_edades_table) — solo index(), mismo
 * criterio minimalista que GeneroController (sin CRUD, a diferencia de
 * SexoController que sí es un catálogo editable por el organizador).
 */
class CalculoEdadController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => CalculoEdad::where('activo', true)->get(),
        ]);
    }
}
