<?php

namespace App\Support;

use App\Models\Evento;
use App\Models\Participante;
use App\Models\PromoCode;

/**
 * Reporte de códigos promocionales usados, por evento (11/09/2026) — el
 * usuario preguntó si existía algo así al generar los códigos de
 * Naranjillo Ultra Trail; no existía, solo la pestaña "Promos" del editor
 * de evento (usado/sin-usar por código, sin decir quién ni cuánto). Mismo
 * criterio que ReporteInscritosData/DashboardInscripcionesData: por-un-
 * evento, sin paginar (un evento no tiene cientos de códigos).
 *
 * `PromoCode` no tiene timestamps (ver el modelo) — no hay una fecha de
 * uso real; `participantes.created_at` se usa como proxy (coincide con la
 * fecha de inscripción, que en la práctica es cuando se consumió el
 * código).
 */
class PromoCodeReporteData
{
    public static function paraEvento(Evento $evento): array
    {
        $codigos = PromoCode::where('event_id', $evento->id)->orderBy('promo_code')->get();

        // Un solo query para TODOS los participantes con promo de este
        // evento (evita N+1 — sin esto, sería una query por código).
        $participantesConPromo = Participante::whereHas(
            'registration',
            fn ($q) => $q->where('evento_id', $evento->id)
        )
            ->whereNotNull('promo_codigo')
            ->where('promo_codigo', '!=', '')
            ->get(['id', 'nombre', 'apellido', 'numero_documento', 'promo_codigo', 'promo_descuento', 'created_at']);

        // Comparación exacta (case-sensitive) — mismo criterio BINARY que
        // ya usa el resto del sistema para promo_code (ver
        // PromoCodeController::promoCode()/RegistrationService::consumePromoCode()).
        // Collection::keyBy() es case-sensitive por default para strings,
        // así que no hace falta nada especial acá.
        $porCodigo = $participantesConPromo->keyBy('promo_codigo');

        $filas = $codigos->map(function (PromoCode $pc) use ($porCodigo) {
            $participante = $porCodigo->get($pc->promo_code);

            return [
                'id' => $pc->id,
                'codigo' => $pc->promo_code,
                'tipo' => $pc->discount_type ?? 'fixed_price',
                'valor' => $pc->discount_type === 'percentage' ? (float) $pc->discount_percent : (float) $pc->price,
                'usado' => (bool) $pc->usado,
                'participante' => $participante ? [
                    'nombre' => $participante->nombre,
                    'apellido' => $participante->apellido,
                    'numeroDocumento' => $participante->numero_documento,
                ] : null,
                'montoDescontado' => $participante ? (float) $participante->promo_descuento : null,
                'fecha' => optional($participante?->created_at)->toIso8601String(),
            ];
        });

        return [
            'filas' => $filas->values()->all(),
            'totalCodigos' => $codigos->count(),
            'totalUsados' => $codigos->where('usado', true)->count(),
            'totalDescontado' => round($participantesConPromo->sum('promo_descuento'), 2),
        ];
    }
}
