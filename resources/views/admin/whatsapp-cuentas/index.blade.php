@extends('admin.layouts.app')

@section('title', 'WhatsApp Business — Admin Eventos')

@section('content')
<div class="flex justify-between items-center mb-4">
    <div>
        <h1 class="text-lg font-bold">WhatsApp Business (API oficial)</h1>
        <p class="text-sm text-slate-500">
            Credenciales de la API oficial de Meta por organizador — solo mandan mensajes los organizadores con el
            canal "Oficial" activado en <a href="{{ route('admin.organizadores.index') }}" class="text-brand-600 hover:underline">Organizadores</a>.
        </p>
    </div>
    <a href="{{ route('admin.whatsapp-cuentas.create') }}" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
        + Nueva cuenta
    </a>
</div>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left">
            <tr>
                <th class="px-4 py-2">Nombre</th>
                <th class="px-4 py-2">Organizador</th>
                <th class="px-4 py-2">Phone Number ID</th>
                <th class="px-4 py-2">Plantilla</th>
                <th class="px-4 py-2">Activo</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($cuentas as $cuenta)
                <tr class="border-t border-slate-100">
                    <td class="px-4 py-2 font-semibold">{{ $cuenta['nombre'] }}</td>
                    <td class="px-4 py-2">{{ $cuenta['organizadorNombre'] ?? '— sin asignar —' }}</td>
                    <td class="px-4 py-2 font-mono text-xs">{{ $cuenta['phoneNumberId'] }}</td>
                    <td class="px-4 py-2 text-xs text-slate-500">{{ $cuenta['templateName'] }} ({{ $cuenta['templateLang'] }})</td>
                    <td class="px-4 py-2">{{ $cuenta['activo'] ? 'Sí' : 'No' }}</td>
                    <td class="px-4 py-2 text-right space-x-2">
                        <a href="{{ route('admin.whatsapp-cuentas.edit', $cuenta['id']) }}" class="text-brand-600 hover:underline">Editar</a>
                        <form method="POST" action="{{ route('admin.whatsapp-cuentas.destroy', $cuenta['id']) }}" class="inline"
                              onsubmit="return confirm('¿Eliminar esta cuenta de WhatsApp?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">No hay cuentas de WhatsApp registradas.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
