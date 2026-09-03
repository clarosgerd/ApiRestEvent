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
  {{-- Parametrizado (03/09/2026) — el QR embebido como data: URI en el
       cuerpo del correo lo bloquean muchos clientes de correo (Gmail web,
       Outlook, varios webmail/gateways corporativos) y queda como imagen
       rota — bug real reportado por un usuario, "segunda vez" (no era
       casual). Este PDF adjunto (ya lo usaba PagoConfirmadoMail) es la
       vía confiable: dompdf no depende del bloqueo de imágenes del
       cliente de correo. InscripcionPendienteMail (pago pendiente) ahora
       también lo adjunta — antes no tenía ningún PDF, solo el QR inline
       que fallaba. Título/estado/pie de página con default = el mismo
       texto de siempre para no cambiar nada en PagoConfirmadoMail. --}}
  <tr><td style="background:#022858;padding:20px 24px;text-align:center;">
    <span style="color:#ffffff;font-size:18px;font-weight:bold;">{{ $pdfTitle ?? 'Entrada electrónica' }} — {{ $registration->evento_nombre }}</span>
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
        <td style="padding:4px 0;color:{{ $statusColor ?? '#258f36' }};font-weight:bold;">{{ $statusLabel ?? '✓ Pagado' }}</td>
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
    {{-- Precio USD fijo (24/08/2026) — mismo criterio que emails/confirmacion.blade.php. --}}
    <span style="color:#ffffff;font-size:24px;font-weight:bold;">
      @if ($registration->moneda_pago === 'USD')
        ${{ number_format((float) $registration->total_pagado, 2) }}
      @else
        Bs{{ number_format((float) $registration->totals->grand_total, 2) }}
      @endif
    </span>
  </td></tr>

  @if (!empty($qrImage))
  <tr><td style="padding:16px 24px;text-align:center;">
    <img src="data:image/png;base64,{{ $qrImage }}" alt="QR de referencia" width="120" height="120">
  </td></tr>
  @endif

  <tr><td style="padding:12px 24px;text-align:center;color:#607080;font-size:10px;">
    {{ $pdfFooterMsg ?? 'Guarda esta entrada como comprobante de pago' }} · {{ $registration->referencia }}
  </td></tr>
</table>
</body>
</html>
