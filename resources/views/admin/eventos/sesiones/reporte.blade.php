<?php /* Reporte de asistencia/concurrencia por sesión. Ver AsistenciaSesionController::reporte() y elascenso/event/brain/ (sesión 11/08/2026). */ ?>
@extends('admin.layouts.app')

@section('title', 'Reporte de asistencia — '.$evento['name'])

@section('content')
<div class="flex justify-between items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="text-lg font-bold">Reporte de asistencia</h1>
        <p class="text-sm text-slate-600">{{ $evento['name'] }}</p>
    </div>
    <a href="{{ route('admin.sesiones.index', $evento['id']) }}" class="text-sm text-brand-600 hover:underline self-center">
        ← Volver a sesiones
    </a>
</div>

<p class="text-xs text-slate-500 mb-4">
    El % de concurrencia se calcula sobre el total de participantes pagados del evento
    ({{ $totalParticipantesPagados }}) — no hay pre-inscripción por sesión en esta versión, así que mide "de todos
    los que pagaron, cuántos pasaron por esta sesión", comparable entre sesiones del mismo evento.
</p>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left">
            <tr>
                <th class="px-4 py-2">Sesión</th>
                <th class="px-4 py-2">Sala</th>
                <th class="px-4 py-2">Fecha</th>
                <th class="px-4 py-2 text-right">Cupo</th>
                <th class="px-4 py-2 text-right">Asistieron</th>
                <th class="px-4 py-2 text-right">% concurrencia</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sesiones as $sesion)
                <tr class="border-t border-slate-100">
                    <td class="px-4 py-2 font-semibold">{{ $sesion['titulo'] }}</td>
                    <td class="px-4 py-2">{{ $sesion['sala'] ?? '—' }}</td>
                    <td class="px-4 py-2">{{ \Carbon\Carbon::parse($sesion['fecha'])->format('d/m/Y') }}</td>
                    <td class="px-4 py-2 text-right">{{ $sesion['cupo'] ?? 'Sin límite' }}</td>
                    <td class="px-4 py-2 text-right">{{ $sesion['asistieron'] }}</td>
                    <td class="px-4 py-2 text-right font-semibold">{{ number_format($sesion['porcentajeConcurrencia'], 1) }}%</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">No hay sesiones registradas todavía.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
