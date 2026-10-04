<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#022858;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#022858;padding:32px 16px;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.15);">

  <tr><td style="background:#1a6fc4;padding:28px 32px;text-align:center;">
    <h1 style="color:#ffffff;font-size:22px;margin:0 0 4px;">Ya sos staff de este evento</h1>
    <p style="color:rgba(255,255,255,.7);font-size:13px;margin:0;">{{ $registration->evento_nombre }}</p>
  </td></tr>

  <tr><td style="padding:24px 32px;">
    <p style="font-size:14px;color:#1a2a3a;margin:0 0 16px;">
      Hola{{ $participante ? ' ' . $participante->nombre : '' }}, tu registro como staff para
      <strong>{{ $registration->evento_nombre }}</strong> ya está confirmado. Podés usar la app de staff para
      descargar la lista completa de participantes y acreditar gente en el evento, incluso sin conexión a
      internet.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f8fb;border-radius:8px;margin:0 0 16px;">
      <tr><td style="padding:16px 20px;">
        <p style="font-size:11px;color:#607080;margin:0 0 4px;text-transform:uppercase;letter-spacing:1px;">Usuario (correo)</p>
        <p style="font-size:15px;color:#1a2a3a;font-weight:700;margin:0 0 12px;">{{ $participante?->correo }}</p>
        <p style="font-size:11px;color:#607080;margin:0 0 4px;text-transform:uppercase;letter-spacing:1px;">Contraseña</p>
        <p style="font-size:15px;color:#1a2a3a;font-weight:700;margin:0;">Tu número de documento ({{ $participante?->numero_documento }})</p>
      </td></tr>
    </table>

    <p style="font-size:13px;color:#607080;margin:0;">
      Antes de ir al evento, abrí la app con conexión a internet al menos una vez para descargar la lista de
      participantes — después podés usarla sin conexión.
    </p>
  </td></tr>

  <tr><td style="padding:16px 32px;text-align:center;background:#f4f8fb;">
    <p style="font-size:11px;color:#607080;margin:0;">
      Referencia: {{ $registration->referencia }}
    </p>
  </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
