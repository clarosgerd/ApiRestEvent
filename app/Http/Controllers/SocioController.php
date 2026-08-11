<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\StoreSocioRequest;
use App\Http\Requests\UpdateSocioRequest;
use App\Models\Socio;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * CRUD de socios de PassToGo — solo super_admin. Ver
 * elascenso/event/brain/ (sesión 11/08/2026) y LiquidarEventoAction, que
 * es quien realmente consume estos porcentajes.
 */
class SocioController extends Controller
{
    use AuthorizesEventoScope;

    public function index(): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $socios = Socio::orderBy('id')->get();

        return response()->json([
            'success' => true,
            'data' => $socios,
            'porcentaje_total' => round((float) $socios->where('activo', true)->sum('porcentaje'), 2),
        ]);
    }

    public function store(StoreSocioRequest $request): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $data = $request->validated();
        $data['activo'] = $data['activo'] ?? true;

        $socio = Socio::create($data);

        AdminAuditLogger::log('create', 'Socio', $socio->id, null, null, $socio->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Socio creado correctamente.',
            'data' => $socio,
        ], 201);
    }

    public function update(UpdateSocioRequest $request, Socio $socio): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $before = $socio->toArray();
        $socio->update($request->validated());

        AdminAuditLogger::log('update', 'Socio', $socio->id, null, $before, $socio->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Socio actualizado correctamente.',
            'data' => $socio,
        ]);
    }

    public function destroy(Socio $socio): JsonResponse
    {
        $this->assertIsSuperAdmin();

        // No se borra si ya tiene detalles de liquidación asociados — el
        // historial de reparto de plata no debe poder desaparecer. El
        // superadmin puede en cambio desactivarlo (activo=false) para
        // sacarlo del reparto futuro sin perder el registro pasado.
        if ($socio->liquidacionDetalles()->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'Este socio ya tiene liquidaciones registradas — desactívelo en vez de eliminarlo.',
            ], 409);
        }

        $before = $socio->toArray();
        $socio->delete();

        AdminAuditLogger::log('delete', 'Socio', $socio->id, null, $before, null);

        return response()->json([
            'success' => true,
            'message' => 'Socio eliminado correctamente.',
        ]);
    }
}
