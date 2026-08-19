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
