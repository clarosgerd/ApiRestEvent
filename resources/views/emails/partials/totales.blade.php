@php
    $totals = $registration->totals;
    // Cargo de servicio (11/08/2026) — antes "(5%)" hardcodeado. Se
    // calcula el % efectivo desde lo ya guardado (fee/inscripción) en
    // vez de leer evento.fee_pct: así el correo/PDF de una inscripción
    // vieja sigue mostrando el % que realmente se cobró ese día, aunque
    // el organizador haya cambiado el % del evento después. Ver
    // PRD-cargo-servicio-por-evento.md.
    $feePctEfectivo = (float) $totals->inscripcion > 0
        ? ((float) $totals->fee / (float) $totals->inscripcion) * 100
        : 5;
    $feePctTexto = rtrim(rtrim(number_format($feePctEfectivo, 1), '0'), '.');
@endphp
@if ($registration->moneda_pago === 'USD')
  {{--
    Pago pendiente USD / precio fijo (24/08/2026) — pedido explícito del
    usuario: nada de Bs acá, ni como referencia. Se recalcula el desglose
    en USD desde los mismos datos que usa el backend para total_pagado
    (CurrencyResolverData::resolverPrecioFijo(): categoría.price_usd +
    sesión/taller.price_usd), en vez de convertir el total en Bs con una
    tasa — este modo es precio fijo, no tiene tasa de cambio.
  --}}
  @php
    $categoriasPorId = $registration->evento?->categories?->keyBy(fn ($c) => (string) $c->id) ?? collect();
    $inscripcionUsd = 0.0;
    $tallerUsd = 0.0;
    foreach ($registration->participants as $p) {
        $cat = $categoriasPorId->get($p->categoria);
        $inscripcionUsd += (float) ($cat->price_usd ?? 0);
        foreach ($p->talleresSesiones as $ts) {
            $tallerUsd += (float) ($ts->sesionCongreso->price_usd ?? $ts->taller->price_usd ?? 0);
        }
    }
    // Cargo de servicio como resto, no recalculado con fee_pct acá — así
    // el desglose siempre suma exacto contra total_pagado (fuente de
    // verdad, ya validado server-side), sin duplicar la lógica de
    // fee_incluye_talleres.
    $cargoUsd = round((float) $registration->total_pagado - $inscripcionUsd - $tallerUsd, 2);
  @endphp
  <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;color:#1a2a3a;">
    <tr><td style="padding:5px 0;color:#607080;">Inscripción</td><td style="padding:5px 0;text-align:right;">US${{ number_format($inscripcionUsd, 2) }}</td></tr>
    @if ($tallerUsd > 0)
      <tr><td style="padding:5px 0;color:#607080;">Taller</td><td style="padding:5px 0;text-align:right;">US${{ number_format($tallerUsd, 2) }}</td></tr>
    @endif
    @if ($cargoUsd > 0)
      <tr><td style="padding:5px 0;color:#607080;">Cargo de servicio</td><td style="padding:5px 0;text-align:right;">US${{ number_format($cargoUsd, 2) }}</td></tr>
    @endif
    <tr><td style="padding:8px 0 0;font-weight:700;border-top:1px solid #e2e8f0;">Total pagado (USD)</td><td style="padding:8px 0 0;text-align:right;font-weight:700;border-top:1px solid #e2e8f0;">US${{ number_format((float) $registration->total_pagado, 2) }}</td></tr>
  </table>
@else
  <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;color:#1a2a3a;">
    <tr><td style="padding:5px 0;color:#607080;">Inscripción</td><td style="padding:5px 0;text-align:right;">Bs{{ number_format((float) $totals->inscripcion, 2) }}</td></tr>
    <tr><td style="padding:5px 0;color:#607080;">Donación</td><td style="padding:5px 0;text-align:right;">Bs{{ number_format((float) $totals->donacion, 2) }}</td></tr>
    <tr><td style="padding:5px 0;color:#607080;">Taller</td><td style="padding:5px 0;text-align:right;">Bs{{ number_format((float) $totals->souvenirs, 2) }}</td></tr>
    <tr><td style="padding:5px 0;color:#607080;">Cargo de servicio </td><td style="padding:5px 0;text-align:right;">Bs{{ number_format((float) $totals->fee, 2) }}</td></tr>
    @if ((float) $totals->descuento > 0)
      <tr><td style="padding:5px 0;color:#258f36;">Descuento</td><td style="padding:5px 0;text-align:right;color:#258f36;">-Bs{{ number_format((float) $totals->descuento, 2) }}</td></tr>
    @endif
    @if ((float) $totals->descuento_registrante > 0)
      <tr><td style="padding:5px 0;color:#258f36;">Descuento de grupo</td><td style="padding:5px 0;text-align:right;color:#258f36;">-Bs{{ number_format((float) $totals->descuento_registrante, 2) }}</td></tr>
    @endif
  </table>
@endif
