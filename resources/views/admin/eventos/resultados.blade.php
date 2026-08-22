@extends('admin.layouts.app')

@section('title', 'Resultados / ChronoTrack — '.$evento['name'])

@section('content')
<div class="flex justify-between items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="text-lg font-bold">Resultados / ChronoTrack</h1>
        <p class="text-sm text-slate-600">{{ $evento['name'] }}</p>
    </div>
    <a href="{{ route('admin.eventos.edit', $evento['id']) }}" class="text-sm text-brand-600 hover:underline self-center">
        ← Volver a editar evento
    </a>
</div>

<p class="text-xs text-slate-500 mb-4">
    Solo consumo, de solo lectura — no creamos ni administramos nada en ChronoTrack.
    El ID de evento lo obtenés vos mismo cuando registrás la carrera ahí (numeración,
    cronometraje, etc.).
</p>

<div class="bg-white rounded-lg shadow p-4 mb-6">
    <h2 class="font-bold mb-3">ID de evento en ChronoTrack</h2>
    <form method="POST" action="{{ route('admin.eventos.update', $evento['id']) }}" class="flex gap-2 flex-wrap items-end">
        @csrf
        @method('PUT')
        <div>
            <label class="block text-xs font-semibold mb-1">chronotrack_event_id</label>
            <input type="text" name="chronotrackEventId" value="{{ old('chronotrackEventId', $evento['chronotrackEventId'] ?? '') }}"
                   placeholder="ej. 93491" class="border border-slate-300 rounded-md px-3 py-2 text-sm w-64">
        </div>
        <button type="submit" class="bg-slate-700 hover:bg-slate-800 text-white rounded-md px-4 py-2 text-sm font-semibold">
            Guardar
        </button>
    </form>
</div>

<div class="bg-white rounded-lg shadow p-4">
    <div class="flex justify-between items-center flex-wrap gap-2 mb-3">
        <h2 class="font-bold">Sincronizar</h2>
        <form method="POST" action="{{ route('admin.chronotrack.sincronizar', $evento['id']) }}"
              onsubmit="return confirm('¿Sincronizar ahora? Trae los resultados que ChronoTrack ya tenga generados para este evento.');">
            @csrf
            <button type="submit"
                    {{ empty($evento['chronotrackEventId']) ? 'disabled' : '' }}
                    class="bg-brand-600 hover:bg-brand-700 disabled:bg-slate-300 disabled:cursor-not-allowed text-white rounded-md px-4 py-2 text-sm font-semibold">
                Sincronizar ahora
            </button>
        </form>
    </div>

    @if (empty($evento['chronotrackEventId']))
        <p class="text-sm text-slate-500">Guardá primero el ID de evento de ChronoTrack para poder sincronizar.</p>
    @endif

    @if (session('syncResult'))
        @php($r = session('syncResult'))
        <div class="border-t border-slate-200 pt-4 mt-2">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                <div class="bg-slate-50 rounded-md p-3 text-center">
                    <div class="text-xl font-bold text-slate-800">{{ $r['procesados'] }}</div>
                    <div class="text-xs text-slate-500 uppercase tracking-wide mt-1">Procesados</div>
                </div>
                <div class="bg-amber-50 rounded-md p-3 text-center">
                    <div class="text-xl font-bold text-amber-700">{{ $r['dnf'] }}</div>
                    <div class="text-xs text-slate-500 uppercase tracking-wide mt-1">DNF</div>
                </div>
                <div class="bg-red-50 rounded-md p-3 text-center">
                    <div class="text-xl font-bold text-red-700">{{ $r['dns'] }}</div>
                    <div class="text-xs text-slate-500 uppercase tracking-wide mt-1">DNS</div>
                </div>
                <div class="bg-slate-50 rounded-md p-3 text-center">
                    <div class="text-xl font-bold text-slate-800">{{ count($r['no_vinculados']) }}</div>
                    <div class="text-xs text-slate-500 uppercase tracking-wide mt-1">Sin vincular</div>
                </div>
            </div>

            @if (count($r['no_vinculados']) > 0)
                <p class="text-xs font-semibold text-slate-600 mb-2">
                    Bibs sin participante vinculado — revisar la numeración de corredor si deberían matchear:
                </p>
                <div class="flex flex-wrap gap-1">
                    @foreach ($r['no_vinculados'] as $item)
                        <span class="text-xs bg-slate-100 border border-slate-200 rounded px-2 py-0.5">{{ $item['numero_corredor'] ?? '?' }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
@endsection
