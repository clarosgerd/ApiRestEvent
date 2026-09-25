<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:24px 16px;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:10px;overflow:hidden;">

  <tr><td style="padding:28px 32px 8px;">
    {{-- Texto de la empresa: siempre escapado ({{ }}), nunca {!! !!}. --}}
    @foreach ($parrafos as $parrafo)
      <p style="font-size:15px;line-height:1.55;color:#1a2a3a;margin:0 0 14px;">{!! nl2br(e($parrafo)) !!}</p>
    @endforeach

    @if ($catalogoUrl)
      <p style="margin:6px 0 14px;">
        <a href="{{ $catalogoUrl }}" style="display:inline-block;background:#022858;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;padding:10px 18px;border-radius:6px;">Ver información de {{ $empresa }}</a>
      </p>
    @endif
  </td></tr>

  <tr><td style="padding:16px 32px 24px;border-top:1px solid #e3e8ee;">
    <p style="font-size:12px;line-height:1.5;color:#607080;margin:0;">
      Recibes este mensaje porque visitaste el stand de <strong>{{ $empresa }}</strong>
      @if ($evento) en <strong>{{ $evento }}</strong>@endif
      y la empresa registró tu visita. Este correo lo envía {{ $empresa }} a través de Inscrito;
      si respondes, tu respuesta le llega directamente a la empresa.
    </p>
    <p style="font-size:12px;line-height:1.5;color:#607080;margin:8px 0 0;">
      ¿No quieres recibir más correos de seguimiento de expositores?
      <a href="{{ $bajaUrl }}" style="color:#022858;">Darme de baja</a>.
    </p>
  </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
