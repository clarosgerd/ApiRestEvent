<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#022858;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#022858;padding:32px 16px;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.15);">

  <tr><td style="background:#022858;padding:28px 32px;text-align:center;">
    <h1 style="color:#ffffff;font-size:22px;margin:0 0 4px;">Certificado de asistencia</h1>
    <p style="color:rgba(255,255,255,.6);font-size:13px;margin:0;">{{ $evento->nombre }}</p>
  </td></tr>

  <tr><td style="padding:24px 32px;">
    <p style="font-size:14px;color:#1a2a3a;margin:0 0 12px;">
      Hola {{ $participante->nombre }}, gracias por participar en {{ $evento->nombre }}. Adjuntamos tu certificado
      de asistencia en PDF, con el detalle de las sesiones a las que asististe.
    </p>

    <p style="font-size:12px;font-weight:700;color:#022858;text-transform:uppercase;margin:20px 0 10px;">
      Sesiones asistidas
    </p>
    <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;color:#1a2a3a;">
      @foreach ($sesiones as $sesion)
        <tr>
          <td style="padding:6px 0;border-bottom:1px solid #eef2f6;">
            <strong>{{ $sesion->titulo }}</strong>
            @if ($sesion->sala)
              <span style="color:#607080;"> · {{ $sesion->sala }}</span>
            @endif
            <br>
            <span style="color:#607080;font-size:12px;">
              {{ \Carbon\Carbon::parse($sesion->fecha)->locale('es')->translatedFormat('d \d\e F \d\e Y') }}
            </span>
          </td>
        </tr>
      @endforeach
    </table>
  </td></tr>

  <tr><td style="padding:16px 32px;text-align:center;background:#f4f8fb;">
    <p style="font-size:11px;color:#607080;margin:0;">
      Este certificado se generó automáticamente al cierre del evento.
    </p>
  </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
