<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; margin: 0; }
  table.grid { width: 100%; border-collapse: collapse; }
  td.badge-cell { width: 33.33%; padding: 8px; vertical-align: top; }
  .badge {
    border: 2px solid #022858;
    border-radius: 10px;
    padding: 16px 10px;
    text-align: center;
    background: #ffffff;
  }
  .badge .logo { max-height: 32px; max-width: 90%; margin-bottom: 6px; }
  .badge .eventName {
    font-size: 9px; text-transform: uppercase; letter-spacing: 1px;
    color: #00acbe; font-weight: bold; margin-bottom: 8px;
  }
  .badge .name { font-size: 15px; font-weight: bold; color: #022858; margin-bottom: 6px; }
  .badge .role {
    display: inline-block; font-size: 10px; font-weight: bold; text-transform: uppercase;
    padding: 3px 12px; border-radius: 20px; margin-bottom: 12px;
    background: #eef2f1; color: #022858;
  }
  .badge .photo-box {
    width: 4cm; height: 4cm;
    margin: 0 auto 12px;
    border: 1px dashed #b0bac8;
    background: #f7f9fb;
    display: table;
  }
  .badge .photo-box .label {
    display: table-cell;
    vertical-align: middle;
    text-align: center;
    color: #a0aab8; font-size: 9px; text-transform: uppercase; letter-spacing: 1px;
  }
  .badge img.qr { width: 72px; height: 72px; }
  .badge .ref { font-size: 8px; color: #607080; margin-top: 6px; letter-spacing: 1px; }
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
          $esPonente = strtolower($item['categoria']) === 'ponente';
        @endphp
        <td class="badge-cell">
          <div class="badge" style="border-color: {{ $itemColor }};">
            @if (!empty($logo))
              <img class="logo" src="{{ $logo }}">
            @endif
            <div class="eventName">{{ $evento->nombre }}</div>
            <div class="name" style="color: {{ $itemColor }};">{{ $item['nombre'] }}</div>
            <div class="role" style="background: {{ $esPonente ? $itemColor : '#eef2f1' }}; color: {{ $esPonente ? '#ffffff' : $itemColor }};">{{ $item['categoria'] }}</div><br>
            <div class="photo-box"><span class="label">Foto<br>4x4cm</span></div>
            <img class="qr" src="data:image/png;base64,{{ $item['qr'] }}">
            <div class="ref">{{ $item['referencia'] }}</div>
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
