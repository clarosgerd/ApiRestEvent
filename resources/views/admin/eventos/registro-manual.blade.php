@extends('admin.layouts.app')

@section('title', 'Carga masiva de inscripciones — '.$evento['name'])

@section('content')
<div class="flex justify-between items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="text-lg font-bold">Carga masiva de inscripciones por CSV</h1>
        <p class="text-sm text-slate-600">{{ $evento['name'] }}</p>
    </div>
    <a href="{{ route('admin.eventos.edit', $evento['id']) }}" class="text-sm text-brand-600 hover:underline self-center">
        ← Volver a editar evento
    </a>
</div>

@if (session('status'))
    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-2 rounded-md mb-5 text-sm">
        {{ session('status') }}
    </div>
@endif
@if ($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-2 rounded-md mb-5 text-sm">
        {{ $errors->first() }}
    </div>
@endif

<section class="bg-white rounded-lg shadow p-6 mb-6">
    <p class="text-sm text-slate-600 mb-4">
        Cada fila del CSV crea su propia inscripción, con pago <strong>pendiente</strong>
        (categoría + cargo de servicio del 5%, sin souvenirs ni código promocional).
        El tipo de formulario y la categoría se aplican a <strong>todo el archivo</strong> —
        si necesitás mezclar categorías o tipos de formulario, subí un archivo por combinación.
    </p>

    <form method="POST" action="{{ route('admin.registro-manual.store', $evento['id']) }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold mb-1">Tipo de formulario</label>
                <select name="form_types_id" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    <option value="">Seleccionar…</option>
                    @foreach ($evento['formTypes'] ?? [] as $ft)
                        <option value="{{ $ft['id'] }}" {{ !empty($ft['hasTeam']) ? 'disabled' : '' }}>
                            {{ $ft['name'] }}{{ !empty($ft['hasTeam']) ? ' (requiere equipo — no soportado acá)' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Categoría</label>
                <select name="categoria" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    <option value="">Seleccionar…</option>
                    @foreach ($evento['categories'] ?? [] as $cat)
                        <option value="{{ $cat['name'] }}">{{ $cat['name'] }} — {{ $cat['price'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-semibold mb-1">Archivo CSV</label>
            <input type="file" name="csv" accept=".csv,text/csv" required
                   class="w-full text-sm border border-slate-300 rounded-md px-3 py-2">
            <p class="text-xs text-slate-500 mt-1">
                Columnas requeridas: <code class="bg-slate-100 px-1 rounded">numero_documento, tipo_documento, nombre, apellido, alias, genero, fecha_nacimiento (AAAA-MM-DD), email, direccion, ciudad, telefono, contacto_emergencia_nombre, contacto_emergencia_telefono, contacto_emergencia_relacion, talleres</code>.
                <a href="{{ route('admin.registro-manual.plantilla', $evento['id']) }}" class="text-brand-600 hover:underline">Descargar plantilla</a>.
            </p>
        </div>

        @if (!empty($evento['talleres']))
            {{-- Talleres (21/08/2026) — la columna "talleres" del CSV se
                 completa con el NOMBRE del taller (no un ID), uno o más
                 separados por ';'. Solo se pueden identificar sin
                 ambigüedad desde el CSV los que tienen exactamente una
                 sesión activa — un taller con 0 o 2+ horarios no se puede
                 seleccionar por nombre solo, hay que cargar esa fila desde
                 el formulario público o el panel en su lugar. --}}
            <div class="bg-slate-50 border border-slate-200 rounded-md p-3 text-xs text-slate-600">
                <p class="font-semibold mb-1">Columna "talleres" — nombres válidos para este evento:</p>
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach ($evento['talleres'] as $taller)
                        @php($sesionesActivas = collect($taller['sesiones'] ?? [])->where('activa', true))
                        <li>
                            <code class="bg-white px-1 rounded border border-slate-200">{{ $taller['nombre'] }}</code>
                            {{ ($taller['modalidad'] ?? null) === 'REQUIRED' ? '— obligatorio' : '' }}
                            @if ($sesionesActivas->count() !== 1)
                                <span class="text-amber-600">
                                    ({{ $sesionesActivas->count() }} horarios activos — no seleccionable por CSV, usar el formulario público o el panel)
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <p class="mt-2">Para más de uno en la misma fila: <code class="bg-white px-1 rounded border border-slate-200">Taller A;Taller B</code>. Dejar vacío si no aplica.</p>
            </div>
        @endif

        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2 rounded-md">
            Subir y crear inscripciones
        </button>
    </form>
</section>

@if (session('registroManualReporte'))
    @php($reporte = session('registroManualReporte'))
    <section class="bg-white rounded-lg shadow p-6">
        <h2 class="font-bold mb-3 text-sm">Resultado de la última carga</h2>

        @if (!empty($reporte['creados']))
            <p class="text-sm font-semibold text-green-700 mb-1">Creadas ({{ count($reporte['creados']) }})</p>
            <ul class="text-sm text-slate-700 mb-4 list-disc list-inside">
                @foreach ($reporte['creados'] as $c)
                    <li>Fila {{ $c['fila'] }} — documento {{ $c['numero_documento'] }} → referencia <code class="bg-slate-100 px-1 rounded">{{ $c['referencia'] }}</code></li>
                @endforeach
            </ul>
        @endif

        @if (!empty($reporte['errores']))
            <p class="text-sm font-semibold text-red-700 mb-1">Con error ({{ count($reporte['errores']) }})</p>
            <ul class="text-sm text-slate-700 list-disc list-inside">
                @foreach ($reporte['errores'] as $e)
                    <li>Fila {{ $e['fila'] }} — documento {{ $e['numero_documento'] ?? '—' }}: {{ $e['error'] }}</li>
                @endforeach
            </ul>
        @endif
    </section>
@endif
@endsection
