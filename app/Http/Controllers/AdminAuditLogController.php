<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuditLogController extends Controller
{
    /**
     * super_admin ve todo (filtrable por ?evento_id=), admin queda forzado
     * a su propio evento_id sin importar el query param — ver
     * brain/PLAN-PANEL-ADMIN-EVENTOS-02082026.md §1.3.
     */
    public function index(Request $request): JsonResponse
    {
        $admin = auth('admins')->user();

        $query = AdminAuditLog::with('adminUser:id,nombre,email')->latest('id');

        if ($admin->rol === 'admin') {
            // Admin de evento asignado a varios eventos (28/08/2026) —
            // whereIn con evento_id (principal) + eventosAdicionales, ver
            // AdminUser::eventoIds().
            $query->whereIn('evento_id', $admin->eventoIds());
        } elseif ($request->filled('evento_id')) {
            $query->where('evento_id', (int) $request->query('evento_id'));
        }

        return response()->json([
            'success' => true,
            'data'    => $query->paginate(20),
        ]);
    }
}
