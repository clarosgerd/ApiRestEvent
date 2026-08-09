<?php

namespace App\Http\Controllers;

use App\Models\TipoEvento;
use Illuminate\Http\JsonResponse;

/**
 * Catálogo de disciplinas de evento (Carrera de Ruta, Trail Running,
 * Ciclismo, Caminata, Triatlón, Natación, más "Congreso / No aplica" para
 * eventos no deportivos) — lectura pública, sin datos sensibles. Usado por
 * admin-eventos para poblar los selects de tipo/subtipo al crear o editar
 * un evento. Ver brain/PLAN-ENDPOINT-CONSUMO-05082026.md.
 */
class TipoEventoController extends Controller
{
    public function index(): JsonResponse
    {
        $tipos = TipoEvento::where('activo', true)
            ->with(['subtipos' => fn ($q) => $q->where('activo', true)->orderBy('nombre')])
            ->orderBy('nombre')
            ->get()
            ->map(fn (TipoEvento $tipo) => [
                'id' => $tipo->id,
                'nombre' => $tipo->nombre,
                'icono' => $tipo->icono,
                'subtipos' => $tipo->subtipos->map(fn ($sub) => [
                    'id' => $sub->id,
                    'nombre' => $sub->nombre,
                ]),
            ]);

        return response()->json([
            'success' => true,
            'tiposEvento' => $tipos,
        ]);
    }
}
