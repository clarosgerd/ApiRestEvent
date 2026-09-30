<?php

namespace App\Support;

use App\Models\Evento;

/**
 * Cargo de servicio en ediciones de inscripciones pagadas (07/09/2026) —
 * pedido del usuario: hasta ahora ninguna edición pagada (souvenir,
 * taller, subida de categoría) cobraba ningún cargo de servicio, ni
 * siquiera cuando correspondería según las reglas que ya existen para el
 * alta normal (`CrearInscripcionAction::validateFeePct()`). Mismo
 * criterio acá, aplicado solo a lo NUEVO de esta edición:
 * - Categoría: siempre (como la inscripción base en el alta normal).
 * - Souvenir agregado: solo si `aplica_cargo_servicio=true`.
 * - Taller agregado: solo si `evento.fee_incluye_talleres=true`.
 *
 * Un solo lugar con la fórmula, usado tanto por
 * `ActualizarInscripcionPagadaAction` (aplica de verdad, autoservicio y
 * Caja) como por `CalcularCostoAdicionalAction` (cotiza el QR SIP antes de
 * cobrar) — tienen que dar el MISMO número, o la cotización y el cobro
 * real divergen (una vez que SIP cobró no hay reembolso, ver
 * ConfirmarPagoAdicionalAction).
 */
class EdicionPagadaFeeData
{
    /**
     * @param float $deltaCategoria puede ser negativo (Caja, modo 'libre',
     *   bajada de categoría) — nunca negativo en autoservicio/SIP
     *   (`EdicionPagadaCategoriaData` con modo 'solo_subida' lo impide).
     * @param float $deltaSouvenirsConCargo ya filtrado por
     *   `aplica_cargo_servicio=true` (ver EdicionPagadaSouvenirsData).
     * @param float $deltaTalleres puede ser negativo (29/09/2026 — Caja
     *   puede quitar un taller ya pagado, ver
     *   ActualizarInscripcionPagadaAction) — nunca negativo en
     *   autoservicio/SIP, que jamás quitan talleres.
     */
    public static function calcular(
        Evento $evento,
        float $deltaCategoria,
        float $deltaSouvenirsConCargo,
        float $deltaTalleres,
    ): float {
        // El fee nunca se reduce cuando algo baja de precio (categoría más
        // barata, o ahora también un taller quitado/cambiado por uno más
        // barato, 29/09/2026) — decisión explícita del usuario: la comisión
        // de la pasarela sobre el cobro original ya se pagó y no se
        // recupera. Mismo clamp para los dos, por la misma razón.
        $deltaCategoriaParaFee = max(0.0, $deltaCategoria);
        $deltaTalleresParaFee = max(0.0, $deltaTalleres);

        $base = $deltaCategoriaParaFee
            + ($evento->fee_incluye_talleres ? $deltaTalleresParaFee : 0)
            + $deltaSouvenirsConCargo;

        return round($base * (float) $evento->fee_pct, 2);
    }
}
