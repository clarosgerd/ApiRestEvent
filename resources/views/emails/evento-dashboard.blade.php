<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#022858;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#022858;padding:32px 16px;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.15);">

  <tr><td style="background:#022858;padding:28px 32px;text-align:center;">
    <h1 style="color:#ffffff;font-size:22px;margin:0 0 4px;">Dashboard de inscripciones</h1>
    <p style="color:rgba(255,255,255,.6);font-size:13px;margin:0;">{{ $evento->nombre }}</p>
  </td></tr>

  <tr><td style="padding:24px 32px;">
    <p style="font-size:14px;color:#1a2a3a;margin:0 0 16px;">
      Acá tenés el link privado para revisar el estado de las inscripciones de
      <strong>{{ $evento->nombre }}</strong>: pagadas, pendientes, canceladas y fallidas,
      por categoría y por tipo de formulario.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;color:#1a2a3a;margin-bottom:20px;">
      <tr>
        <td style="padding:6px 0;color:#607080;width:130px;">Evento</td>
        <td style="padding:6px 0;font-weight:600;">{{ $evento->nombre }}</td>
      </tr>
      <tr>
        <td style="padding:6px 0;color:#607080;">Fecha y hora</td>
        <td style="padding:6px 0;">{{ \Carbon\Carbon::parse($evento->fecha_inicio)->locale('es')->translatedFormat('d \d\e F \d\e Y') }} · {{ $evento->localTime ?? '' }}</td>
      </tr>
      <tr>
        <td style="padding:6px 0;color:#607080;">Lugar</td>
        <td style="padding:6px 0;">{{ $evento->direccion ?? $evento->lugar ?? '' }}</td>
      </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0">
      <tr><td align="center" style="padding-bottom:{{ $deliveryUrl ? '12px' : '0' }};">
        <a href="{{ $dashboardUrl }}"
           style="display:inline-block;background:#00bad2;color:#ffffff;font-weight:700;font-size:14px;
                  text-decoration:none;padding:14px 28px;border-radius:8px;">
          Ver dashboard de inscripciones →
        </a>
      </td></tr>
      @if($deliveryUrl)
      <tr><td align="center">
        <a href="{{ $deliveryUrl }}"
           style="display:inline-block;background:#022858;color:#ffffff;font-weight:700;font-size:14px;
                  text-decoration:none;padding:14px 28px;border-radius:8px;">
          Ver dashboard de delivery de kits →
        </a>
      </td></tr>
      @endif
    </table>

    <p style="font-size:11px;color:#94a3b8;margin:18px 0 0;text-align:center;">
      {{ $deliveryUrl ? 'Estos links son privados — no los compartas.' : 'Este link es privado — no lo compartas.' }}
    </p>
  </td></tr>

  <tr><td style="padding:16px 32px;text-align:center;background:#f4f8fb;">
    <p style="font-size:11px;color:#607080;margin:0;">Evento #{{ $evento->id }}</p>
  </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
