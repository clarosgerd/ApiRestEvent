@extends('admin.layouts.app')

@section('title', 'Calendario — '.$evento['name'])

@section('content')
@php
    $tituloPeriodo = match ($vista) {
        'semana' => 'Semana del '.$ancla->copy()->startOfWeek()->locale('es')->translatedFormat('d M').' al '.$ancla->copy()->endOfWeek()->locale('es')->translatedFormat('d M Y'),
        'dia' => $ancla->copy()->locale('es')->translatedFormat('l d \d\e F \d\e Y'),
        default => ucfirst($ancla->copy()->locale('es')->translatedFormat('F Y')),
    };
    $parametros = fn (string $v, string $fecha) => ['event' => $evento['id'], 'vista' => $v, 'fecha' => $fecha];
    $nombresDias = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
@endphp

<div class="flex justify-between items-center mb-4 flex-wrap gap-2">
    <h1 class="text-lg font-bold">Calendario: {{ $evento['name'] }}</h1>
    <div class="flex gap-2 flex-wrap items-center">
        <a href="{{ route('admin.eventos.edit', $evento['id']) }}" class="text-sm text-brand-600 hover:underline">‹ Volver al evento</a>
    </div>
</div>

<div class="flex flex-wrap gap-3 items-center justify-between mb-4">
    <div class="inline-flex rounded-md border border-slate-300 overflow-hidden text-sm">
        @foreach (['mes' => 'Mes', 'semana' => 'Semana', 'dia' => 'Día'] as $clave => $etiqueta)
            <a href="{{ route('admin.eventos.calendario', $parametros($clave, $ancla->toDateString())) }}"
               class="px-3 py-1.5 {{ $vista === $clave ? 'bg-brand-600 text-white' : 'bg-white text-slate-700 hover:bg-slate-50' }}">{{ $etiqueta }}</a>
        @endforeach
    </div>
    <div class="flex gap-2 items-center text-sm">
        <a href="{{ route('admin.eventos.calendario', $parametros($vista, $navegacion['anterior'])) }}" class="px-2 py-1 border border-slate-300 rounded hover:bg-slate-50">‹ Anterior</a>
        <a href="{{ route('admin.eventos.calendario', $parametros($vista, now()->toDateString())) }}" class="px-2 py-1 border border-slate-300 rounded hover:bg-slate-50">Hoy</a>
        <a href="{{ route('admin.eventos.calendario', $parametros($vista, $navegacion['siguiente'])) }}" class="px-2 py-1 border border-slate-300 rounded hover:bg-slate-50">Siguiente ›</a>
    </div>
</div>

<h2 class="text-base font-semibold mb-3">{{ $tituloPeriodo }}</h2>

@if ($totalBloques === 0)
    <div class="mb-4 rounded border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600">
        Este evento todavía no tiene agenda ni sesiones cargadas.
    </div>
@endif

@if ($vista === 'mes')
    <div class="overflow-x-auto">
        <table class="w-full bg-white rounded-lg shadow text-sm table-fixed">
            <thead>
                <tr class="bg-brand-600 text-white">
                    @foreach ($nombresDias as $nombre)
                        <th class="px-2 py-2 font-semibold text-center">{{ $nombre }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($grilla['semanas'] as $semana)
                    <tr class="align-top">
                        @foreach ($semana as $dia)
                            <td class="border border-slate-100 p-1.5 min-h-[90px] {{ $dia['enEvento'] ? 'bg-sky-50' : '' }} {{ $dia['enMes'] ? '' : 'opacity-40' }}">
                                <div class="text-xs font-semibold {{ $dia['hoy'] ? 'text-brand-600' : 'text-slate-600' }}">{{ $dia['numero'] }}</div>
                                @foreach ($dia['bloques'] as $bloque)
                                    <div class="mt-1 rounded px-1.5 py-0.5 text-xs {{ $bloque['tipo'] === 'sesion' ? 'bg-violet-100 text-violet-900' : 'bg-emerald-100 text-emerald-900' }}">
                                        <span class="font-mono">{{ substr((string) $bloque['hora_inicio'], 0, 5) }}</span>
                                        {{ $bloque['titulo'] }}
                                        @if (!empty($bloque['sala']))<span class="text-slate-600">· {{ $bloque['sala'] }}</span>@endif
                                    </div>
                                @endforeach
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@elseif ($vista === 'semana')
    <div class="grid grid-cols-1 md:grid-cols-7 gap-2">
        @foreach ($grilla['dias'] as $i => $dia)
            <div class="bg-white rounded-lg shadow p-2 {{ $dia['enEvento'] ? 'ring-1 ring-sky-300' : '' }}">
                <div class="text-sm font-semibold {{ $dia['hoy'] ? 'text-brand-600' : 'text-slate-700' }}">
                    {{ $nombresDias[$i] }} {{ $dia['numero'] }}
                </div>
                @forelse ($dia['bloques'] as $bloque)
                    <div class="mt-2 rounded px-2 py-1 text-xs {{ $bloque['tipo'] === 'sesion' ? 'bg-violet-100 text-violet-900' : 'bg-emerald-100 text-emerald-900' }}">
                        <div class="font-mono">{{ substr((string) $bloque['hora_inicio'], 0, 5) }}@if (!empty($bloque['hora_fin'])) – {{ substr((string) $bloque['hora_fin'], 0, 5) }}@endif</div>
                        <div class="font-semibold">{{ $bloque['titulo'] }}</div>
                        @if (!empty($bloque['sala']))<div>{{ $bloque['sala'] }}</div>@endif
                        @if (!empty($bloque['ponente']))<div class="text-slate-600">{{ $bloque['ponente'] }}</div>@endif
                    </div>
                @empty
                    <div class="mt-2 text-xs text-slate-400">Sin actividades</div>
                @endforelse
            </div>
        @endforeach
    </div>
@else
    <div class="bg-white rounded-lg shadow divide-y divide-slate-100">
        @forelse ($grilla['bloques'] as $bloque)
            <div class="p-3 flex gap-3 items-start">
                <div class="font-mono text-sm w-28 shrink-0">
                    {{ substr((string) $bloque['hora_inicio'], 0, 5) }}@if (!empty($bloque['hora_fin'])) – {{ substr((string) $bloque['hora_fin'], 0, 5) }}@endif
                </div>
                <div class="min-w-0">
                    <div class="text-sm font-semibold">{{ $bloque['titulo'] }}</div>
                    <div class="text-xs text-slate-600">
                        {{ $bloque['tipo'] === 'sesion' ? 'Sesión de congreso' : 'Agenda' }}
                        @if (!empty($bloque['sala'])) · {{ $bloque['sala'] }}@endif
                        @if (!empty($bloque['ponente'])) · {{ $bloque['ponente'] }}@endif
                    </div>
                </div>
            </div>
        @empty
            <div class="p-3 text-sm text-slate-500">Sin actividades este día.</div>
        @endforelse
    </div>
@endif
@endsection
