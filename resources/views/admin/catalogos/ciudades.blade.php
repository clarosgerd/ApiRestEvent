<?php /* Catálogo de ciudad — administración (super_admin). Ver elascenso/event/brain/PLAN-CATALOGOS-GLOBALES-15082026.md. */ ?>
@extends('admin.layouts.app')

@section('title', 'Ciudad — Catálogos')

@section('content')
<div class="flex justify-between items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="text-lg font-bold">Ciudad</h1>
        <p class="text-sm text-slate-500">
            <a href="{{ route('admin.catalogos.index') }}" class="text-brand-600 hover:underline">← Catálogos</a>
            · <a href="{{ route('admin.catalogos.paises.index') }}" class="text-brand-600 hover:underline">Ver países →</a>
        </p>
    </div>
</div>

@if (session('status'))
    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-2 rounded-md mb-5 text-sm">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-2 rounded-md mb-5 text-sm">{{ $errors->first() }}</div>
@endif

@if (count($paises) === 0)
    <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-2 rounded-md mb-5 text-sm">
        No hay países cargados todavía — <a href="{{ route('admin.catalogos.paises.index') }}" class="underline">creá uno primero</a>.
    </div>
@endif

<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left">
            <tr>
                <th class="px-4 py-2">Nombre</th>
                <th class="px-4 py-2">País</th>
                <th class="px-4 py-2">Activo</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($ciudades as $ciudad)
                <tr class="border-t border-slate-100">
                    <td class="px-4 py-2">
                        <input type="text" name="nombre" value="{{ $ciudad['nombre'] }}" required
                               form="ciudad-form-{{ $ciudad['id'] }}"
                               class="border border-slate-300 rounded px-2 py-1 w-full">
                    </td>
                    <td class="px-4 py-2">
                        <select name="pais_id" required form="ciudad-form-{{ $ciudad['id'] }}"
                                class="border border-slate-300 rounded px-2 py-1">
                            @foreach ($paises as $pais)
                                <option value="{{ $pais['id'] }}" @selected($ciudad['pais_id'] == $pais['id'])>{{ $pais['nombre'] }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td class="px-4 py-2">
                        <input type="hidden" name="activo" value="0" form="ciudad-form-{{ $ciudad['id'] }}">
                        <input type="checkbox" name="activo" value="1" {{ $ciudad['activo'] ? 'checked' : '' }}
                               form="ciudad-form-{{ $ciudad['id'] }}">
                    </td>
                    <td class="px-4 py-2 text-right space-x-2">
                        <form method="POST" action="{{ route('admin.catalogos.ciudades.update', $ciudad['id']) }}" id="ciudad-form-{{ $ciudad['id'] }}" class="inline">
                            @csrf
                            @method('PUT')
                        </form>
                        <button type="submit" form="ciudad-form-{{ $ciudad['id'] }}" class="text-brand-600 hover:underline">Guardar</button>
                        <form method="POST" action="{{ route('admin.catalogos.ciudades.destroy', $ciudad['id']) }}" class="inline"
                              onsubmit="return confirm('¿Eliminar esta ciudad? Solo se puede si no tiene organizadores asociados.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">No hay ciudades registradas.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if (count($paises) > 0)
<div class="bg-white rounded-lg shadow p-4 max-w-md">
    <h2 class="font-semibold mb-3">+ Nueva ciudad</h2>
    <form method="POST" action="{{ route('admin.catalogos.ciudades.store') }}" class="space-y-3">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">País</label>
            <select name="pais_id" required class="border border-slate-300 rounded px-2 py-1.5 w-full">
                @foreach ($paises as $pais)
                    <option value="{{ $pais['id'] }}">{{ $pais['nombre'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Nombre</label>
            <input type="text" name="nombre" value="{{ old('nombre') }}" required
                   class="border border-slate-300 rounded px-2 py-1.5 w-full">
        </div>
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
            Crear ciudad
        </button>
    </form>
</div>
@endif
@endsection
