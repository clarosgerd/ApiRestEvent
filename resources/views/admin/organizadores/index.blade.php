<?php /* CRUD de organizadores — entidad de negocio dueña de uno o varios eventos. Ver PRD-organizadores-crud.md (sesión 11/08/2026). */ ?>
@extends('admin.layouts.app')

@section('title', 'Organizadores — Admin Eventos')

@section('content')
<div class="mb-4">
    <h1 class="text-lg font-bold">Organizadores</h1>
    <p class="text-sm text-slate-500">
        Cada evento se asigna a un organizador al crearlo — una vez publicado, no se puede reasignar.
        <a href="{{ route('admin.dashboard') }}" class="text-brand-600 hover:underline">Ver eventos →</a>
    </p>
</div>

<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left">
            <tr>
                <th class="px-4 py-2">Razón social</th>
                <th class="px-4 py-2">Nombre comercial</th>
                <th class="px-4 py-2">RUT/NIT</th>
                <th class="px-4 py-2">Email</th>
                <th class="px-4 py-2">Teléfono</th>
                <th class="px-4 py-2">Activo</th>
                <th class="px-4 py-2">Eventos</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($organizadores as $org)
                {{-- Mismo patrón que socios/index.blade.php: el <form> de edición
                     vive vacío en la última <td>, los inputs de las otras <td> se
                     asocian por form="" (un <form> no puede envolver solo algunas
                     <td> dentro de un <tr>). --}}
                <tr class="border-t border-slate-100 align-top">
                    <td class="px-4 py-2">
                        <input type="text" name="razon_social" value="{{ $org['razon_social'] }}" required
                               form="org-form-{{ $org['id'] }}"
                               class="border border-slate-300 rounded px-2 py-1 w-full">
                    </td>
                    <td class="px-4 py-2">
                        <input type="text" name="nombre_comercial" value="{{ $org['nombre_comercial'] }}"
                               form="org-form-{{ $org['id'] }}"
                               class="border border-slate-300 rounded px-2 py-1 w-full">
                    </td>
                    <td class="px-4 py-2">
                        <input type="text" name="rut_nit" value="{{ $org['rut_nit'] }}"
                               form="org-form-{{ $org['id'] }}"
                               class="border border-slate-300 rounded px-2 py-1 w-28">
                    </td>
                    <td class="px-4 py-2">
                        <input type="email" name="email" value="{{ $org['email'] }}" required
                               form="org-form-{{ $org['id'] }}"
                               class="border border-slate-300 rounded px-2 py-1 w-full">
                    </td>
                    <td class="px-4 py-2">
                        <input type="text" name="telefono" value="{{ $org['telefono'] }}"
                               form="org-form-{{ $org['id'] }}"
                               class="border border-slate-300 rounded px-2 py-1 w-28">
                    </td>
                    <td class="px-4 py-2">
                        <input type="hidden" name="activo" value="0" form="org-form-{{ $org['id'] }}">
                        <input type="checkbox" name="activo" value="1" {{ $org['activo'] ? 'checked' : '' }}
                               form="org-form-{{ $org['id'] }}">
                    </td>
                    <td class="px-4 py-2 text-slate-500">{{ $org['eventos_count'] ?? 0 }}</td>
                    <td class="px-4 py-2 text-right whitespace-nowrap space-x-2">
                        <form method="POST" action="{{ route('admin.organizadores.update', $org['id']) }}" id="org-form-{{ $org['id'] }}" class="inline">
                            @csrf
                            @method('PUT')
                        </form>
                        <button type="submit" form="org-form-{{ $org['id'] }}" class="text-brand-600 hover:underline">Guardar</button>
                        <a href="{{ route('admin.organizadores.formas-pago', $org['id']) }}" class="text-brand-600 hover:underline">Formas de pago</a>
                        <form method="POST" action="{{ route('admin.organizadores.destroy', $org['id']) }}" class="inline"
                              onsubmit="return confirm('¿Eliminar este organizador? Solo se puede si no tiene eventos asociados.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-4 py-6 text-center text-slate-400">No hay organizadores registrados.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="bg-white rounded-lg shadow p-4 max-w-2xl">
    <h2 class="font-semibold mb-3">+ Nuevo organizador</h2>
    <form method="POST" action="{{ route('admin.organizadores.store') }}" class="space-y-3">
        @csrf
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Razón social</label>
                <input type="text" name="razon_social" value="{{ old('razon_social') }}" required
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Nombre comercial <span class="text-slate-400">(opcional)</span></label>
                <input type="text" name="nombre_comercial" value="{{ old('nombre_comercial') }}"
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">RUT/NIT <span class="text-slate-400">(opcional)</span></label>
                <input type="text" name="rut_nit" value="{{ old('rut_nit') }}"
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Teléfono <span class="text-slate-400">(opcional)</span></label>
                <input type="text" name="telefono" value="{{ old('telefono') }}"
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Dirección <span class="text-slate-400">(opcional)</span></label>
                <input type="text" name="direccion" value="{{ old('direccion') }}"
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
        </div>
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
            Crear organizador
        </button>
    </form>
</div>
@endsection
