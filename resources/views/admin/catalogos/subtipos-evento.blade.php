<?php /* Catálogo de subtipo de evento — administración (super_admin). Ver elascenso/event/brain/PLAN-CATALOGOS-GLOBALES-15082026.md. */ ?>
@extends('admin.layouts.app')

@section('title', 'Subtipo de evento — Catálogos')

@section('content')
<div class="flex justify-between items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="text-lg font-bold">Subtipo de evento</h1>
        <p class="text-sm text-slate-500">
            <a href="{{ route('admin.catalogos.index') }}" class="text-brand-600 hover:underline">← Catálogos</a>
            · <a href="{{ route('admin.catalogos.tipos-evento.index') }}" class="text-brand-600 hover:underline">Ver tipos →</a>
        </p>
    </div>
</div>

@if (session('status'))
    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-2 rounded-md mb-5 text-sm">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-2 rounded-md mb-5 text-sm">{{ $errors->first() }}</div>
@endif

<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left">
            <tr>
                <th class="px-4 py-2">Nombre</th>
                <th class="px-4 py-2">Tipo de evento</th>
                <th class="px-4 py-2">Activo</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($subtipos as $subtipo)
                <tr class="border-t border-slate-100">
                    <td class="px-4 py-2">
                        <input type="text" name="nombre" value="{{ $subtipo['nombre'] }}" required
                               form="subtipo-form-{{ $subtipo['id'] }}"
                               class="border border-slate-300 rounded px-2 py-1 w-full">
                    </td>
                    <td class="px-4 py-2">
                        <select name="tipo_evento_id" required form="subtipo-form-{{ $subtipo['id'] }}"
                                class="border border-slate-300 rounded px-2 py-1">
                            @foreach ($tipos as $tipo)
                                <option value="{{ $tipo['id'] }}" @selected($subtipo['tipo_evento_id'] == $tipo['id'])>{{ $tipo['nombre'] }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td class="px-4 py-2">
                        <input type="hidden" name="activo" value="0" form="subtipo-form-{{ $subtipo['id'] }}">
                        <input type="checkbox" name="activo" value="1" {{ $subtipo['activo'] ? 'checked' : '' }}
                               form="subtipo-form-{{ $subtipo['id'] }}">
                    </td>
                    <td class="px-4 py-2 text-right space-x-2">
                        <form method="POST" action="{{ route('admin.catalogos.subtipos-evento.update', $subtipo['id']) }}" id="subtipo-form-{{ $subtipo['id'] }}" class="inline">
                            @csrf
                            @method('PUT')
                        </form>
                        <button type="submit" form="subtipo-form-{{ $subtipo['id'] }}" class="text-brand-600 hover:underline">Guardar</button>
                        <form method="POST" action="{{ route('admin.catalogos.subtipos-evento.destroy', $subtipo['id']) }}" class="inline"
                              onsubmit="return confirm('¿Eliminar este subtipo? Solo se puede si no tiene eventos asociados.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">No hay subtipos registrados.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="bg-white rounded-lg shadow p-4 max-w-md">
    <h2 class="font-semibold mb-3">+ Nuevo subtipo</h2>
    <form method="POST" action="{{ route('admin.catalogos.subtipos-evento.store') }}" class="space-y-3">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">Tipo de evento</label>
            <select name="tipo_evento_id" required class="border border-slate-300 rounded px-2 py-1.5 w-full">
                @foreach ($tipos as $tipo)
                    <option value="{{ $tipo['id'] }}">{{ $tipo['nombre'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Nombre</label>
            <input type="text" name="nombre" value="{{ old('nombre') }}" required
                   class="border border-slate-300 rounded px-2 py-1.5 w-full">
        </div>
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
            Crear subtipo
        </button>
    </form>
</div>
@endsection
