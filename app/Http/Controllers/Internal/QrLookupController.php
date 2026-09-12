<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use Illuminate\Http\JsonResponse;

/**
 * Lookup de participante por QR para la app externa Android/iOS
 * (12/09/2026, ver brain/api_rest_event/PLAN-QR-LOOKUP-APP-EXTERNA-12092026.md)
 * — un equipo aparte (fuera de nuestro alcance) va a construir esa app; acá
 * solo se expone lectura mínima para que, al escanear el QR de referencia
 * (`ReferenceQrService`, el mismo que ya usa Acreditación en
 * admin-eventos), puedan mostrar quién es la persona.
 *
 * A propósito devuelve MUCHO menos que `GET /registrations/{reference}`
 * (público, sin secreto, pensado para autoservicio) — ese expone
 * documento, fecha de nacimiento, correo, teléfono, contacto de
 * emergencia y montos financieros completos. Para un integrador externo
 * nuevo, con su propio secreto, el contrato queda acotado a lo que de
 * verdad hace falta para identificar a alguien en la puerta: nombre,
 * categoría/rol, evento, estado de pago y si ya se acreditó.
 *
 * NO reusa `checkinLookup()` (scoped por evento, exige `auth:admins`) — el
 * QR solo trae la referencia, sin evento, y esta app no tiene ni debe
 * tener credenciales de nuestro panel de administración.
 */
class QrLookupController extends Controller
{
    public function show(string $referencia): JsonResponse
    {
        $registration = Registration::with(['participants', 'formType', 'evento.categories'])
            ->where('referencia', $referencia)
            ->first();

        if (!$registration) {
            return response()->json(['success' => false, 'error' => 'Referencia no encontrada.'], 404);
        }

        $rol = $registration->formType->name ?? null;
        // Mismo fallback que gafetesPdf()/exportCsv(): participante.categoria
        // guarda el ID real de Category cuando el form_type la usa, o texto
        // crudo cuando no (ej. inscripciones sincronizadas externas) — acá
        // no se puede saber cuál sin intentar el mapeo.
        $categoryNames = $registration->evento?->categories->pluck('name', 'id') ?? collect();

        return response()->json([
            'success' => true,
            'data' => [
                'referencia'    => $registration->referencia,
                'evento_nombre' => $registration->evento_nombre,
                'pago_status'   => $registration->pago_status,
                'participantes' => $registration->participants->map(fn ($p) => [
                    'nombre'      => $p->nombre,
                    'apellido'    => $p->apellido,
                    'categoria'   => $categoryNames[$p->categoria] ?? $p->categoria,
                    'rol'         => $rol,
                    'checkedInAt' => optional($p->checked_in_at)->toIso8601String(),
                ])->values(),
            ],
        ]);
    }
}
