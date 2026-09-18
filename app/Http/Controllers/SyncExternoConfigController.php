<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\StoreEventoSyncExternoConfigRequest;
use App\Http\Resources\EventoSyncExternoConfigResource;
use App\Models\Evento;
use App\Models\EventoSyncExternoConfig;
use App\Models\FormType;
use App\Services\SyncExternoPullService;
use Illuminate\Http\JsonResponse;

/**
 * Sync periódico (pull) de participantes de un evento con registro propio
 * externo (17/09/2026) — administra `EventoSyncExternoConfig` (a qué URL
 * llamar, con qué token, cada hora). Scoping: SOLO super_admin (no "admin de
 * su propio evento" como Presupuesto/Numeración) — `token` es una credencial
 * de integración sensible, mismo criterio que Socios/Liquidación financiera.
 */
class SyncExternoConfigController extends Controller
{
    use AuthorizesEventoScope;

    public function show(Evento $event): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $config = EventoSyncExternoConfig::where('evento_id', $event->id)->first();

        return response()->json([
            'success' => true,
            'data' => $config ? new EventoSyncExternoConfigResource($config) : null,
        ]);
    }

    public function store(StoreEventoSyncExternoConfigRequest $request, Evento $event): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $data = $request->validated();

        $formType = FormType::find($data['form_types_id']);
        if (! $formType || (int) $formType->event_id !== $event->id) {
            return response()->json([
                'success' => false,
                'error' => 'Ese FormType no pertenece a este evento.',
            ], 422);
        }

        $valores = [
            'form_types_id' => $data['form_types_id'],
            'nombre_fuente' => $data['nombre_fuente'] ?? null,
            'url' => $data['url'],
            'activo' => $data['activo'] ?? true,
        ];

        // Campo vacío = "no tocar el token actual" — se omite del array y
        // updateOrCreate() no lo pisa; nunca se exige de nuevo en cada
        // guardado (ver EventoSyncExternoConfigResource, que tampoco lo
        // devuelve completo).
        if (! empty($data['token'])) {
            $valores['token'] = $data['token'];
        }

        $config = EventoSyncExternoConfig::updateOrCreate(['evento_id' => $event->id], $valores);

        return response()->json([
            'success' => true,
            'message' => 'Configuración guardada correctamente.',
            'data' => new EventoSyncExternoConfigResource($config),
        ]);
    }

    public function sincronizarAhora(Evento $event, SyncExternoPullService $service): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $config = EventoSyncExternoConfig::where('evento_id', $event->id)->first();

        if (! $config) {
            return response()->json([
                'success' => false,
                'error' => 'Este evento todavía no tiene una fuente configurada.',
            ], 422);
        }

        $resumen = $service->sincronizar($config);

        return response()->json([
            'success' => $resumen['error'] === null,
            'data' => $resumen,
        ]);
    }
}
