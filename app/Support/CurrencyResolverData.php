<?php

namespace App\Support;

use App\DTOs\RegistrationDTO;

/**
 * Inscripción en BOB y USD (18/08/2026) — ver
 * brain/PLAN-INSCRIPCION-BOB-USD-IMPLEMENTACION.md. Valida y normaliza
 * el snapshot de moneda/tasa enviado por el frontend:
 *
 * - BOB es siempre la fuente de verdad para precios (categoría,
 *   souvenirs, talleres, fee, donación). El `grand_total` del payload
 *   está en BOB y nunca se recalcula acá.
 * - Si el participante eligió USD, el frontend convirtió el total a USD
 *   con la tasa vigente del cache (api/tipo_cambio.php) y mandó el
 *   snapshot (`moneda_pago='USD'`, `tipo_cambio_aplicado`, `total_pagado`).
 *   Esta clase verifica que ese snapshot sea consistente con el
 *   `grand_total` BOB dentro de una tolerancia (2% por redondeo +
 *   posible drift de la tasa entre captura del frontend y validación
 *   acá). Si no coincide, rechaza la inscripción con 422 limpio — sin
 *   esto el cliente podría mandar números arbitrarios en `total_pagado`.
 *
 * El backend NO consulta la API externa de tipo de cambio: el frontend
 * es quien captura la tasa al momento de confirmar (cuando el usuario
 * ya está en el paso de pago), y la trae congelada en el payload. Esto
 * garantiza consistencia entre lo que el usuario vio y lo que se cobra.
 */
class CurrencyResolverData
{
    /**
     * Tolerancia permitida entre `total_bob * tasa` y `total_pagado` (en
     * valor absoluto). Pensada para cubrir redondeo a 2 decimales + un
     * drift de tasa de hasta ~1.5% entre la captura en el frontend y la
     * validación acá (mismo orden que la tolerancia de `fee_pct` ya en
     * uso). Ver brain/PLAN-INSCRIPCION-BOB-USD-IMPLEMENTACION.md.
     */
    private const TOLERANCIA_PORCENTAJE = 0.02;

    /**
     * Devuelve el total a cobrar y la tasa usada, validados contra el
     * grand_total BOB. Si moneda es BOB, retorna el mismo total sin
     * cambios. Si es USD, valida el snapshot contra el grand_total.
     *
     * @return array{total_pagado: float, tipo_cambio_aplicado: float}
     */
    public static function resolver(
        float $grandTotalBOB,
        ?string $monedaPago,
        ?float $tipoCambioAplicado,
        ?float $totalPagado,
    ): array {
        $moneda = strtoupper((string) $monedaPago) ?: 'BOB';

        if ($moneda === 'BOB') {
            // Camino legacy: siempre BOB, sin tasa ni total_pagado (null).
            // El grand_total en BOB vive en RegistrationTotal.grand_total
            // — no duplicarlo acá. Ver
            // brain/PLAN-INSCRIPCION-BOB-USD-IMPLEMENTACION.md.
            return [
                'total_pagado'        => null,
                'tipo_cambio_aplicado' => null,
            ];
        }

        if ($moneda !== 'USD') {
            throw new \DomainException(
                "Moneda de cobro no soportada: '{$monedaPago}'. Solo BOB o USD."
            );
        }

        // USD: el frontend DEBE haber mandado tasa + total_pagado.
        if ($tipoCambioAplicado === null || $totalPagado === null) {
            throw new \DomainException(
                'Para pagar en USD se requiere el snapshot de tasa y total_pagado.'
            );
        }

        if ($tipoCambioAplicado <= 0) {
            throw new \DomainException(
                'La tasa de cambio aplicada debe ser mayor a cero.'
            );
        }

        $esperado = round($grandTotalBOB * $tipoCambioAplicado, 2);
        $delta = abs($esperado - $totalPagado);

        // Tolerancia proporcional al total — para inscripciones chicas
        // ($10 USD) un drift de 1% ya puede ser unos centavos que sí
        // representen redondeo. Usamos el 2% del esperado.
        $tolerancia = max(0.05, $esperado * self::TOLERANCIA_PORCENTAJE);

        if ($delta > $tolerancia) {
            throw new \DomainException(
                sprintf(
                    'El total_pagado en USD (%.2f) no coincide con el grand_total BOB (%.2f) a la tasa %.4f (esperado %.2f, delta %.2f, tolerancia %.2f). Recargá la página e intentá de nuevo.',
                    $totalPagado,
                    $grandTotalBOB,
                    $tipoCambioAplicado,
                    $esperado,
                    $delta,
                    $tolerancia
                )
            );
        }

        return [
            'total_pagado'        => round($totalPagado, 2),
            'tipo_cambio_aplicado' => round($tipoCambioAplicado, 4),
        ];
    }

    /**
     * Atajo: valida contra el `grandTotal` que viene en `RegistrationDTO`.
     * @return array{total_pagado: float, tipo_cambio_aplicado: float|null}
     */
    public static function resolverFromDTO(RegistrationDTO $dto): array
    {
        $grandTotal = (float) ($dto->totals->grandTotal ?? 0);

        return self::resolver(
            $grandTotal,
            $dto->monedaPago ?? 'BOB',
            $dto->tipoCambioAplicado,
            $dto->totalPagado,
        );
    }
}