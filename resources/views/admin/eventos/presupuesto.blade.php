<?php /* Presupuesto de un evento — control financiero del organizador. Ver PresupuestoController y elascenso/event/brain/ (sesión 11/08/2026). */ ?>
@extends('admin.layouts.app')

@section('title', 'Presupuesto — '.$evento['name'])

@section('content')
<div class="flex justify-between items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="text-lg font-bold">Presupuesto: {{ $evento['name'] }}</h1>
        <p class="text-sm text-slate-500">Ingresos y gastos manuales del evento (patrocinios, donaciones, logística, premios...).</p>
    </div>
    <a href="{{ route('admin.eventos.edit', $evento['id']) }}" class="text-sm text-brand-600 hover:underline">← Volver al evento</a>
</div>

@if ($balance)
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-xl font-bold text-brand-600">${{ number_format($balance['ingresosInscripciones'], 2) }}</div>
        <div class="text-xs text-slate-500 uppercase tracking-wide mt-1">Ingreso por inscripciones</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-xl font-bold text-brand-600">${{ number_format($balance['ingresosManuales'], 2) }}</div>
        <div class="text-xs text-slate-500 uppercase tracking-wide mt-1">Ingresos manuales</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-xl font-bold text-red-600">${{ number_format($balance['gastosManuales'], 2) }}</div>
        <div class="text-xs text-slate-500 uppercase tracking-wide mt-1">Gastos</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-xl font-bold text-green-600">${{ number_format($balance['utilidadNeta'], 2) }}</div>
        <div class="text-xs text-slate-500 uppercase tracking-wide mt-1">Utilidad neta</div>
    </div>
</div>
@endif

<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left">
            <tr>
                <th class="px-4 py-2">Tipo</th>
                <th class="px-4 py-2">Categoría</th>
                <th class="px-4 py-2 text-right">Monto</th>
                <th class="px-4 py-2">Moneda</th>
                <th class="px-4 py-2">Fecha</th>
                <th class="px-4 py-2">Comprobante</th>
                <th class="px-4 py-2">Registrado por</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($movimientos as $movimiento)
                <tr class="border-t border-slate-100">
                    <td class="px-4 py-2">
                        <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $movimiento['tipo'] === 'ingreso' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                            {{ ucfirst($movimiento['tipo']) }}
                        </span>
                    </td>
                    <td class="px-4 py-2">{{ $movimiento['categoria']['nombre'] ?? '—' }}</td>
                    <td class="px-4 py-2 text-right font-semibold">${{ number_format($movimiento['monto'], 2) }}</td>
                    <td class="px-4 py-2">{{ $movimiento['moneda'] ?? '—' }}</td>
                    <td class="px-4 py-2">{{ \Carbon\Carbon::parse($movimiento['fecha'])->format('d/m/Y') }}</td>
                    <td class="px-4 py-2">
                        @if (!empty($movimiento['comprobante_url']))
                            <a href="{{ $movimiento['comprobante_url'] }}" target="_blank" class="text-brand-600 hover:underline">Ver</a>
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-2">{{ $movimiento['registrado_por']['nombre'] ?? '—' }}</td>
                    <td class="px-4 py-2 text-right">
                        <form method="POST" action="{{ route('admin.presupuesto.destroy', [$evento['id'], $movimiento['id']]) }}" class="inline"
                              onsubmit="return confirm('¿Eliminar este movimiento?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-4 py-6 text-center text-slate-400">No hay movimientos registrados todavía.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="bg-white rounded-lg shadow p-4 max-w-lg">
    <h2 class="font-semibold mb-3">+ Nuevo movimiento</h2>
    <form method="POST" action="{{ route('admin.presupuesto.store', $evento['id']) }}" class="space-y-3" id="presupuesto-form">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">Categoría</label>
            <select name="presupuesto_categoria_id" id="presupuesto-categoria" required
                    class="border border-slate-300 rounded px-2 py-1.5 w-full">
                @foreach ($categorias as $categoria)
                    <option value="{{ $categoria['id'] }}" data-tipo="{{ $categoria['tipo'] }}">
                        {{ $categoria['nombre'] }} ({{ $categoria['tipo'] === 'ingreso' ? 'Ingreso' : 'Gasto' }})
                    </option>
                @endforeach
            </select>
            {{-- El tipo se deriva de la categoría elegida, no lo escribe el usuario a mano
                 (evita el 422 de "tipo no coincide con la categoría" del lado API). --}}
            <input type="hidden" name="tipo" id="presupuesto-tipo" value="{{ $categorias[0]['tipo'] ?? 'gasto' }}">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Monto</label>
            <input type="number" name="monto" step="0.01" min="0.01" required
                   class="border border-slate-300 rounded px-2 py-1.5 w-full">
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Moneda</label>
                <input type="text" name="moneda" placeholder="BOB" maxlength="10"
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Fecha</label>
                <input type="date" name="fecha" value="{{ now()->toDateString() }}" required
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">URL de comprobante (opcional)</label>
            <input type="url" name="comprobante_url" placeholder="https://..."
                   class="border border-slate-300 rounded px-2 py-1.5 w-full">
        </div>
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
            Registrar movimiento
        </button>
    </form>
</div>

<script>
document.getElementById('presupuesto-categoria').addEventListener('change', function () {
    var selected = this.options[this.selectedIndex];
    document.getElementById('presupuesto-tipo').value = selected.getAttribute('data-tipo') || 'gasto';
});
</script>
@endsection
