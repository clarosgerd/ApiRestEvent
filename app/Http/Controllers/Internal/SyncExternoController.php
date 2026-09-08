<?php

namespace App\Http\Controllers\Internal;

use App\Actions\SincronizarParticipanteExternoAction;
use App\Http\Controllers\Controller;
use App\Models\Evento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sync de participantes de un congreso externo (07/09/2026) — ver
 * brain/PLAN-SYNC-CONGRESO-EXTERNO-07092026.md.
 *
 * Endpoint server-to-server ÚNICAMENTE (protegido por
 * `RequiresExternalSyncSecret`, no por `auth:admins`/Sanctum) — lo llama el
 * Google Apps Script del organizador del congreso (un tercero real, no
 * nuestro propio backend). Recibe una copia de su Sheet completo en cada
 * disparo — no intenta interpretar "qué es nuevo", el upsert por correo en
 * `SincronizarParticipanteExternoAction` ya lo hace idempotente.
 */
class SyncExternoController extends Controller
{
    public function sync(Request $request, Evento $event, SincronizarParticipanteExternoAction $action): JsonResponse
    {
        $data = $request->validate([
            'participantes' => ['required', 'array', 'min:1'],
            'participantes.*.nombre' => ['nullable', 'string', 'max:255'],
            'participantes.*.apellido' => ['nullable', 'string', 'max:255'],
            'participantes.*.correo' => ['nullable', 'string', 'max:255'],
            'participantes.*.telefono' => ['nullable', 'string', 'max:50'],
            'participantes.*.categoria' => ['nullable', 'string', 'max:255'],
            'participantes.*.ubicacion' => ['nullable', 'string', 'max:255'],
        ]);

        // El evento tiene que tener EXACTAMENTE un form_type — es el
        // "balde" donde cuelgan las Registration sincronizadas (ver §1 del
        // plan). Ambigüedad (0 o 2+) es un error de configuración, no algo
        // que este endpoint deba adivinar.
        $formTypes = $event->formTypes()->get();
        if ($formTypes->count() !== 1) {
            return response()->json([
                'success' => false,
                'error' => "El evento #{$event->id} tiene {$formTypes->count()} form_type(s) — necesita exactamente 1 para poder sincronizar.",
            ], 422);
        }
        $formType = $formTypes->first();

        $creados = 0;
        $actualizados = 0;
        $omitidos = [];

        foreach ($data['participantes'] as $i => $fila) {
            // Por-fila, no una sola transacción para todo el batch — una
            // fila con datos sucios de un Sheet ajeno no debe tumbar el
            // resto (ver Action, cada llamada ya abre su propia
            // transacción individual).
            $resultado = $action->run($event, $formType, $fila);

            match ($resultado['resultado']) {
                'creado' => $creados++,
                'actualizado' => $actualizados++,
                'omitido' => $omitidos[] = ['fila' => $i, 'motivo' => $resultado['motivo']],
            };
        }

        return response()->json([
            'success' => true,
            'creados' => $creados,
            'actualizados' => $actualizados,
            'omitidos' => $omitidos,
        ]);
    }
}
