<?php

namespace App\Support;

use App\Models\Evento;
use App\Models\PromoCode;
use App\Models\PromoCodeUsage;

/**
 * Reporte de códigos promocionales usados, por evento (11/09/2026,
 * reescrito 13/09/2026 para multi-uso) — el usuario preguntó si existía
 * algo así al generar los códigos de Naranjillo Ultra Trail; no existía,
 * solo la pestaña "Promos" del editor de evento (usado/sin-usar por
 * código, sin decir quién ni cuánto). Mismo criterio que
 * ReporteInscritosData/DashboardInscripcionesData: por-un-evento, sin
 * paginar (un evento no tiene cientos de códigos).
 *
 * Multi-uso (13/09/2026) — antes esto matcheaba un único Participante por
 * código vía `Collection::keyBy('promo_codigo')`, que solo podía mostrar
 * UN uso por código. Ahora lee `PromoCodeUsage` (una fila por uso real,
 * ver RegistrationService::consumePromoCode()) y arma una lista `usos[]`
 * por código, sin límite de cuántos. `usado` en cada fila sigue
 * significando "agotado" (`times_used >= max_uses`), no "tocado alguna
 * vez" — mismo campo que ya usan los demás lectores del sistema.
 */
class PromoCodeReporteData
{
    public static function paraEvento(Evento $evento): array
    {
        $codigos = PromoCode::where('event_id', $evento->id)->orderBy('promo_code')->get();

        // Un solo query para TODOS los usos de los códigos de este evento
        // (evita N+1 — sin esto, sería una query por código).
        $usosPorCodigo = PromoCodeUsage::whereIn('promo_code_id', $codigos->pluck('id'))
            ->with('participante:id,nombre,apellido,numero_documento')
            ->orderBy('used_at')
            ->get()
            ->groupBy('promo_code_id');

        $filas = $codigos->map(function (PromoCode $pc) use ($usosPorCodigo) {
            $usos = $usosPorCodigo->get($pc->id, collect());

            return [
                'id' => $pc->id,
                'codigo' => $pc->promo_code,
                'tipo' => $pc->discount_type ?? 'fixed_price',
                'valor' => $pc->discount_type === 'percentage' ? (float) $pc->discount_percent : (float) $pc->price,
                'maxUsos' => (int) $pc->max_uses,
                'vecesUsado' => (int) $pc->times_used,
                'usado' => (bool) $pc->usado,
                'usos' => $usos->map(fn (PromoCodeUsage $u) => [
                    'participante' => $u->participante ? [
                        'nombre' => $u->participante->nombre,
                        'apellido' => $u->participante->apellido,
                        'numeroDocumento' => $u->participante->numero_documento,
                    ] : null,
                    'montoDescontado' => $u->monto_descontado !== null ? (float) $u->monto_descontado : null,
                    'fecha' => optional($u->used_at)->toIso8601String(),
                ])->values()->all(),
            ];
        });

        return [
            'filas' => $filas->values()->all(),
            'totalCodigos' => $codigos->count(),
            // "Códigos tocados al menos una vez" — mismo significado que
            // antes (la tarjeta lee "Usados / Total", una cuenta de
            // usage-events acá volvería nonsense esa fracción).
            'totalUsados' => $codigos->where('times_used', '>', 0)->count(),
            // Nuevo (13/09/2026): la dimensión de multi-uso — cuántos usos
            // reales pasaron en total, sin importar cuántos códigos distintos.
            'totalUsos' => (int) $codigos->sum('times_used'),
            'totalDescontado' => round($usosPorCodigo->flatten()->sum('monto_descontado'), 2),
        ];
    }
}
