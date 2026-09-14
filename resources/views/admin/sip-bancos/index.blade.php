@extends('admin.layouts.app')

@section('title', 'Bancos SIP — Admin Eventos')

@section('content')
<div class="flex justify-between items-center mb-4">
    <div>
        <h1 class="text-lg font-bold">Bancos SIP</h1>
        <p class="text-sm text-slate-500">Credenciales de cobro por organizador — un organizador sin banco asignado sigue usando el banco por defecto.</p>
    </div>
    <a href="{{ route('admin.sip-bancos.create') }}" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
        + Nuevo banco
    </a>
</div>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left">
            <tr>
                <th class="px-4 py-2">Nombre</th>
                <th class="px-4 py-2">Organizador</th>
                <th class="px-4 py-2">Usuario callback</th>
                <th class="px-4 py-2">Activo</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($bancos as $banco)
                <tr class="border-t border-slate-100">
                    <td class="px-4 py-2 font-semibold">{{ $banco['nombre'] }}</td>
                    <td class="px-4 py-2">{{ $banco['organizadorNombre'] ?? '— sin asignar (banco por defecto) —' }}</td>
                    <td class="px-4 py-2">{{ $banco['callbackBasicUser'] }}</td>
                    <td class="px-4 py-2">{{ $banco['activo'] ? 'Sí' : 'No' }}</td>
                    <td class="px-4 py-2 text-right space-x-2">
                        <a href="{{ route('admin.sip-bancos.edit', $banco['id']) }}" class="text-brand-600 hover:underline">Editar</a>
                        <form method="POST" action="{{ route('admin.sip-bancos.destroy', $banco['id']) }}" class="inline"
                              onsubmit="return confirm('¿Eliminar este banco SIP?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">No hay bancos SIP registrados — se usa el banco por defecto (.env) para todos los organizadores.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
