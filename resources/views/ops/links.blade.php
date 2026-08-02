@extends('ops.layout')

@section('title', 'Enlaces')

@section('content')
<h1 class="text-lg font-bold mb-1">Enlaces de organizador / delivery</h1>
<p class="text-sm text-slate-600 mb-4">
    Reemplaza a <code>organizador:generar-link</code> / <code>delivery:generar-link</code>
    por consola — útil en UAT, donde no hay SSH para correrlos.
</p>

<form method="GET" action="{{ route('ops.enlaces') }}" class="mb-6 flex gap-2">
    <select name="evento" onchange="if(this.value) window.location = '{{ url('/ops/enlaces') }}/' + this.value"
            class="w-full max-w-md border border-slate-300 rounded-md px-3 py-2 text-sm">
        <option value="">Elegí un evento…</option>
        @foreach ($eventos as $evento)
            <option value="{{ $evento->id }}" @selected(isset($selected) && $selected->id === $evento->id)>
                #{{ $evento->id }} — {{ $evento->nombre }}
            </option>
        @endforeach
    </select>
</form>

@isset($links)
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="font-bold mb-4">{{ $selected->nombre }}</h2>
        <div class="space-y-3">
            @foreach ($links as $label => $url)
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">{{ $label }}</label>
                    <div class="flex gap-2">
                        <input type="text" readonly value="{{ $url }}" onclick="this.select()"
                               class="w-full border border-slate-300 rounded-md px-3 py-2 text-xs font-mono bg-slate-50">
                        <button type="button" onclick="navigator.clipboard.writeText('{{ $url }}')"
                                class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-3 py-2 rounded-md whitespace-nowrap">
                            Copiar
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endisset
@endsection
