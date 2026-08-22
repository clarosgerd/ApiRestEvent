@extends('admin.layouts.app')

@section('title', 'Cierres de caja — '.($evento['name'] ?? 'Evento'))

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.eventos.edit', $evento['id']) }}" class="text-sm text-brand-600 hover:underline">&larr; Volver al evento</a>
</div>
<h1 class="text-lg font-bold mb-1">Cierres de caja</h1>
<p class="text-sm text-slate-500 mb-5">{{ $evento['name'] ?? '' }} — control de turnos abiertos/cerrados por cajero.</p>

@if ($error)
    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-2 rounded-md mb-5 text-sm">{{ $error }}</div>
@endif

<form method="GET" class="bg-white rounded-lg shadow p-4 mb-5 flex flex-wrap gap-3 items-end">
    <div>
        <label class="block text-xs font-semibold mb-1" for="desde">Desde</label>
        <input type="date" name="desde" id="desde" value="{{ request('desde') }}" class="border border-slate-300 rounded-md px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-xs font-semibold mb-1" for="hasta">Hasta</label>
        <input type="date" name="hasta" id="hasta" value="{{ request('hasta') }}" class="border border-slate-300 rounded-md px-3 py-2 text-sm">
    </div>
    <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white rounded-md px-4 py-2 text-sm font-semibold">Filtrar</button>
</form>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr>
                <th class="px-3 py-2">Cajero</th>
                <th class="px-3 py-2">Abierto</th>
                <th class="px-3 py-2">Cerrado</th>
                <th class="px-3 py-2 text-right">Fondo inicial</th>
                <th class="px-3 py-2 text-right">Cobrado</th>
                <th class="px-3 py-2 text-right">Esperado</th>
                <th class="px-3 py-2 text-right">Contado</th>
                <th class="px-3 py-2 text-right">Diferencia</th>
                <th class="px-3 py-2">Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($turnos as $t)
                <tr class="border-t border-slate-100">
                    <td class="px-3 py-2">{{ $t['cajeroNombre'] ?? ('#'.$t['cajeroId']) }}</td>
                    <td class="px-3 py-2">{{ \Illuminate\Support\Carbon::parse($t['abiertoAt'])->format('d/m/Y H:i') }}</td>
                    <td class="px-3 py-2">{{ $t['cerradoAt'] ? \Illuminate\Support\Carbon::parse($t['cerradoAt'])->format('d/m/Y H:i') : '—' }}</td>
                    <td class="px-3 py-2 text-right">{{ number_format($t['fondoInicial'], 2) }}</td>
                    <td class="px-3 py-2 text-right">{{ number_format($t['totalCobrado'] ?? 0, 2) }}</td>
                    <td class="px-3 py-2 text-right">{{ $t['montoEsperado'] !== null ? number_format($t['montoEsperado'], 2) : '—' }}</td>
                    <td class="px-3 py-2 text-right">{{ $t['montoContado'] !== null ? number_format($t['montoContado'], 2) : '—' }}</td>
                    <td class="px-3 py-2 text-right {{ ($t['diferencia'] ?? 0) < 0 ? 'text-red-600' : (($t['diferencia'] ?? 0) > 0 ? 'text-amber-600' : '') }}">
                        {{ $t['diferencia'] !== null ? number_format($t['diferencia'], 2) : '—' }}
                    </td>
                    <td class="px-3 py-2">
                        <span class="text-xs px-2 py-0.5 rounded-full {{ $t['estado'] === 'abierto' ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-700' }}">
                            {{ $t['estado'] }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="px-3 py-6 text-center text-slate-400">Todavía no hay turnos de caja registrados.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
