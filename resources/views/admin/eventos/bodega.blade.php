<?php /* Bodega de stock por evento — ver
     ApiRestEvent/brain/api_rest_event/PLAN-BODEGA-STOCK-EVENTO-14082026.md.
     Catálogo de ítems del evento + resumen agregado de sus asignaciones
     (un ítem puede ofrecerse en varios form_types, cada uno con su propio
     cupo independiente — "cupos separados por form_type", no un pool
     compartido). Gestionar el precio/incluido/stock propio de cada
     asignación se sigue haciendo donde siempre (pestaña "Tipos" del
     evento + su pantalla de stock) — esta pantalla es catálogo +
     resumen + el botón de asignar. */ ?>
@extends('admin.layouts.app')

@section('title', 'Bodega de stock — ' . $evento['name'] . ' — Admin Eventos')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.eventos.edit', $evento['id']) }}#tipos" class="text-sm text-brand-600 hover:underline">← Volver al evento</a>
    <h1 class="text-lg font-bold mt-1">Bodega de stock — {{ $evento['name'] }}</h1>
    <p class="text-sm text-slate-500">
        Catálogo de ítems del evento. Un mismo ítem físico (ej. "Medalla Finisher") puede asignarse a
        varios tipos de inscripción — cada asignación tiene su propio precio y su propio cupo,
        completamente independientes entre sí. El precio/incluido/stock de cada asignación se edita
        desde la pestaña "Tipos" del evento, como siempre — acá solo se gestiona el catálogo y se
        asigna a nuevos tipos de inscripción.
    </p>
</div>

@if ($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-md p-3 mb-4">
        @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif

@if (session('status'))
    <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-md p-3 mb-4">
        {{ session('status') }}
    </div>
@endif

<div class="space-y-4 mb-6">
    @forelse ($itemBodega as $item)
        @php
            $formTypesAsignados = collect($item['asignaciones'] ?? [])->pluck('form_types_id')->all();
            $formTypesDisponibles = collect($formTypes)->reject(fn ($ft) => in_array($ft['id'], $formTypesAsignados));
        @endphp
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-start justify-between gap-3">
                <form method="POST" action="{{ route('admin.bodega.update', [$evento['id'], $item['id']]) }}" class="grid grid-cols-6 gap-2 items-end flex-1">
                    @csrf
                    @method('PUT')
                    <input type="text" name="nombre" value="{{ $item['nombre'] }}" placeholder="Nombre" class="col-span-2 w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    <input type="text" name="icon" value="{{ $item['icon'] }}" placeholder="Ícono" maxlength="10" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    <input type="url" name="foto_url" value="{{ $item['foto_url'] }}" placeholder="URL de foto (opcional)" class="col-span-2 w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1.5 rounded">Guardar</button>
                </form>
                <form method="POST" action="{{ route('admin.bodega.destroy', [$evento['id'], $item['id']]) }}"
                      onsubmit="return confirm('¿Eliminar este ítem de bodega? Las asignaciones ya creadas NO se borran, quedan standalone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-xs text-red-600 hover:underline whitespace-nowrap">Eliminar</button>
                </form>
            </div>
            <div class="flex items-center gap-3 mt-1">
                <label class="text-xs flex items-center gap-1">
                    <input type="checkbox" disabled @checked($item['requiere_talla'])> Talla
                </label>
                <label class="text-xs flex items-center gap-1">
                    <input type="checkbox" disabled @checked($item['requiere_sexo'])> Sexo
                </label>
                <span class="text-xs text-slate-400">(editar talla/sexo desde cada asignación en la pestaña "Tipos")</span>
            </div>

            <table class="w-full text-sm mt-3">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-2 py-1.5">Tipo de inscripción</th>
                        <th class="px-2 py-1.5">Precio</th>
                        <th class="px-2 py-1.5">Incluido</th>
                        <th class="px-2 py-1.5">Cupo total</th>
                        <th class="px-2 py-1.5">Disponible</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($item['asignaciones'] ?? [] as $asignacion)
                        <tr class="border-t border-slate-100">
                            <td class="px-2 py-1.5">{{ $asignacion['form_type_nombre'] ?? $asignacion['form_types_id'] }}</td>
                            <td class="px-2 py-1.5">{{ $asignacion['price'] }}</td>
                            <td class="px-2 py-1.5">{{ $asignacion['incluido'] ? 'Sí' : 'No' }}</td>
                            <td class="px-2 py-1.5">{{ $asignacion['cupo_total'] ?? '— (sin controlar)' }}</td>
                            <td class="px-2 py-1.5 {{ $asignacion['disponible'] === 0 ? 'text-red-600 font-semibold' : '' }}">
                                {{ $asignacion['disponible'] ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-2 py-3 text-center text-slate-400">Todavía no está asignado a ningún tipo de inscripción.</td></tr>
                    @endforelse
                </tbody>
            </table>

            @if ($formTypesDisponibles->isNotEmpty())
                <form method="POST" action="{{ route('admin.bodega.asignar', [$evento['id'], $item['id']]) }}" class="flex items-end gap-2 mt-2">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium mb-1">Asignar a tipo de inscripción</label>
                        <select name="form_types_id" class="border border-slate-300 rounded px-2 py-1.5 text-sm" required>
                            <option value="">Elegir…</option>
                            @foreach ($formTypesDisponibles as $ft)
                                <option value="{{ $ft['id'] }}">{{ $ft['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-3 py-1.5 rounded">+ Asignar</button>
                </form>
            @else
                <p class="text-xs text-slate-400 mt-2">Ya está asignado a todos los tipos de inscripción del evento.</p>
            @endif
        </div>
    @empty
        <p class="text-sm text-slate-400">Todavía no hay ítems en la bodega de este evento.</p>
    @endforelse
</div>

<div class="bg-white rounded-lg shadow p-4 max-w-lg">
    <h2 class="font-semibold mb-3">+ Nuevo ítem de bodega</h2>
    <form method="POST" action="{{ route('admin.bodega.store', $evento['id']) }}" class="space-y-3">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">Nombre</label>
            <input type="text" name="nombre" required class="border border-slate-300 rounded px-2 py-1.5 w-full" placeholder="Medalla Finisher">
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Ícono (opcional)</label>
                <input type="text" name="icon" maxlength="10" class="border border-slate-300 rounded px-2 py-1.5 w-full" placeholder="🏅">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">URL de foto (opcional)</label>
                <input type="url" name="foto_url" class="border border-slate-300 rounded px-2 py-1.5 w-full">
            </div>
        </div>
        <div class="flex items-center gap-4">
            <label class="text-sm flex items-center gap-1.5">
                <input type="checkbox" name="requiere_talla" value="1"> Requiere talla
            </label>
            <label class="text-sm flex items-center gap-1.5">
                <input type="checkbox" name="requiere_sexo" value="1"> Requiere sexo
            </label>
        </div>
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
            Crear
        </button>
    </form>
</div>
@endsection
