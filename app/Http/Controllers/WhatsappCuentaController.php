<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\StoreWhatsappCuentaRequest;
use App\Http\Requests\UpdateWhatsappCuentaRequest;
use App\Http\Resources\WhatsappCuentaResource;
use App\Models\WhatsappCuenta;
use Illuminate\Http\JsonResponse;

/**
 * CRUD de cuentas de WhatsApp Business oficial — solo super_admin
 * (credenciales reales de Meta Cloud API, mismo criterio de sensibilidad
 * que SipBancoController). Ver
 * C:\Users\User\.claude\plans\rippling-watching-stallman.md (08/10/2026).
 */
class WhatsappCuentaController extends Controller
{
    use AuthorizesEventoScope;

    public function index(): JsonResponse
    {
        $this->assertIsSuperAdmin();

        return response()->json([
            'success' => true,
            'data' => WhatsappCuentaResource::collection(WhatsappCuenta::with('organizador')->orderBy('nombre')->get()),
        ]);
    }

    public function store(StoreWhatsappCuentaRequest $request): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $cuenta = WhatsappCuenta::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Cuenta de WhatsApp creada correctamente.',
            'data' => new WhatsappCuentaResource($cuenta->load('organizador')),
        ], 201);
    }

    public function show(WhatsappCuenta $whatsappCuenta): JsonResponse
    {
        $this->assertIsSuperAdmin();

        return response()->json([
            'success' => true,
            'data' => new WhatsappCuentaResource($whatsappCuenta->load('organizador')),
        ]);
    }

    public function update(UpdateWhatsappCuentaRequest $request, WhatsappCuenta $whatsappCuenta): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $data = $request->validated();
        // "Dejar vacío para no cambiar" (08/10/2026) — mismo criterio que
        // SipBancoController::update(): un access_token ausente o vacío en
        // el form de edición no debe pisar el valor real ya guardado.
        if (array_key_exists('access_token', $data) && ($data['access_token'] === null || $data['access_token'] === '')) {
            unset($data['access_token']);
        }

        $whatsappCuenta->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Cuenta de WhatsApp actualizada correctamente.',
            'data' => new WhatsappCuentaResource($whatsappCuenta->load('organizador')),
        ]);
    }

    public function destroy(WhatsappCuenta $whatsappCuenta): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $whatsappCuenta->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cuenta de WhatsApp eliminada correctamente.',
        ]);
    }
}
