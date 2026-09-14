@extends('admin.layouts.app')

@section('title', ($usuario ? 'Editar' : 'Nuevo').' usuario — Admin Eventos')

@section('content')
<h1 class="text-lg font-bold mb-4">{{ $usuario ? 'Editar usuario' : 'Nuevo usuario' }}</h1>

<div class="bg-white rounded-lg shadow p-6 max-w-lg">
    <form method="POST" action="{{ $action }}" class="space-y-4">
        @csrf
        @if ($usuario)
            @method('PUT')
        @endif

        <div>
            <label class="block text-sm font-semibold mb-1" for="nombre">Nombre</label>
            <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $usuario['nombre'] ?? '') }}" required
                   class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-1" for="email">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email', $usuario['email'] ?? '') }}" required
                   class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-1" for="password">
                Contraseña @if ($usuario) <span class="font-normal text-slate-500">(dejar vacío para no cambiar)</span> @endif
            </label>
            <input type="password" name="password" id="password" {{ $usuario ? '' : 'required' }}
                   class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-1" for="rol">Rol</label>
            <select name="rol" id="rol" required onchange="toggleEventoWraps(this.value)"
                    class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                @php($rolActual = old('rol', $usuario['rol'] ?? 'admin'))
                <option value="admin" @selected($rolActual === 'admin')>admin (scoped a uno o varios eventos)</option>
                <option value="super_admin" @selected($rolActual === 'super_admin')>super_admin (ve todo)</option>
                {{-- Caja de cobro presencial (14/08/2026) — permisos mínimos,
                     solo el módulo de Caja de su evento asignado. Ver
                     ApiRestEvent/brain/api_rest_event/PLAN-CAJA-COBRO-PRESENCIAL-14082026.md. --}}
                <option value="cajero" @selected($rolActual === 'cajero')>cajero (solo caja, scoped a un evento)</option>
            </select>
        </div>
        <div id="evento_id_wrap" class="{{ $rolActual === 'super_admin' ? 'hidden' : '' }}">
            <label class="block text-sm font-semibold mb-1" for="evento_id">
                Evento {{ $rolActual === 'admin' ? 'principal' : 'asignado' }}
            </label>
            <select name="evento_id" id="evento_id" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                <option value="">— seleccionar —</option>
                @foreach ($eventos as $evento)
                    <option value="{{ $evento['id'] }}" @selected((string) old('evento_id', $usuario['evento_id'] ?? '') === (string) $evento['id'])>
                        {{ $evento['id'] }} — {{ $evento['name'] }}
                    </option>
                @endforeach
            </select>
        </div>
        {{-- Admin de evento asignado a varios eventos (28/08/2026,
             sincronizado 14/09/2026) — solo tiene sentido para rol admin
             (cajero sigue con un único evento, decisión explícita del
             usuario). Opcional: un admin sin eventos adicionales se
             comporta exactamente igual que antes de esta feature. --}}
        <div id="eventos_adicionales_wrap" class="{{ $rolActual === 'admin' ? '' : 'hidden' }}">
            <label class="block text-sm font-semibold mb-1" for="evento_ids_adicionales">
                Eventos adicionales <span class="font-normal text-slate-500">(opcional, mantené Ctrl/Cmd para elegir varios)</span>
            </label>
            @php($adicionalesActuales = old('evento_ids_adicionales', collect($usuario['eventos_adicionales'] ?? [])->pluck('id')->all()))
            <select name="evento_ids_adicionales[]" id="evento_ids_adicionales" multiple size="5"
                    class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                @foreach ($eventos as $evento)
                    <option value="{{ $evento['id'] }}" @selected(in_array((string) $evento['id'], array_map('strval', $adicionalesActuales), true))>
                        {{ $evento['id'] }} — {{ $evento['name'] }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="activo" value="1" {{ old('activo', $usuario['activo'] ?? true) ? 'checked' : '' }}>
                Activo
            </label>
        </div>

        <script>
            function toggleEventoWraps(rol) {
                document.getElementById('evento_id_wrap').classList.toggle('hidden', rol === 'super_admin');
                document.getElementById('eventos_adicionales_wrap').classList.toggle('hidden', rol !== 'admin');
            }
        </script>

        <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white rounded-md px-3 py-2 text-sm font-semibold">
            Guardar
        </button>
    </form>
</div>
@endsection
