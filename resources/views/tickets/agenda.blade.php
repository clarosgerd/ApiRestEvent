<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; color: #1a2a3a; font-size: 11px; }
  table { width: 100%; border-collapse: collapse; }
  .day-header {
    background: #eef2f1; color: #022858; font-weight: bold; font-size: 12px;
    padding: 6px 10px; margin-top: 14px;
  }
  .section-title {
    color: #00bad2; font-weight: bold; font-size: 10px; text-transform: uppercase;
    letter-spacing: 0.5px; padding: 8px 0 4px;
  }
  .item-time { color: #607080; font-size: 10px; white-space: nowrap; width: 70px; }
  .item-title { font-weight: bold; }
  .item-desc { color: #465565; font-size: 10px; }
  .item-meta { color: #00acbe; font-size: 9px; }
  td { padding: 4px 6px; vertical-align: top; border-bottom: 1px solid #e2e8f0; }
</style>
</head>
<body>

<table>
  <tr><td style="background:#022858;padding:18px 22px;text-align:center;border-bottom:none;">
    <span style="color:#ffffff;font-size:17px;font-weight:bold;">{{ $evento->nombre }}</span><br>
    <span style="color:#9fd8e0;font-size:10px;">
      @if ($evento->fecha_inicio)
        {{ \Carbon\Carbon::parse($evento->fecha_inicio)->locale('es')->translatedFormat('d \d\e F \d\e Y') }}
        @if ($evento->fecha_fin && $evento->fecha_fin !== $evento->fecha_inicio)
          – {{ \Carbon\Carbon::parse($evento->fecha_fin)->locale('es')->translatedFormat('d \d\e F \d\e Y') }}
        @endif
      @endif
      @if ($evento->lugar)
        · {{ $evento->lugar }}
      @endif
    </span>
  </td></tr>
</table>

<div style="padding:4px 4px;">
  <div style="text-align:center;color:#607080;font-size:10px;margin:10px 0 4px;">
    Programa completo del evento
  </div>

@php $mostrarEncabezadoDia = $estructura->count() > 1; @endphp

@forelse ($estructura as $fecha => $grupo)
  @if ($mostrarEncabezadoDia)
    <div class="day-header">
      {{ $fecha ? \Carbon\Carbon::parse($fecha)->locale('es')->translatedFormat('l d \d\e F') : 'Programa general' }}
    </div>
  @endif

  @if ($grupo['general']->isNotEmpty())
    <table>
      @foreach ($grupo['general'] as $item)
        <tr>
          <td class="item-time">
            {{ substr($item->hora_inicio, 0, 5) }}{{ $item->hora_fin ? ' – ' . substr($item->hora_fin, 0, 5) : '' }}
          </td>
          <td>
            <div class="item-title">{{ $item->titulo }}</div>
            @if ($item->descripcion)
              <div class="item-desc">{{ $item->descripcion }}</div>
            @endif
            @if ($item->ponente || $item->sala)
              <div class="item-meta">
                {{ $item->ponente ? 'Ponente: ' . $item->ponente . ($item->ponente_cargo ? ' · ' . $item->ponente_cargo : '') : '' }}
                {{ $item->ponente && $item->sala ? '  ·  ' : '' }}
                {{ $item->sala ? 'Sala: ' . $item->sala : '' }}
              </div>
            @endif
          </td>
        </tr>
      @endforeach
    </table>
  @endif

  @if ($grupo['porTipo']->isNotEmpty())
    <table>
      <tr>
        @foreach ($grupo['porTipo'] as $formTypeName => $items)
          <td style="width: {{ number_format(100 / max(count($grupo['porTipo']), 1), 2) }}%; border-bottom: none; vertical-align: top;">
            <div class="section-title">{{ $formTypeName }}</div>
            <table>
              @foreach ($items as $item)
                <tr>
                  <td class="item-time" style="width:55px;">
                    {{ substr($item->hora_inicio, 0, 5) }}
                  </td>
                  <td>
                    <div class="item-title">{{ $item->titulo }}</div>
                    @if ($item->descripcion)
                      <div class="item-desc">{{ $item->descripcion }}</div>
                    @endif
                  </td>
                </tr>
              @endforeach
            </table>
          </td>
        @endforeach
      </tr>
    </table>
  @endif
@empty
  <div style="text-align:center;color:#607080;padding:24px;">
    Este evento todavía no tiene agenda cargada.
  </div>
@endforelse

</div>

<table>
  <tr><td style="padding:10px 22px;text-align:center;color:#607080;font-size:9px;border-bottom:none;">
    {{ $evento->nombre }} · agenda generada el {{ \Carbon\Carbon::now()->locale('es')->translatedFormat('d/m/Y') }}
  </td></tr>
</table>

</body>
</html>
