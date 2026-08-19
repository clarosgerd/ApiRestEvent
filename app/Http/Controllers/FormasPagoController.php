<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\StoreFormasPagoRequest;
use App\Http\Requests\UpdateFormasPagoRequest;
use App\Models\FormasPago;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * CRUD del catálogo global de formas de pago (19/08/2026) — solo
 * super_admin, mismo criterio que PaisController/OrganizadorController. Ver
 * brain/PLAN-INTEGRACION-PAGO-MERU-19082026.md — nace para poder agregar
 * "meru" (u otra pasarela futura) sin escribir directo en la BD, y porque
 * hoy ni siquiera sip/multipago tenían una pantalla de administración.
 *
 * Este controlador administra el catálogo del SISTEMA (organizador_id
 * null). Qué organizador tiene prendida cada forma de pago se administra
 * aparte, en OrganizadorController::formasPago()/updateFormasPago().
 *
 * El controlador ya existía como stub vacío (scaffolding de
 * `make:controller --resource`, nunca implementado) — se completa acá.
 */
class FormasPagoController extends Controller
{
    use AuthorizesEventoScope;

    public function index(): JsonResponse
    {
        $this->assertIsSuperAdmin();

        return response()->json([
            'success' => true,
            'data' => FormasPago::whereNull('organizador_id')->orderBy('nombre')->get(),
        ]);
    }

    public function store(StoreFormasPagoRequest $request): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $data = $request->validated();
        $data['activo'] = $data['activo'] ?? true;
        $data['organizador_id'] = null;

        $formasPago = FormasPago::create($data);

        AdminAuditLogger::log('create', 'FormasPago', $formasPago->id, null, null, $formasPago->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Forma de pago creada correctamente.',
            'data' => $formasPago,
        ], 201);
    }

    public function update(UpdateFormasPagoRequest $request, FormasPago $formasPago): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $before = $formasPago->toArray();
        $formasPago->update($request->validated());

        AdminAuditLogger::log('update', 'FormasPago', $formasPago->id, null, $before, $formasPago->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Forma de pago actualizada correctamente.',
            'data' => $formasPago,
        ]);
    }

    public function destroy(FormasPago $formasPago): JsonResponse
    {
        $this->assertIsSuperAdmin();

        // No se borra un método que algún organizador todavía tiene
        // seleccionado — lo dejaría con un pivote apuntando a nada
        // (organizador_formas_pago.forma_pago_id huérfano) y, si era su
        // única selección, formasPagoEfectivas() caería sola al default
        // del sistema sin que nadie lo haya decidido. Desactivarlo en
        // cambio lo saca de los checkouts nuevos sin ese efecto.
        if ($formasPago->organizadoresQueLoSeleccionaron()->wherePivot('activo', true)->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'Esta forma de pago está seleccionada por al menos un organizador — desactívela en vez de eliminarla.',
            ], 409);
        }

        $before = $formasPago->toArray();
        $formasPago->delete();

        AdminAuditLogger::log('delete', 'FormasPago', $formasPago->id, null, $before, null);

        return response()->json([
            'success' => true,
            'message' => 'Forma de pago eliminada correctamente.',
        ]);
    }
}
