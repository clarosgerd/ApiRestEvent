<?php

namespace Tests\Unit;

use App\Support\CurrencyResolverData;
use Tests\TestCase;

/**
 * Cobertura de las reglas de snapshot de moneda/tasa al confirmar un
 * pago (18/08/2026) — ver
 * brain/PLAN-INSCRIPCION-BOB-USD-IMPLEMENTACION.md.
 *
 * El backend NUNCA consulta la API de tipo de cambio: confía el snapshot
 * que el frontend capturó al confirmar, y verifica que sea consistente
 * con el grand_total BOB dentro de tolerancia.
 */
class CurrencyResolverDataTest extends TestCase
{
    public function test_bob_devuelve_null_en_total_y_tasa(): void
    {
        // En BOB el grand_total vive en RegistrationTotal.grand_total —
        // acá no se duplica. La regla es "snapshot USD solo si hay USD".
        $r = CurrencyResolverData::resolver(100.0, 'BOB', null, null);
        $this->assertNull($r['total_pagado']);
        $this->assertNull($r['tipo_cambio_aplicado']);
    }

    public function test_bob_default_explícito_devuelve_null(): void
    {
        $r = CurrencyResolverData::resolver(250.0, null, null, null);
        $this->assertNull($r['total_pagado']);
        $this->assertNull($r['tipo_cambio_aplicado']);
    }

    public function test_usd_con_snapshot_exacto_pasa(): void
    {
        // 100 BOB * 0.144 = 14.40 USD
        $r = CurrencyResolverData::resolver(100.0, 'USD', 0.144, 14.40);
        $this->assertSame(14.40, $r['total_pagado']);
        $this->assertSame(0.144, $r['tipo_cambio_aplicado']);
    }

    public function test_usd_con_drift_dentro_de_tolerancia_pasa(): void
    {
        // 100 BOB * 0.144 = 14.40; toleramos ±2% (≈ ±0.288) — 14.50 OK
        $r = CurrencyResolverData::resolver(100.0, 'USD', 0.144, 14.50);
        $this->assertSame(14.50, $r['total_pagado']);
    }

    public function test_usd_con_drift_fuera_de_tolerancia_rechaza(): void
    {
        // 100 BOB * 0.144 = 14.40; tolerancia ±0.288 — 15.00 fuera (delta 0.60).
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/no coincide con el grand_total/');
        CurrencyResolverData::resolver(100.0, 'USD', 0.144, 15.00);
    }

    public function test_usd_sin_tasa_rechaza(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/snapshot de tasa/');
        CurrencyResolverData::resolver(100.0, 'USD', null, 14.40);
    }

    public function test_usd_sin_total_pagado_rechaza(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/snapshot de tasa/');
        CurrencyResolverData::resolver(100.0, 'USD', 0.144, null);
    }

    public function test_usd_con_tasa_cero_o_negativa_rechaza(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/mayor a cero/');
        CurrencyResolverData::resolver(100.0, 'USD', 0, 14.40);

        $this->expectException(\DomainException::class);
        CurrencyResolverData::resolver(100.0, 'USD', -0.1, 14.40);
    }

    public function test_moneda_no_soportada_rechaza(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/Moneda de cobro no soportada/');
        CurrencyResolverData::resolver(100.0, 'EUR', 0.1, 10.0);
    }

    public function test_total_pequeno_con_redondeo_pasa_por_tolerancia_minima(): void
    {
        // Para totales chicos la tolerancia absoluta mínima es $0.05
        // (cubrir redondeo a 2 decimales incluso cuando el % sea sub-centavo).
        // 1 BOB * 0.144 = 0.144; tolerancia = max(0.05, 0.00288) = 0.05.
        // Mandamos 0.20 (delta 0.056, >0.05) — RECHAZA.
        $this->expectException(\DomainException::class);
        CurrencyResolverData::resolver(1.0, 'USD', 0.144, 0.20);

        // Pero 0.19 (delta 0.046) — PASA.
        $r = CurrencyResolverData::resolver(1.0, 'USD', 0.144, 0.19);
        $this->assertSame(0.19, $r['total_pagado']);
    }
}