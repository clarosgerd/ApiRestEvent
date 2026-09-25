<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    /* Gafete tipo "pegatina" (23/09/2026), para impresoras de etiquetas:
       el gafete físico ya viene predefinido/impreso, esta pegatina se pega
       encima. El tamaño de página YA es exactamente el de la pegatina (ver
       EventoController::renderGafetesPdf(), setPaper con dimensiones
       custom en puntos), así que no hace falta ningún margen/grilla.
       24/09/2026: nombre arriba, categoria al lado derecho del QR,
       referencia abajo (antes era solo QR).
       24/09/2026 (fix real): bug encontrado con datos reales de COLABIOCLI
       2026 (7x5cm, horizontal) - el QR se dimensionaba solo en base al
       ancho (width:70%/height:auto), que en una pegatina mas ancha que
       alta da un QR casi tan alto como toda la pegatina; sumado al texto
       desborda verticalmente y dompdf mete paginas en blanco antes de la
       real (confirmado reproduciendo con el evento/participante reales).
       El tamano del QR ahora se calcula abajo considerando ambas
       dimensiones de la pegatina, no solo el ancho, y se arma con una
       tabla real (mas predecible en dompdf que divs apilados).
       overflow:hidden como red de seguridad adicional. */
    @page { margin: 0; }
    body { margin: 0; padding: 0; font-family: Arial, sans-serif; }
    .pegatina {
      width: {{ $dims['width_cm'] }}cm;
      height: {{ $dims['height_cm'] }}cm;
      page-break-after: always;
      overflow: hidden;
    }
    .pegatina:last-child { page-break-after: auto; }
    .pegatina table {
      width: 100%;
      height: 100%;
      border-collapse: collapse;
    }
    .pegatina .fila-nombre td {
      text-align: center;
      font-weight: bold;
      font-size: 11pt;
      line-height: 1.15;
      padding: 2px 2px 0;
    }
    .pegatina .celda-qr {
      text-align: center;
      vertical-align: middle;
    }
    .pegatina .celda-categoria {
      text-align: left;
      vertical-align: middle;
      font-size: 7pt;
      font-weight: bold;
      color: #444;
      padding-left: 0px;
       word-wrap: break-word;
    }
    .pegatina .fila-referencia td {
      text-align: center;
      font-size: 8pt;
      font-weight: bold;
      color: #666;
      padding: 0 2px 2px;
    }
  </style>
</head>
<body>
  @foreach ($items as $item)
    @php
      // QR cuadrado: se limita por AMBAS dimensiones de la pegatina (antes
      // solo se limitaba por el ancho, causando el desborde vertical en
      // pegatinas horizontales — ver comentario arriba). 0.9cm reservados
      // para las filas de nombre/referencia, 0.55 del ancho reservado para
      // la columna de categoría al lado.
      $qrPorAlto = max(0.8, $dims['height_cm'] - 0.9);
      $qrPorAncho = $dims['width_cm'] * 0.55;
      $qrCm = min($qrPorAlto, $qrPorAncho);
    @endphp
    <div class="pegatina">
      <table>
        <tr class="fila-nombre">
          <td colspan="2">{{ $item['nombre'] }}</td>
        </tr>
        <tr>
          <td class="celda-qr">
            <img src="data:image/png;base64,{{ $item['qr'] }}" style="width: {{ $qrCm }}cm; height: {{ $qrCm }}cm;">
          </td>
          <td class="celda-categoria">{{ $item['categoria'] }}</td>
        </tr>
        <tr class="fila-referencia">
          <td colspan="2">{{ $item['referencia'] }}</td>
        </tr>
      </table>
    </div>
  @endforeach
</body>
</html>
