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
       una página 2 casi en blanco. Primer intento de fix: white-space:nowrap
       (forzar 1 sola línea). MAL — con un nombre largo de 4 palabras
       ("FREDDY PAUL SORUCO JUSTINIANO", reportado en UAT con screenshot al
       día siguiente) dompdf no soporta text-overflow:ellipsis sobre texto
       centrado y en vez de "…" recorta por el MEDIO, dejando invisibles el
       principio Y el final del nombre — peor que el bug original.
       20/10/2026 (fix real, reemplaza al anterior): se saca nowrap/overflow/
       text-overflow — el nombre puede volver a envolver a 2 líneas (nombre
       COMPLETO visible, nunca recortado), pero el alto reservado para
       nombre+referencia ahora SIEMPRE asume el peor caso (2 líneas),
       calculado a partir del tamaño de fuente real elegido para ese nombre
       — no solo cuando supera un umbral de caracteres — así nunca vuelve a
       desbordar a una página 2 sin importar si el nombre termina
       renderizando en 1 o 2 líneas.
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
      /* Envuelve a 2 líneas si hace falta (nombre SIEMPRE completo, nunca
         recortado) — ver $alturaNombreCm abajo, que reserva el alto
         asumiendo esto. word-break: red de seguridad para una palabra
         suelta más larga que la pegatina (caso raro). */
      word-break: break-word;
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
      // Fuente del nombre: 3 escalones por longitud (nombre + título si
      // está prendido) — cuanto más largo, más chico, para necesitar menos
      // seguido una 2da línea. No garantiza 1 sola línea (dompdf decide el
      // wrap real según el ancho), por eso el alto reservado abajo siempre
      // asume 2 líneas de todos modos.
      $len = mb_strlen($item['nombre']);
      $nombrePt = $len > 22 ? 13 : ($len > 14 ? 16 : 20);
      // QR cuadrado: se limita por AMBAS dimensiones de la pegatina (antes
      // solo se limitaba por el ancho, causando el desborde vertical en
      // pegatinas horizontales — ver comentario arriba). El alto reservado
      // para nombre+referencia se calcula siempre para el PEOR caso del
      // nombre (2 líneas, al tamaño de fuente real elegido arriba) — un
      // nombre que termina en 1 sola línea simplemente deja un poco de aire
      // de más, pero nunca desborda la página (bug real del 19-20/10/2026).
      // 1pt ≈ 0.0353cm de alto de línea.
      $alturaNombreCm = ($nombrePt * 0.0353 * 2) + 0.3;
      $alturaReferenciaCm = !empty($item['referencia']) ? 0.35 : 0;
      $qrPorAlto = max(0.8, $dims['height_cm'] - $alturaNombreCm - $alturaReferenciaCm);
      $qrPorAncho = $dims['width_cm'] * ($tieneLaterales ? 0.55 : 0.9);
      // QR al 70% del tamaño calculado: deja margen de aire alrededor.
      $qrCm = min($qrPorAlto, $qrPorAncho) * 0.7;
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
