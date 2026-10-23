<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#022858;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#022858;padding:32px 16px;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.15);">

  <tr><td style="background:#022858;padding:28px 32px;text-align:center;">
    <h1 style="color:#ffffff;font-size:22px;margin:0 0 4px;">¡Estamos a pocos días! 🎉</h1>
    <p style="color:rgba(255,255,255,.6);font-size:13px;margin:0;">{{ $eventoNombre }}</p>
  </td></tr>

  <tr><td style="padding:28px 32px;">
    <p style="font-size:14px;color:#1a2a3a;margin:0 0 16px;">Estimado/a Participante:</p>
    <p style="font-size:14px;color:#1a2a3a;margin:0 0 16px;line-height:1.6;">
       Vamos por el 2do Día de nuestro congreso
      ¡Y mañana es la gran fiesta del {{ $eventoNombre }}! Queremos que no te pierdas nada, por eso
      te invitamos a pasar este sábado <strong>{{ $fecha }}</strong> a recoger tu manilla de acceso
      (personal e intransferible).
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;color:#1a2a3a;margin:0 0 16px;">
      <tr>
        <td style="padding:6px 0;color:#607080;width:110px;">Dónde</td>
        <td style="padding:6px 0;font-weight:600;">{{ $lugar }}</td>
      </tr>
      <tr>
        <td style="padding:6px 0;color:#607080;">Horario</td>
        <td style="padding:6px 0;font-weight:600;">{{ $horaInicio }} a {{ $horaCierre }}</td>
      </tr>
    </table>

    <p style="font-size:14px;color:#1a2a3a;margin:0 0 10px;line-height:1.6;">
      Recuerda que, para ingresar a la fiesta, es indispensable presentar:
    </p>
    <ul style="font-size:14px;color:#1a2a3a;margin:0 0 16px;padding-left:20px;line-height:1.8;">
      <li>Tu manilla</li>
      <li>Tu gafete con su respectivo QR</li>
    </ul>

    <p style="font-size:14px;color:#1a2a3a;margin:0 0 16px;line-height:1.6;">
      Sin estos dos elementos no podremos permitir el acceso, así que te pedimos tenerlos contigo el día del evento.
    </p>

    <p style="font-size:14px;color:#1a2a3a;margin:0 0 4px;">¡Te esperamos para celebrar juntos!</p>
    <p style="font-size:14px;color:#1a2a3a;margin:0;">Un cordial saludo,<br>Comité organizador</p>
  </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
