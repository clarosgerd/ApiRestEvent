<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#022858;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#022858;padding:32px 16px;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.15);">

  <tr><td style="background:#00bad2;padding:28px 32px;text-align:center;">
    <h1 style="color:#ffffff;font-size:22px;margin:0 0 4px;">Tu acceso de expositor</h1>
    <p style="color:rgba(255,255,255,.85);font-size:13px;margin:0;">{{ $evento->nombre ?? '' }}</p>
  </td></tr>

  <tr><td style="padding:24px 32px 8px;">
    <p style="font-size:14px;color:#1a2a3a;margin:0 0 12px;">
      Hola, <strong>{{ $cuenta->nombre }}</strong>. Tu pago fue confirmado y ya tienes
      habilitada la cuenta de tu empresa para capturar contactos (leads) de los
      asistentes que visiten tu stand.
    </p>
    @if ($categoria)
      <p style="font-size:13px;color:#607080;margin:0 0 4px;">Stand contratado: <strong style="color:#1a2a3a;">{{ $categoria }}</strong></p>
    @endif
  </td></tr>

  <tr><td style="padding:8px 32px;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f8fb;border-radius:8px;">
      <tr><td style="padding:16px 20px;">
        <p style="color:#607080;font-size:11px;margin:0 0 2px;text-transform:uppercase;letter-spacing:1px;">Usuario</p>
        <p style="color:#1a2a3a;font-size:16px;font-weight:700;margin:0 0 12px;">{{ $cuenta->email }}</p>
        <p style="color:#607080;font-size:11px;margin:0 0 2px;text-transform:uppercase;letter-spacing:1px;">Contraseña</p>
        <p style="color:#1a2a3a;font-size:20px;font-weight:800;letter-spacing:2px;margin:0;font-family:Consolas,'Courier New',monospace;">{{ $passwordPlano }}</p>
      </td></tr>
    </table>
    <p style="font-size:12px;color:#607080;margin:10px 0 0;">
      Todo tu equipo puede usar este mismo usuario y contraseña en distintos celulares.
      Guárdalos en un lugar seguro y no los compartas fuera de tu empresa.
    </p>
  </td></tr>

  <tr><td style="padding:16px 32px 8px;">
    <p style="font-size:14px;color:#1a2a3a;margin:0 0 10px;"><strong>Descarga la app de escaneo</strong></p>
    @if ($appUrlAndroid || $appUrlIos)
      <p style="margin:0;">
        @if ($appUrlAndroid)
          <a href="{{ $appUrlAndroid }}" style="display:inline-block;background:#022858;color:#ffffff;text-decoration:none;font-size:13px;font-weight:700;padding:10px 18px;border-radius:6px;margin:0 8px 8px 0;">Android</a>
        @endif
        @if ($appUrlIos)
          <a href="{{ $appUrlIos }}" style="display:inline-block;background:#022858;color:#ffffff;text-decoration:none;font-size:13px;font-weight:700;padding:10px 18px;border-radius:6px;margin:0 8px 8px 0;">iPhone (iOS)</a>
        @endif
      </p>
    @else
      <p style="font-size:13px;color:#607080;margin:0;">
        El organizador te enviará el link de descarga de la app antes del evento.
      </p>
    @endif
  </td></tr>

  @if ($instrucciones)
    <tr><td style="padding:8px 32px;">
      <p style="font-size:13px;color:#1a2a3a;margin:0;white-space:pre-line;">{{ $instrucciones }}</p>
    </td></tr>
  @endif

  @if ($dashboardUrl)
    <tr><td style="padding:8px 32px;">
      <p style="font-size:13px;color:#1a2a3a;margin:0;">
        También puedes ver los contactos capturados y exportarlos desde tu panel:
        <a href="{{ $dashboardUrl }}" style="color:#00bad2;">{{ $dashboardUrl }}</a>
      </p>
    </td></tr>
  @endif

  <tr><td style="padding:16px 32px;text-align:center;background:#f4f8fb;margin-top:16px;">
    <p style="font-size:11px;color:#607080;margin:0;">
      Este correo contiene tus credenciales de acceso. Si no reconoces esta inscripción, responde al organizador del evento.
    </p>
  </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
