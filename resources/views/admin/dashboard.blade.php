@extends('admin.layouts.app')

@section('title', 'Eventos — Admin Eventos')

@section('content')
<div class="flex justify-between items-center mb-4">
    <h1 class="text-lg font-bold">
        {{ $admin['rol'] === 'super_admin' ? 'Eventos' : 'Mi evento' }}
    </h1>
    @if ($admin['rol'] === 'super_admin')
        <a href="{{ route('admin.eventos.create') }}" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
            + Nuevo evento
        </a>
    @endif
</div>

@if ($admin['rol'] === 'super_admin')
    <form method="GET" action="{{ route('admin.dashboard') }}" class="mb-4 flex gap-2">
        <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por nombre de evento…"
               class="w-full max-w-sm border border-slate-300 rounded-md px-3 py-2 text-sm">
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
            Buscar
        </button>
        @if ($search !== '')
            <a href="{{ route('admin.dashboard') }}" class="text-sm text-slate-500 hover:underline self-center">
                Limpiar
            </a>
        @endif
    </form>
@endif

@if (empty($eventos))
    <p class="text-sm text-slate-600">No hay eventos para mostrar.</p>
@else
    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-4 py-2">ID</th>
                    <th class="px-4 py-2">Nombre</th>
                    <th class="px-4 py-2">Fecha</th>
                    <th class="px-4 py-2">Ubicación</th>
                    <th class="px-4 py-2">Estado</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($eventos as $evento)
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2">{{ $evento['id'] }}</td>
                        <td class="px-4 py-2 font-semibold">{{ $evento['name'] }}</td>
                        <td class="px-4 py-2">{{ $evento['date'] }}</td>
                        <td class="px-4 py-2">{{ $evento['location'] }}</td>
                        <td class="px-4 py-2">
                            @if ($evento['publicado'])
                                <span class="text-green-700 bg-green-50 px-2 py-0.5 rounded text-xs font-semibold">Publicado</span>
                            @else
                                <span class="text-amber-700 bg-amber-50 px-2 py-0.5 rounded text-xs font-semibold">Borrador</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right whitespace-nowrap space-x-2">
                            <a href="{{ route('admin.eventos.edit', $evento['id']) }}" class="text-xs text-brand-600 hover:underline">
                                Editar
                            </a>
                            @if (Route::has('admin.numeracion.index'))
                                <a href="{{ route('admin.numeracion.index', $evento['id']) }}" class="text-xs text-brand-600 hover:underline">
                                    Numeración
                                </a>
                            @endif
                            @if (Route::has('admin.acreditacion.index'))
                                <a href="{{ route('admin.acreditacion.index', $evento['id']) }}" class="text-xs text-brand-600 hover:underline">
                                    Acreditación
                                </a>
                            @endif
                            @if ($admin['rol'] === 'super_admin' && Route::has('admin.registro-manual.index'))
                                <a href="{{ route('admin.registro-manual.index', $evento['id']) }}" class="text-xs text-brand-600 hover:underline">
                                    Carga masiva
                                </a>
                            @endif
                            @unless ($evento['publicado'])
                                <form method="POST" action="{{ route('admin.eventos.publicar', $evento['id']) }}" class="inline"
                                      onsubmit="return confirm('¿Publicar este evento?\n\nEsto envía un correo REAL al organizador con el link de su dashboard, y no se puede deshacer ni reintentar.')">
                                    @csrf
                                    <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1 rounded">
                                        Publicar
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.eventos.despublicar', $evento['id']) }}" class="inline"
                                      onsubmit="return confirm('¿Despublicar este evento?\n\nDeja de ser visible/inscribible para participantes. No envía ningún correo, y se bloquea si el evento ya tiene participantes inscritos.')">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="text-xs bg-amber-600 hover:bg-amber-700 text-white px-2 py-1 rounded">
                                        Despublicar
                                    </button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($pagination)
        <div class="flex justify-between items-center mt-3">
            <p class="text-xs text-slate-500">
                Página {{ $pagination['current_page'] }} de {{ $pagination['last_page'] }}
                ({{ $pagination['total'] }} eventos en total)
            </p>
            @if ($pagination['last_page'] > 1)
                <div class="flex gap-2">
                    @if ($pagination['current_page'] > 1)
                        <a href="{{ route('admin.dashboard', ['page' => $pagination['current_page'] - 1, 'search' => $search ?: null]) }}"
                           class="text-xs bg-white border border-slate-300 hover:bg-slate-50 px-3 py-1.5 rounded-md">
                            « Anterior
                        </a>
                    @endif
                    @if ($pagination['current_page'] < $pagination['last_page'])
                        <a href="{{ route('admin.dashboard', ['page' => $pagination['current_page'] + 1, 'search' => $search ?: null]) }}"
                           class="text-xs bg-white border border-slate-300 hover:bg-slate-50 px-3 py-1.5 rounded-md">
                            Siguiente »
                        </a>
                    @endif
                </div>
            @endif
        </div>
    @endif
@endif
@endsection
