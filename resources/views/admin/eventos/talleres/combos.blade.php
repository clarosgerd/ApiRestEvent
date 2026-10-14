<?php /* Combos de talleres (12/10/2026, pedido del usuario) — "Taller A + Taller B
     por el precio de 1" o "por $300 fijo". Precio por categoría (13/10/2026) —
     el mismo combo puede costar distinto según la categoría del participante. */ ?>
@extends('admin.layouts.app')

@section('title', 'Combos de talleres — '.$evento['name'])

@section('content')
<div class="flex justify-between items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="text-lg font-bold">Combos de talleres: {{ $evento['name'] }}</h1>
        <p class="text-sm text-slate-500">
            Ej. "Taller A + Taller B por el precio de 1", o por un precio fijo. El combo se aplica cuando el
            participante elige TODOS los talleres marcados acá, juntos, en la misma inscripción o edición — nunca
            retroactivo sobre un taller que ya pagó de antes. El mismo combo puede cobrar distinto según la
            categoría del participante — cargá el precio de cada categoría abajo, en "Editar" → "Precios por
            categoría".
        </p>
    </div>
    <a href="{{ route('admin.talleres.index', $evento['id']) }}" class="text-sm text-brand-600 hover:underline self-center">
        ← Volver a talleres
    </a>
</div>

@php
    $nombrePorTaller = collect($talleres)->keyBy('id')->map(fn ($t) => $t['nombre']);
    $nombrePorCategoria = collect($categorias)->keyBy('id')->map(fn ($c) => $c['name']);
@endphp

<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left">
            <tr>
                <th class="px-4 py-2">Nombre</th>
                <th class="px-4 py-2">Talleres</th>
                <th class="px-4 py-2">Precios por categoría</th>
                <th class="px-4 py-2">Vigencia</th>
                <th class="px-4 py-2">Estado</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($combos as $combo)
                <tr class="border-t border-slate-100 align-top">
                    <td class="px-4 py-2 font-semibold">{{ $combo['nombre'] }}</td>
                    <td class="px-4 py-2">
                        <div class="flex flex-wrap gap-1">
                            @foreach ($combo['tallerIds'] ?? [] as $tid)
                                <span class="inline-block text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">
                                    {{ $nombrePorTaller[$tid] ?? "#$tid" }}
                                </span>
                            @endforeach
                        </div>
                    </td>
                    <td class="px-4 py-2">
                        @forelse ($combo['precios'] ?? [] as $precio)
                            <div class="text-xs">
                                {{ $precio['categoriaNombre'] ?? 'Todas las demás categorías' }}:
                                <strong>Bs {{ number_format($precio['precio'], 2) }}</strong>
                                @if (!is_null($precio['priceUsd'] ?? null))
                                    <span class="text-slate-400">·</span> US$ {{ number_format($precio['priceUsd'], 2) }}
                                @endif
                            </div>
                        @empty
                            <span class="text-xs text-amber-600">Sin precios — no se ofrece a nadie todavía</span>
                        @endforelse
                    </td>
                    <td class="px-4 py-2 text-xs">
                        @if ($combo['fechaDesde'] || $combo['fechaHasta'])
                            {{ $combo['fechaDesde'] ?? '…' }} → {{ $combo['fechaHasta'] ?? '…' }}
                        @else
                            <span class="text-slate-400">Sin vencimiento</span>
                        @endif
                    </td>
                    <td class="px-4 py-2">
                        @if (!$combo['activo'])
                            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">Inactivo</span>
                        @elseif ($combo['vigente'])
                            <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">Vigente</span>
                        @else
                            <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">Fuera de fecha</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-right space-x-2 whitespace-nowrap">
                        <a href="#edit-combo-{{ $combo['id'] }}" class="text-brand-600 hover:underline">Editar</a>
                        <form method="POST" action="{{ route('admin.taller-combos.destroy', [$evento['id'], $combo['id']]) }}" class="inline"
                              onsubmit="return confirm('¿Eliminar este combo? Las inscripciones ya cobradas con este combo no cambian.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">No hay combos de talleres todavía.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@php($tallerIdsUsados = collect($combos)->pluck('tallerIds')->flatten()->countBy())

{{-- Formularios de edición inline --}}
@foreach ($combos as $combo)
<div id="edit-combo-{{ $combo['id'] }}" class="bg-white rounded-lg shadow p-4 mb-4 max-w-lg">
    <h2 class="font-semibold mb-3">Editar: {{ $combo['nombre'] }}</h2>
    <form method="POST" action="{{ route('admin.taller-combos.update', [$evento['id'], $combo['id']]) }}" class="space-y-3">
        @csrf
        @method('PUT')
        <div>
            <label class="block text-sm font-medium mb-1">Nombre</label>
            <input type="text" name="nombre" value="{{ old('nombre', $combo['nombre']) }}" required
                   class="border border-slate-300 rounded px-2 py-1.5 w-full">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Talleres incluidos (mínimo 2)</label>
            <div class="border border-slate-300 rounded px-3 py-2 space-y-1 max-h-40 overflow-y-auto">
                @foreach ($talleres as $taller)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="taller_ids[]" value="{{ $taller['id'] }}"
                               @checked(in_array($taller['id'], $combo['tallerIds'] ?? []))>
                        {{ $taller['nombre'] }}
                        @if (($tallerIdsUsados[$taller['id']] ?? 0) > 1)
                            <span class="text-xs text-amber-600">(en más de un combo)</span>
                        @endif
                    </label>
                @endforeach
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Desde <span class="font-normal text-slate-400">(opc.)</span></label>
                <input type="date" name="fecha_desde" value="{{ old('fecha_desde', $combo['fechaDesde']) }}"
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Hasta <span class="font-normal text-slate-400">(opc.)</span></label>
                <input type="date" name="fecha_hasta" value="{{ old('fecha_hasta', $combo['fechaHasta']) }}"
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="activo" value="0">
            <input type="checkbox" name="activo" value="1" @checked(old('activo', $combo['activo']))>
            Activo
        </label>
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
            Guardar cambios
        </button>
    </form>

    {{-- Precios por categoría (13/10/2026, pedido del usuario) — mismo
         patrón que eventos/talleres/periodos.blade.php, cambiando fecha
         por categoría. Formularios independientes del de arriba: cada
         fila de precio se guarda aparte. --}}
    <hr class="my-4 border-slate-200">
    <h3 class="font-semibold text-sm mb-2">Precios por categoría</h3>
    <table class="w-full text-sm mb-3">
        <thead class="bg-slate-50 text-left">
            <tr>
                <th class="px-2 py-1">Categoría</th>
                <th class="px-2 py-1">Precio</th>
                <th class="px-2 py-1">Precio USD <span class="font-normal text-slate-400">(opc.)</span></th>
                <th class="px-2 py-1"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($combo['precios'] ?? [] as $precio)
                <tr class="border-t border-slate-100">
                    <td class="px-2 py-1">
                        {{ $precio['categoriaNombre'] ?? 'Todas las demás categorías' }}
                    </td>
                    <td class="px-2 py-1">
                        <input type="number" step="0.01" min="0" name="precio" value="{{ $precio['precio'] }}" required
                               form="combo-precio-form-{{ $precio['id'] }}" class="border border-slate-300 rounded px-2 py-1 w-24">
                    </td>
                    <td class="px-2 py-1">
                        <input type="number" step="0.01" min="0" name="price_usd" value="{{ $precio['priceUsd'] }}" placeholder="opc."
                               form="combo-precio-form-{{ $precio['id'] }}" class="border border-slate-300 rounded px-2 py-1 w-24">
                    </td>
                    <td class="px-2 py-1 text-right space-x-2 whitespace-nowrap">
                        <form method="POST" action="{{ route('admin.taller-combos.precios.update', [$evento['id'], $combo['id'], $precio['id']]) }}"
                              id="combo-precio-form-{{ $precio['id'] }}" class="inline">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="category_id" value="{{ $precio['categoriaId'] }}">
                        </form>
                        <button type="submit" form="combo-precio-form-{{ $precio['id'] }}" class="text-brand-600 hover:underline">Guardar</button>
                        <form method="POST" action="{{ route('admin.taller-combos.precios.destroy', [$evento['id'], $combo['id'], $precio['id']]) }}" class="inline"
                              onsubmit="return confirm('¿Eliminar este precio?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-2 py-3 text-center text-slate-400 text-xs">Sin precios cargados — este combo no se ofrece a nadie todavía.</td></tr>
            @endforelse
        </tbody>
    </table>
    <form method="POST" action="{{ route('admin.taller-combos.precios.store', [$evento['id'], $combo['id']]) }}" class="flex items-end gap-2">
        @csrf
        <div class="flex-1">
            <label class="block text-xs font-medium mb-1">Categoría</label>
            <select name="category_id" class="border border-slate-300 rounded px-2 py-1.5 w-full text-sm">
                <option value="">— Precio por default (categorías no listadas) —</option>
                @foreach ($categorias as $categoria)
                    <option value="{{ $categoria['id'] }}">{{ $categoria['name'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium mb-1">Precio</label>
            <input type="number" step="0.01" min="0" name="precio" required class="border border-slate-300 rounded px-2 py-1.5 w-24">
        </div>
        <div>
            <label class="block text-xs font-medium mb-1">Precio USD <span class="font-normal text-slate-400">(opc.)</span></label>
            <input type="number" step="0.01" min="0" name="price_usd" placeholder="Solo modo Precio USD fijo" class="border border-slate-300 rounded px-2 py-1.5 w-24">
        </div>
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-1.5 rounded-md">
            + Agregar
        </button>
    </form>
</div>
@endforeach

<div class="bg-white rounded-lg shadow p-4 max-w-lg">
    <h2 class="font-semibold mb-3">+ Nuevo combo</h2>
    <p class="text-xs text-slate-500 mb-3">
        El precio se carga después de crear el combo, en "Editar" → "Precios por categoría" (el mismo combo
        puede costar distinto según la categoría).
    </p>
    <form method="POST" action="{{ route('admin.taller-combos.store', $evento['id']) }}" class="space-y-3">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">Nombre</label>
            <input type="text" name="nombre" value="{{ old('nombre') }}" required
                   placeholder="Ej. 2x1 Taller A + Taller B"
                   class="border border-slate-300 rounded px-2 py-1.5 w-full">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Talleres incluidos (mínimo 2)</label>
            <div class="border border-slate-300 rounded px-3 py-2 space-y-1 max-h-40 overflow-y-auto">
                @forelse ($talleres as $taller)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="taller_ids[]" value="{{ $taller['id'] }}">
                        {{ $taller['nombre'] }}
                        @if (($tallerIdsUsados[$taller['id']] ?? 0) > 0)
                            <span class="text-xs text-amber-600">(ya está en otro combo)</span>
                        @endif
                    </label>
                @empty
                    <p class="text-sm text-slate-400">Este evento todavía no tiene talleres cargados.</p>
                @endforelse
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Desde <span class="font-normal text-slate-400">(opc.)</span></label>
                <input type="date" name="fecha_desde" value="{{ old('fecha_desde') }}"
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Hasta <span class="font-normal text-slate-400">(opc.)</span></label>
                <input type="date" name="fecha_hasta" value="{{ old('fecha_hasta') }}"
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="activo" value="1" @checked(old('activo', true))>
            Activo
        </label>
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
            Crear combo
        </button>
    </form>
</div>
@endsection
