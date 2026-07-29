<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; color: #1a2a3a; font-size: 12px; }
  table { width: 100%; border-collapse: collapse; }
</style>
</head>
<body>
<table>
  <tr><td style="background:#022858;padding:20px 24px;text-align:center;">
    <span style="color:#ffffff;font-size:18px;font-weight:bold;">Entrada electrónica — {{ $registration->evento_nombre }}</span>
  </td></tr>

  <tr><td style="background:#00bad2;padding:14px 24px;text-align:center;">
    <span style="color:#ffffff;font-size:11px;text-transform:uppercase;letter-spacing:1px;">Número de referencia</span><br>
    <span style="color:#ffffff;font-size:22px;font-weight:bold;letter-spacing:3px;">{{ $registration->referencia }}</span>
  </td></tr>

  <tr><td style="padding:16px 24px;">
    <table>
      <tr>
        <td style="padding:4px 0;color:#607080;width:130px;">Evento</td>
        <td style="padding:4px 0;font-weight:bold;">{{ $registration->evento_nombre }}</td>
      </tr>
      <tr>
        <td style="padding:4px 0;color:#607080;">Fecha y hora</td>
        <td style="padding:4px 0;">{{ $evento?->fecha_inicio ? \Carbon\Carbon::parse($evento->fecha_inicio)->locale('es')->translatedFormat('d \d\e F \d\e Y') : '' }} · {{ $evento->localTime ?? '' }}</td>
      </tr>
      <tr>
        <td style="padding:4px 0;color:#607080;">Lugar</td>
        <td style="padding:4px 0;">{{ $evento->lugar ?? '' }}</td>
      </tr>
      <tr>
        <td style="padding:4px 0;color:#607080;">Método de pago</td>
        <td style="padding:4px 0;">{{ $payLabel }}</td>
      </tr>
      <tr>
        <td style="padding:4px 0;color:#607080;">Estado</td>
        <td style="padding:4px 0;color:#258f36;font-weight:bold;">✓ Pagado</td>
      </tr>
    </table>
  </td></tr>

  <tr><td style="padding:12px 24px;">
    <div style="font-size:11px;font-weight:bold;color:#022858;text-transform:uppercase;margin-bottom:8px;">Participantes</div>
    <table>
      @include('emails.partials.participantes', ['registration' => $registration])
    </table>
  </td></tr>

  <tr><td style="padding:12px 24px;">
    <div style="font-size:11px;font-weight:bold;color:#022858;text-transform:uppercase;margin-bottom:8px;">Resumen de pago</div>
    @include('emails.partials.totales', ['registration' => $registration])
  </td></tr>

  <tr><td style="background:#022858;padding:16px 24px;text-align:center;">
    <span style="color:#ffffff;font-size:11px;text-transform:uppercase;">Total general</span><br>
    <span style="color:#ffffff;font-size:24px;font-weight:bold;">Bs{{ number_format((float) $registration->totals->grand_total, 2) }}</span>
  </td></tr>

  @if (!empty($qrImage))
  <tr><td style="padding:16px 24px;text-align:center;">
    <img src="data:image/png;base64,{{ $qrImage }}" alt="QR de referencia" width="120" height="120">
  </td></tr>
  @endif

  <tr><td style="padding:12px 24px;text-align:center;color:#607080;font-size:10px;">
    Guarda esta entrada como comprobante de pago · {{ $registration->referencia }}
  </td></tr>
</table>
</body>
</html>
