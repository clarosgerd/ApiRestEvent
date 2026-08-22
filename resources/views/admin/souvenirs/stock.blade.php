<?php /* Stock por talla/sexo de un ítem del kit — ver
     ApiRestEvent/brain/api_rest_event/PRD-kit-tallas-stock-lista-espera.md
     (sesión 11/08/2026). */ ?>
@extends('admin.layouts.app')

@section('title', 'Stock — ' . $nombre . ' — Admin Eventos')

@section('content')
<div class="mb-4">
    @if ($eventoId)
        {{-- #tipos (12/08/2026) — eventos/edit.blade.php pasó a tabs, esto
             abre directo la pestaña de Tipos de formulario (donde viven
             los ítems del kit) en vez de la primera. --}}
        <a href="{{ route('admin.eventos.edit', $eventoId) }}#tipos" class="text-sm text-brand-600 hover:underline">← Volver al evento</a>
    @endif
    <h1 class="text-lg font-bold mt-1">Stock — {{ $nombre }}</h1>
    <p class="text-sm text-slate-500">
        Cada fila es una combinación talla/sexo con su cupo. Un ítem sin ninguna fila acá tiene
        <strong>disponibilidad no controlada</strong> — se ofrece siempre, sin límite. "Disponible" se calcula en
        vivo (cantidad menos inscripciones vigentes), no es un contador que haya que ajustar a mano.
    </p>
</div>

<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left">
            <tr>
                <th class="px-4 py-2">Talla</th>
                <th class="px-4 py-2">Sexo</th>
                <th class="px-4 py-2">Cantidad total</th>
                <th class="px-4 py-2">Disponible ahora</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($stock as $fila)
                <tr class="border-t border-slate-100">
                    <td class="px-4 py-2">{{ $fila['talla'] ?? '—' }}</td>
                    <td class="px-4 py-2">{{ $fila['sexo'] ?? '—' }}</td>
                    <td class="px-4 py-2">
                        <input type="number" min="0" name="cantidad_total" value="{{ $fila['cantidad_total'] }}"
                               form="stock-form-{{ $fila['id'] }}" class="border border-slate-300 rounded px-2 py-1 w-24">
                    </td>
                    <td class="px-4 py-2 {{ $fila['disponible'] === 0 ? 'text-red-600 font-semibold' : '' }}">
                        {{ $fila['disponible'] }}
                    </td>
                    <td class="px-4 py-2 text-right space-x-2">
                        <form method="POST" action="{{ route('admin.souvenirs.stock.update', $fila['id']) }}" id="stock-form-{{ $fila['id'] }}" class="inline">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="souvenir_id" value="{{ $souvenirId }}">
                            <input type="hidden" name="evento_id" value="{{ $eventoId }}">
                            <input type="hidden" name="nombre" value="{{ $nombre }}">
                        </form>
                        <button type="submit" form="stock-form-{{ $fila['id'] }}" class="text-brand-600 hover:underline">Guardar</button>
                        <form method="POST" action="{{ route('admin.souvenirs.stock.destroy', $fila['id']) }}" class="inline"
                              onsubmit="return confirm('¿Eliminar esta fila de stock? El ítem queda sin control de esa combinación (disponibilidad no controlada).')">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="souvenir_id" value="{{ $souvenirId }}">
                            <input type="hidden" name="evento_id" value="{{ $eventoId }}">
                            <input type="hidden" name="nombre" value="{{ $nombre }}">
                            <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Sin stock cargado — disponibilidad no controlada.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="bg-white rounded-lg shadow p-4 max-w-md">
    <h2 class="font-semibold mb-3">+ Nueva combinación</h2>
    <form method="POST" action="{{ route('admin.souvenirs.stock.store', $souvenirId) }}" class="space-y-3">
        @csrf
        <input type="hidden" name="evento_id" value="{{ $eventoId }}">
        <input type="hidden" name="nombre" value="{{ $nombre }}">
        <div>
            <label class="block text-sm font-medium mb-1">Talla (opcional si el ítem no la requiere)</label>
            <input type="text" name="talla" maxlength="20" class="border border-slate-300 rounded px-2 py-1.5 w-full" placeholder="M">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Sexo (opcional)</label>
            <select name="sexo" class="border border-slate-300 rounded px-2 py-1.5 w-full">
                <option value="">—</option>
                <option value="masculino">Masculino</option>
                <option value="femenino">Femenino</option>
                <option value="unisex">Unisex</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Cantidad total</label>
            <input type="number" name="cantidad_total" min="0" required class="border border-slate-300 rounded px-2 py-1.5 w-full">
        </div>
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
            Agregar
        </button>
    </form>
</div>
@endsection
