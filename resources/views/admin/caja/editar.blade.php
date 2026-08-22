@extends('admin.layouts.app')

@section('title', 'Editar inscripción — Caja')

@section('content')
@php
    $p = $registro['participantes'][0] ?? [];
    $pagoStatus = $registro['pago_status'] ?? 'pending';
    $ftActual = collect($evento['formTypes'] ?? [])->firstWhere('id', $registro['form_types_id'] ?? null);
    $costoEdicion = $ftActual['costo_edicion'] ?? 0;
@endphp

<div class="mb-4">
    <a href="{{ route('admin.caja.buscar', $evento['id']) }}" class="text-sm text-brand-600 hover:underline">&larr; Volver a la búsqueda</a>
</div>
<h1 class="text-lg font-bold mb-1">Editar inscripción {{ $referencia }}</h1>
<p class="text-sm text-slate-500 mb-5">
    Estado actual: <strong>{{ $pagoStatus === 'paid' ? 'Pagada' : 'Pendiente' }}</strong>
    @if ($pagoStatus !== 'paid')
        — editar acá no cobra nada por sí solo; si además querés cobrarla, hacelo desde "Buscar" con el botón Cobrar.
    @endif
</p>

<p class="text-xs text-slate-500 mb-4">
    Nota: el kit/souvenirs no vienen prellenados por una limitación del detalle que devuelve
    ApiRestEvent hoy — si la inscripción ya tenía ítems del kit y siguen aplicando, volvé a
    marcarlos.
</p>

@include('admin.caja._formulario', [
    'modo' => 'editar',
    'formTypeFijo' => $registro['form_types_id'] ?? null,
    'prefill' => [
        'nombre' => $p['nombre'] ?? '',
        'apellido' => $p['apellido'] ?? '',
        'alias' => $p['alias'] ?? '',
        'genero' => $p['genero'] ?? '',
        'tipoDocumento' => $p['tipoDocumento'] ?? 'DNI',
        'numeroDocumento' => $p['numeroDocumento'] ?? '',
        'nacimientoDia' => $p['nacimiento']['dia'] ?? '',
        'nacimientoMes' => $p['nacimiento']['mes'] ?? '',
        'nacimientoAnio' => $p['nacimiento']['anio'] ?? '',
        'correo' => $p['correo'] ?? '',
        'direccion' => $p['direccion'] ?? '',
        'ciudad' => $p['ciudad'] ?? '',
        'telefono' => $p['telefono'] ?? '',
        'contactoNombre' => $p['contacto_emergencia']['nombre'] ?? '',
        'contactoCelular' => $p['contacto_emergencia']['celular'] ?? '',
        'contactoRelacion' => $p['contacto_emergencia']['relacion'] ?? '',
        'categoria' => $p['categoria'] ?? null,
        'equipoId' => $p['equipoId'] ?? null,
        'quiereDelivery' => $p['quiereDelivery'] ?? false,
        'donacion' => $p['donacion'] ?? 0,
        'promoCodigo' => $p['promoCodigo'] ?? '',
        'souvenirs' => [],
        // Congresos con talleres desde Caja (20/08/2026).
        'talleres' => $p['talleres'] ?? [],
    ],
    'costoEdicion' => $costoEdicion,
    'actionUrl' => route('admin.caja.editar.store', [$evento['id'], $referencia]),
    'pagoStatus' => $pagoStatus,
])
@endsection
