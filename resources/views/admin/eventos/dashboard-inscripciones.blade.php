@extends('admin.layouts.app')

@section('title', 'Dashboard de inscripciones — '.$evento['name'])

@section('content')
<div class="flex justify-between items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="text-lg font-bold">Dashboard de inscripciones</h1>
        <p class="text-sm text-slate-600">{{ $evento['name'] }}</p>
    </div>
    <a href="{{ route('admin.eventos.edit', $evento['id']) }}" class="text-sm text-brand-600 hover:underline self-center">
        ← Volver a editar evento
    </a>
</div>

<p class="text-xs text-slate-500 mb-4">
    Mismo conteo que se le manda por correo al organizador cuando se publica el evento.
</p>

{{-- Tarjetas de totales — clicables (15/08/2026), llevan al detalle
     fila-por-fila filtrado por estado (ver ParticipantesDetalleController). --}}
<div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
    <a href="{{ route('admin.participantes.detalle', $evento['id']) }}" class="bg-white rounded-lg shadow p-4 text-center hover:shadow-md transition-shadow">
        <div class="text-2xl font-bold text-brand-600">{{ $totalGeneral['total'] }}</div>
        <div class="text-xs text-slate-500 uppercase tracking-wide mt-1">Total inscritos</div>
    </a>
    <a href="{{ route('admin.participantes.detalle', ['event' => $evento['id'], 'pago_status' => 'paid']) }}" class="bg-white rounded-lg shadow p-4 text-center hover:shadow-md transition-shadow">
        <div class="text-2xl font-bold text-green-600">{{ $totalGeneral['paid'] }}</div>
        <div class="text-xs text-slate-500 uppercase tracking-wide mt-1">Pagados</div>
    </a>
    <a href="{{ route('admin.participantes.detalle', ['event' => $evento['id'], 'pago_status' => 'pending']) }}" class="bg-white rounded-lg shadow p-4 text-center hover:shadow-md transition-shadow">
        <div class="text-2xl font-bold text-amber-600">{{ $totalGeneral['pending'] }}</div>
        <div class="text-xs text-slate-500 uppercase tracking-wide mt-1">Pendientes</div>
    </a>
    <a href="{{ route('admin.participantes.detalle', ['event' => $evento['id'], 'pago_status' => 'cancelled']) }}" class="bg-white rounded-lg shadow p-4 text-center hover:shadow-md transition-shadow">
        <div class="text-2xl font-bold text-red-600">{{ $totalGeneral['cancelled'] }}</div>
        <div class="text-xs text-slate-500 uppercase tracking-wide mt-1">Cancelados</div>
    </a>
    <a href="{{ route('admin.participantes.detalle', ['event' => $evento['id'], 'pago_status' => 'failed']) }}" class="bg-white rounded-lg shadow p-4 text-center hover:shadow-md transition-shadow">
        <div class="text-2xl font-bold text-red-600">{{ $totalGeneral['failed'] }}</div>
        <div class="text-xs text-slate-500 uppercase tracking-wide mt-1">Fallidos</div>
    </a>
</div>

{{-- Balance del presupuesto --}}
<div class="flex justify-between items-center mb-2">
    <h2 class="font-bold text-sm text-brand-600">Balance del evento</h2>
    @if (Route::has('admin.presupuesto.index'))
        <a href="{{ route('admin.presupuesto.index', $evento['id']) }}" class="text-sm text-brand-600 hover:underline">
            Ver/editar presupuesto →
        </a>
    @endif
</div>
@if ($balance)
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-xl font-bold text-brand-600">${{ number_format($balance['ingresosInscripciones'], 2) }}</div>
        <div class="text-xs text-slate-500 uppercase tracking-wide mt-1">Ingreso por inscripciones</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-xl font-bold text-brand-600">${{ number_format($balance['ingresosManuales'], 2) }}</div>
        <div class="text-xs text-slate-500 uppercase tracking-wide mt-1">Ingresos manuales</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-xl font-bold text-red-600">${{ number_format($balance['gastosManuales'], 2) }}</div>
        <div class="text-xs text-slate-500 uppercase tracking-wide mt-1">Gastos</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-xl font-bold text-green-600">${{ number_format($balance['utilidadNeta'], 2) }}</div>
        <div class="text-xs text-slate-500 uppercase tracking-wide mt-1">Utilidad neta</div>
    </div>
</div>
@endif

{{-- Por categoría --}}
<h2 class="font-bold text-sm text-brand-600 mb-2">Por categoría</h2>
<div class="overflow-x-auto mb-6">
    <table class="w-full bg-white rounded-lg shadow text-sm">
        <thead>
            <tr class="bg-brand-600 text-white text-left">
                <th class="px-3 py-2 font-semibold">Categoría</th>
                @foreach ($estados as $estado)
                    <th class="px-3 py-2 font-semibold text-right">{{ ucfirst($estado) }}</th>
                @endforeach
                <th class="px-3 py-2 font-semibold text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($porCategoria as $categoria => $counts)
                <tr class="border-t border-slate-100">
                    <td class="px-3 py-2">{{ $nombresCategorias[$categoria] ?? $categoria }}</td>
                    @foreach ($estados as $estado)
                        <td class="px-3 py-2 text-right">{{ $counts[$estado] }}</td>
                    @endforeach
                    <td class="px-3 py-2 text-right font-semibold">{{ $counts['total'] }}</td>
                </tr>
            @empty
                <tr><td class="px-3 py-2 text-slate-500" colspan="{{ count($estados) + 2 }}">Todavía no hay inscripciones.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Por tipo de formulario --}}
<h2 class="font-bold text-sm text-brand-600 mb-2">Por tipo de formulario</h2>
<div class="overflow-x-auto">
    <table class="w-full bg-white rounded-lg shadow text-sm">
        <thead>
            <tr class="bg-brand-600 text-white text-left">
                <th class="px-3 py-2 font-semibold">Tipo de formulario</th>
                @foreach ($estados as $estado)
                    <th class="px-3 py-2 font-semibold text-right">{{ ucfirst($estado) }}</th>
                @endforeach
                <th class="px-3 py-2 font-semibold text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($porFormulario as $formTypeId => $counts)
                <tr class="border-t border-slate-100">
                    <td class="px-3 py-2">{{ $nombresFormTypes[$formTypeId] ?? 'Sin especificar' }}</td>
                    @foreach ($estados as $estado)
                        <td class="px-3 py-2 text-right">{{ $counts[$estado] }}</td>
                    @endforeach
                    <td class="px-3 py-2 text-right font-semibold">{{ $counts['total'] }}</td>
                </tr>
            @empty
                <tr><td class="px-3 py-2 text-slate-500" colspan="{{ count($estados) + 2 }}">Todavía no hay inscripciones.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($reporteInscritos)
<p class="text-xs text-slate-500 mb-4 mt-8">
    Las 3 tablas de abajo solo cuentan inscripciones <strong>pagadas</strong> — son un reporte de
    recaudación, no de estado (eso ya está arriba).
</p>

{{-- Recaudación por modalidad (tipo de formulario) --}}
<h2 class="font-bold text-sm text-brand-600 mb-2">Inscritos por modalidad</h2>
<div class="overflow-x-auto mb-6">
    <table class="w-full bg-white rounded-lg shadow text-sm">
        <thead>
            <tr class="bg-brand-600 text-white text-left">
                <th class="px-3 py-2 font-semibold">Modalidad</th>
                <th class="px-3 py-2 font-semibold text-right">Cantidad</th>
                <th class="px-3 py-2 font-semibold text-right">Recaudación</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reporteInscritos['porModalidad']['filas'] as $fila)
                <tr class="border-t border-slate-100">
                    <td class="px-3 py-2">{{ $fila['nombre'] }}</td>
                    <td class="px-3 py-2 text-right">{{ $fila['cantidad'] }}</td>
                    <td class="px-3 py-2 text-right">${{ number_format($fila['recaudacion'], 2) }}</td>
                </tr>
            @empty
                <tr><td class="px-3 py-2 text-slate-500" colspan="3">Todavía no hay inscripciones pagadas.</td></tr>
            @endforelse
            <tr class="border-t border-slate-200 font-semibold bg-slate-50">
                <td class="px-3 py-2">Total</td>
                <td class="px-3 py-2 text-right">{{ $reporteInscritos['porModalidad']['totalCantidad'] }}</td>
                <td class="px-3 py-2 text-right">${{ number_format($reporteInscritos['porModalidad']['totalRecaudacion'], 2) }}</td>
            </tr>
        </tbody>
    </table>
</div>

{{-- Recaudación por categoría / distancia --}}
<h2 class="font-bold text-sm text-brand-600 mb-2">Inscritos por categoría / distancia</h2>
<div class="overflow-x-auto mb-6">
    <table class="w-full bg-white rounded-lg shadow text-sm">
        <thead>
            <tr class="bg-brand-600 text-white text-left">
                <th class="px-3 py-2 font-semibold">Categoría</th>
                <th class="px-3 py-2 font-semibold text-right">Cantidad</th>
                <th class="px-3 py-2 font-semibold text-right">Recaudación</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reporteInscritos['porCategoria']['filas'] as $fila)
                <tr class="border-t border-slate-100">
                    <td class="px-3 py-2">{{ $fila['nombre'] }}</td>
                    <td class="px-3 py-2 text-right">{{ $fila['cantidad'] }}</td>
                    <td class="px-3 py-2 text-right">${{ number_format($fila['recaudacion'], 2) }}</td>
                </tr>
            @empty
                <tr><td class="px-3 py-2 text-slate-500" colspan="3">Todavía no hay inscripciones pagadas.</td></tr>
            @endforelse
            <tr class="border-t border-slate-200 font-semibold bg-slate-50">
                <td class="px-3 py-2">Total</td>
                <td class="px-3 py-2 text-right">{{ $reporteInscritos['porCategoria']['totalCantidad'] }}</td>
                <td class="px-3 py-2 text-right">${{ number_format($reporteInscritos['porCategoria']['totalRecaudacion'], 2) }}</td>
            </tr>
        </tbody>
    </table>
</div>

{{-- Poleras (sexo/talla) --}}
<h2 class="font-bold text-sm text-brand-600 mb-2">Reporte de poleras</h2>
<div class="overflow-x-auto">
    <table class="w-full bg-white rounded-lg shadow text-sm max-w-md">
        <thead>
            <tr class="bg-brand-600 text-white text-left">
                <th class="px-3 py-2 font-semibold">Sexo</th>
                <th class="px-3 py-2 font-semibold">Talla</th>
                <th class="px-3 py-2 font-semibold text-right">Cantidad</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reporteInscritos['poleras']['filas'] as $fila)
                <tr class="border-t border-slate-100">
                    <td class="px-3 py-2">{{ $fila['sexo'] }}</td>
                    <td class="px-3 py-2">{{ $fila['talla'] }}</td>
                    <td class="px-3 py-2 text-right">{{ $fila['cantidad'] }}</td>
                </tr>
            @empty
                <tr><td class="px-3 py-2 text-slate-500" colspan="3">Nadie pagado eligió polera todavía.</td></tr>
            @endforelse
            <tr class="border-t border-slate-200 font-semibold bg-slate-50">
                <td class="px-3 py-2" colspan="2">Total</td>
                <td class="px-3 py-2 text-right">{{ $reporteInscritos['poleras']['total'] }}</td>
            </tr>
        </tbody>
    </table>
</div>

{{-- Reporte de talleres (19/08/2026) — pedido del usuario: "no tenemos
     reporte de talleres". Condicional a propósito: la mayoría de los
     eventos (carreras) no tienen talleres, mostrar una tabla siempre
     vacía en el dashboard de todos sería ruido. Se agrupa por sesión
     (no solo por taller) porque el cupo/horario son por sesión — ver
     ReporteInscritosData::agruparPorTaller(). --}}
@if (!empty($reporteInscritos['porTaller']['filas']))
<div class="flex items-center justify-between mt-6 mb-2">
    <h2 class="font-bold text-sm text-brand-600">Reporte de talleres</h2>
    {{-- CSV sin agrupar (20/08/2026) — una fila por selección de taller,
         ordenado por fecha, para que el organizador pueda bajarlo y
         trabajarlo aparte (no es la misma tabla agrupada de abajo). --}}
    <a href="{{ route('admin.eventos.dashboard.talleres.csv', $evento['id']) }}"
       class="inline-block bg-white border border-slate-300 hover:bg-slate-50 text-xs font-semibold px-3 py-1.5 rounded-md">
        Descargar CSV (detalle sin agrupar)
    </a>
</div>
<div class="overflow-x-auto">
    <table class="w-full bg-white rounded-lg shadow text-sm">
        <thead>
            <tr class="bg-brand-600 text-white text-left">
                <th class="px-3 py-2 font-semibold">Taller</th>
                <th class="px-3 py-2 font-semibold">Sesión</th>
                <th class="px-3 py-2 font-semibold">Fecha / horario</th>
                <th class="px-3 py-2 font-semibold text-right">Inscritos</th>
                <th class="px-3 py-2 font-semibold text-right">Cupo</th>
                <th class="px-3 py-2 font-semibold text-right">Disponible</th>
                <th class="px-3 py-2 font-semibold text-right">Recaudación</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reporteInscritos['porTaller']['filas'] as $fila)
                <tr class="border-t border-slate-100">
                    <td class="px-3 py-2">{{ $fila['tallerNombre'] }}</td>
                    <td class="px-3 py-2">{{ $fila['sesionTitulo'] }}</td>
                    <td class="px-3 py-2">
                        {{ $fila['fecha'] }}
                        @if ($fila['horaInicio'])
                            · {{ $fila['horaInicio'] }}–{{ $fila['horaFin'] }}
                        @endif
                    </td>
                    <td class="px-3 py-2 text-right">{{ $fila['cantidad'] }}</td>
                    <td class="px-3 py-2 text-right">{{ $fila['cupo'] ?? '—' }}</td>
                    <td class="px-3 py-2 text-right {{ $fila['disponible'] === 0 ? 'text-red-600 font-semibold' : '' }}">
                        {{ $fila['disponible'] ?? '—' }}
                    </td>
                    <td class="px-3 py-2 text-right">${{ number_format($fila['recaudacion'], 2) }}</td>
                </tr>
            @endforeach
            <tr class="border-t border-slate-200 font-semibold bg-slate-50">
                <td class="px-3 py-2" colspan="3">Total</td>
                <td class="px-3 py-2 text-right">{{ $reporteInscritos['porTaller']['totalCantidad'] }}</td>
                <td class="px-3 py-2" colspan="2"></td>
                <td class="px-3 py-2 text-right">${{ number_format($reporteInscritos['porTaller']['totalRecaudacion'], 2) }}</td>
            </tr>
        </tbody>
    </table>
</div>
@endif
@endif
@endsection
