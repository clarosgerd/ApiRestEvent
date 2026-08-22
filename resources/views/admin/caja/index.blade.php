@extends('admin.layouts.app')

@section('title', 'Caja — '.($evento['name'] ?? 'Evento'))

@section('content')
<h1 class="text-lg font-bold mb-1">Caja de cobro presencial</h1>
<p class="text-sm text-slate-500 mb-5">{{ $evento['name'] ?? '' }}</p>

@if (!$turno)
    <div class="bg-white rounded-lg shadow p-6 max-w-md">
        <h2 class="font-semibold mb-3">Abrir turno de caja</h2>
        <p class="text-sm text-slate-600 mb-4">
            No podés cobrar sin un turno abierto — es lo que permite controlar el cierre de caja al
            final de la jornada.
        </p>
        <form method="POST" action="{{ route('admin.caja.turno.abrir', $evento['id']) }}" class="space-y-3">
            @csrf
            <div>
                <label class="block text-sm font-semibold mb-1" for="fondo_inicial">Fondo inicial (efectivo para dar cambio)</label>
                <input type="number" step="0.01" min="0" name="fondo_inicial" id="fondo_inicial" value="0" required
                       class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
            </div>
            <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white rounded-md px-3 py-2 text-sm font-semibold">
                Abrir turno
            </button>
        </form>
    </div>
@else
    <div class="bg-white rounded-lg shadow p-5 mb-5">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <p class="text-sm text-slate-500">Turno abierto desde</p>
                <p class="font-semibold">{{ \Illuminate\Support\Carbon::parse($turno['abiertoAt'])->format('d/m/Y H:i') }}</p>
            </div>
            <div>
                <p class="text-sm text-slate-500">Fondo inicial</p>
                <p class="font-semibold">{{ number_format($turno['fondoInicial'], 2) }}</p>
            </div>
            <div>
                <p class="text-sm text-slate-500">Cobrado en este turno</p>
                <p class="font-semibold text-brand-600">{{ number_format($turno['totalCobrado'] ?? 0, 2) }}</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
        <a href="{{ route('admin.caja.nueva', $evento['id']) }}"
           class="bg-brand-600 hover:bg-brand-700 text-white rounded-lg shadow p-5 text-center font-semibold">
            + Nueva inscripción
        </a>
        <a href="{{ route('admin.caja.buscar', $evento['id']) }}"
           class="bg-white hover:bg-slate-50 border border-slate-300 rounded-lg shadow p-5 text-center font-semibold">
            🔎 Buscar / cobrar / editar
        </a>
    </div>

    <div class="bg-white rounded-lg shadow p-6 max-w-md">
        <h2 class="font-semibold mb-3">Cerrar turno</h2>
        <p class="text-sm text-slate-600 mb-4">
            Contá el efectivo físico antes de cerrar — el sistema calcula el esperado
            (fondo inicial + lo cobrado en este turno) y la diferencia queda registrada.
        </p>
        <form method="POST" action="{{ route('admin.caja.turno.cerrar', [$evento['id'], $turno['id']]) }}" class="space-y-3">
            @csrf
            <div>
                <label class="block text-sm font-semibold mb-1" for="monto_contado">Monto contado</label>
                <input type="number" step="0.01" min="0" name="monto_contado" id="monto_contado" required
                       class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1" for="notas">Notas (opcional)</label>
                <textarea name="notas" id="notas" rows="2" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></textarea>
            </div>
            <button type="submit" onclick="return confirm('¿Cerrar el turno? No vas a poder seguir cobrando hasta abrir uno nuevo.')"
                    class="w-full bg-slate-700 hover:bg-slate-800 text-white rounded-md px-3 py-2 text-sm font-semibold">
                Cerrar turno
            </button>
        </form>
    </div>
@endif
@endsection
