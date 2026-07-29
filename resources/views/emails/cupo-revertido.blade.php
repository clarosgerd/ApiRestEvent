<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#022858;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#022858;padding:32px 16px;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.15);">

  <tr><td style="background:#607080;padding:28px 32px;text-align:center;">
    <h1 style="color:#ffffff;font-size:22px;margin:0 0 4px;">Registro cancelado</h1>
    <p style="color:rgba(255,255,255,.6);font-size:13px;margin:0;">{{ $registration->evento_nombre }}</p>
  </td></tr>

  <tr><td style="padding:18px 32px;text-align:center;background:#f4f8fb;">
    <p style="color:#607080;font-size:11px;margin:0 0 2px;text-transform:uppercase;letter-spacing:1px;">Número de referencia</p>
    <p style="color:#1a2a3a;font-size:22px;font-weight:800;letter-spacing:4px;margin:0;">{{ $registration->referencia }}</p>
  </td></tr>

  <tr><td style="padding:24px 32px;">
    <p style="font-size:14px;color:#1a2a3a;margin:0 0 12px;">
      Tu registro para <strong>{{ $registration->evento_nombre }}</strong> no fue pagado dentro
      del plazo requerido, por lo que tu cupo fue reasignado y este registro ahora está
      <strong>cancelado</strong>.
    </p>
    <p style="font-size:13px;color:#607080;margin:0;">
      Si crees que esto es un error, por favor contacta al organizador del evento.
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
