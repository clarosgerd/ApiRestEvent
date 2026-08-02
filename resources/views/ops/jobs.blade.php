@extends('ops.layout')

@section('title', 'Jobs')

@section('content')
<h1 class="text-lg font-bold mb-4">Jobs</h1>

<section class="mb-6">
    <h2 class="font-bold mb-2">Pendientes ({{ $pending->count() }})</h2>
    @if ($pending->isEmpty())
        <p class="text-sm text-slate-600">No hay jobs pendientes.</p>
    @else
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-4 py-2">ID</th>
                        <th class="px-4 py-2">Cola</th>
                        <th class="px-4 py-2">Intentos</th>
                        <th class="px-4 py-2">Encolado</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pending as $job)
                        <tr class="border-t border-slate-100">
                            <td class="px-4 py-2">{{ $job->id }}</td>
                            <td class="px-4 py-2">{{ $job->queue }}</td>
                            <td class="px-4 py-2">{{ $job->attempts }}</td>
                            <td class="px-4 py-2">{{ \Illuminate\Support\Carbon::createFromTimestamp($job->created_at)->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2 text-right">
                                <form method="POST" action="{{ route('ops.jobs.destroy', $job->id) }}"
                                      onsubmit="return confirm('¿Eliminar este job pendiente?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-600 hover:underline">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

<section>
    <h2 class="font-bold mb-2">Fallidos ({{ $failed->count() }})</h2>
    @if ($failed->isEmpty())
        <p class="text-sm text-slate-600">No hay jobs fallidos.</p>
    @else
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-4 py-2">Cola</th>
                        <th class="px-4 py-2">Falló</th>
                        <th class="px-4 py-2">Excepción</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($failed as $job)
                        <tr class="border-t border-slate-100 align-top">
                            <td class="px-4 py-2">{{ $job->queue }}</td>
                            <td class="px-4 py-2">{{ $job->failed_at }}</td>
                            <td class="px-4 py-2 font-mono text-xs max-w-md truncate" title="{{ $job->exception }}">
                                {{ \Illuminate\Support\Str::limit($job->exception, 150) }}
                            </td>
                            <td class="px-4 py-2 text-right whitespace-nowrap space-x-2">
                                <form method="POST" action="{{ route('ops.jobs.retry', $job->uuid) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs text-brand-600 hover:underline">Reintentar</button>
                                </form>
                                <form method="POST" action="{{ route('ops.jobs.forget', $job->uuid) }}" class="inline"
                                      onsubmit="return confirm('¿Eliminar este job fallido?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-600 hover:underline">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
