<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; margin: 0; color: {{ $brand }}; }
  .page {
    width: 100%;
    padding: 28px;
    page-break-after: always;
  }
  .page:last-child { page-break-after: auto; }
  .frame {
    border: 3px solid {{ $brand }};
    padding: 6px;
  }
  .frame-inner {
    border: 1px solid #00acbe;
    padding: 60px 50px;
    text-align: center;
  }
  .logo { max-height: 44px; max-width: 60%; margin-bottom: 10px; }
  .eventName {
    font-size: 11px; text-transform: uppercase; letter-spacing: 2px;
    color: #00acbe; font-weight: bold; margin-bottom: 6px;
  }
  .titulo {
    font-size: 28px; font-weight: bold; text-transform: uppercase;
    letter-spacing: 1px; margin: 10px 0 26px;
  }
  .otorga { font-size: 12px; color: #607080; margin-bottom: 10px; }
  .nombre {
    font-size: 26px; font-weight: bold; margin-bottom: 10px;
    border-bottom: 1px solid #cfd8e3; display: inline-block; padding: 0 30px 8px;
  }
  .detalle { font-size: 13px; color: #34445c; margin: 4px 0; }
  .detalle strong { color: {{ $brand }}; }
  .stats {
    margin-top: 18px; display: block;
  }
  .stats table { margin: 0 auto; border-collapse: collapse; }
  .stats td {
    padding: 6px 22px; font-size: 11px; text-align: center;
    border-left: 1px solid #cfd8e3;
  }
  .stats td:first-child { border-left: none; }
  .stats .valor { display: block; font-size: 15px; font-weight: bold; color: {{ $brand }}; }
  .stats .etiqueta { display: block; text-transform: uppercase; letter-spacing: 1px; color: #607080; }
  .firma {
    margin-top: 40px;
    display: inline-block;
    width: 220px;
    border-top: 1px solid {{ $brand }};
    padding-top: 6px;
    font-size: 10px;
    color: #607080;
  }
  .ref { position: absolute; bottom: 14px; right: 40px; font-size: 8px; color: #a0aab8; letter-spacing: 1px; }
</style>
</head>
<body>

@forelse ($items as $item)
  <div class="page">
    <div class="frame">
      <div class="frame-inner" style="position: relative;">
        @if (!empty($logo))
          <img class="logo" src="{{ $logo }}">
        @endif
        <div class="eventName">{{ $evento->nombre }}</div>

        @if ($item['tipo'] === 'participacion')
          <div class="titulo">Certificado de Participación</div>
        @else
          <div class="titulo">Certificado de Asistencia</div>
        @endif

        <div class="otorga">Se otorga el presente certificado a</div>
        <div class="nombre">{{ $item['nombre'] }}</div>

        @if ($item['tipo'] === 'participacion')
          <div class="detalle">
            por su participación en la categoría <strong>{{ $item['categoria'] }}</strong>@if (!empty($evento->fecha_inicio)), realizado el <strong>{{ \Illuminate\Support\Carbon::parse($evento->fecha_inicio)->locale('es')->translatedFormat('d \d\e F \d\e Y') }}</strong>@endif
          </div>

          @if ($item['tiempoOficial'] || $item['posicionGeneral'] || $item['posicionCategoria'])
            <div class="stats">
              <table>
                @if ($item['tiempoOficial'])
                  <td><span class="valor">{{ $item['tiempoOficial'] }}</span><span class="etiqueta">Tiempo oficial</span></td>
                @endif
                @if ($item['posicionGeneral'])
                  <td><span class="valor">{{ $item['posicionGeneral'] }}°</span><span class="etiqueta">Puesto general</span></td>
                @endif
                @if ($item['posicionCategoria'])
                  <td><span class="valor">{{ $item['posicionCategoria'] }}°</span><span class="etiqueta">Puesto categoría</span></td>
                @endif
              </table>
            </div>
          @endif
        @else
          <div class="detalle">
            por su asistencia como <strong>{{ $item['rol'] }}</strong>@if (!empty($evento->fecha_inicio)), el <strong>{{ \Illuminate\Support\Carbon::parse($evento->fecha_inicio)->locale('es')->translatedFormat('d \d\e F \d\e Y') }}</strong>@endif
          </div>
        @endif

        <div>
          <div class="firma">Firma / sello del organizador</div>
        </div>

        <div class="ref">{{ $item['referencia'] }}</div>
      </div>
    </div>
  </div>
@empty
  <div class="page">
    <div class="frame">
      <div class="frame-inner">
        <div style="padding-top: 200px; color: #607080;">
          Este evento todavía no tiene participantes elegibles para
          certificado (carreras: se necesitan resultados cargados con
          estado "finisher"; congresos: se necesitan inscripciones
          pagadas).
        </div>
      </div>
    </div>
  </div>
@endforelse

</body>
</html>
