<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#022858;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#022858;padding:32px 16px;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.15);">

  <tr><td style="background:#1f9d55;padding:28px 32px;text-align:center;">
    <h1 style="color:#ffffff;font-size:22px;margin:0 0 4px;">¡Ya hay lugar!</h1>
    <p style="color:rgba(255,255,255,.75);font-size:13px;margin:0;">{{ $evento->nombre }}</p>
  </td></tr>

  <tr><td style="padding:24px 32px;">
    <p style="font-size:14px;color:#1a2a3a;margin:0 0 12px;">
      Hola {{ $entry->nombre }}, te habías anotado en la lista de espera de
      <strong>{{ $formType->name }}</strong>
      @if ($entry->talla)
        (talla {{ $entry->talla }})
      @endif
      y ahora se liberó un lugar.
    </p>
    <p style="font-size:14px;color:#1a2a3a;margin:0 0 12px;">
      Volvé a la página de inscripción del evento para completar tu registro —
      el lugar no queda reservado, se asigna a quien se inscriba primero.
    </p>
    <p style="font-size:13px;color:#607080;margin:0;">
      Si ya no te interesa, no necesitás hacer nada más.
    </p>
  </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
