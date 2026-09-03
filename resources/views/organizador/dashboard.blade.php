<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — {{ $evento->nombre }}</title>
  <style>
    :root { --primary:#00bad2; --secondary:#022858; --success:#258f36; --danger:#c0392b; --warning:#b07d00; --border:#e2e8f0; --muted:#64748b; }
    * { box-sizing: border-box; }
    body { font-family: -apple-system, Segoe UI, Roboto, Arial, sans-serif; background:#f1f5f9; color:#1e293b; margin:0; padding:24px; }
    .wrap { max-width: 960px; margin: 0 auto; }
    h1 { color: var(--secondary); font-size: 22px; margin: 0 0 4px; }
    .subtitle { color: var(--muted); font-size: 14px; margin: 0 0 24px; }
    .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 14px; margin-bottom: 28px; }
    .card { background: #fff; border-radius: 10px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.08); text-align: center; }
    .card .num { font-size: 26px; font-weight: 700; color: var(--secondary); }
    .card .label { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: .4px; margin-top: 4px; }
    .card.paid .num { color: var(--success); }
    .card.pending .num { color: var(--warning); }
    .card.cancelled .num, .card.failed .num { color: var(--danger); }
    table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.08); margin-bottom: 28px; }
    th, td { padding: 10px 14px; text-align: left; font-size: 13px; border-bottom: 1px solid var(--border); }
    th { background: var(--secondary); color: #fff; font-weight: 600; }
    tr:last-child td { border-bottom: none; }
    td.num, th.num { text-align: right; }
    .section-title { font-size: 15px; font-weight: 700; color: var(--secondary); margin: 0 0 10px; }
    .btn {
      display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
      background: var(--primary); color: #fff; font-size: 12px; font-weight: 600;
      border-radius: 8px; padding: 6px 12px; margin: 2px 4px 2px 0;
    }
    .btn:hover { background: #008fa0; }
    .btn.secondary { background: var(--light, #e2e8f0); color: var(--secondary); }
    .downloads { background: #fff; border-radius: 10px; padding: 18px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
    .downloads-group { margin-bottom: 14px; }
    .downloads-group:last-child { margin-bottom: 0; }
    .downloads-group h3 { font-size: 13px; color: var(--muted); margin: 0 0 8px; font-weight: 600; }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>{{ $evento->nombre }}</h1>
    <p class="subtitle">Dashboard de inscripciones · {{ $evento->direccion }} · {{ $evento->fecha_inicio }}</p>

    <div class="cards">
      <div class="card"><div class="num">{{ $totalGeneral['total'] }}</div><div class="label">Total inscritos</div></div>
      <div class="card paid"><div class="num">{{ $totalGeneral['paid'] }}</div><div class="label">Pagados</div></div>
      <div class="card pending"><div class="num">{{ $totalGeneral['pending'] }}</div><div class="label">Pendientes</div></div>
      <div class="card cancelled"><div class="num">{{ $totalGeneral['cancelled'] }}</div><div class="label">Cancelados</div></div>
      <div class="card failed"><div class="num">{{ $totalGeneral['failed'] }}</div><div class="label">Fallidos</div></div>
    </div>

    <p class="section-title">Balance del evento</p>
    <div class="cards">
      <div class="card"><div class="num">${{ number_format($balance['ingresosInscripciones'], 2) }}</div><div class="label">Ingreso por inscripciones</div></div>
      <div class="card"><div class="num">${{ number_format($balance['ingresosManuales'], 2) }}</div><div class="label">Ingresos manuales</div></div>
      <div class="card"><div class="num">${{ number_format($balance['gastosManuales'], 2) }}</div><div class="label">Gastos</div></div>
      <div class="card paid"><div class="num">${{ number_format($balance['utilidadNeta'], 2) }}</div><div class="label">Utilidad neta</div></div>
    </div>

    <p class="section-title">Por categoría</p>
    <table>
      <thead>
        <tr>
          <th>Categoría</th>
          @foreach ($estados as $estado)
            <th class="num">{{ ucfirst($estado) }}</th>
          @endforeach
          <th class="num">Total</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($porCategoria as $categoria => $counts)
          <tr>
            <td>{{ $nombresCategorias[$categoria] ?? $categoria }}</td>
            @foreach ($estados as $estado)
              <td class="num">{{ $counts[$estado] }}</td>
            @endforeach
            <td class="num"><strong>{{ $counts['total'] }}</strong></td>
          </tr>
        @empty
          <tr><td colspan="{{ count($estados) + 2 }}">Todavía no hay inscripciones.</td></tr>
        @endforelse
      </tbody>
    </table>

    {{-- Inscritos por categoría/distancia con recaudación (03/09/2026,
         pedido del usuario) — distinto de "Por categoría" de arriba (esa
         cuenta por estado de pago, sin dinero). Acá solo cuenta lo `paid`,
         "Recaudación" es dinero efectivamente cobrado — mismo reporte que
         ya existe en el panel autenticado (admin-eventos → Dashboard). --}}
    <p class="section-title">Inscritos por categoría / distancia</p>
    <table>
      <thead>
        <tr>
          <th>Categoría</th>
          <th class="num">Cantidad</th>
          <th class="num">Recaudación</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($reporteInscritos['porCategoria']['filas'] as $fila)
          <tr>
            <td>{{ $fila['nombre'] }}</td>
            <td class="num">{{ $fila['cantidad'] }}</td>
            <td class="num">${{ number_format($fila['recaudacion'], 2) }}</td>
          </tr>
        @empty
          <tr><td colspan="3">Todavía no hay inscripciones pagadas.</td></tr>
        @endforelse
      </tbody>
      @if (count($reporteInscritos['porCategoria']['filas']) > 0)
      <tfoot>
        <tr>
          <td><strong>Total</strong></td>
          <td class="num"><strong>{{ $reporteInscritos['porCategoria']['totalCantidad'] }}</strong></td>
          <td class="num"><strong>${{ number_format($reporteInscritos['porCategoria']['totalRecaudacion'], 2) }}</strong></td>
        </tr>
      </tfoot>
      @endif
    </table>

    {{-- Reporte de poleras (03/09/2026) — pedido del usuario: faltaba acá,
         ver ReporteInscritosData::agruparPoleras(). Sale del souvenir
         marcado `es_polera=true` en admin-eventos — si un evento no lo
         tiene marcado todavía, esta tabla queda vacía (no es un error, ver
         checklist de deploy). --}}
    <p class="section-title">Reporte de poleras</p>
    <table>
      <thead>
        <tr>
          <th>Sexo</th>
          <th>Talla</th>
          <th class="num">Cantidad</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($reporteInscritos['poleras']['filas'] as $fila)
          <tr>
            <td>{{ $fila['sexo'] }}</td>
            <td>{{ $fila['talla'] }}</td>
            <td class="num">{{ $fila['cantidad'] }}</td>
          </tr>
        @empty
          <tr><td colspan="3">Nadie pagado eligió polera todavía.</td></tr>
        @endforelse
      </tbody>
      @if (count($reporteInscritos['poleras']['filas']) > 0)
      <tfoot>
        <tr>
          <td colspan="2"><strong>Total</strong></td>
          <td class="num"><strong>{{ $reporteInscritos['poleras']['total'] }}</strong></td>
        </tr>
      </tfoot>
      @endif
    </table>

    <p class="section-title">Por tipo de formulario</p>
    <table>
      <thead>
        <tr>
          <th>Tipo de formulario</th>
          @foreach ($estados as $estado)
            <th class="num">{{ ucfirst($estado) }}</th>
          @endforeach
          <th class="num">Total</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($porFormulario as $formTypeId => $counts)
          <tr>
            <td>{{ $nombresFormTypes[$formTypeId] ?? 'Sin especificar' }}</td>
            @foreach ($estados as $estado)
              <td class="num">{{ $counts[$estado] }}</td>
            @endforeach
            <td class="num"><strong>{{ $counts['total'] }}</strong></td>
          </tr>
        @empty
          <tr><td colspan="{{ count($estados) + 2 }}">Todavía no hay inscripciones.</td></tr>
        @endforelse
      </tbody>
    </table>

    <p class="section-title">Descargar listado de participantes (CSV)</p>
    <div class="downloads">
      <div class="downloads-group">
        <h3>Completo</h3>
        <a class="btn" href="{{ $exportBaseUrl }}">⬇ Descargar todos</a>
      </div>

      @if (count($porCategoria) > 0)
      <div class="downloads-group">
        <h3>Por categoría</h3>
        @foreach ($porCategoria as $categoria => $counts)
          <a class="btn secondary" href="{{ $exportBaseUrl }}&categoria={{ urlencode($categoria) }}">⬇ {{ $nombresCategorias[$categoria] ?? $categoria }} ({{ $counts['total'] }})</a>
        @endforeach
      </div>
      @endif

      @if (count($porFormulario) > 0)
      <div class="downloads-group">
        <h3>Por tipo de formulario</h3>
        @foreach ($porFormulario as $formTypeId => $counts)
          <a class="btn secondary" href="{{ $exportBaseUrl }}&form_type_id={{ $formTypeId }}">⬇ {{ $nombresFormTypes[$formTypeId] ?? 'Sin especificar' }} ({{ $counts['total'] }})</a>
        @endforeach
      </div>
      @endif

      <div class="downloads-group">
        <h3>Por estado de pago</h3>
        @foreach ($estados as $estado)
          <a class="btn secondary" href="{{ $exportBaseUrl }}&pago_status={{ $estado }}">⬇ {{ ucfirst($estado) }} ({{ $totalGeneral[$estado] }})</a>
        @endforeach
      </div>
    </div>
  </div>
</body>
</html>
