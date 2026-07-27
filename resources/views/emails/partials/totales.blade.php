@php($totals = $registration->totals)
<table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;color:#1a2a3a;">
  <tr><td style="padding:5px 0;color:#607080;">Inscription</td><td style="padding:5px 0;text-align:right;">Bs{{ number_format((float) $totals->inscripcion, 2) }}</td></tr>
  <tr><td style="padding:5px 0;color:#607080;">Donation</td><td style="padding:5px 0;text-align:right;">Bs{{ number_format((float) $totals->donacion, 2) }}</td></tr>
  <tr><td style="padding:5px 0;color:#607080;">Souvenirs</td><td style="padding:5px 0;text-align:right;">Bs{{ number_format((float) $totals->souvenirs, 2) }}</td></tr>
  <tr><td style="padding:5px 0;color:#607080;">Service Fee (5%)</td><td style="padding:5px 0;text-align:right;">Bs{{ number_format((float) $totals->fee, 2) }}</td></tr>
  @if ((float) $totals->descuento > 0)
    <tr><td style="padding:5px 0;color:#258f36;">Discount</td><td style="padding:5px 0;text-align:right;color:#258f36;">-Bs{{ number_format((float) $totals->descuento, 2) }}</td></tr>
  @endif
  @if ((float) $totals->descuento_registrante > 0)
    <tr><td style="padding:5px 0;color:#258f36;">Group discount</td><td style="padding:5px 0;text-align:right;color:#258f36;">-Bs{{ number_format((float) $totals->descuento_registrante, 2) }}</td></tr>
  @endif
</table>
