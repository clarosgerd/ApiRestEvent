@extends('admin.layouts.app')

@section('title', 'Auditoría — Admin Eventos')

@section('content')
<div class="flex justify-between items-center mb-4">
    <h1 class="text-lg font-bold">Log de auditoría</h1>
    <form method="GET" action="{{ route('admin.auditoria.index') }}" class="flex gap-2">
        <input type="number" name="evento_id" placeholder="Filtrar por evento_id" value="{{ $eventoId }}"
               class="border border-slate-300 rounded-md px-3 py-1.5 text-sm w-48">
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-1.5 rounded-md">
            Filtrar
        </button>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left">
            <tr>
                <th class="px-4 py-2">Fecha</th>
                <th class="px-4 py-2">Admin</th>
                <th class="px-4 py-2">Acción</th>
                <th class="px-4 py-2">Entidad</th>
                <th class="px-4 py-2">Evento</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($logs as $log)
                <tr class="border-t border-slate-100">
                    <td class="px-4 py-2">{{ $log['created_at'] }}</td>
                    <td class="px-4 py-2">{{ $log['admin_user']['nombre'] ?? '—' }}</td>
                    <td class="px-4 py-2">{{ $log['accion'] }}</td>
                    <td class="px-4 py-2">{{ $log['entidad'] }} #{{ $log['entidad_id'] }}</td>
                    <td class="px-4 py-2">{{ $log['evento_id'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td class="px-4 py-2 text-slate-500" colspan="5">Sin registros.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
