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
    <span style="color:#ffffff;font-size:18px;font-weight:bold;">E-Ticket — {{ $registration->evento_nombre }}</span>
  </td></tr>

  <tr><td style="background:#00bad2;padding:14px 24px;text-align:center;">
    <span style="color:#ffffff;font-size:11px;text-transform:uppercase;letter-spacing:1px;">Reference Number</span><br>
    <span style="color:#ffffff;font-size:22px;font-weight:bold;letter-spacing:3px;">{{ $registration->referencia }}</span>
  </td></tr>

  <tr><td style="padding:16px 24px;">
    <table>
      <tr>
        <td style="padding:4px 0;color:#607080;width:130px;">Event</td>
        <td style="padding:4px 0;font-weight:bold;">{{ $registration->evento_nombre }}</td>
      </tr>
      <tr>
        <td style="padding:4px 0;color:#607080;">Date &amp; Time</td>
        <td style="padding:4px 0;">{{ $evento?->fecha_inicio ? \Carbon\Carbon::parse($evento->fecha_inicio)->format('F j, Y') : '' }} · {{ $evento->localTime ?? '' }}</td>
      </tr>
      <tr>
        <td style="padding:4px 0;color:#607080;">Location</td>
        <td style="padding:4px 0;">{{ $evento->lugar ?? '' }}</td>
      </tr>
      <tr>
        <td style="padding:4px 0;color:#607080;">Payment Method</td>
        <td style="padding:4px 0;">{{ $payLabel }}</td>
      </tr>
      <tr>
        <td style="padding:4px 0;color:#607080;">Status</td>
        <td style="padding:4px 0;color:#258f36;font-weight:bold;">✓ Paid</td>
      </tr>
    </table>
  </td></tr>

  <tr><td style="padding:12px 24px;">
    <div style="font-size:11px;font-weight:bold;color:#022858;text-transform:uppercase;margin-bottom:8px;">Participants</div>
    <table>
      @include('emails.partials.participantes', ['registration' => $registration])
    </table>
  </td></tr>

  <tr><td style="padding:12px 24px;">
    <div style="font-size:11px;font-weight:bold;color:#022858;text-transform:uppercase;margin-bottom:8px;">Payment Summary</div>
    @include('emails.partials.totales', ['registration' => $registration])
  </td></tr>

  <tr><td style="background:#022858;padding:16px 24px;text-align:center;">
    <span style="color:#ffffff;font-size:11px;text-transform:uppercase;">Grand Total</span><br>
    <span style="color:#ffffff;font-size:24px;font-weight:bold;">Bs{{ number_format((float) $registration->totals->grand_total, 2) }}</span>
  </td></tr>

  <tr><td style="padding:12px 24px;text-align:center;color:#607080;font-size:10px;">
    Keep this e-ticket as proof of payment · {{ $registration->referencia }}
  </td></tr>
</table>
</body>
</html>
