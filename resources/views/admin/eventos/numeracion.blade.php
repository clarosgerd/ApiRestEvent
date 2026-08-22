@extends('admin.layouts.app')

@section('title', 'Numeración — '.$evento['name'])

@section('content')
{{--
    `participantes.categoria` guarda el ID de la categoría (no el nombre —
    ver RegistrationController::importarBulk en ApiRestEvent y el bug real
    de 10/08/2026 documentado ahí: antes de ese fix, la carga masiva por CSV
    guardaba el nombre, y este filtro comparaba por nombre, así que solo
    "funcionaba" para esos participantes y quedaba roto para el resto). Este
    mapa resuelve ID→nombre para mostrar algo legible sin tocar el filtro,
    que sigue viajando como ID.
--}}
@php($categoriasPorId = collect($evento['categories'] ?? [])->keyBy(fn ($c) => (string) $c['id']))
<div class="flex justify-between items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="text-lg font-bold">Numeración de corredor y chip</h1>
        <p class="text-sm text-slate-600">{{ $evento['name'] }}</p>
    </div>
    <a href="{{ route('admin.eventos.edit', $evento['id']) }}" class="text-sm text-brand-600 hover:underline self-center">
        ← Volver a editar evento
    </a>
</div>

{{-- Filtro por categoría --}}
<form method="GET" action="{{ route('admin.numeracion.index', $evento['id']) }}" class="mb-4 flex gap-2 items-end flex-wrap">
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Categoría</label>
        <select name="categoria" onchange="this.form.submit()"
                class="border border-slate-300 rounded-md px-3 py-2 text-sm min-w-[200px]">
            <option value="" @selected($categoriaSeleccionada === '')>Todas las categorías</option>
            @foreach ($evento['categories'] ?? [] as $cat)
                <option value="{{ $cat['id'] }}" @selected($categoriaSeleccionada === (string) $cat['id'])>{{ $cat['name'] }}</option>
            @endforeach
        </select>
    </div>
    <noscript><button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">Filtrar</button></noscript>
</form>

@if (session('status'))
    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-2 rounded-md mb-5 text-sm">
        {{ session('status') }}
    </div>
@endif
@if ($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-2 rounded-md mb-5 text-sm">
        {{ $errors->first() }}
    </div>
@endif

{{-- CSV: descargar / subir --}}
<section class="bg-white rounded-lg shadow p-5 mb-6">
    <h2 class="font-bold mb-3 text-sm">Carga masiva por CSV</h2>
    <div class="grid sm:grid-cols-2 gap-5">
        <div>
            <p class="text-xs text-slate-600 mb-2">
                Descargá la lista {{ $categoriaSeleccionada !== '' ? '(categoría: '.($categoriasPorId[$categoriaSeleccionada]['name'] ?? $categoriaSeleccionada).')' : '(todas las categorías)' }},
                completá <code class="bg-slate-100 px-1 rounded">numero_corredor</code> y
                <code class="bg-slate-100 px-1 rounded">chip</code>, y volvé a subir el mismo archivo.
                No cambies la columna <code class="bg-slate-100 px-1 rounded">numero_documento</code> — es la que identifica a cada participante.
            </p>
            <a href="{{ route('admin.numeracion.csv.download', array_filter(['event' => $evento['id'], 'categoria' => $categoriaSeleccionada ?: null])) }}"
               class="inline-block bg-white border border-slate-300 hover:bg-slate-50 text-sm font-semibold px-3 py-2 rounded-md">
                Descargar CSV
            </a>
        </div>
        <div>
            <form method="POST" action="{{ route('admin.numeracion.csv.upload', $evento['id']) }}" enctype="multipart/form-data" class="space-y-2">
                @csrf
                <input type="hidden" name="categoria" value="{{ $categoriaSeleccionada }}">
                <label class="block text-xs font-semibold text-slate-600">Subir CSV completado</label>
                <input type="file" name="csv" accept=".csv,text/csv" required
                       class="w-full text-sm border border-slate-300 rounded-md px-3 py-2">
                <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
                    Subir y actualizar
                </button>
            </form>
        </div>
    </div>
</section>

{{-- Edición manual --}}
<section class="bg-white rounded-lg shadow p-6">
    <h2 class="font-bold mb-4 text-sm">
        Edición manual
        <span class="font-normal text-slate-500">({{ count($participantes) }} participante(s))</span>
    </h2>

    @if (count($participantes))
        <div class="grid grid-cols-12 gap-2 px-3 text-xs font-semibold text-slate-500 mb-1">
            <div class="col-span-3">Participante</div>
            <div class="col-span-2">Documento</div>
            <div class="col-span-2">Categoría</div>
            <div class="col-span-2">Nº de corredor</div>
            <div class="col-span-2">Chip</div>
            <div class="col-span-1"></div>
        </div>
    @endif

    @forelse ($participantes as $p)
        <div class="border border-slate-200 rounded-md p-2 mb-2">
            <form method="POST" action="{{ route('admin.numeracion.update', ['reference' => $p['referencia'], 'participante' => $p['id']]) }}"
                  class="grid grid-cols-12 gap-2 items-center">
                @csrf
                @method('PATCH')
                <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                <input type="hidden" name="categoria" value="{{ $categoriaSeleccionada }}">
                <div class="col-span-3 text-sm font-semibold truncate" title="{{ $p['nombre'] }} {{ $p['apellido'] }}">
                    {{ $p['nombre'] }} {{ $p['apellido'] }}
                </div>
                <div class="col-span-2 text-sm text-slate-600 truncate">{{ $p['numeroDocumento'] }}</div>
                <div class="col-span-2 text-sm text-slate-600 truncate">{{ $categoriasPorId[$p['categoria']]['name'] ?? $p['categoria'] }}</div>
                <div class="col-span-2">
                    <input type="text" name="numero_corredor" value="{{ $p['numeroCorredor'] }}" maxlength="50"
                           class="w-full border border-slate-300 rounded px-2 py-1 text-sm font-mono">
                </div>
                <div class="col-span-2">
                    <input type="text" name="chip" value="{{ $p['chip'] }}" maxlength="50"
                           class="w-full border border-slate-300 rounded px-2 py-1 text-sm font-mono">
                </div>
                <div class="col-span-1 text-right">
                    <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1 rounded">
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    @empty
        <p class="text-sm text-slate-500">
            No hay participantes {{ $categoriaSeleccionada !== '' ? 'en esta categoría' : 'inscritos todavía' }}.
        </p>
    @endforelse
</section>
@endsection
