<?php /* Catálogo de tipo de evento — administración (super_admin). El público GET /tipos-evento (sin auth) sigue igual, no lo toca esta pantalla. Ver elascenso/event/brain/PLAN-CATALOGOS-GLOBALES-15082026.md. */ ?>
@extends('admin.layouts.app')

@section('title', 'Tipo de evento — Catálogos')

@section('content')
<div class="flex justify-between items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="text-lg font-bold">Tipo de evento</h1>
        <p class="text-sm text-slate-500">
            <a href="{{ route('admin.catalogos.index') }}" class="text-brand-600 hover:underline">← Catálogos</a>
            · <a href="{{ route('admin.catalogos.subtipos-evento.index') }}" class="text-brand-600 hover:underline">Ver subtipos →</a>
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
                <th class="px-4 py-2">Ícono</th>
                <th class="px-4 py-2">Activo</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($tipos as $tipo)
                <tr class="border-t border-slate-100">
                    <td class="px-4 py-2">
                        <input type="text" name="nombre" value="{{ $tipo['nombre'] }}" required
                               form="tipo-form-{{ $tipo['id'] }}"
                               class="border border-slate-300 rounded px-2 py-1 w-full">
                    </td>
                    <td class="px-4 py-2">
                        <input type="text" name="icono" value="{{ $tipo['icono'] }}"
                               form="tipo-form-{{ $tipo['id'] }}"
                               class="border border-slate-300 rounded px-2 py-1 w-32">
                    </td>
                    <td class="px-4 py-2">
                        <input type="hidden" name="activo" value="0" form="tipo-form-{{ $tipo['id'] }}">
                        <input type="checkbox" name="activo" value="1" {{ $tipo['activo'] ? 'checked' : '' }}
                               form="tipo-form-{{ $tipo['id'] }}">
                    </td>
                    <td class="px-4 py-2 text-right space-x-2">
                        <form method="POST" action="{{ route('admin.catalogos.tipos-evento.update', $tipo['id']) }}" id="tipo-form-{{ $tipo['id'] }}" class="inline">
                            @csrf
                            @method('PUT')
                        </form>
                        <button type="submit" form="tipo-form-{{ $tipo['id'] }}" class="text-brand-600 hover:underline">Guardar</button>
                        <form method="POST" action="{{ route('admin.catalogos.tipos-evento.destroy', $tipo['id']) }}" class="inline"
                              onsubmit="return confirm('¿Eliminar este tipo de evento? Solo se puede si no tiene subtipos ni eventos asociados.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">No hay tipos de evento registrados.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="bg-white rounded-lg shadow p-4 max-w-md">
    <h2 class="font-semibold mb-3">+ Nuevo tipo de evento</h2>
    <form method="POST" action="{{ route('admin.catalogos.tipos-evento.store') }}" class="space-y-3">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">Nombre</label>
            <input type="text" name="nombre" value="{{ old('nombre') }}" required
                   class="border border-slate-300 rounded px-2 py-1.5 w-full">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Ícono (opcional)</label>
            <input type="text" name="icono" value="{{ old('icono') }}"
                   class="border border-slate-300 rounded px-2 py-1.5 w-full">
        </div>
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
            Crear tipo de evento
        </button>
    </form>
</div>
@endsection
