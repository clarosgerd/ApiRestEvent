<?php /* Precios por período de una categoría — ver
     ApiRestEvent/brain/api_rest_event/PRD-precios-periodos-fechas.md
     (sesión 12/08/2026). */ ?>
@extends('admin.layouts.app')

@section('title', 'Períodos de precio — ' . ($categoria['name'] ?? 'Categoría') . ' — Admin Eventos')

@section('content')
<div class="mb-4">
    @if ($eventoId)
        {{-- #categorias (12/08/2026) — eventos/edit.blade.php pasó a tabs,
             esto abre directo la pestaña de Categorías en vez de la primera. --}}
        <a href="{{ route('admin.eventos.edit', $eventoId) }}#categorias" class="text-sm text-brand-600 hover:underline">← Volver al evento</a>
    @endif
    <h1 class="text-lg font-bold mt-1">Períodos de precio — {{ $categoria['name'] ?? 'Categoría' }}</h1>
    <p class="text-sm text-slate-500">
        Precio vigente hoy:
        <strong>Bs {{ number_format($categoria['precio_vigente'] ?? $categoria['price'] ?? 0, 2) }}</strong>
        @if (!empty($categoria['periodo_vigente_nombre']))
            <span class="inline-block bg-red-50 text-red-700 text-xs font-semibold px-2 py-0.5 rounded-full ml-1">
                🔥 {{ $categoria['periodo_vigente_nombre'] }}
            </span>
        @endif
        @if (!is_null($categoria['precio_usd_vigente'] ?? null))
            <span class="text-slate-400">·</span>
            US$ {{ number_format($categoria['precio_usd_vigente'], 2) }}
        @endif
    </p>
    <p class="text-xs text-slate-400 mt-1">
        Una categoría sin ningún período acá cobra su precio base ({{ 'Bs ' . number_format($categoria['price'] ?? 0, 2) }})
        tal cual, sin cambios. Fuera de período (huecos entre fechas, o después del último) se cae al precio del
        período vencido más reciente — nunca se bloquea una venta por un hueco de configuración.
    </p>
    <p class="text-xs text-slate-400 mt-1">
        "Precio USD" es opcional por período — solo importa si el evento cobra en USD sin tipo de cambio
        (Configuración → Precio USD fijo). Un período sin USD cargado cae al precio USD base de la categoría
        ({{ !is_null($categoria['priceUsd'] ?? null) ? 'US$ '.number_format($categoria['priceUsd'], 2) : 'sin cargar' }}).
    </p>
</div>

<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left">
            <tr>
                <th class="px-4 py-2">Nombre</th>
                <th class="px-4 py-2">Precio</th>
                <th class="px-4 py-2">Precio USD <span class="font-normal text-slate-400">(opc.)</span></th>
                <th class="px-4 py-2">Desde</th>
                <th class="px-4 py-2">Hasta</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($periodos as $periodo)
                <tr class="border-t border-slate-100 {{ ($categoria['periodo_vigente_nombre'] ?? null) === $periodo['nombre'] ? 'bg-emerald-50' : '' }}">
                    <td class="px-4 py-2">
                        <input type="text" name="nombre" value="{{ $periodo['nombre'] }}" required
                               form="periodo-form-{{ $periodo['id'] }}" class="border border-slate-300 rounded px-2 py-1 w-32">
                    </td>
                    <td class="px-4 py-2">
                        <input type="number" step="0.01" min="0" name="price" value="{{ $periodo['price'] }}" required
                               form="periodo-form-{{ $periodo['id'] }}" class="border border-slate-300 rounded px-2 py-1 w-24">
                    </td>
                    <td class="px-4 py-2">
                        <input type="number" step="0.01" min="0" name="price_usd" value="{{ $periodo['price_usd'] }}" placeholder="opc."
                               form="periodo-form-{{ $periodo['id'] }}" class="border border-slate-300 rounded px-2 py-1 w-24">
                    </td>
                    <td class="px-4 py-2">
                        <input type="date" name="fecha_desde" value="{{ $periodo['fecha_desde'] }}" required
                               form="periodo-form-{{ $periodo['id'] }}" class="border border-slate-300 rounded px-2 py-1">
                    </td>
                    <td class="px-4 py-2">
                        <input type="date" name="fecha_hasta" value="{{ $periodo['fecha_hasta'] }}" required
                               form="periodo-form-{{ $periodo['id'] }}" class="border border-slate-300 rounded px-2 py-1">
                    </td>
                    <td class="px-4 py-2 text-right space-x-2">
                        <form method="POST" action="{{ route('admin.categorias.periodos.update', $periodo['id']) }}" id="periodo-form-{{ $periodo['id'] }}" class="inline">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="category_id" value="{{ $categoryId }}">
                            <input type="hidden" name="evento_id" value="{{ $eventoId }}">
                        </form>
                        <button type="submit" form="periodo-form-{{ $periodo['id'] }}" class="text-brand-600 hover:underline">Guardar</button>
                        <form method="POST" action="{{ route('admin.categorias.periodos.destroy', $periodo['id']) }}" class="inline"
                              onsubmit="return confirm('¿Eliminar este período? La categoría vuelve a su precio base para ese rango de fechas.')">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="category_id" value="{{ $categoryId }}">
                            <input type="hidden" name="evento_id" value="{{ $eventoId }}">
                            <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Sin períodos cargados — se cobra el precio base tal cual.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="bg-white rounded-lg shadow p-4 max-w-md">
    <h2 class="font-semibold mb-3">+ Nuevo período</h2>
    <form method="POST" action="{{ route('admin.categorias.periodos.store', $categoryId) }}" class="space-y-3">
        @csrf
        <input type="hidden" name="evento_id" value="{{ $eventoId }}">
        <div>
            <label class="block text-sm font-medium mb-1">Nombre</label>
            <input type="text" name="nombre" required class="border border-slate-300 rounded px-2 py-1.5 w-full" placeholder="Preventa">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Precio</label>
            <input type="number" name="price" step="0.01" min="0" required class="border border-slate-300 rounded px-2 py-1.5 w-full">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Precio USD <span class="font-normal text-slate-400">(opcional)</span></label>
            <input type="number" name="price_usd" step="0.01" min="0" placeholder="Solo si el evento cobra en USD fijo" class="border border-slate-300 rounded px-2 py-1.5 w-full">
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-sm font-medium mb-1">Desde</label>
                <input type="date" name="fecha_desde" required class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Hasta</label>
                <input type="date" name="fecha_hasta" required class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
        </div>
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
            Agregar
        </button>
    </form>
</div>
@endsection
