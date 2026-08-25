<?php /* Formas de pago activas de un organizador — ver elascenso/event/brain/PLAN-INTEGRACION-PAGO-MERU-19082026.md. */ ?>
@extends('admin.layouts.app')

@section('title', 'Formas de pago — ' . ($organizadorData['razon_social'] ?? 'Organizador'))

@section('content')
<div class="mb-4">
    <h1 class="text-lg font-bold">Formas de pago — {{ $organizadorData['razon_social'] ?? "Organizador #$organizadorId" }}</h1>
    <p class="text-sm text-slate-500">
        <a href="{{ route('admin.organizadores.index') }}" class="text-brand-600 hover:underline">← Organizadores</a>
        · <a href="{{ route('admin.catalogos.formas-pago.index') }}" class="text-brand-600 hover:underline">Catálogo global →</a>
    </p>
</div>

@if (session('status'))
    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-2 rounded-md mb-5 text-sm">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-2 rounded-md mb-5 text-sm">{{ $errors->first() }}</div>
@endif

@if ($usandoDefault)
    <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-2 rounded-md mb-5 text-sm">
        Este organizador todavía no tiene una selección propia — hoy está usando el default del sistema
        (todas las formas de pago del sistema marcadas como "Activo" en el catálogo global). Tildar y guardar
        acá reemplaza ese default por una selección explícita.
    </div>
@endif

<div class="bg-white rounded-lg shadow p-4 max-w-xl">
    <form method="POST" action="{{ route('admin.organizadores.formas-pago.update', $organizadorId) }}" class="space-y-3">
        @csrf
        @method('PUT')

        @forelse ($formasPago as $fp)
            <label class="flex items-start gap-2 border border-slate-200 rounded-md px-3 py-2 hover:bg-slate-50">
                <input type="checkbox" name="forma_pago_ids[]" value="{{ $fp['id'] }}"
                       {{ $fp['seleccionada'] ? 'checked' : '' }} class="mt-0.5">
                <span class="flex-1">
                    <span class="font-medium">{{ $fp['nombre'] }}</span>
                    <span class="text-xs text-slate-400 font-mono">({{ $fp['slug'] }})</span>
                    <span class="text-xs px-1.5 py-0.5 rounded {{ $fp['tipo'] === 'integrado' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                        {{ $fp['tipo'] === 'integrado' ? 'Integrado' : 'Manual' }}
                    </span>
                    @unless ($fp['esDelSistema'])
                        <span class="text-xs px-1.5 py-0.5 rounded bg-amber-50 text-amber-700">Propia del organizador</span>
                    @endunless
                    @if ($fp['slug'] === 'pendiente_usd')
                        <input type="url" name="link_pago_pendiente_usd"
                               value="{{ old('link_pago_pendiente_usd', $fp['linkPago'] ?? '') }}"
                               placeholder="https://... (link de pago que se envía por correo, vence a las 24h)"
                               class="mt-1 block w-full text-sm border border-slate-300 rounded px-2 py-1">
                    @endif
                </span>
            </label>
        @empty
            <p class="text-sm text-slate-400">
                No hay formas de pago en el catálogo global todavía —
                <a href="{{ route('admin.catalogos.formas-pago.index') }}" class="text-brand-600 hover:underline">creá una primero</a>.
            </p>
        @endforelse

        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
            Guardar selección
        </button>
    </form>
</div>
@endsection
