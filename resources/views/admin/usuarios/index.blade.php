@extends('admin.layouts.app')

@section('title', 'Usuarios — Admin Eventos')

@section('content')
<div class="flex justify-between items-center mb-4">
    <h1 class="text-lg font-bold">Usuarios admin</h1>
    <a href="{{ route('admin.usuarios.create') }}" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
        + Nuevo usuario
    </a>
</div>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left">
            <tr>
                <th class="px-4 py-2">Nombre</th>
                <th class="px-4 py-2">Email</th>
                <th class="px-4 py-2">Rol</th>
                <th class="px-4 py-2">Evento</th>
                <th class="px-4 py-2">Activo</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($usuarios as $usuario)
                <tr class="border-t border-slate-100">
                    <td class="px-4 py-2 font-semibold">{{ $usuario['nombre'] }}</td>
                    <td class="px-4 py-2">{{ $usuario['email'] }}</td>
                    <td class="px-4 py-2">{{ $usuario['rol'] }}</td>
                    <td class="px-4 py-2">{{ $usuario['evento_id'] ?? '—' }}</td>
                    <td class="px-4 py-2">{{ $usuario['activo'] ? 'Sí' : 'No' }}</td>
                    <td class="px-4 py-2 text-right space-x-2">
                        <a href="{{ route('admin.usuarios.edit', $usuario['id']) }}" class="text-brand-600 hover:underline">Editar</a>
                        <form method="POST" action="{{ route('admin.usuarios.destroy', $usuario['id']) }}" class="inline"
                              onsubmit="return confirm('¿Eliminar este usuario?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
