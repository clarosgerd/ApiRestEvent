@extends('admin.layouts.app')

@section('title', ($cuenta ? 'Editar' : 'Nueva').' cuenta de WhatsApp — Admin Eventos')

@section('content')
<h1 class="text-lg font-bold mb-1">{{ $cuenta ? 'Editar cuenta de WhatsApp' : 'Nueva cuenta de WhatsApp' }}</h1>
<p class="text-sm text-slate-500 mb-4">
    <a href="{{ route('admin.whatsapp-cuentas.index') }}" class="text-brand-600 hover:underline">← WhatsApp Business</a>
</p>

<div class="bg-white rounded-lg shadow p-6 max-w-lg">
    {{-- Corrección 09/10/2026 — Meta dejó de aceptar una variable posicional
         suelta ({{1}}): "La plantilla contiene parámetros variables con
         formato incorrecto" al intentar aprobarla. Ahora exige una variable
         CON NOMBRE y texto fijo antes/después — ver
         WhatsappCloudApiService::NOMBRE_PARAMETRO, que manda
         siempre "mensaje" como parameter_name. --}}
    <p class="text-xs text-slate-500 bg-slate-50 border border-slate-200 rounded-md p-3 mb-4">
        El organizador tiene que traer su propia cuenta de WhatsApp Business ya aprobada por Meta, con UNA plantilla
        de utilidad aprobada con un solo parámetro de texto <strong>con nombre</strong> — el sistema siempre manda
        el parámetro con el nombre <code class="bg-white px-1 rounded">mensaje</code>, así que el cuerpo de la
        plantilla en Meta tiene que usar exactamente esa variable, con texto fijo antes y después (no puede quedar
        pegada al principio ni al final). Ejemplo de cuerpo a copiar en Meta:
        <code class="bg-white px-1 rounded block mt-1">Pass2Go: @{{mensaje}}<br>Gracias por confiar en nosotros.</code>
        Ese parámetro es donde viaja el mensaje completo (confirmación de pago, recordatorio, etc.) — no hace falta
        aprobar una plantilla distinta por cada tipo de aviso.
    </p>

    <form method="POST" action="{{ $action }}" class="space-y-4">
        @csrf
        @if ($cuenta)
            @method('PUT')
        @endif

        <div>
            <label class="block text-sm font-semibold mb-1" for="organizador_id">Organizador</label>
            <select name="organizador_id" id="organizador_id" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                <option value="">— sin asignar —</option>
                @foreach ($organizadores as $organizador)
                    <option value="{{ $organizador['id'] }}" @selected((string) old('organizador_id', $cuenta['organizadorId'] ?? '') === (string) $organizador['id'])>
                        {{ $organizador['nombre'] }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold mb-1" for="nombre">Nombre</label>
            <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $cuenta['nombre'] ?? '') }}" required
                   placeholder="Ej. WhatsApp COLABIOCLI"
                   class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-1" for="phone_number_id">Phone Number ID</label>
            <input type="text" name="phone_number_id" id="phone_number_id" value="{{ old('phone_number_id', $cuenta['phoneNumberId'] ?? '') }}" required
                   placeholder="Meta Business → WhatsApp → Configuración de la API"
                   class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-1" for="business_account_id">
                Business Account ID <span class="font-normal text-slate-500">(opcional)</span>
            </label>
            <input type="text" name="business_account_id" id="business_account_id" value="{{ old('business_account_id', $cuenta['businessAccountId'] ?? '') }}"
                   class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-1" for="access_token">
                Access Token @if ($cuenta) <span class="font-normal text-slate-500">(dejar vacío para no cambiar)</span> @endif
            </label>
            <input type="password" name="access_token" id="access_token" {{ $cuenta ? '' : 'required' }}
                   class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-1" for="template_name">Nombre de la plantilla</label>
            <input type="text" name="template_name" id="template_name" value="{{ old('template_name', $cuenta['templateName'] ?? 'notificacion_sistema') }}"
                   class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-semibold mb-1" for="template_lang">Idioma de la plantilla</label>
            <input type="text" name="template_lang" id="template_lang" value="{{ old('template_lang', $cuenta['templateLang'] ?? 'es') }}"
                   placeholder="es"
                   class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
        </div>
        <div>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="hidden" name="activo" value="0">
                <input type="checkbox" name="activo" value="1" {{ old('activo', $cuenta['activo'] ?? true) ? 'checked' : '' }}>
                Activo
            </label>
        </div>

        <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white rounded-md px-3 py-2 text-sm font-semibold">
            Guardar
        </button>
    </form>
</div>
@endsection
