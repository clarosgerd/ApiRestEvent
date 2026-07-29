<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#022858;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#022858;padding:32px 16px;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.15);">

  <tr><td style="background:#022858;padding:28px 32px;text-align:center;">
    <h1 style="color:#ffffff;font-size:22px;margin:0 0 4px;">Eventos que podrían interesarte</h1>
    <p style="color:rgba(255,255,255,.6);font-size:13px;margin:0;">Hola {{ $persona->nombre }}, según eventos en los que participaste antes</p>
  </td></tr>

  <tr><td style="padding:20px 32px;">
    @foreach ($eventos as $evento)
      <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:12px;">
        <tr>
          <td style="padding:16px;background:#f4f8fb;border-radius:8px;">
            <strong style="color:#022858;font-size:15px;">{{ $evento->nombre }}</strong><br>
            <span style="font-size:13px;color:#607080;line-height:2;">
              @if ($evento->fecha_inicio)
                {{ \Carbon\Carbon::parse($evento->fecha_inicio)->locale('es')->translatedFormat('d \d\e F \d\e Y') }}<br>
              @endif
              {{ $evento->lugar }}
            </span>
          </td>
        </tr>
      </table>
    @endforeach
  </td></tr>

  <tr><td style="padding:16px 32px;text-align:center;background:#f4f8fb;">
    <p style="font-size:11px;color:#607080;margin:0;">
      Recibes este correo porque te registraste antes en eventos similares.<br>
      <a href="{{ $optOutUrl }}" style="color:#607080;">Darme de baja de correos de marketing</a>
    </p>
  </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
