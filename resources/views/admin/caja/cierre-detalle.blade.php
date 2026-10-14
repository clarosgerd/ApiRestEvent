@extends('admin.layouts.app')

@section('title', 'Detalle de turno — Cierres de caja')

@section('content')
@php
    $tipoLabels = [
        'inscripcion_nueva' => 'Inscripción nueva',
        'cobro_pendiente'   => 'Cobro pendiente',
        'edicion_pagada'    => 'Edición pagada',
        'anulacion'         => 'Anulación',
    ];
@endphp

{{-- Impresión (07/10/2026) — mismo patrón que admin/caja/eticket.blade.php:
     .no-print se oculta en pantalla de impresión, .cierre-imprimible es lo
     ÚNICO visible al imprimir (oculta el menú/sidebar del panel sin tener
     que tocar el layout). --}}
<div class="mb-4 no-print flex items-center justify-between gap-2 flex-wrap">
    <a href="{{ route('admin.caja.cierres', $evento['id']) }}" class="text-sm text-brand-600 hover:underline">&larr; Volver a cierres de caja</a>
    <div class="flex items-center gap-2">
        {{-- Descargar CSV (08/10/2026) — mismos datos que la tabla de abajo
             (+motivo/observaciones), pedido real del usuario junto con
             "Imprimir". Ver Admin\CajaController::cierreCsv(). --}}
        <a href="{{ route('admin.caja.cierres.csv', [$evento['id'], $turno['id']]) }}"
           class="bg-white border border-slate-300 hover:bg-slate-50 rounded-md px-4 py-2 text-sm font-semibold">
            ⬇️ Descargar CSV
        </a>
        <button type="button" onclick="window.print()" class="bg-brand-600 hover:bg-brand-700 text-white rounded-md px-4 py-2 text-sm font-semibold">
            🖨️ Imprimir
        </button>
    </div>
</div>

<style>
    @media print {
        body * { visibility: hidden; }
        .cierre-imprimible, .cierre-imprimible * { visibility: visible; }
        .cierre-imprimible { position: absolute; left: 0; top: 0; width: 100%; }
        .no-print { display: none !important; }
    }
</style>

<div class="cierre-imprimible">
<h1 class="text-lg font-bold mb-1">Detalle de turno</h1>
<p class="text-sm text-slate-500 mb-5">{{ $evento['name'] ?? '' }} — {{ $turno['cajeroNombre'] ?? ('#'.$turno['cajeroId']) }}</p>

<div class="bg-white rounded-lg shadow p-4 mb-5 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
    <div>
        <p class="text-xs text-slate-500">Abierto</p>
        <p class="font-semibold">{{ \Illuminate\Support\Carbon::parse($turno['abiertoAt'])->format('d/m/Y H:i') }}</p>
    </div>
    <div>
        <p class="text-xs text-slate-500">Cerrado</p>
        <p class="font-semibold">{{ $turno['cerradoAt'] ? \Illuminate\Support\Carbon::parse($turno['cerradoAt'])->format('d/m/Y H:i') : '—' }}</p>
    </div>
    <div>
        <p class="text-xs text-slate-500">Fondo inicial</p>
        <p class="font-semibold">{{ number_format($turno['fondoInicial'], 2) }}</p>
    </div>
    <div>
        <p class="text-xs text-slate-500">Estado</p>
        <span class="text-xs px-2 py-0.5 rounded-full {{ $turno['estado'] === 'abierto' ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-700' }}">
            {{ $turno['estado'] }}
        </span>
    </div>
    <div>
        <p class="text-xs text-slate-500">Esperado</p>
        <p class="font-semibold">{{ $turno['montoEsperado'] !== null ? number_format($turno['montoEsperado'], 2) : '—' }}</p>
    </div>
    <div>
        <p class="text-xs text-slate-500">Contado</p>
        <p class="font-semibold">{{ $turno['montoContado'] !== null ? number_format($turno['montoContado'], 2) : '—' }}</p>
    </div>
    <div>
        <p class="text-xs text-slate-500">Diferencia</p>
        <p class="font-semibold {{ ($turno['diferencia'] ?? 0) < 0 ? 'text-red-600' : (($turno['diferencia'] ?? 0) > 0 ? 'text-amber-600' : '') }}">
            {{ $turno['diferencia'] !== null ? number_format($turno['diferencia'], 2) : '—' }}
        </p>
    </div>
    <div>
        <p class="text-xs text-slate-500">Total movimientos</p>
        <p class="font-semibold">{{ number_format($turno['totalCobrado'] ?? 0, 2) }}</p>
    </div>
    {{-- Método de pago en Caja (18/09/2026) — desglose informativo; "Esperado"
         de arriba ya excluye el QR (nunca pasa por el cajón físico), este
         desglose explica por qué. --}}
    <div>
        <p class="text-xs text-slate-500">Efectivo</p>
        <p class="font-semibold">{{ number_format($turno['totalEfectivo'] ?? 0, 2) }}</p>
    </div>
    <div>
        <p class="text-xs text-slate-500">QR</p>
        <p class="font-semibold">{{ number_format($turno['totalQr'] ?? 0, 2) }}</p>
    </div>
    {{-- Métodos de pago nuevos (30/09/2026) — mismo criterio: ninguno pasa
         por el cajón, "Esperado" de arriba no los incluye. --}}
    <div>
        <p class="text-xs text-slate-500">Depósito</p>
        <p class="font-semibold">{{ number_format($turno['totalDeposito'] ?? 0, 2) }}</p>
    </div>
    <div>
        <p class="text-xs text-slate-500">Organizador</p>
        <p class="font-semibold">{{ number_format($turno['totalOrganizador'] ?? 0, 2) }}</p>
    </div>
    <div>
        <p class="text-xs text-slate-500">Cortesía</p>
        <p class="font-semibold">{{ number_format($turno['totalCortesia'] ?? 0, 2) }}</p>
    </div>
</div>

<h2 class="font-bold text-sm text-brand-600 mb-2">Movimientos ({{ count($turno['movimientos'] ?? []) }})</h2>
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr>
                <th class="px-3 py-2">Hora</th>
                <th class="px-3 py-2">Tipo</th>
                <th class="px-3 py-2">Inscripción</th>
                <th class="px-3 py-2">Método</th>
                <th class="px-3 py-2 text-right">Monto</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($turno['movimientos'] ?? [] as $m)
                <tr class="border-t border-slate-100">
                    <td class="px-3 py-2">{{ \Illuminate\Support\Carbon::parse($m['createdAt'])->format('d/m/Y H:i') }}</td>
                    <td class="px-3 py-2">
                        {{ $tipoLabels[$m['tipo']] ?? $m['tipo'] }}
                        {{-- Quitar/cambiar un taller ya pagado (29/09/2026) —
                             solo aparece cuando el movimiento incluyó
                             quitar un taller ya cobrado. --}}
                        @if (!empty($m['motivo']))
                            <div class="text-xs text-slate-500 font-normal mt-0.5">{{ $m['motivo'] }}</div>
                        @endif
                        {{-- Observaciones (07/10/2026) — nota libre y opcional,
                             para cualquier método de pago. --}}
                        @if (!empty($m['observaciones']))
                            <div class="text-xs text-slate-400 font-normal italic mt-0.5">{{ $m['observaciones'] }}</div>
                        @endif
                    </td>
                    <td class="px-3 py-2">
                        @if ($m['registrationReferencia'])
                            <a href="{{ route('admin.caja.eticket', [$evento['id'], $m['registrationReferencia']]) }}" class="text-brand-600 hover:underline">{{ $m['registrationReferencia'] }}</a>
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-3 py-2">{{ $m['metodoPago'] ?? '—' }}</td>
                    <td class="px-3 py-2 text-right {{ $m['monto'] < 0 ? 'text-red-600 font-semibold' : '' }}">
                        {{ number_format($m['monto'], 2) }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-3 py-6 text-center text-slate-400">Este turno todavía no tiene movimientos.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
</div>
@endsection
