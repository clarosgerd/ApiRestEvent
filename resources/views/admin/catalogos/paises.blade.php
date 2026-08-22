<?php /* Catálogo de país — administración (super_admin). Ver elascenso/event/brain/PLAN-CATALOGOS-GLOBALES-15082026.md. */ ?>
@extends('admin.layouts.app')

@section('title', 'País — Catálogos')

@section('content')
<div class="flex justify-between items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="text-lg font-bold">País</h1>
        <p class="text-sm text-slate-500">
            <a href="{{ route('admin.catalogos.index') }}" class="text-brand-600 hover:underline">← Catálogos</a>
            · <a href="{{ route('admin.catalogos.ciudades.index') }}" class="text-brand-600 hover:underline">Ver ciudades →</a>
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
                <th class="px-4 py-2">ISO2</th>
                <th class="px-4 py-2">ISO3</th>
                <th class="px-4 py-2">Prefijo tel.</th>
                <th class="px-4 py-2">Activo</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($paises as $pais)
                <tr class="border-t border-slate-100">
                    <td class="px-4 py-2">
                        <input type="text" name="nombre" value="{{ $pais['nombre'] }}" required
                               form="pais-form-{{ $pais['id'] }}"
                               class="border border-slate-300 rounded px-2 py-1 w-full">
                    </td>
                    <td class="px-4 py-2">
                        <input type="text" name="iso2" value="{{ $pais['iso2'] }}" maxlength="2" required
                               form="pais-form-{{ $pais['id'] }}"
                               class="border border-slate-300 rounded px-2 py-1 w-16 uppercase">
                    </td>
                    <td class="px-4 py-2">
                        <input type="text" name="iso3" value="{{ $pais['iso3'] }}" maxlength="3"
                               form="pais-form-{{ $pais['id'] }}"
                               class="border border-slate-300 rounded px-2 py-1 w-16 uppercase">
                    </td>
                    <td class="px-4 py-2">
                        <input type="text" name="prefijo_tel" value="{{ $pais['prefijo_tel'] }}" maxlength="6"
                               form="pais-form-{{ $pais['id'] }}"
                               class="border border-slate-300 rounded px-2 py-1 w-20">
                    </td>
                    <td class="px-4 py-2">
                        <input type="hidden" name="activo" value="0" form="pais-form-{{ $pais['id'] }}">
                        <input type="checkbox" name="activo" value="1" {{ $pais['activo'] ? 'checked' : '' }}
                               form="pais-form-{{ $pais['id'] }}">
                    </td>
                    <td class="px-4 py-2 text-right space-x-2">
                        <form method="POST" action="{{ route('admin.catalogos.paises.update', $pais['id']) }}" id="pais-form-{{ $pais['id'] }}" class="inline">
                            @csrf
                            @method('PUT')
                        </form>
                        <button type="submit" form="pais-form-{{ $pais['id'] }}" class="text-brand-600 hover:underline">Guardar</button>
                        <form method="POST" action="{{ route('admin.catalogos.paises.destroy', $pais['id']) }}" class="inline"
                              onsubmit="return confirm('¿Eliminar este país? Solo se puede si no tiene ciudades ni organizadores asociados.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">No hay países registrados.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="bg-white rounded-lg shadow p-4 max-w-md">
    <h2 class="font-semibold mb-3">+ Nuevo país</h2>
    <form method="POST" action="{{ route('admin.catalogos.paises.store') }}" class="space-y-3">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">Nombre</label>
            <input type="text" name="nombre" value="{{ old('nombre') }}" required
                   class="border border-slate-300 rounded px-2 py-1.5 w-full">
        </div>
        <div class="grid grid-cols-3 gap-2">
            <div>
                <label class="block text-sm font-medium mb-1">ISO2</label>
                <input type="text" name="iso2" value="{{ old('iso2') }}" maxlength="2" required
                       class="border border-slate-300 rounded px-2 py-1.5 w-full uppercase">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">ISO3</label>
                <input type="text" name="iso3" value="{{ old('iso3') }}" maxlength="3"
                       class="border border-slate-300 rounded px-2 py-1.5 w-full uppercase">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Prefijo tel.</label>
                <input type="text" name="prefijo_tel" value="{{ old('prefijo_tel') }}" maxlength="6"
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
        </div>
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
            Crear país
        </button>
    </form>
</div>
@endsection
