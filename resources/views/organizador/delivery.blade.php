<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Delivery — {{ $evento->nombre }}</title>
  <style>
    :root { --primary:#00bad2; --secondary:#022858; --success:#258f36; --danger:#c0392b; --warning:#b07d00; --border:#e2e8f0; --muted:#64748b; }
    * { box-sizing: border-box; }
    body { font-family: -apple-system, Segoe UI, Roboto, Arial, sans-serif; background:#f1f5f9; color:#1e293b; margin:0; padding:24px; }
    .wrap { max-width: 1100px; margin: 0 auto; }
    h1 { color: var(--secondary); font-size: 22px; margin: 0 0 4px; }
    .subtitle { color: var(--muted); font-size: 14px; margin: 0 0 24px; }
    .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 14px; margin-bottom: 28px; }
    .card { background: #fff; border-radius: 10px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.08); text-align: center; }
    .card .num { font-size: 26px; font-weight: 700; color: var(--secondary); }
    .card .label { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: .4px; margin-top: 4px; }
    .card.pendiente .num  { color: var(--warning); }
    .card.confirmado .num { color: var(--primary); }
    .card.entregado .num  { color: var(--success); }
    .card.cancelado .num  { color: var(--danger); }
    table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.08); margin-bottom: 28px; }
    th, td { padding: 10px 14px; text-align: left; font-size: 13px; border-bottom: 1px solid var(--border); vertical-align: top; }
    th { background: var(--secondary); color: #fff; font-weight: 600; }
    tr:last-child td { border-bottom: none; }
    .section-title { font-size: 15px; font-weight: 700; color: var(--secondary); margin: 0 0 10px; }
    .muted { color: var(--muted); font-size: 11px; }
    .badge {
      display: inline-block; padding: 3px 10px; border-radius: 20px;
      font-size: 11px; font-weight: 700; text-transform: uppercase;
    }
    .badge.pendiente  { background: #fff8e6; color: var(--warning); }
    .badge.confirmado { background: #e6f7fa; color: var(--primary); }
    .badge.entregado  { background: #e6f9f0; color: var(--success); }
    .badge.cancelado  { background: #fdecea; color: var(--danger); }
    .btn {
      display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
      background: var(--primary); color: #fff; font-size: 12px; font-weight: 600;
      border-radius: 8px; padding: 6px 12px; margin: 2px 4px 2px 0;
    }
    .btn:hover { background: #008fa0; }
    .btn.secondary { background: var(--light, #e2e8f0); color: var(--secondary); }
    .btn.danger { background: var(--danger); }
    .btn.danger:hover { background: #a83226; }
    .downloads { background: #fff; border-radius: 10px; padding: 18px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>{{ $evento->nombre }}</h1>
    <p class="subtitle">Delivery de kits · {{ $evento->direccion }} · {{ $evento->fecha_inicio }}</p>

    <div class="cards">
      <div class="card"><div class="num">{{ $resumen['total'] }}</div><div class="label">Total delivery</div></div>
      <div class="card pendiente"><div class="num">{{ $resumen['pendiente'] }}</div><div class="label">Pendientes</div></div>
      <div class="card confirmado"><div class="num">{{ $resumen['confirmado'] }}</div><div class="label">Confirmados</div></div>
      <div class="card entregado"><div class="num">{{ $resumen['entregado'] }}</div><div class="label">Entregados</div></div>
      <div class="card cancelado"><div class="num">{{ $resumen['cancelado'] }}</div><div class="label">Cancelados</div></div>
    </div>

    <p class="section-title">Participantes con delivery solicitado</p>
    <table>
      <thead>
        <tr>
          <th>Participante</th>
          <th>Dirección</th>
          <th>Teléfono</th>
          <th>Kit</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($participantes as $p)
          @php $baseUrl = $updateEstadoUrlFor($p); @endphp
          <tr>
            <td>
              {{ $p->nombre }} {{ $p->apellido }}<br>
              <span class="muted">{{ $p->tipo_documento }} {{ $p->numero_documento }} · {{ $p->correo }}</span>
            </td>
            <td>{{ $p->direccion }}, {{ $p->ciudad }}</td>
            <td>{{ $p->telefono }}</td>
            <td>
              {{ $p->categoria }}
              @if ($p->polera && $p->polera !== 'No shirt')
                · Polera {{ $p->polera }}
              @endif
              @if ($p->souvenirParticipante->isNotEmpty())
                <br><span class="muted">{{ $p->souvenirParticipante->pluck('nombre')->implode(', ') }}</span>
              @endif
            </td>
            <td><span class="badge {{ $p->estado_delivery }}">{{ $p->estado_delivery }}</span></td>
            <td>
              @if ($p->estado_delivery === 'pendiente')
                <a class="btn" href="{{ $baseUrl }}&estado=confirmado">Confirmar</a>
                <a class="btn danger" href="{{ $baseUrl }}&estado=cancelado">Cancelar</a>
              @elseif ($p->estado_delivery === 'confirmado')
                <a class="btn" href="{{ $baseUrl }}&estado=entregado">Marcar entregado</a>
                <a class="btn danger" href="{{ $baseUrl }}&estado=cancelado">Cancelar</a>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6">Todavía no hay participantes con delivery solicitado.</td></tr>
        @endforelse
      </tbody>
    </table>

    <p class="section-title">Descargar listado (CSV) — para la empresa de delivery</p>
    <div class="downloads">
      <a class="btn" href="{{ $exportBaseUrl }}">⬇ Descargar todos</a>
      @foreach (['pendiente', 'confirmado', 'entregado', 'cancelado'] as $estado)
        <a class="btn secondary" href="{{ $exportBaseUrl }}&estado_delivery={{ $estado }}">⬇ {{ ucfirst($estado) }} ({{ $resumen[$estado] }})</a>
      @endforeach
    </div>
  </div>
</body>
</html>
