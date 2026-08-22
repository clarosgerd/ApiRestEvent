<?php /* Socios de PassToGo — config global para la liquidación de utilidades. Ver elascenso/event/brain/ (sesión 11/08/2026). */ ?>
@extends('admin.layouts.app')

@section('title', 'Socios — Admin Eventos')

@section('content')
<div class="flex justify-between items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="text-lg font-bold">Socios</h1>
        <p class="text-sm text-slate-500">
            % de reparto del service fee al liquidar un evento cerrado.
            <a href="{{ route('admin.dashboard') }}" class="text-brand-600 hover:underline">Ver eventos →</a>
        </p>
    </div>
    <span class="text-sm font-semibold px-3 py-1.5 rounded-md {{ abs($porcentajeTotal - 100) < 0.01 ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700' }}">
        Suma de activos: {{ number_format($porcentajeTotal, 2) }}%
        @if (abs($porcentajeTotal - 100) >= 0.01)
            — debe ser 100% para poder liquidar
        @endif
    </span>
</div>

<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left">
            <tr>
                <th class="px-4 py-2">Nombre</th>
                <th class="px-4 py-2">Porcentaje</th>
                <th class="px-4 py-2">Activo</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($socios as $socio)
                {{-- Un <form> no puede envolver solo algunas <td> dentro de un <tr>
                     (HTML inválido, el navegador lo reubica) — el <form> de edición
                     vive vacío dentro de la última <td> (solo csrf/method), y los
                     inputs de las otras <td> se asocian por el atributo form="". --}}
                <tr class="border-t border-slate-100">
                    <td class="px-4 py-2">
                        <input type="text" name="nombre" value="{{ $socio['nombre'] }}" required
                               form="socio-form-{{ $socio['id'] }}"
                               class="border border-slate-300 rounded px-2 py-1 w-full">
                    </td>
                    <td class="px-4 py-2">
                        <input type="number" name="porcentaje" value="{{ $socio['porcentaje'] }}" step="0.01" min="0" max="100" required
                               form="socio-form-{{ $socio['id'] }}"
                               class="border border-slate-300 rounded px-2 py-1 w-24">
                    </td>
                    <td class="px-4 py-2">
                        {{-- hidden 0 antes del checkbox: si queda sin marcar, igual
                             se manda activo=0 (si no, un checkbox sin marcar no
                             manda nada y nunca se podría desactivar un socio). --}}
                        <input type="hidden" name="activo" value="0" form="socio-form-{{ $socio['id'] }}">
                        <input type="checkbox" name="activo" value="1" {{ $socio['activo'] ? 'checked' : '' }}
                               form="socio-form-{{ $socio['id'] }}">
                    </td>
                    <td class="px-4 py-2 text-right space-x-2">
                        <form method="POST" action="{{ route('admin.socios.update', $socio['id']) }}" id="socio-form-{{ $socio['id'] }}" class="inline">
                            @csrf
                            @method('PUT')
                        </form>
                        <button type="submit" form="socio-form-{{ $socio['id'] }}" class="text-brand-600 hover:underline">Guardar</button>
                        <form method="POST" action="{{ route('admin.socios.destroy', $socio['id']) }}" class="inline"
                              onsubmit="return confirm('¿Eliminar este socio? Solo se puede si no tiene liquidaciones registradas.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">No hay socios registrados.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="bg-white rounded-lg shadow p-4 max-w-md">
    <h2 class="font-semibold mb-3">+ Nuevo socio</h2>
    <form method="POST" action="{{ route('admin.socios.store') }}" class="space-y-3">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">Nombre</label>
            <input type="text" name="nombre" value="{{ old('nombre') }}" required
                   class="border border-slate-300 rounded px-2 py-1.5 w-full">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Porcentaje</label>
            <input type="number" name="porcentaje" value="{{ old('porcentaje') }}" step="0.01" min="0" max="100" required
                   class="border border-slate-300 rounded px-2 py-1.5 w-32">
        </div>
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
            Crear socio
        </button>
    </form>
</div>
@endsection
