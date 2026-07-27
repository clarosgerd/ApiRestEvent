@foreach ($registration->participants as $p)
    <tr>
      <td style="padding:16px;background:#f4f8fb;border-radius:8px;margin-bottom:8px;">
        <strong style="color:#022858;font-size:15px;">{{ $p->alias }}</strong>
        <span style="color:#607080;"> — {{ $p->nombre }} {{ $p->apellido }}</span><br>
        <span style="font-size:13px;color:#607080;line-height:2;">
          Category: <strong>{{ $p->categoria }}</strong> · Bs{{ number_format((float) $p->precio_categoria, 2) }}<br>
          Document: <strong>{{ $p->tipo_documento }} {{ $p->numero_documento }}</strong><br>
          Shirt: <strong>{{ $p->polera }}</strong><br>
          Souvenirs:
          @if ($p->souvenirParticipante->isEmpty())
            None
          @else
            {{ $p->souvenirParticipante->map(fn ($s) => ($s->nombre ?? '') . ' (Bs' . number_format((float) ($s->precio ?? 0), 2) . ')')->implode(', ') }}
          @endif
          <br>
          @if ((float) $p->donacion > 0)
            Donation: <strong>Bs{{ number_format((float) $p->donacion, 2) }}</strong><br>
          @endif
          @if ((float) $p->promo_descuento > 0)
            Promo: <strong>{{ $p->promo_codigo }} (-Bs{{ number_format((float) $p->promo_descuento, 2) }})</strong>
          @endif
        </span>
      </td>
    </tr>
    <tr><td style="height:8px;"></td></tr>
@endforeach
