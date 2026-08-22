<?php /* Consolidación financiera — liquidación de utilidades por evento. Ver LiquidacionController y elascenso/event/brain/ (sesión 11/08/2026). */ ?>
@extends('admin.layouts.app')

@section('title', 'Liquidación — '.$evento['name'])

@section('content')
<div class="flex justify-between items-center mb-4 flex-wrap gap-2">
    <h1 class="text-lg font-bold">Liquidación: {{ $evento['name'] }}</h1>
    <a href="{{ route('admin.eventos.edit', $evento['id']) }}" class="text-sm text-brand-600 hover:underline">← Volver al evento</a>
</div>

@if ($liquidacion)
    {{-- Ya liquidado — solo lectura, no hay flujo de edición/anulación en esta fase. --}}
    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-2 rounded-md mb-5 text-sm">
        Este evento ya fue liquidado el {{ \Carbon\Carbon::parse($liquidacion['liquidado_en'])->format('d/m/Y H:i') }}
        @if (!empty($liquidacion['liquidado_por']))
            por {{ $liquidacion['liquidado_por']['nombre'] }}
        @endif
        .
    </div>

    <div class="bg-white rounded-lg shadow p-4 mb-6 grid grid-cols-2 gap-3 max-w-md text-sm">
        <span class="text-slate-500">Monto base (service fee)</span>
        <span class="font-semibold text-right">${{ number_format($liquidacion['monto_base'], 2) }}</span>
        <span class="text-slate-500">Inscripciones pagadas</span>
        <span class="font-semibold text-right">{{ $liquidacion['cantidad_inscripciones'] }}</span>
    </div>

    <div class="bg-white rounded-lg shadow overflow-x-auto max-w-lg">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-4 py-2">Socio</th>
                    <th class="px-4 py-2 text-right">%</th>
                    <th class="px-4 py-2 text-right">Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($liquidacion['detalles'] as $detalle)
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2">{{ $detalle['socio_nombre'] }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format($detalle['porcentaje'], 2) }}%</td>
                        <td class="px-4 py-2 text-right font-semibold">${{ number_format($detalle['monto'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@elseif ($evento['status'] !== 'closed')
    <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-md text-sm max-w-lg">
        Este evento todavía no está cerrado (estado actual: <strong>{{ $evento['status'] }}</strong>).
        La liquidación solo se puede hacer una vez que el evento cierra automáticamente
        (al pasar su fecha de fin).
    </div>
@elseif (!$preview)
    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-md text-sm max-w-lg">
        No se pudo calcular el preview de liquidación. Intente recargar la página.
    </div>
@else
    @php($calculo = $preview['data'])
    @php($listoParaLiquidar = abs($calculo['porcentaje_total'] - 100) < 0.01 && count($calculo['detalles']) > 0)

    @if (!$listoParaLiquidar)
        <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-md mb-4 text-sm max-w-lg">
            Los socios activos suman {{ number_format($calculo['porcentaje_total'], 2) }}% — deben sumar exactamente 100%
            antes de poder liquidar. Revisar en <a href="{{ route('admin.socios.index') }}" class="underline">Socios</a>.
        </div>
    @endif

    <p class="text-sm text-slate-500 mb-3">Preview — todavía no se confirmó nada, este cálculo se recalcula cada vez que se recarga la página.</p>

    <div class="bg-white rounded-lg shadow p-4 mb-6 grid grid-cols-2 gap-3 max-w-md text-sm">
        <span class="text-slate-500">Monto base (service fee)</span>
        <span class="font-semibold text-right">${{ number_format($calculo['monto_base'], 2) }}</span>
        <span class="text-slate-500">Inscripciones pagadas</span>
        <span class="font-semibold text-right">{{ $calculo['cantidad_inscripciones'] }}</span>
    </div>

    <div class="bg-white rounded-lg shadow overflow-x-auto max-w-lg mb-5">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-4 py-2">Socio</th>
                    <th class="px-4 py-2 text-right">%</th>
                    <th class="px-4 py-2 text-right">Monto proyectado</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($calculo['detalles'] as $detalle)
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2">{{ $detalle['socio_nombre'] }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format($detalle['porcentaje'], 2) }}%</td>
                        <td class="px-4 py-2 text-right font-semibold">${{ number_format($detalle['monto'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-6 text-center text-slate-400">No hay socios activos configurados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($listoParaLiquidar)
        <form method="POST" action="{{ route('admin.liquidacion.store', $evento['id']) }}"
              onsubmit="return confirm('¿Confirmar la liquidación de este evento? No se puede deshacer.')">
            @csrf
            <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2 rounded-md">
                Liquidar evento
            </button>
        </form>
    @endif
@endif
@endsection
