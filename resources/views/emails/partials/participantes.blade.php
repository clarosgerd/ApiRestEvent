{{--
    `participantes.categoria` guarda el ID de la categoría, no el nombre
    (ver elascenso/event/brain/BUG-FILTRO-CATEGORIA-NUMERACION-10082026.md)
    — mostrarlo crudo acá salía como "Categoría: 1" en vez de "Categoría:
    5K" en el email/PDF. $categoriasPorId resuelve el nombre; si no
    encuentra la categoría (dato legacy inconsistente, o el evento no
    tiene categorías) cae al valor crudo en vez de romper la vista.
--}}
@php($categoriasPorId = $registration->evento?->categories?->keyBy(fn ($c) => (string) $c->id) ?? collect())
@foreach ($registration->participants as $p)
    <tr>
      <td style="padding:16px;background:#f4f8fb;border-radius:8px;margin-bottom:8px;">
        <strong style="color:#022858;font-size:15px;">{{ $p->alias }}</strong>
        <span style="color:#607080;"> — {{ $p->nombre }} {{ $p->apellido }}</span><br>
        <span style="font-size:13px;color:#607080;line-height:2;">
          Categoría: <strong>{{ $categoriasPorId->get($p->categoria)->name ?? $p->categoria }}</strong> · Bs{{ number_format((float) $p->precio_categoria, 2) }}<br>
          Documento: <strong>{{ $p->tipo_documento }} {{ $p->numero_documento }}</strong><br>
          Camiseta: <strong>{{ $p->polera }}</strong><br>
          Recuerdos:
          @if ($p->souvenirParticipante->isEmpty())
            Ninguno
          @else
            {{ $p->souvenirParticipante->map(fn ($s) => ($s->nombre ?? '') . ' (Bs' . number_format((float) ($s->precio ?? 0), 2) . ')')->implode(', ') }}
          @endif
          <br>
          @if ((float) $p->donacion > 0)
            Donación: <strong>Bs{{ number_format((float) $p->donacion, 2) }}</strong><br>
          @endif
          @if ((float) $p->promo_descuento > 0)
            Promo: <strong>{{ $p->promo_codigo }} (-Bs{{ number_format((float) $p->promo_descuento, 2) }})</strong>
          @endif
        </span>
      </td>
    </tr>
    <tr><td style="height:8px;"></td></tr>
@endforeach
