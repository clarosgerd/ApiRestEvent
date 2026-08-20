<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  @page { margin: 1cm; }
  body { font-family: DejaVu Sans, sans-serif; margin: 0; }
  table.grid { width: 100%; border-collapse: collapse; }
  td.badge-cell { width: 7.3cm; padding: 4px; vertical-align: top; }
  .badge {
    width: 7cm; height: 5cm; box-sizing: border-box;
    border: 2px solid #022858;
    border-radius: 10px;
    padding: 10px 8px;
    background: #ffffff;
  }
  .badge .name {
    text-align: center;
    font-size: 15px; font-weight: bold; color: #022858;
    margin-bottom: 12px;
  }
  .badge .body { display: table; width: 100%; }
  .badge .qr-cell { display: table-cell; width: 45%; vertical-align: middle; text-align: left; }
  .badge .role-cell { display: table-cell; width: 55%; vertical-align: middle; text-align: center; }
  .badge img.qr { width: 62px; height: 62px; }
  .badge .role {
    font-size: 13px; font-weight: bold; text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  .badge .role + .role { margin-top: 10px; }
</style>
</head>
<body>

<div style="text-align:center;padding:10px 0 16px;color:#607080;font-size:11px;">
  {{ $evento->nombre }} — gafetes de acceso ({{ collect($filas)->flatten(1)->count() }} participantes)
</div>

@foreach ($filas as $fila)
  <table class="grid">
    <tr>
      @foreach ($fila as $item)
        @php
          $itemColor = $item['color'] ?? '#022858';
        @endphp
        <td class="badge-cell">
          <div class="badge" style="border-color: {{ $itemColor }};">
            <div class="name" style="color: {{ $itemColor }};">{{ $item['nombre'] }}</div>
            <div class="body">
              <div class="qr-cell">
                <img class="qr" src="data:image/png;base64,{{ $item['qr'] }}">
              </div>
              <div class="role-cell">
                @if (!empty($item['rol']))
                  <div class="role" style="color: {{ $itemColor }};">{{ $item['rol'] }}</div>
                @endif
                @if (!empty($item['categoria']) && $item['categoria'] !== ($item['rol'] ?? null))
                  <div class="role" style="color: {{ $itemColor }};">{{ $item['categoria'] }}</div>
                @endif
              </div>
            </div>
          </div>
        </td>
      @endforeach
      @for ($k = count($fila); $k < 3; $k++)
        <td class="badge-cell"></td>
      @endfor
    </tr>
  </table>
@endforeach

@if (empty($filas))
  <div style="text-align:center;color:#607080;padding:40px;">
    Este evento todavía no tiene participantes inscritos.
  </div>
@endif

</body>
</html>
