<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\StoreSipBancoRequest;
use App\Http\Requests\UpdateSipBancoRequest;
use App\Http\Resources\SipBancoResource;
use App\Models\SipBanco;
use Illuminate\Http\JsonResponse;

/**
 * CRUD de bancos SIP — solo super_admin (credenciales reales de banco,
 * más sensible que el resto del panel). Ver
 * brain/api_rest_event/PLAN-SIP-MULTIBANCO-28082026.md.
 */
class SipBancoController extends Controller
{
    use AuthorizesEventoScope;

    public function index(): JsonResponse
    {
        $this->assertIsSuperAdmin();

        return response()->json([
            'success' => true,
            'data' => SipBancoResource::collection(SipBanco::with('organizador')->orderBy('nombre')->get()),
        ]);
    }

    public function store(StoreSipBancoRequest $request): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $banco = SipBanco::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Banco SIP creado correctamente.',
            'data' => new SipBancoResource($banco->load('organizador')),
        ], 201);
    }

    public function show(SipBanco $sipBanco): JsonResponse
    {
        $this->assertIsSuperAdmin();

        return response()->json([
            'success' => true,
            'data' => new SipBancoResource($sipBanco->load('organizador')),
        ]);
    }

    public function update(UpdateSipBancoRequest $request, SipBanco $sipBanco): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $data = $request->validated();
        // "Dejar vacío para no cambiar" (28/08/2026) — mismo criterio que
        // la contraseña de AdminUser: un secreto ausente o vacío en el
        // form de edición no debe pisar el valor real ya guardado.
        foreach (['sip_password', 'sip_apikey', 'sip_apikey_servicio', 'callback_basic_password'] as $secretField) {
            if (array_key_exists($secretField, $data) && ($data[$secretField] === null || $data[$secretField] === '')) {
                unset($data[$secretField]);
            }
        }

        $sipBanco->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Banco SIP actualizado correctamente.',
            'data' => new SipBancoResource($sipBanco->load('organizador')),
        ]);
    }

    public function destroy(SipBanco $sipBanco): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $sipBanco->delete();

        return response()->json([
            'success' => true,
            'message' => 'Banco SIP eliminado correctamente.',
        ]);
    }
}
