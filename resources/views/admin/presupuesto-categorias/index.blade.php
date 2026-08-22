<?php /* Catálogo de rubros del presupuesto — config global. Ver elascenso/event/brain/ (sesión 11/08/2026). */ ?>
@extends('admin.layouts.app')

@section('title', 'Categorías de presupuesto — Admin Eventos')

@section('content')
<div class="flex justify-between items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="text-lg font-bold">Categorías de presupuesto</h1>
        <p class="text-sm text-slate-500">Rubros disponibles al registrar ingresos/gastos en el presupuesto de un evento.</p>
    </div>
</div>

<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left">
            <tr>
                <th class="px-4 py-2">Nombre</th>
                <th class="px-4 py-2">Tipo</th>
                <th class="px-4 py-2">Activo</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($categorias as $categoria)
                <tr class="border-t border-slate-100">
                    <td class="px-4 py-2">
                        <input type="text" name="nombre" value="{{ $categoria['nombre'] }}" required
                               form="categoria-form-{{ $categoria['id'] }}"
                               class="border border-slate-300 rounded px-2 py-1 w-full">
                    </td>
                    <td class="px-4 py-2">
                        <select name="tipo" form="categoria-form-{{ $categoria['id'] }}" class="border border-slate-300 rounded px-2 py-1">
                            <option value="ingreso" {{ $categoria['tipo'] === 'ingreso' ? 'selected' : '' }}>Ingreso</option>
                            <option value="gasto" {{ $categoria['tipo'] === 'gasto' ? 'selected' : '' }}>Gasto</option>
                        </select>
                    </td>
                    <td class="px-4 py-2">
                        <input type="hidden" name="activo" value="0" form="categoria-form-{{ $categoria['id'] }}">
                        <input type="checkbox" name="activo" value="1" {{ $categoria['activo'] ? 'checked' : '' }}
                               form="categoria-form-{{ $categoria['id'] }}">
                    </td>
                    <td class="px-4 py-2 text-right space-x-2">
                        <form method="POST" action="{{ route('admin.presupuesto-categorias.update', $categoria['id']) }}" id="categoria-form-{{ $categoria['id'] }}" class="inline">
                            @csrf
                            @method('PUT')
                        </form>
                        <button type="submit" form="categoria-form-{{ $categoria['id'] }}" class="text-brand-600 hover:underline">Guardar</button>
                        <form method="POST" action="{{ route('admin.presupuesto-categorias.destroy', $categoria['id']) }}" class="inline"
                              onsubmit="return confirm('¿Eliminar esta categoría? Solo se puede si no tiene movimientos registrados.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">No hay categorías registradas.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="bg-white rounded-lg shadow p-4 max-w-md">
    <h2 class="font-semibold mb-3">+ Nueva categoría</h2>
    <form method="POST" action="{{ route('admin.presupuesto-categorias.store') }}" class="space-y-3">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">Nombre</label>
            <input type="text" name="nombre" value="{{ old('nombre') }}" required
                   class="border border-slate-300 rounded px-2 py-1.5 w-full">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Tipo</label>
            <select name="tipo" class="border border-slate-300 rounded px-2 py-1.5 w-full">
                <option value="gasto">Gasto</option>
                <option value="ingreso">Ingreso</option>
            </select>
        </div>
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
            Crear categoría
        </button>
    </form>
</div>
@endsection
