@extends('admin.layouts.app')

@section('title', ($banco ? 'Editar' : 'Nuevo').' banco SIP — Admin Eventos')

@section('content')
<h1 class="text-lg font-bold mb-1">{{ $banco ? 'Editar banco SIP' : 'Nuevo banco SIP' }}</h1>
<p class="text-sm text-slate-500 mb-4">
    <a href="{{ route('admin.sip-bancos.index') }}" class="text-brand-600 hover:underline">← Bancos SIP</a>
</p>

<div class="bg-white rounded-lg shadow p-6 max-w-lg">
    <form method="POST" action="{{ $action }}" class="space-y-4">
        @csrf
        @if ($banco)
            @method('PUT')
        @endif

        <div>
            <label class="block text-sm font-semibold mb-1" for="organizador_id">Organizador</label>
            <select name="organizador_id" id="organizador_id" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                <option value="">— sin asignar (banco por defecto) —</option>
                @foreach ($organizadores as $organizador)
                    <option value="{{ $organizador['id'] }}" @selected((string) old('organizador_id', $banco['organizadorId'] ?? '') === (string) $organizador['id'])>
                        {{ $organizador['nombre'] }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold mb-1" for="nombre">Nombre</label>
            <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $banco['nombre'] ?? '') }}" required
                   placeholder="Ej. Bisa, Banco Unión..."
                   class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-1" for="sip_username">Usuario SIP</label>
            <input type="text" name="sip_username" id="sip_username" value="{{ old('sip_username', $banco['sipUsername'] ?? '') }}" required
                   class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-1" for="sip_password">
                Contraseña SIP @if ($banco) <span class="font-normal text-slate-500">(dejar vacío para no cambiar)</span> @endif
            </label>
            <input type="password" name="sip_password" id="sip_password" {{ $banco ? '' : 'required' }}
                   class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-1" for="sip_apikey">
                API Key @if ($banco) <span class="font-normal text-slate-500">(dejar vacío para no cambiar)</span> @endif
            </label>
            <input type="password" name="sip_apikey" id="sip_apikey" {{ $banco ? '' : 'required' }}
                   class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-1" for="sip_apikey_servicio">
                API Key de servicio @if ($banco) <span class="font-normal text-slate-500">(dejar vacío para no cambiar)</span> @endif
            </label>
            <input type="password" name="sip_apikey_servicio" id="sip_apikey_servicio" {{ $banco ? '' : 'required' }}
                   class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-1" for="sip_base_auth_url">
                URL de autenticación <span class="font-normal text-slate-500">(opcional — vacío usa la del banco por defecto)</span>
            </label>
            <input type="url" name="sip_base_auth_url" id="sip_base_auth_url" value="{{ old('sip_base_auth_url', $banco['sipBaseAuthUrl'] ?? '') }}"
                   class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-1" for="sip_base_api_url">
                URL de API <span class="font-normal text-slate-500">(opcional — vacío usa la del banco por defecto)</span>
            </label>
            <input type="url" name="sip_base_api_url" id="sip_base_api_url" value="{{ old('sip_base_api_url', $banco['sipBaseApiUrl'] ?? '') }}"
                   class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-1" for="callback_basic_user">Usuario callback</label>
            <input type="text" name="callback_basic_user" id="callback_basic_user" value="{{ old('callback_basic_user', $banco['callbackBasicUser'] ?? '') }}" required
                   class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-1" for="callback_basic_password">
                Contraseña callback @if ($banco) <span class="font-normal text-slate-500">(dejar vacío para no cambiar)</span> @endif
            </label>
            <input type="password" name="callback_basic_password" id="callback_basic_password" {{ $banco ? '' : 'required' }}
                   class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
        </div>
        <div>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="hidden" name="activo" value="0">
                <input type="checkbox" name="activo" value="1" {{ old('activo', $banco['activo'] ?? true) ? 'checked' : '' }}>
                Activo
            </label>
        </div>

        <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white rounded-md px-3 py-2 text-sm font-semibold">
            Guardar
        </button>
    </form>
</div>
@endsection
