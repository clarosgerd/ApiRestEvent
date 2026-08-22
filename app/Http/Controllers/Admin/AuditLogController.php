<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\AdminAuditLogController as ApiAdminAuditLogController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1b — ver AdminUserController
 * (mismo patrón de delegación) y
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 * El scoping (`admin` ve solo su evento, `super_admin` ve todo/filtra por
 * `?evento_id=`) vive en el controller de la API delegado — no se
 * reimplementa acá.
 */
class AuditLogController extends Controller
{
    use DelegatesToApiJson;

    public function index(Request $request, ApiAdminAuditLogController $api): View
    {
        $paginado = $this->dataFrom($api->index($request));

        return view('admin.auditoria.index', [
            'logs'     => $paginado['data'] ?? [],
            'eventoId' => $request->query('evento_id'),
        ]);
    }
}
