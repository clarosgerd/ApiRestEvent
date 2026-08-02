@extends('ops.layout')

@section('title', 'Backups')

@section('content')
<div class="flex justify-between items-center mb-4">
    <h1 class="text-lg font-bold">Backups</h1>
    <form method="POST" action="{{ route('ops.backups.run') }}"
          onsubmit="return confirm('¿Ejecutar un backup de la base de datos ahora?\n\nLa página va a esperar a que termine (dump + subida a Drive).')">
        @csrf
        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
            Ejecutar backup ahora
        </button>
    </form>
</div>

@if ($runs->isEmpty())
    <p class="text-sm text-slate-600">Todavía no hay backups registrados.</p>
@else
    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-4 py-2">Fecha</th>
                    <th class="px-4 py-2">Tipo</th>
                    <th class="px-4 py-2">Estado</th>
                    <th class="px-4 py-2">Archivo</th>
                    <th class="px-4 py-2">Tamaño</th>
                    <th class="px-4 py-2">Disparado por</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($runs as $run)
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2">{{ $run->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-2">{{ $run->type === 'cleanup' ? 'Limpieza' : 'Backup' }}</td>
                        <td class="px-4 py-2">
                            @if ($run->status === 'success')
                                <span class="text-green-700 bg-green-50 px-2 py-0.5 rounded text-xs font-semibold">OK</span>
                            @else
                                <span class="text-red-700 bg-red-50 px-2 py-0.5 rounded text-xs font-semibold" title="{{ $run->error_message }}">Falló</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 font-mono text-xs">{{ $run->filename ? basename($run->filename) : '—' }}</td>
                        <td class="px-4 py-2">{{ $run->size_bytes ? number_format($run->size_bytes / 1024 / 1024, 2).' MB' : '—' }}</td>
                        <td class="px-4 py-2">{{ $run->triggeredBy->email ?? 'Programado' }}</td>
                        <td class="px-4 py-2 text-right">
                            @if ($run->status === 'success' && $run->filename)
                                <a href="{{ route('ops.backups.download', $run->id) }}" class="text-xs text-brand-600 hover:underline">
                                    Descargar
                                </a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $runs->links() }}
    </div>
@endif
@endsection
