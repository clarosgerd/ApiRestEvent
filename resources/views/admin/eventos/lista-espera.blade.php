<?php /* Lista de espera de un evento — de solo lectura, promoción automática.
     Ver ApiRestEvent/brain/api_rest_event/PRD-kit-tallas-stock-lista-espera.md
     (sesión 11/08/2026). */ ?>
@extends('admin.layouts.app')

@section('title', 'Lista de espera — ' . $evento['name'] . ' — Admin Eventos')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.eventos.edit', $evento['id']) }}" class="text-sm text-brand-600 hover:underline">← Volver al evento</a>
    <h1 class="text-lg font-bold mt-1">Lista de espera — {{ $evento['name'] }}</h1>
    <p class="text-sm text-slate-500">
        Se anota gente sola, sin intervención del organizador, cuando un tipo de inscripción se llena o se agota
        una talla. Un job diario notifica por correo cuando se libera un lugar — de solo lectura acá, no hay botón
        de "promover a mano" todavía.
    </p>
</div>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left">
            <tr>
                <th class="px-4 py-2">Nombre</th>
                <th class="px-4 py-2">Correo</th>
                <th class="px-4 py-2">Tipo de inscripción</th>
                <th class="px-4 py-2">Ítem / talla</th>
                <th class="px-4 py-2">Estado</th>
                <th class="px-4 py-2">Anotado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lista as $fila)
                <tr class="border-t border-slate-100">
                    <td class="px-4 py-2">{{ $fila['nombre'] }}</td>
                    <td class="px-4 py-2">{{ $fila['correo'] }}</td>
                    <td class="px-4 py-2">{{ $nombresFormTypes[$fila['form_types_id']] ?? $fila['form_types_id'] }}</td>
                    <td class="px-4 py-2">
                        @if ($fila['souvenir_nombre'])
                            {{ $fila['souvenir_nombre'] }}
                            @if ($fila['talla']) ({{ $fila['talla'] }}@if($fila['sexo']), {{ $fila['sexo'] }}@endif) @endif
                        @else
                            <span class="text-slate-400">Cupo general</span>
                        @endif
                    </td>
                    <td class="px-4 py-2">
                        @php $colores = ['pendiente' => 'bg-amber-100 text-amber-700', 'promovido' => 'bg-green-100 text-green-700', 'expirado' => 'bg-slate-100 text-slate-500', 'cancelado' => 'bg-slate-100 text-slate-500']; @endphp
                        <span class="px-2 py-0.5 rounded text-xs font-medium {{ $colores[$fila['estado']] ?? '' }}">{{ $fila['estado'] }}</span>
                    </td>
                    <td class="px-4 py-2 text-slate-500">{{ \Illuminate\Support\Carbon::parse($fila['created_at'])->format('d/m/Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Nadie se anotó a la lista de espera todavía.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
