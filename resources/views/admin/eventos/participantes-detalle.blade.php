@extends('admin.layouts.app')

@section('title', 'Detalle de inscritos — '.$evento['name'])

@section('content')
@php($categoriasPorId = collect($evento['categories'] ?? [])->keyBy(fn ($c) => (string) $c['id']))
@php($estadoLabels = ['paid' => 'Pagado', 'pending' => 'Pendiente', 'cancelled' => 'Cancelado', 'failed' => 'Fallido'])

@if (session('status'))
    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-2 rounded-md mb-4 text-sm">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-2 rounded-md mb-4 text-sm">{{ $errors->first() }}</div>
@endif
<div class="flex justify-between items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="text-lg font-bold">Detalle de inscritos</h1>
        <p class="text-sm text-slate-600">{{ $evento['name'] }}</p>
    </div>
    <a href="{{ route('admin.eventos.dashboard', $evento['id']) }}" class="text-sm text-brand-600 hover:underline self-center">
        ← Volver al dashboard
    </a>
</div>

{{-- Filtros --}}
<form method="GET" action="{{ route('admin.participantes.detalle', $evento['id']) }}" class="mb-4 flex gap-2 items-end flex-wrap">
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Estado</label>
        <select name="pago_status" onchange="this.form.submit()"
                class="border border-slate-300 rounded-md px-3 py-2 text-sm min-w-[160px]">
            <option value="" @selected($pagoStatusSeleccionado === '')>Todos los estados</option>
            @foreach ($estadoLabels as $valor => $label)
                <option value="{{ $valor }}" @selected($pagoStatusSeleccionado === $valor)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Categoría</label>
        <select name="categoria" onchange="this.form.submit()"
                class="border border-slate-300 rounded-md px-3 py-2 text-sm min-w-[160px]">
            <option value="" @selected($categoriaSeleccionada === '')>Todas las categorías</option>
            @foreach ($evento['categories'] ?? [] as $cat)
                <option value="{{ $cat['id'] }}" @selected($categoriaSeleccionada === (string) $cat['id'])>{{ $cat['name'] }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Buscar</label>
        <input type="text" name="search" value="{{ $searchSeleccionado }}" placeholder="Documento, nombre, apellido o correo"
               class="border border-slate-300 rounded-md px-3 py-2 text-sm min-w-[240px]">
    </div>
    <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">Buscar</button>
    @if ($searchSeleccionado !== '')
        <a href="{{ route('admin.participantes.detalle', array_filter(['event' => $evento['id'], 'categoria' => $categoriaSeleccionada ?: null, 'pago_status' => $pagoStatusSeleccionado ?: null])) }}"
           class="text-sm text-slate-500 hover:underline self-center">Limpiar búsqueda</a>
    @endif
    <a href="{{ route('admin.participantes.detalle.csv', array_filter(['event' => $evento['id'], 'categoria' => $categoriaSeleccionada ?: null, 'pago_status' => $pagoStatusSeleccionado ?: null, 'search' => $searchSeleccionado ?: null])) }}"
       class="inline-block bg-white border border-slate-300 hover:bg-slate-50 text-sm font-semibold px-3 py-2 rounded-md">
        Descargar CSV (todo, sin paginar)
    </a>
</form>

<div class="overflow-x-auto">
    <table class="w-full bg-white rounded-lg shadow text-sm">
        <thead>
            <tr class="bg-brand-600 text-white text-left">
                @if ($usaNumeracion)
                    <th class="px-3 py-2 font-semibold">Número</th>
                @endif
                <th class="px-3 py-2 font-semibold">Estado</th>
                <th class="px-3 py-2 font-semibold text-right">Importe</th>
                {{-- importeTaller/importeTotal (19/08/2026) — para conciliar contra el banco, ver ApiRestEvent. --}}
                <th class="px-3 py-2 font-semibold text-right">Taller</th>
                <th class="px-3 py-2 font-semibold text-right">Total</th>
                <th class="px-3 py-2 font-semibold">Descuento</th>
                @if ($mostrarGrupal)
                    <th class="px-3 py-2 font-semibold text-right">Desc. grupal</th>
                @endif
                <th class="px-3 py-2 font-semibold">CI</th>
                <th class="px-3 py-2 font-semibold">Nombre</th>
                <th class="px-3 py-2 font-semibold">Apellido</th>
                {{-- Carreras: alias (etiqueta de corredor). Congresos: título. Mismo campo `alias`. --}}
                <th class="px-3 py-2 font-semibold">{{ $usaNumeracion ? 'Alias' : 'Título' }}</th>
                @if ($mostrarEquipo)
                    <th class="px-3 py-2 font-semibold">Equipo</th>
                @endif
                @if ($mostrarPolera)
                    <th class="px-3 py-2 font-semibold">Polera</th>
                @endif
                <th class="px-3 py-2 font-semibold">Sexo</th>
                <th class="px-3 py-2 font-semibold">Celular</th>
                <th class="px-3 py-2 font-semibold">Fecha inscripción</th>
                <th class="px-3 py-2 font-semibold">Ref</th>
                <th class="px-3 py-2 font-semibold">Nacimiento</th>
                <th class="px-3 py-2 font-semibold">{{ $usaNumeracion ? 'Distancia' : 'Categoría' }}</th>
                <th class="px-3 py-2 font-semibold">Acción</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($participantes as $p)
                <tr class="border-t border-slate-100">
                    @if ($usaNumeracion)
                        <td class="px-3 py-2 font-mono">{{ $p['numeroCorredor'] }}</td>
                    @endif
                    <td class="px-3 py-2">{{ $estadoLabels[$p['pagoStatus']] ?? $p['pagoStatus'] }}</td>
                    <td class="px-3 py-2 text-right">${{ number_format($p['importe'], 2) }}</td>
                    <td class="px-3 py-2 text-right">${{ number_format($p['importeTaller'] ?? 0, 2) }}</td>
                    <td class="px-3 py-2 text-right font-semibold">${{ number_format($p['importeTotal'] ?? $p['importe'], 2) }}</td>
                    <td class="px-3 py-2">
                        @if (!empty($p['promoCodigo']))
                            <span class="font-mono text-xs">{{ $p['promoCodigo'] }}</span>
                            <span class="text-emerald-700">−${{ number_format($p['promoDescuento'] ?? 0, 2) }}</span>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    @if ($mostrarGrupal)
                        <td class="px-3 py-2 text-right">${{ number_format($p['descuentoGrupal'] ?? 0, 2) }}</td>
                    @endif
                    <td class="px-3 py-2">{{ $p['numeroDocumento'] }}</td>
                    <td class="px-3 py-2">{{ $p['nombre'] }}</td>
                    <td class="px-3 py-2">{{ $p['apellido'] }}</td>
                    <td class="px-3 py-2">{{ $p['alias'] ?? '' }}</td>
                    @if ($mostrarEquipo)
                        <td class="px-3 py-2">{{ $p['equipo'] ?? '' }}</td>
                    @endif
                    @if ($mostrarPolera)
                        <td class="px-3 py-2">{{ $p['poleraTalla'] }}</td>
                    @endif
                    <td class="px-3 py-2">{{ $p['genero'] }}</td>
                    <td class="px-3 py-2">{{ $p['telefono'] }}</td>
                    <td class="px-3 py-2">{{ $p['fechaInscripcion'] ? \Illuminate\Support\Carbon::parse($p['fechaInscripcion'])->format('Y-m-d H:i') : '—' }}</td>
                    <td class="px-3 py-2 font-mono">{{ $p['referencia'] }}</td>
                    <td class="px-3 py-2">{{ $p['fechaNacimiento'] }}</td>
                    <td class="px-3 py-2">{{ $categoriasPorId[$p['categoria']]['name'] ?? $p['categoria'] }}</td>
                    <td class="px-3 py-2">
                        {{-- Conciliación manual de "Pago pendiente (USD)" (24/08/2026) —
                             solo se ofrece para filas pendientes de ese método específico;
                             SIP/Multipago se confirman solos por su propia pasarela, no acá. --}}
                        @if ($p['pagoStatus'] === 'pending' && ($p['tipoPago'] ?? null) === 'pendiente_usd')
                            <form method="POST" action="{{ route('admin.participantes.detalle.confirmar-pago-manual', ['event' => $evento['id'], 'referencia' => $p['referencia']]) }}"
                                  onsubmit="return confirm('¿Confirmar que {{ $p['nombre'] }} {{ $p['apellido'] }} (ref. {{ $p['referencia'] }}) ya pagó?');">
                                @csrf
                                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold px-2.5 py-1.5 rounded-md whitespace-nowrap">
                                    Confirmar pago
                                </button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td class="px-3 py-2 text-slate-500" colspan="{{ ($usaNumeracion ? 17 : 16) + ($mostrarEquipo ? 1 : 0) + ($mostrarPolera ? 1 : 0) + ($mostrarGrupal ? 1 : 0) }}">No hay inscritos con estos filtros.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($meta)
    <div class="flex justify-between items-center mt-4 text-sm text-slate-600">
        <span>Página {{ $meta['currentPage'] }} de {{ $meta['lastPage'] }} — {{ $meta['total'] }} inscrito(s) en total</span>
        <div class="flex gap-2">
            @if ($meta['currentPage'] > 1)
                <a href="{{ route('admin.participantes.detalle', array_filter(['event' => $evento['id'], 'categoria' => $categoriaSeleccionada ?: null, 'pago_status' => $pagoStatusSeleccionado ?: null, 'search' => $searchSeleccionado ?: null, 'page' => $meta['currentPage'] - 1])) }}"
                   class="bg-white border border-slate-300 hover:bg-slate-50 px-3 py-1.5 rounded-md">← Anterior</a>
            @endif
            @if ($meta['currentPage'] < $meta['lastPage'])
                <a href="{{ route('admin.participantes.detalle', array_filter(['event' => $evento['id'], 'categoria' => $categoriaSeleccionada ?: null, 'pago_status' => $pagoStatusSeleccionado ?: null, 'search' => $searchSeleccionado ?: null, 'page' => $meta['currentPage'] + 1])) }}"
                   class="bg-white border border-slate-300 hover:bg-slate-50 px-3 py-1.5 rounded-md">Siguiente →</a>
            @endif
        </div>
    </div>
@endif
@endsection
