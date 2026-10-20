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
       overflow:hidden como red de seguridad adicional.
       07/10/2026: se quita el rol/categoría (Participante/Staff/Ponente/
       Expositor) del gafete — pedido explícito del organizador de que
       solo se imprima nombre, QR y referencia. El QR vuelve a ocupar
       todo el ancho de la pegatina (antes reservaba una columna aparte
       para ese texto).
       19/10/2026: descripción de categoría y país agregados AL LADO del
       QR (no debajo de la referencia, eso se probó y se descartó el mismo
       día — ver mockup del usuario) — una columna lateral con hasta 2
       líneas apiladas (descripción arriba, país abajo), solo si el item
       tiene al menos una de las dos; el QR vuelve a ocupar todo el ancho
       cuando no hay ninguna.
       19/10/2026 (fix real, reportado en UAT con screenshot): un nombre
       corto con espacio ("PEDRO ECHAGUE", 13 caracteres) pasaba el umbral
       de 14 y usaba 20pt, pero igual envolvía a 2 líneas en una pegatina
       angosta — el umbral por cantidad de caracteres no contempla el ancho
       real de la pegatina. Esa 2da línea excedía el 0.9cm reservado para
       nombre+referencia (ver $qrPorAlto abajo) y dompdf mandaba el resto a
       una página 2 casi en blanco. white-space:nowrap en fila-nombre
       fuerza SIEMPRE una sola línea (con recorte si no entra) — la causa
       raíz, no el síntoma.
       19/10/2026: título/alias (Dr., Dra., etc.) antepuesto al nombre y
       referencia, apagables por evento — ver EventoController::gafeteDims()/
       construirItemGafete(), $dims['mostrar_titulo']/['mostrar_referencia'].
       El QR siempre codifica la referencia real aunque el texto esté
       apagado. */
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
      font-size: 20pt;
      line-height: 1.0;
      padding: 10px 2px 0;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .pegatina .celda-qr {
      text-align: center;
      vertical-align: middle;
    }
    .pegatina .fila-referencia td {
      text-align: center;
      font-size: 8pt;
      font-weight: bold;
      color: #666;
      padding: 0 2px 2px;
    }
    .pegatina .celda-lateral {
      text-align: center;
      vertical-align: middle;
      padding: 0 4px;
    }
    .pegatina .celda-lateral div {
      font-size: 9pt;
      font-weight: bold;
      color: #333;
      line-height: 1.3;
    }
  </style>
</head>
<body>
  @foreach ($items as $item)
    @php
      // Líneas de la columna lateral (al lado del QR): descripción de
      // categoría arriba, país abajo — cada una solo si no está vacía.
      $lineasLaterales = array_filter([$item['categoria_descripcion'] ?? null, $item['pais'] ?? null]);
      $tieneLaterales = count($lineasLaterales) > 0;
      // QR cuadrado: se limita por AMBAS dimensiones de la pegatina (antes
      // solo se limitaba por el ancho, causando el desborde vertical en
      // pegatinas horizontales — ver comentario arriba). 0.9cm reservados
      // para las filas de nombre/referencia. Si hay columna lateral, el QR
      // cede ancho (no alto) para hacerle lugar.
      $qrPorAlto = max(0.8, $dims['height_cm'] - 0.9);
      $qrPorAncho = $dims['width_cm'] * ($tieneLaterales ? 0.55 : 0.9);
      // QR al 70% del tamaño calculado: deja margen de aire alrededor.
      $qrCm = min($qrPorAlto, $qrPorAncho) * 0.7;
      // Nombres de más de 14 caracteres ocupan dos líneas a 20pt y desbordan la pegatina
      // (la referencia caía a otra página). Por encima de 14 caracteres se usa 15pt.
      $nombrePt = mb_strlen($item['nombre']) > 14 ? 15 : 20;
    @endphp
    <div class="pegatina">
      <table>
        <tr class="fila-nombre">
          <td @if ($tieneLaterales) colspan="2" @endif style="font-size: {{ $nombrePt }}pt;">{{ $item['nombre'] }}</td>
        </tr>
        <tr>
          <td class="celda-qr" style="width: {{ $qrCm }}cm;">
            <img src="data:image/png;base64,{{ $item['qr'] }}" style="width: {{ $qrCm }}cm; height: {{ $qrCm }}cm;">
          </td>
          @if ($tieneLaterales)
            <td class="celda-lateral">
              @foreach ($lineasLaterales as $linea)
                <div>{{ $linea }}</div>
              @endforeach
            </td>
          @endif
        </tr>
        @if (!empty($item['referencia']))
          <tr class="fila-referencia">
            <td @if ($tieneLaterales) colspan="2" @endif>{{ $item['referencia'] }}</td>
          </tr>
        @endif
      </table>
    </div>
  @endforeach
</body>
</html>
