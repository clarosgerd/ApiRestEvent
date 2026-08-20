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
@if ($registration->moneda_pago === 'USD')
  {{--
    Cobro en USD (18/08/2026 tipo de cambio, 19/08/2026 precio fijo) — la
    tabla de arriba sigue en Bs a propósito (es el desglose real de
    inscripción/donación/souvenirs/fee, fuente de verdad de siempre). Esta
    fila aparte muestra el monto real que se cobró en dólares, que antes
    no aparecía en el correo para nada — el participante solo veía Bs
    aunque hubiera pagado en USD. Con tipo de cambio se ve la tasa usada;
    con precio fijo (`tipo_cambio_aplicado` null) no hay tasa que mostrar.
  --}}
  <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;color:#1a2a3a;margin-top:8px;border-top:1px solid #e2e8f0;padding-top:8px;">
    <tr>
      <td style="padding:5px 0;font-weight:700;">Total pagado (USD)</td>
      <td style="padding:5px 0;text-align:right;font-weight:700;">
        US${{ number_format((float) $registration->total_pagado, 2) }}
        @if ($registration->tipo_cambio_aplicado)
          <span style="font-weight:400;color:#607080;"> (TC {{ number_format((float) $registration->tipo_cambio_aplicado, 4) }})</span>
        @endif
      </td>
    </tr>
  </table>
@endif
