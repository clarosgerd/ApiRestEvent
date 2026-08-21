<?php

namespace App\Support;

use App\DTOs\RegistrationDTO;
use App\Models\SesionCongreso;
use App\Support\Taller\ResolverPrecioTallerData;

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

    /**
     * Precio USD fijo, sin tipo de cambio (19/08/2026) — ver
     * brain/PLAN-PRECIO-USD-FIJO-19082026.md. Modo alternativo a
     * `resolver()` para eventos con `usd_precio_fijo=true`: en vez de
     * `grand_total_BOB × tasa`, el USD esperado sale de sumar
     * `categories.price_usd` de cada participante — sin tocar para nada
     * el bookkeeping en BOB (`totals->registration/fee/grandTotal`), que
     * sigue siendo la fuente de verdad de siempre para reportes,
     * liquidaciones, etc. Solo cambia de dónde sale el número que se
     * cobra realmente en la pasarela cuando el pago es en USD.
     *
     * Alcance confirmado con el usuario: categoría/inscripción + talleres
     * (19/08/2026, extendido el mismo día — antes cualquier taller
     * bloqueaba el pago en USD fijo). Souvenirs, donación y el add-on de
     * camiseta siguen sin precio en USD fijo. Para no cobrar de menos en
     * silencio, se rechaza si el participante trae alguno de esos ítems
     * distinto de cero, o si un taller seleccionado no tiene price_usd
     * cargado (ver ResolverPrecioTallerData::unitPriceUsd()).
     */
    public static function resolverPrecioFijo(RegistrationDTO $dto, \App\Models\Evento $evento): array
    {
        $totalUsd = 0.0;
        $totalTalleresUsd = 0.0;

        foreach ($dto->participants as $p) {
            if ($p->donation > 0 || !empty($p->souvenirs) || $p->shirtPrice > 0) {
                throw new \DomainException(
                    'Este evento cobra en USD solo la inscripción y los talleres — sacá souvenirs, camiseta o donación del carrito, o pagá en BOB.'
                );
            }

            $categoria = \App\Models\Category::find((int) $p->category);
            // Precio USD fijo por período (20/08/2026) — antes esto leía
            // $categoria->price_usd directo, ignorando por completo los
            // períodos de precio (que sí aplican del lado BOB, ver
            // validatePrecioCategoria() en CrearInscripcionAction).
            // Resultado real: alguien pagando en USD siempre pagaba lo
            // mismo sin importar el período vigente, mientras alguien
            // pagando en BOB sí veía el precio subir/bajar por fecha. Ver
            // PrecioVigenteData::paraCategoria().
            $precioUsdVigente = $categoria ? PrecioVigenteData::paraCategoria($categoria)['precio_usd'] : null;
            if (!$categoria || $precioUsdVigente === null) {
                throw new \DomainException(
                    'La categoría elegida no tiene precio en USD configurado para este evento. Recargá la página e intentá de nuevo.'
                );
            }

            $totalUsd += $precioUsdVigente;

            foreach ($p->talleres as $tallerSel) {
                $sesion = SesionCongreso::with('taller')
                    ->where('id', $tallerSel->sesionCongresoId)
                    ->where('evento_id', $evento->id)
                    ->first();

                if (!$sesion || !$sesion->taller) {
                    throw new \DomainException('El taller seleccionado no pertenece a este evento.');
                }

                $unitUsd = ResolverPrecioTallerData::unitPriceUsd($sesion->taller, $sesion, $evento);
                if ($unitUsd === null) {
                    throw new \DomainException(
                        "El taller '{$sesion->taller->nombre}' no tiene precio USD configurado para este evento. Recargá la página e intentá de nuevo."
                    );
                }

                $totalUsd += $unitUsd;
                $totalTalleresUsd += $unitUsd;
            }
        }

        // Mismo fee_pct que ya usa el evento — aplicado directo sobre la
        // base USD, sin pasar por BOB (ver validateFeePct() para el
        // camino BOB, que sigue intacto y corre en paralelo a esto).
        // Respeta feeIncluyeTalleres igual que el camino BOB — antes de
        // que los talleres se pudieran cobrar en USD fijo esto no hacía
        // falta (talleres estaba bloqueado en este modo).
        $feeBaseUsd = $totalUsd - ($evento->fee_incluye_talleres ? 0.0 : $totalTalleresUsd);
        $feeUsd = round($feeBaseUsd * (float) $evento->fee_pct, 2);
        $esperado = round($totalUsd + $feeUsd, 2);

        if ($dto->totalPagado === null) {
            throw new \DomainException(
                'Para pagar en USD se requiere total_pagado.'
            );
        }

        $delta = abs($esperado - $dto->totalPagado);
        // Sin tasa de cambio de por medio acá, la única tolerancia real es
        // redondeo — no hace falta el margen por drift de tasa de
        // resolver().
        $tolerancia = 0.02;

        if ($delta > $tolerancia) {
            throw new \DomainException(
                sprintf(
                    'El total_pagado en USD (%.2f) no coincide con el precio fijo esperado (%.2f). Recargá la página e intentá de nuevo.',
                    $dto->totalPagado,
                    $esperado
                )
            );
        }

        return [
            'total_pagado'        => round($dto->totalPagado, 2),
            // Sin tasa en este modo — a propósito, es la señal de que esta
            // inscripción usó precio fijo y no tipo de cambio.
            'tipo_cambio_aplicado' => null,
        ];
    }
}