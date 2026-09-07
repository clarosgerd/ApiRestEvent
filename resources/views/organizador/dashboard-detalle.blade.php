<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Detalle de inscritos — {{ $evento->nombre }}</title>
  <style>
    :root { --primary:#00bad2; --secondary:#022858; --success:#258f36; --danger:#c0392b; --warning:#b07d00; --border:#e2e8f0; --muted:#64748b; }
    * { box-sizing: border-box; }
    body { font-family: -apple-system, Segoe UI, Roboto, Arial, sans-serif; background:#f1f5f9; color:#1e293b; margin:0; padding:24px; }
    .wrap { max-width: 1100px; margin: 0 auto; }
    h1 { color: var(--secondary); font-size: 22px; margin: 0 0 4px; }
    .subtitle { color: var(--muted); font-size: 14px; margin: 0 0 24px; }
    .back { display: inline-block; font-size: 13px; color: var(--primary); text-decoration: none; margin-bottom: 14px; }
    .back:hover { text-decoration: underline; }
    form.filters { background: #fff; border-radius: 10px; padding: 14px 16px; box-shadow: 0 1px 3px rgba(0,0,0,.08); margin-bottom: 20px; display: flex; gap: 10px; align-items: end; flex-wrap: wrap; }
    .field label { display: block; font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .4px; margin-bottom: 4px; }
    .field input, .field select { border: 1px solid var(--border); border-radius: 8px; padding: 8px 10px; font-size: 13px; min-width: 200px; }
    .btn { background: var(--primary); color: #fff; border: none; font-size: 13px; font-weight: 600; border-radius: 8px; padding: 9px 16px; cursor: pointer; }
    .btn:hover { background: #008fa0; }
    .btn.secondary { background: #fff; color: var(--secondary); border: 1px solid var(--border); text-decoration: none; display: inline-flex; align-items: center; }
    table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
    th, td { padding: 10px 12px; text-align: left; font-size: 13px; border-bottom: 1px solid var(--border); }
    th { background: var(--secondary); color: #fff; font-weight: 600; }
    tr:last-child td { border-bottom: none; }
    td.num, th.num { text-align: right; }
    .estado { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
    .estado.paid { background: #dcfce7; color: var(--success); }
    .estado.pending { background: #fef3c7; color: var(--warning); }
    .estado.cancelled, .estado.failed { background: #fee2e2; color: var(--danger); }
    .pager { display: flex; justify-content: space-between; align-items: center; margin-top: 16px; font-size: 13px; color: var(--muted); }
    .pager .links { display: flex; gap: 8px; }
    .empty { padding: 20px; text-align: center; color: var(--muted); font-size: 13px; }
  </style>
</head>
<body>
  <div class="wrap">
    <a class="back" href="{{ $dashboardUrl }}">← Volver al dashboard</a>
    <h1>Detalle de inscritos</h1>
    <p class="subtitle">{{ $evento->nombre }}</p>

    <form class="filters" method="GET" action="{{ $baseUrl }}">
      {{-- Un <form method="GET"> descarta la query string del `action` al
           armar el submit — la firma viaja de nuevo como campo oculto para
           sobrevivir. --}}
      <input type="hidden" name="signature" value="{{ $signature }}">

      <div class="field">
        <label>Buscar</label>
        <input type="text" name="search" value="{{ $searchSeleccionado }}" placeholder="Documento, nombre, apellido o correo">
      </div>
      <div class="field">
        <label>Estado</label>
        <select name="pago_status">
          <option value="" @selected($pagoStatusSeleccionado === '')>Todos los estados</option>
          <option value="paid" @selected($pagoStatusSeleccionado === 'paid')>Pagado</option>
          <option value="pending" @selected($pagoStatusSeleccionado === 'pending')>Pendiente</option>
          <option value="cancelled" @selected($pagoStatusSeleccionado === 'cancelled')>Cancelado</option>
          <option value="failed" @selected($pagoStatusSeleccionado === 'failed')>Fallido</option>
        </select>
      </div>
      <div class="field">
        <label>Categoría</label>
        <select name="categoria">
          <option value="" @selected($categoriaSeleccionada === '')>Todas</option>
          @foreach ($nombresCategorias as $id => $nombre)
            <option value="{{ $id }}" @selected($categoriaSeleccionada === (string) $id)>{{ $nombre }}</option>
          @endforeach
        </select>
      </div>
      <button type="submit" class="btn">Buscar</button>
      @if ($searchSeleccionado !== '' || $pagoStatusSeleccionado !== '' || $categoriaSeleccionada !== '')
        <a class="btn secondary" href="{{ $baseUrl }}">Limpiar filtros</a>
      @endif
    </form>

    <table>
      <thead>
        <tr>
          <th>Estado</th>
          <th class="num">Importe</th>
          <th>Documento</th>
          <th>Nombre</th>
          <th>Apellido</th>
          <th>Correo</th>
          <th>Teléfono</th>
          <th>Categoría</th>
          <th>Fecha inscripción</th>
          <th>Referencia</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($participantes as $p)
          <tr>
            <td><span class="estado {{ $p->registration->pago_status }}">{{ ucfirst($p->registration->pago_status) }}</span></td>
            <td class="num">${{ number_format($p->subtotal, 2) }}</td>
            <td>{{ $p->numero_documento }}</td>
            <td>{{ $p->nombre }}</td>
            <td>{{ $p->apellido }}</td>
            <td>{{ $p->correo }}</td>
            <td>{{ $p->telefono }}</td>
            <td>{{ $nombresCategorias[$p->categoria] ?? $p->categoria }}</td>
            <td>{{ optional($p->registration->fecha)->format('Y-m-d H:i') }}</td>
            <td>{{ $p->registration->referencia }}</td>
          </tr>
        @empty
          <tr><td colspan="10" class="empty">No hay inscritos con estos filtros.</td></tr>
        @endforelse
      </tbody>
    </table>

    @if ($participantes->total() > 0)
    <div class="pager">
      <span>Página {{ $participantes->currentPage() }} de {{ $participantes->lastPage() }} — {{ $participantes->total() }} inscrito(s) en total</span>
      <div class="links">
        @if ($participantes->currentPage() > 1)
          <a class="btn secondary" href="{{ $baseUrl }}&pago_status={{ $pagoStatusSeleccionado }}&categoria={{ $categoriaSeleccionada }}&search={{ urlencode($searchSeleccionado) }}&page={{ $participantes->currentPage() - 1 }}">← Anterior</a>
        @endif
        @if ($participantes->currentPage() < $participantes->lastPage())
          <a class="btn secondary" href="{{ $baseUrl }}&pago_status={{ $pagoStatusSeleccionado }}&categoria={{ $categoriaSeleccionada }}&search={{ urlencode($searchSeleccionado) }}&page={{ $participantes->currentPage() + 1 }}">Siguiente →</a>
        @endif
      </div>
    </div>
    @endif
  </div>
</body>
</html>
