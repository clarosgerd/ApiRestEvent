@extends('admin.layouts.app')

@section('title', 'Nueva inscripción — Caja')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.caja.index', $evento['id']) }}" class="text-sm text-brand-600 hover:underline">&larr; Volver a Caja</a>
</div>
<h1 class="text-lg font-bold mb-5">Nueva inscripción — {{ $evento['name'] ?? '' }}</h1>

@include('admin.caja._formulario', [
    'modo' => 'nueva',
    'formTypeFijo' => null,
    'prefill' => null,
    'costoEdicion' => null,
    'actionUrl' => route('admin.caja.nueva.store', $evento['id']),
    'pagoStatus' => null,
])
@endsection
