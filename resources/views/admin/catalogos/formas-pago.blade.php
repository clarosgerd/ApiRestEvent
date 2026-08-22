<?php /* Catálogo global de formas de pago — administración (super_admin). Ver elascenso/event/brain/PLAN-INTEGRACION-PAGO-MERU-19082026.md. */ ?>
@extends('admin.layouts.app')

@section('title', 'Formas de pago — Catálogos')

@section('content')
<div class="flex justify-between items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="text-lg font-bold">Formas de pago</h1>
        <p class="text-sm text-slate-500">
            <a href="{{ route('admin.catalogos.index') }}" class="text-brand-600 hover:underline">← Catálogos</a>
            @if (Route::has('admin.organizadores.index'))
                · <a href="{{ route('admin.organizadores.index') }}" class="text-brand-600 hover:underline">Ver organizadores →</a>
            @endif
        </p>
    </div>
</div>

@if (session('status'))
    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-2 rounded-md mb-5 text-sm">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-2 rounded-md mb-5 text-sm">{{ $errors->first() }}</div>
@endif

<div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-2 rounded-md mb-5 text-sm">
    Este es el catálogo del <strong>sistema</strong> (disponible para cualquier organizador). Qué método tiene
    prendido cada organizador se configura desde
    @if (Route::has('admin.organizadores.index'))
        <a href="{{ route('admin.organizadores.index') }}" class="underline">Organizadores</a>
    @else
        Organizadores <span class="text-blue-600">(pantalla todavía no migrada acá — usar admin-eventos por ahora)</span>
    @endif
    , entrando a "Formas de pago" en su fila.
    <br>
    "Integrado" = tiene código real de cobro detrás (hoy: <code>sip</code>, <code>multipago</code>) — cualquier
    otro slug en "pasarela" queda visible en el catálogo pero el checkout la rechaza hasta que se implemente.
    "Manual" = solo instrucciones, el pago se confirma fuera del sistema.
</div>

<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left">
            <tr>
                <th class="px-4 py-2">Slug</th>
                <th class="px-4 py-2">Nombre</th>
                <th class="px-4 py-2">Descripción</th>
                <th class="px-4 py-2">Pasarela</th>
                <th class="px-4 py-2">Tipo</th>
                <th class="px-4 py-2">Activo</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($formasPago as $fp)
                <tr class="border-t border-slate-100 align-top">
                    <td class="px-4 py-2">
                        <input type="text" name="slug" value="{{ $fp['slug'] }}" required
                               form="fp-form-{{ $fp['id'] }}"
                               class="border border-slate-300 rounded px-2 py-1 w-28 font-mono text-xs">
                    </td>
                    <td class="px-4 py-2">
                        <input type="text" name="nombre" value="{{ $fp['nombre'] }}" required
                               form="fp-form-{{ $fp['id'] }}"
                               class="border border-slate-300 rounded px-2 py-1 w-full">
                    </td>
                    <td class="px-4 py-2">
                        <input type="text" name="descripcion" value="{{ $fp['descripcion'] }}"
                               form="fp-form-{{ $fp['id'] }}"
                               class="border border-slate-300 rounded px-2 py-1 w-full">
                    </td>
                    <td class="px-4 py-2">
                        <input type="text" name="pasarela" value="{{ $fp['pasarela'] }}"
                               form="fp-form-{{ $fp['id'] }}"
                               class="border border-slate-300 rounded px-2 py-1 w-24 font-mono text-xs">
                    </td>
                    <td class="px-4 py-2">
                        <select name="tipo" form="fp-form-{{ $fp['id'] }}" class="border border-slate-300 rounded px-2 py-1">
                            <option value="integrado" {{ $fp['tipo'] === 'integrado' ? 'selected' : '' }}>Integrado</option>
                            <option value="manual" {{ $fp['tipo'] === 'manual' ? 'selected' : '' }}>Manual</option>
                        </select>
                    </td>
                    <td class="px-4 py-2">
                        <input type="hidden" name="activo" value="0" form="fp-form-{{ $fp['id'] }}">
                        <input type="checkbox" name="activo" value="1" {{ $fp['activo'] ? 'checked' : '' }}
                               form="fp-form-{{ $fp['id'] }}">
                    </td>
                    <td class="px-4 py-2 text-right whitespace-nowrap space-x-2">
                        <form method="POST" action="{{ route('admin.catalogos.formas-pago.update', $fp['id']) }}" id="fp-form-{{ $fp['id'] }}" class="inline">
                            @csrf
                            @method('PUT')
                        </form>
                        <button type="submit" form="fp-form-{{ $fp['id'] }}" class="text-brand-600 hover:underline">Guardar</button>
                        <form method="POST" action="{{ route('admin.catalogos.formas-pago.destroy', $fp['id']) }}" class="inline"
                              onsubmit="return confirm('¿Eliminar esta forma de pago? Solo se puede si ningún organizador la tiene seleccionada.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-6 text-center text-slate-400">No hay formas de pago registradas.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="bg-white rounded-lg shadow p-4 max-w-lg">
    <h2 class="font-semibold mb-3">+ Nueva forma de pago</h2>
    <form method="POST" action="{{ route('admin.catalogos.formas-pago.store') }}" class="space-y-3">
        @csrf
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Slug</label>
                <input type="text" name="slug" value="{{ old('slug') }}" required
                       placeholder="p. ej. meru"
                       class="border border-slate-300 rounded px-2 py-1.5 w-full font-mono text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Nombre</label>
                <input type="text" name="nombre" value="{{ old('nombre') }}" required
                       placeholder="p. ej. Meru"
                       class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Descripción <span class="text-slate-400">(opcional)</span></label>
            <input type="text" name="descripcion" value="{{ old('descripcion') }}"
                   class="border border-slate-300 rounded px-2 py-1.5 w-full">
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Pasarela <span class="text-slate-400">(opcional)</span></label>
                <input type="text" name="pasarela" value="{{ old('pasarela') }}"
                       placeholder="igual al slug si es integrado"
                       class="border border-slate-300 rounded px-2 py-1.5 w-full font-mono text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Tipo</label>
                <select name="tipo" class="border border-slate-300 rounded px-2 py-1.5 w-full">
                    <option value="manual" {{ old('tipo') === 'manual' ? 'selected' : '' }}>Manual</option>
                    <option value="integrado" {{ old('tipo') === 'integrado' ? 'selected' : '' }}>Integrado</option>
                </select>
            </div>
        </div>
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
            Crear forma de pago
        </button>
    </form>
</div>
@endsection
