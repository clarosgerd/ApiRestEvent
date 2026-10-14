<?php /* Precios por período de un taller (12/10/2026, pedido del usuario) —
     mismo patrón que categorias/periodos.blade.php. */ ?>
@extends('admin.layouts.app')

@section('title', 'Períodos de precio — ' . ($taller['nombre'] ?? 'Taller') . ' — Admin Eventos')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.talleres.index', $eventoId) }}" class="text-sm text-brand-600 hover:underline">← Volver a talleres</a>
    <h1 class="text-lg font-bold mt-1">Períodos de precio — {{ $taller['nombre'] ?? 'Taller' }}</h1>
    <p class="text-sm text-slate-500">
        Precio vigente hoy:
        <strong>Bs {{ number_format($taller['precioVigente'] ?? $taller['precio'] ?? 0, 2) }}</strong>
        @if (!empty($taller['periodoVigenteNombre']))
            <span class="inline-block bg-red-50 text-red-700 text-xs font-semibold px-2 py-0.5 rounded-full ml-1">
                🔥 {{ $taller['periodoVigenteNombre'] }}
            </span>
        @endif
        @if (!is_null($taller['precioUsdVigente'] ?? null))
            <span class="text-slate-400">·</span>
            US$ {{ number_format($taller['precioUsdVigente'], 2) }}
        @endif
    </p>
    <p class="text-xs text-slate-400 mt-1">
        Un taller sin ningún período acá cobra su precio base ({{ 'Bs ' . number_format($taller['precio'] ?? 0, 2) }})
        tal cual, sin cambios — y si una sesión tiene un precio propio cargado (override), ese sigue ganando
        siempre, por encima de cualquier período. Fuera de período (huecos entre fechas, o después del último) se
        cae al precio del período vencido más reciente — nunca se bloquea una venta por un hueco de configuración.
    </p>
</div>

<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left">
            <tr>
                <th class="px-4 py-2">Nombre</th>
                <th class="px-4 py-2">Categoría</th>
                <th class="px-4 py-2">Precio</th>
                <th class="px-4 py-2">Precio USD <span class="font-normal text-slate-400">(opc.)</span></th>
                <th class="px-4 py-2">Desde</th>
                <th class="px-4 py-2">Hasta</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($periodos as $periodo)
                <tr class="border-t border-slate-100 {{ ($taller['periodoVigenteNombre'] ?? null) === $periodo['nombre'] ? 'bg-emerald-50' : '' }}">
                    <td class="px-4 py-2">
                        <input type="text" name="nombre" value="{{ $periodo['nombre'] }}" required
                               form="periodo-form-{{ $periodo['id'] }}" class="border border-slate-300 rounded px-2 py-1 w-32">
                    </td>
                    <td class="px-4 py-2">
                        {{-- Período por categoría (13/10/2026) — opcional; sin
                             elegir, sigue siendo general (aplica a cualquier
                             categoría sin período propio). --}}
                        <select name="category_id" form="periodo-form-{{ $periodo['id'] }}" class="border border-slate-300 rounded px-2 py-1 text-sm">
                            <option value="">— General —</option>
                            @foreach ($categorias as $categoria)
                                <option value="{{ $categoria['id'] }}" @selected(($periodo['categoriaId'] ?? null) == $categoria['id'])>{{ $categoria['name'] }}</option>
                            @endforeach
                        </select>
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
                        <form method="POST" action="{{ route('admin.talleres.periodos.update', [$eventoId, $tallerId, $periodo['id']]) }}" id="periodo-form-{{ $periodo['id'] }}" class="inline">
                            @csrf
                            @method('PUT')
                        </form>
                        <button type="submit" form="periodo-form-{{ $periodo['id'] }}" class="text-brand-600 hover:underline">Guardar</button>
                        <form method="POST" action="{{ route('admin.talleres.periodos.destroy', [$eventoId, $tallerId, $periodo['id']]) }}" class="inline"
                              onsubmit="return confirm('¿Eliminar este período? El taller vuelve a su precio base para ese rango de fechas.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-6 text-center text-slate-400">Sin períodos cargados — se cobra el precio base tal cual.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="bg-white rounded-lg shadow p-4 max-w-md">
    <h2 class="font-semibold mb-3">+ Nuevo período</h2>
    <form method="POST" action="{{ route('admin.talleres.periodos.store', [$eventoId, $tallerId]) }}" class="space-y-3">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">Nombre</label>
            <input type="text" name="nombre" required class="border border-slate-300 rounded px-2 py-1.5 w-full" placeholder="Preventa">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Categoría <span class="font-normal text-slate-400">(opcional)</span></label>
            <select name="category_id" class="border border-slate-300 rounded px-2 py-1.5 w-full">
                <option value="">— General (todas las categorías) —</option>
                @foreach ($categorias as $categoria)
                    <option value="{{ $categoria['id'] }}">{{ $categoria['name'] }}</option>
                @endforeach
            </select>
            <p class="text-xs text-slate-400 mt-1">
                Con una categoría elegida, este período solo afecta a esa categoría (nunca mira el general como
                respaldo). Sin elegir, sigue siendo general.
            </p>
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
