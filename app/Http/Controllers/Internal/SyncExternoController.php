<?php

namespace App\Http\Controllers\Internal;

use App\Actions\SincronizarParticipanteExternoAction;
use App\Http\Controllers\Controller;
use App\Models\Evento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sync de participantes de un congreso externo (07/09/2026, rediseñado
 * 12/09/2026) — ver brain/PLAN-SYNC-CONGRESO-EXTERNO-07092026.md.
 *
 * Endpoint server-to-server ÚNICAMENTE (protegido por
 * `RequiresExternalSyncSecret`, no por `auth:admins`/Sanctum) — lo llama el
 * Google Apps Script del organizador del congreso (un tercero real, no
 * nuestro propio backend). Recibe una copia de sus hojas completas en cada
 * disparo — no intenta interpretar "qué es nuevo", el upsert en
 * `SincronizarParticipanteExternoAction` ya lo hace idempotente.
 *
 * El body trae uno o más GRUPOS, cada uno apuntando a un `form_type_codigo`
 * (resuelto contra `FormType::name` dentro de este evento — sin columna de
 * código dedicada, no hace falta una migración nueva solo para esto: los
 * nombres los define quien arma el evento a mano en admin-eventos, ver §1
 * del plan). Caso real: COLABIOCLI 2026 tiene 2 form_types en el mismo
 * evento ("Congresista" y "Curso Pre-Congreso") porque una misma persona
 * puede tener ambas inscripciones — antes de 12/09 este endpoint exigía
 * exactamente 1 form_type por evento, eso ya no alcanza.
 */
class SyncExternoController extends Controller
{
    public function sync(Request $request, Evento $event, SincronizarParticipanteExternoAction $action): JsonResponse
    {
        $data = $request->validate([
            'grupos' => ['required', 'array', 'min:1'],
            'grupos.*.form_type_codigo' => ['required', 'string', 'max:255'],
            'grupos.*.participantes' => ['required', 'array', 'min:1'],
            'grupos.*.participantes.*.nombre' => ['nullable', 'string', 'max:255'],
            'grupos.*.participantes.*.apellido' => ['nullable', 'string', 'max:255'],
            'grupos.*.participantes.*.correo' => ['nullable', 'string', 'max:255'],
            'grupos.*.participantes.*.telefono' => ['nullable', 'string', 'max:50'],
            'grupos.*.participantes.*.categoria' => ['nullable', 'string', 'max:255'],
            'grupos.*.participantes.*.nombre_curso' => ['nullable', 'string', 'max:255'],
            'grupos.*.participantes.*.ubicacion' => ['nullable', 'string', 'max:255'],
        ]);

        $formTypes = $event->formTypes()->get()->keyBy(fn ($ft) => mb_strtolower(trim($ft->name)));

        $resumenPorGrupo = [];

        foreach ($data['grupos'] as $grupo) {
            $codigo = trim($grupo['form_type_codigo']);
            $formType = $formTypes->get(mb_strtolower($codigo));

            if (! $formType) {
                return response()->json([
                    'success' => false,
                    'error' => "El evento #{$event->id} no tiene un form_type llamado \"{$codigo}\".",
                ], 422);
            }

            $creados = 0;
            $actualizados = 0;
            $omitidos = [];

            foreach ($grupo['participantes'] as $i => $fila) {
                // Por-fila, no una sola transacción para todo el batch —
                // una fila con datos sucios de un Sheet ajeno no debe
                // tumbar el resto (ver Action, cada llamada ya abre su
                // propia transacción individual).
                $resultado = $action->run($event, $formType, $fila);

                match ($resultado['resultado']) {
                    'creado' => $creados++,
                    'actualizado' => $actualizados++,
                    'omitido' => $omitidos[] = ['fila' => $i, 'motivo' => $resultado['motivo']],
                };
            }

            $resumenPorGrupo[] = [
                'form_type_codigo' => $codigo,
                'creados' => $creados,
                'actualizados' => $actualizados,
                'omitidos' => $omitidos,
            ];
        }

        return response()->json([
            'success' => true,
            'grupos' => $resumenPorGrupo,
        ]);
    }
}
