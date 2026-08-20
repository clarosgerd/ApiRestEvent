{{--
    `participantes.categoria` guarda el ID de la categoría, no el nombre
    (ver elascenso/event/brain/BUG-FILTRO-CATEGORIA-NUMERACION-10082026.md)
    — mostrarlo crudo acá salía como "Categoría: 1" en vez de "Categoría:
    5K" en el email/PDF. $categoriasPorId resuelve el nombre; si no
    encuentra la categoría (dato legacy inconsistente, o el evento no
    tiene categorías) cae al valor crudo en vez de romper la vista.
--}}
@php($categoriasPorId = $registration->evento?->categories?->keyBy(fn ($c) => (string) $c->id) ?? collect())
{{--
    Camiseta oculta en congresos (19/08/2026) — pedido del usuario a partir
    del e-ticket real de LA-C404D0CA (evento de congreso, participante sin
    polera): un congreso no reparte camiseta, mostrar "Camiseta: No shirt"
    ahí solo confundía. Carreras/talleres deportivos siguen mostrándola
    igual que siempre.
--}}
@php($esCongreso = $registration->formType?->tipo === 'congreso')
@foreach ($registration->participants as $p)
    <tr>
      <td style="padding:16px;background:#f4f8fb;border-radius:8px;margin-bottom:8px;">
        <strong style="color:#022858;font-size:15px;">{{ $p->alias }}</strong>
        <span style="color:#607080;"> — {{ $p->nombre }} {{ $p->apellido }}</span><br>
        <span style="font-size:13px;color:#607080;line-height:2;">
          {{--
              Precio USD fijo (19/08/2026) — si la inscripción se pagó en
              USD, se agrega el precio USD de la categoría al lado del de
              Bs (que sigue siendo el precio base real, no se reemplaza).
              Antes esta línea era siempre Bs sin importar la moneda de
              cobro real de la inscripción.
          --}}
          @php($categoriaActual = $categoriasPorId->get($p->categoria))
          Categoría: <strong>{{ $categoriaActual->name ?? $p->categoria }}</strong> · Bs{{ number_format((float) $p->precio_categoria, 2) }}
          @if ($registration->moneda_pago === 'USD' && $categoriaActual && $categoriaActual->price_usd !== null)
            <span style="color:#607080;">(US${{ number_format((float) $categoriaActual->price_usd, 2) }})</span>
          @endif
          <br>
          Documento: <strong>{{ $p->tipo_documento }} {{ $p->numero_documento }}</strong><br>
          @unless ($esCongreso)
            Camiseta: <strong>{{ $p->polera }}</strong><br>
          @endunless
          {{--
              Bug real (19/08/2026): esta línea decía "Taller:" pero mostraba
              $p->souvenirParticipante (souvenirs), nunca la selección real de
              taller — la sesión de congreso de verdad nunca se mostraba en
              el correo. Se separan en dos líneas, cada una oculta si está
              vacía (pedido explícito del usuario para souvenirs, mismo
              criterio aplicado a talleres por consistencia).
          --}}
          @if ($p->souvenirParticipante->isNotEmpty())
            Souvenirs: <strong>{{ $p->souvenirParticipante->map(fn ($s) => ($s->nombre ?? '') . ' (Bs' . number_format((float) ($s->precio ?? 0), 2) . ')')->implode(', ') }}</strong><br>
          @endif
          @if ($p->talleresSesiones->isNotEmpty())
            Taller: <strong>{{ $p->talleresSesiones->map(fn ($ts) => ($ts->taller->nombre ?? 'Taller') . ((float) $ts->total > 0 ? ' (Bs' . number_format((float) $ts->total, 2) . ')' : ''))->implode(', ') }}</strong><br>
          @endif
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
