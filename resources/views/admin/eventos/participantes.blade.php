@extends('admin.layouts.app')

@section('title', 'Participantes — '.$evento['name'])

@section('content')
{{-- Ver el mismo comentario en eventos/numeracion.blade.php: `categoria`
     guarda el ID, no el nombre — este mapa solo resuelve el display. --}}
@php($categoriasPorId = collect($evento['categories'] ?? [])->keyBy(fn ($c) => (string) $c['id']))
<div class="flex justify-between items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="text-lg font-bold">Participantes</h1>
        <p class="text-sm text-slate-600">{{ $evento['name'] }}</p>
    </div>
    <a href="{{ route('admin.eventos.edit', $evento['id']) }}" class="text-sm text-brand-600 hover:underline self-center">
        ← Volver a editar evento
    </a>
</div>

<p class="text-xs text-slate-500 mb-4">
    Solo se pueden corregir datos de contacto/identidad no sensibles (nombre, alias, correo,
    teléfono, dirección, ciudad, género, fecha de nacimiento) y la talla de camiseta —
    únicamente si el participante ya tiene una asignada. Categoría, souvenirs, donación,
    código promocional y número de corredor/chip no son editables desde acá.
</p>

{{-- Filtro por categoría --}}
<form method="GET" action="{{ route('admin.participantes.index', $evento['id']) }}" class="mb-4 flex gap-2 items-end flex-wrap">
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

<p class="text-sm text-slate-500 mb-3">{{ count($participantes) }} participante(s)</p>

@forelse ($participantes as $p)
    @php($tienePolera = !empty($p['polera']) && $p['polera'] !== 'No shirt')
    <section class="bg-white rounded-lg shadow p-5 mb-4">
        <form method="POST" action="{{ route('admin.participantes.update', ['event' => $evento['id'], 'participante' => $p['id']]) }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="categoria" value="{{ $categoriaSeleccionada }}">

            <div class="flex justify-between items-start mb-3 flex-wrap gap-2">
                <div class="text-sm">
                    <span class="font-semibold">{{ $p['nombre'] }} {{ $p['apellido'] }}</span>
                    <span class="text-slate-500"> · Doc. {{ $p['numeroDocumento'] }} · {{ $categoriasPorId[$p['categoria']]['name'] ?? $p['categoria'] }}</span>
                </div>
                <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-3 py-1.5 rounded-md font-semibold">
                    Guardar
                </button>
            </div>

            <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Nombre</label>
                    <input type="text" name="nombre" value="{{ $p['nombre'] }}" maxlength="255"
                           class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Apellido</label>
                    <input type="text" name="apellido" value="{{ $p['apellido'] }}" maxlength="255"
                           class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Alias</label>
                    <input type="text" name="alias" value="{{ $p['alias'] }}" maxlength="255"
                           class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Correo</label>
                    <input type="email" name="correo" value="{{ $p['correo'] }}" maxlength="255"
                           class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Teléfono</label>
                    <input type="text" name="telefono" value="{{ $p['telefono'] }}" maxlength="30"
                           class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Ciudad</label>
                    <input type="text" name="ciudad" value="{{ $p['ciudad'] }}" maxlength="255"
                           class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Dirección</label>
                    <input type="text" name="direccion" value="{{ $p['direccion'] }}" maxlength="255"
                           class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Género</label>
                    <select name="genero" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                        @foreach (['Masculino', 'Femenino', 'Otro'] as $g)
                            <option value="{{ $g }}" @selected($p['genero'] === $g)>{{ $g }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Fecha de nacimiento</label>
                    <input type="date" name="fecha_nacimiento" value="{{ $p['fechaNacimiento'] }}"
                           class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Talla de camiseta</label>
                    @if ($tienePolera)
                        <input type="text" name="polera" value="{{ $p['polera'] }}" maxlength="50"
                               class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    @else
                        <input type="text" value="" disabled placeholder="Sin polera asignada"
                               class="w-full border border-slate-200 bg-slate-100 text-slate-400 rounded-md px-3 py-2 text-sm">
                        <p class="text-xs text-slate-400 mt-1">No tiene camiseta asignada — no se puede agregar desde acá.</p>
                    @endif
                </div>
            </div>
        </form>
    </section>
@empty
    <p class="text-sm text-slate-500">
        No hay participantes {{ $categoriaSeleccionada !== '' ? 'en esta categoría' : 'inscritos todavía' }}.
    </p>
@endforelse
@endsection
