<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#022858;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#022858;padding:32px 16px;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.15);">

  <tr><td style="background:#022858;padding:28px 32px;text-align:center;">
    <h1 style="color:#ffffff;font-size:22px;margin:0 0 4px;">Recordatorio de recojo de kit</h1>
    <p style="color:rgba(255,255,255,.6);font-size:13px;margin:0;">{{ $registration->evento_nombre }}</p>
  </td></tr>

  <tr><td style="background:#00bad2;padding:18px 32px;text-align:center;">
    <p style="color:rgba(255,255,255,.8);font-size:11px;margin:0 0 2px;text-transform:uppercase;letter-spacing:1px;">Número de referencia</p>
    <p style="color:#ffffff;font-size:26px;font-weight:800;letter-spacing:4px;margin:0;">{{ $registration->referencia }}</p>
  </td></tr>

  <tr><td style="padding:24px 32px;">
    <p style="font-size:14px;color:#1a2a3a;margin:0 0 12px;">
      ¡Tu evento se acerca! No olvides recoger tu kit de carrera
      (número de pecho, camiseta y recuerdos) antes del evento.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;color:#1a2a3a;">
      <tr>
        <td style="padding:6px 0;color:#607080;width:130px;">Evento</td>
        <td style="padding:6px 0;font-weight:600;">{{ $registration->evento_nombre }}</td>
      </tr>
      <tr>
        <td style="padding:6px 0;color:#607080;">Fecha y hora</td>
        <td style="padding:6px 0;">{{ $evento?->fecha_inicio ? \Carbon\Carbon::parse($evento->fecha_inicio)->locale('es')->translatedFormat('d \d\e F \d\e Y') : '' }} · {{ $evento->localTime ?? '' }}</td>
      </tr>
      <tr>
        <td style="padding:6px 0;color:#607080;">Lugar</td>
        <td style="padding:6px 0;">{{ $evento->lugar ?? '' }}</td>
      </tr>
    </table>
  </td></tr>

  <tr><td style="padding:0 32px;"><hr style="border:none;border-top:2px dashed #d0dce8;margin:0;"></td></tr>

  <tr><td style="padding:20px 32px;">
    <p style="font-size:12px;font-weight:700;color:#022858;text-transform:uppercase;margin:0 0 12px;">Artículos a recoger</p>
    <table width="100%" cellpadding="0" cellspacing="0">
      @include('emails.partials.participantes', ['registration' => $registration])
    </table>
  </td></tr>

  <tr><td style="padding:16px 32px;text-align:center;background:#f4f8fb;">
    <p style="font-size:11px;color:#607080;margin:0;">
      Consulta el horario de entrega del organizador para el lugar y horario exactos.<br>
      Referencia: {{ $registration->referencia }}
    </p>
  </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
