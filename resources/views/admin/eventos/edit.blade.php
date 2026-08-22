@extends('admin.layouts.app')

@section('title', 'Editar evento — Admin Eventos')

@section('content')
{{-- Pestañas accesibles (patrón ARIA APG "tabs", roving tabindex + flechas)
     y menú "Herramientas" con <details> nativo (disclosure, sin necesitar
     ARIA de menú a mano) — mejora de visualización 12/08/2026, pedida por
     el usuario: la página tenía 9 secciones apiladas (789 líneas, había
     que scrollear todo para llegar a Agenda) y 11 botones sueltos en la
     barra superior. La pestaña activa se restaura por el hash de la URL
     (#categorias, etc.) — los controllers que redirigen de vuelta acá
     después de guardar (CategoriaController, FormTypeController, etc.)
     agregan ese hash a propósito para no perder la pestaña en la que
     estaba el usuario. --}}
<style>
    [role="tab"][aria-selected="true"] { border-color: #022858; color: #022858; }
</style>

<div class="flex justify-between items-center mb-4 flex-wrap gap-2">
    <h1 class="text-lg font-bold">Editar: {{ $evento['name'] }}</h1>
    <div class="flex gap-2 flex-wrap items-center">
        @if (Route::has('admin.eventos.dashboard'))
            <a href="{{ route('admin.eventos.dashboard', $evento['id']) }}"
               class="text-sm bg-white border border-slate-300 hover:bg-slate-50 px-3 py-1.5 rounded-md font-semibold">
                Dashboard de inscripciones
            </a>
        @endif
        @if (Route::has('admin.participantes.index'))
            <a href="{{ route('admin.participantes.index', $evento['id']) }}"
               class="text-sm bg-white border border-slate-300 hover:bg-slate-50 px-3 py-1.5 rounded-md font-semibold">
                Participantes
            </a>
        @endif

        {{--
            Consolidación monolito (21/08/2026), Fase 1c — cada link de acá
            abajo apunta a una pantalla que TODAVÍA vive solo en
            admin-eventos (dashboards, numeración, acreditación, carga
            masiva, lista de espera, bodega, Caja, delivery, sesiones de
            congreso, presupuesto, liquidación, ChronoTrack, Participantes).
            Route::has() los oculta hasta que cada una se migre en su propia
            sub-fase (1d Caja, 1e el resto) — así este menú no rompe con un
            RouteNotFoundException mientras tanto. Ver
            ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
        --}}
        <details class="relative group">
            <summary class="text-sm bg-white border border-slate-300 hover:bg-slate-50 px-3 py-1.5 rounded-md font-semibold cursor-pointer select-none list-none flex items-center gap-1.5 [&::-webkit-details-marker]:hidden">
                Herramientas
                <svg class="w-3.5 h-3.5 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.148l3.71-3.918a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                </svg>
            </summary>
            <div class="absolute right-0 mt-1 w-64 bg-white border border-slate-200 rounded-md shadow-lg py-1.5 z-20">
                <p class="px-3 pt-1 pb-1.5 text-xs font-semibold text-slate-400 uppercase tracking-wide">Inscripciones</p>
                @if (Route::has('admin.numeracion.index'))
                    <a href="{{ route('admin.numeracion.index', $evento['id']) }}" class="block px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Numeración de corredor y chip</a>
                @endif
                @if (Route::has('admin.acreditacion.index'))
                    <a href="{{ route('admin.acreditacion.index', $evento['id']) }}" class="block px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Acreditación</a>
                @endif
                @if ((session('admin_user')['rol'] ?? null) === 'super_admin' && Route::has('admin.registro-manual.index'))
                    <a href="{{ route('admin.registro-manual.index', $evento['id']) }}" class="block px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Carga masiva de inscripciones</a>
                @endif
                @if (Route::has('admin.lista-espera.index'))
                    <a href="{{ route('admin.lista-espera.index', $evento['id']) }}" class="block px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Lista de espera</a>
                @endif
                @if (Route::has('admin.bodega.index'))
                    <a href="{{ route('admin.bodega.index', $evento['id']) }}" class="block px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Bodega de stock</a>
                @endif
                @if (Route::has('admin.caja.index'))
                    <a href="{{ route('admin.caja.index', $evento['id']) }}" class="block px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Caja de cobro presencial</a>
                @endif
                @if (Route::has('admin.caja.cierres'))
                    <a href="{{ route('admin.caja.cierres', $evento['id']) }}" class="block px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Cierres de caja</a>
                @endif
                @if (Route::has('admin.delivery.index'))
                    <a href="{{ route('admin.delivery.index', $evento['id']) }}" class="block px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Delivery</a>
                @endif
                @if (($evento['tipoEvento'] ?? null) === 'Congreso / No aplica' && Route::has('admin.sesiones.index'))
                    <a href="{{ route('admin.sesiones.index', $evento['id']) }}" class="block px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Sesiones de congreso</a>
                @endif

                @if (Route::has('admin.presupuesto.index') || (Route::has('admin.liquidacion.show') && (session('admin_user')['rol'] ?? null) === 'super_admin'))
                    <p class="px-3 pt-2 pb-1.5 text-xs font-semibold text-slate-400 uppercase tracking-wide border-t border-slate-100 mt-1">Finanzas</p>
                @endif
                @if (Route::has('admin.presupuesto.index'))
                    <a href="{{ route('admin.presupuesto.index', $evento['id']) }}" class="block px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Presupuesto</a>
                @endif
                @if ((session('admin_user')['rol'] ?? null) === 'super_admin' && Route::has('admin.liquidacion.show'))
                    <a href="{{ route('admin.liquidacion.show', $evento['id']) }}" class="block px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Liquidación</a>
                @endif

                <p class="px-3 pt-2 pb-1.5 text-xs font-semibold text-slate-400 uppercase tracking-wide border-t border-slate-100 mt-1">Reportes</p>
                @if (Route::has('admin.chronotrack.index'))
                    <a href="{{ route('admin.chronotrack.index', $evento['id']) }}" class="block px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Resultados / ChronoTrack</a>
                @endif
                <a href="{{ route('admin.eventos.gafetes-pdf', $evento['id']) }}" target="_blank" class="block px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Gafetes (PDF)</a>
                <a href="{{ route('admin.eventos.certificados-pdf', $evento['id']) }}" target="_blank" class="block px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Certificados (PDF)</a>
            </div>
        </details>
    </div>
</div>

<div class="bg-white rounded-lg shadow mb-6">
    <div role="tablist" aria-label="Secciones del evento" class="flex overflow-x-auto border-b border-slate-200 px-2">
        <button type="button" role="tab" id="tab-datos" data-tab-id="datos" aria-controls="panel-datos" aria-selected="true" tabindex="0"
                class="shrink-0 whitespace-nowrap px-4 py-3 text-sm font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-600 focus-visible:-outline-offset-2">
            Datos
        </button>
        <button type="button" role="tab" id="tab-categorias" data-tab-id="categorias" aria-controls="panel-categorias" aria-selected="false" tabindex="-1"
                class="shrink-0 whitespace-nowrap px-4 py-3 text-sm font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-600 focus-visible:-outline-offset-2">
            Categorías
        </button>
        <button type="button" role="tab" id="tab-tipos" data-tab-id="tipos" aria-controls="panel-tipos" aria-selected="false" tabindex="-1"
                class="shrink-0 whitespace-nowrap px-4 py-3 text-sm font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-600 focus-visible:-outline-offset-2">
            Tipos de formulario
        </button>
        <button type="button" role="tab" id="tab-mapa" data-tab-id="mapa" aria-controls="panel-mapa" aria-selected="false" tabindex="-1"
                class="shrink-0 whitespace-nowrap px-4 py-3 text-sm font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-600 focus-visible:-outline-offset-2">
            Mapa
        </button>
        <button type="button" role="tab" id="tab-promos" data-tab-id="promos" aria-controls="panel-promos" aria-selected="false" tabindex="-1"
                class="shrink-0 whitespace-nowrap px-4 py-3 text-sm font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-600 focus-visible:-outline-offset-2">
            Promos
        </button>
        <button type="button" role="tab" id="tab-auspiciadores" data-tab-id="auspiciadores" aria-controls="panel-auspiciadores" aria-selected="false" tabindex="-1"
                class="shrink-0 whitespace-nowrap px-4 py-3 text-sm font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-600 focus-visible:-outline-offset-2">
            Auspiciadores
        </button>
        <button type="button" role="tab" id="tab-agenda" data-tab-id="agenda" aria-controls="panel-agenda" aria-selected="false" tabindex="-1"
                class="shrink-0 whitespace-nowrap px-4 py-3 text-sm font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-600 focus-visible:-outline-offset-2">
            Agenda
        </button>
        <button type="button" role="tab" id="tab-peligro" data-tab-id="peligro" aria-controls="panel-peligro" aria-selected="false" tabindex="-1"
                class="shrink-0 whitespace-nowrap px-4 py-3 text-sm font-semibold border-b-2 border-transparent text-red-600 hover:text-red-700 hover:border-red-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-600 focus-visible:-outline-offset-2">
            ⚠ Peligro
        </button>
    </div>

    {{-- 1. Datos del evento --}}
    <div id="panel-datos" role="tabpanel" aria-labelledby="tab-datos" tabindex="0" class="p-6">
        <form method="POST" action="{{ route('admin.eventos.update', $evento['id']) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-semibold mb-1">Nombre</label>
                    <input type="text" name="name" value="{{ old('name', $evento['name']) }}" required
                           class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-semibold mb-1">
                        Link directo (slug)
                        <span class="font-normal text-slate-500">(dejalo vacío para no tocarlo)</span>
                    </label>
                    <input type="text" name="url_slug" value="{{ old('url_slug', $evento['urlSlug'] ?? '') }}"
                           placeholder="ej. maraton-santa-cruz-2026" pattern="[a-z0-9]+(-[a-z0-9]+)*"
                           title="Solo minúsculas, números y guiones (sin espacios ni acentos)"
                           class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    <p class="text-xs text-slate-500 mt-1">
                        Se usa para el link que compartís del evento (?evento=slug) en vez del id numérico.
                    </p>
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-semibold mb-1">Descripción corta</label>
                    <input type="text" name="description" value="{{ old('description', $evento['description']) }}" required maxlength="500"
                           class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-semibold mb-1">Descripción larga</label>
                    <textarea name="longDescription" rows="8" maxlength="10000"
                              class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">{{ old('longDescription', $evento['longDescription']) }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Fecha</label>
                    <input type="date" name="date" value="{{ old('date', \Illuminate\Support\Str::before($evento['date'], ' ')) }}" required
                           class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Hora local</label>
                    <input type="time" name="localTime" value="{{ old('localTime', $evento['localTime']) }}"
                           class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-semibold mb-1">Ubicación</label>
                    <input type="text" name="location" value="{{ old('location', $evento['location']) }}" required
                           class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Estado</label>
                    <select name="status" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                        @foreach (['open','closed','coming_soon'] as $opt)
                            <option value="{{ $opt }}" @selected(old('status', $evento['status']) === $opt)>{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">
                        Tipo de evento <span class="font-normal text-slate-500">(disciplina, o "Congreso / No aplica")</span>
                    </label>
                    <select name="tipo_evento_id" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                        <option value="">Seleccionar…</option>
                        @foreach ($tiposEvento as $tipo)
                            <option value="{{ $tipo['id'] }}" @selected((string) old('tipo_evento_id', $evento['tipoEventoId'] ?? '') === (string) $tipo['id'])>
                                {{ $tipo['icono'] }} {{ $tipo['nombre'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Subtipo</label>
                    <select name="subtipo_evento_id" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                        <option value="">Seleccionar…</option>
                        @foreach ($tiposEvento as $tipo)
                            @foreach ($tipo['subtipos'] as $sub)
                                <option value="{{ $sub['id'] }}" @selected((string) old('subtipo_evento_id', $evento['subtipoEventoId'] ?? '') === (string) $sub['id'])>
                                    {{ $tipo['nombre'] }} — {{ $sub['nombre'] }}
                                </option>
                            @endforeach
                        @endforeach
                    </select>
                </div>
                <div>
                    {{-- CRUD de organizadores (11/08/2026) — solo super_admin
                         puede reasignarlo, y solo mientras el evento sea
                         borrador (una vez publicado, ApiRestEvent lo rechaza
                         con 422 — ver EventoController::update() ahí). Fuera
                         de ese caso se muestra como texto de solo lectura, así
                         un admin scoped o un evento ya publicado igual pueden
                         ver quién es el organizador sin poder tocarlo. --}}
                    <label class="block text-sm font-semibold mb-1">Organizador</label>
                    @if ((session('admin_user')['rol'] ?? null) === 'super_admin' && !$evento['publicado'])
                        <select name="organizador_id" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                            <option value="">Sin organizador asignado</option>
                            @foreach ($organizadores as $org)
                                <option value="{{ $org['id'] }}" @selected((string) old('organizador_id', $evento['organizadorId'] ?? '') === (string) $org['id'])>
                                    {{ $org['nombre'] }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        <p class="text-sm text-slate-700 border border-slate-200 rounded-md px-3 py-2 bg-slate-50">
                            {{ $evento['organizador']['nombre'] ?? 'Sin organizador asignado' }}
                            @if ($evento['publicado'])
                                <span class="text-xs text-slate-400">(no editable — evento publicado)</span>
                            @endif
                        </p>
                    @endif
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Video (URL)</label>
                    <input type="text" name="video" value="{{ old('video', $evento['video']) }}"
                           class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Imagen de portada (URL)</label>
                    <input type="text" name="image" value="{{ old('image', $evento['image']) }}"
                           class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">
                        Color de marca <span class="font-normal text-slate-500">(certificados, no gafetes)</span>
                    </label>
                    <input type="color" name="colorHex" value="{{ old('colorHex', $evento['colorHex'] ?: '#022858') }}"
                           class="w-full h-9 border border-slate-300 rounded-md">
                </div>
                @if ((session('admin_user')['rol'] ?? null) === 'super_admin')
                    {{-- Cargo de servicio (11/08/2026) — solo super_admin: es
                         cuánto se queda la plataforma, no un dato interno del
                         organizador. Antes 5% fijo hardcodeado en
                         elascenso/event, ver PRD-cargo-servicio-por-evento.md.
                         Se guarda como fracción en la API (0.05), pero el
                         input muestra/recibe un porcentaje (5) porque es lo
                         que un humano espera escribir acá. --}}
                    <div>
                        <label class="block text-sm font-semibold mb-1">
                            Cargo de servicio (%) <span class="font-normal text-slate-500">(5% por default)</span>
                        </label>
                        <input type="number" name="feePctPorcentaje" step="0.01" min="0" max="20"
                               value="{{ old('feePctPorcentaje', number_format(($evento['fee_pct'] ?? 0.05) * 100, 2)) }}"
                               class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    </div>

                    {{-- Cargo de servicio sobre talleres (19/08/2026) — pedido:
                         "necesito una opción que no apliquemos el fee a los
                         talleres". Default true (mantiene el comportamiento
                         recién confirmado: fee sobre inscripción + talleres);
                         el super_admin lo puede apagar por evento. Souvenirs y
                         donación nunca entraron a la base del fee, esto no
                         cambia eso. --}}
                    <div class="col-span-2">
                        <label class="flex items-center gap-2 text-sm font-semibold">
                            <input type="checkbox" name="feeIncluyeTalleres" value="1"
                                   {{ old('feeIncluyeTalleres', $evento['feeIncluyeTalleres'] ?? true) ? 'checked' : '' }}>
                            Aplicar el cargo de servicio también a los talleres
                            <span class="font-normal text-slate-500">
                                (si se destilda, el cargo se calcula solo sobre la inscripción — igual que souvenirs/donación, que nunca lo incluyen)
                            </span>
                        </label>
                    </div>
                @endif

                {{-- Inscripción en BOB y USD (18/08/2026) — ver
                     brain/PLAN-INSCRIPCION-BOB-USD-IMPLEMENTACION.md. El
                     organizador habilita pago en USD (extranjeros). Default
                     off: eventos existentes siguen BOB-only sin cambio.
                     El frontend solo renderiza el selector USD si esto es
                     true; el backend (ApiRestEvent CrearInscripcionAction)
                     lo enforcea por las dudas. --}}
                <div class="col-span-2">
                    <label class="flex items-center gap-2 text-sm font-semibold">
                        <input type="checkbox" name="aceptaUsd" value="1"
                               {{ !empty($evento['aceptaUsd']) ? 'checked' : '' }}>
                        Acepta pago en USD (extranjeros)
                        <span class="font-normal text-slate-500">
                            (cuando está activo, el participante elige BOB o USD en el paso de pago;
                            USD solo funciona con QR / Multipago)
                        </span>
                    </label>
                </div>

                {{-- Precio USD fijo, sin tipo de cambio (19/08/2026) — ver
                     brain/PLAN-PRECIO-USD-FIJO-19082026.md. Modo alternativo al de
                     arriba (que convierte el precio BOB con la tasa del día):
                     acá el organizador carga un precio en USD fijo por categoría
                     (pestaña Categorías → campo "Precio USD"), sin tasa de por
                     medio. Solo tiene efecto si "Acepta pago en USD" también está
                     tildado. Alcance: solo categoría/inscripción — souvenirs,
                     talleres, donación y camiseta no tienen precio USD fijo, y la
                     inscripción se rechaza si el participante trae alguno. --}}
                <div class="col-span-2">
                    <label class="flex items-center gap-2 text-sm font-semibold">
                        <input type="checkbox" name="usdPrecioFijo" value="1"
                               {{ !empty($evento['usdPrecioFijo']) ? 'checked' : '' }}>
                        Precio USD fijo (sin tipo de cambio)
                        <span class="font-normal text-slate-500">
                            (usa el "Precio USD" cargado en cada categoría en vez de convertir el
                            precio en Bs con la tasa del día — requiere "Acepta pago en USD" arriba)
                        </span>
                    </label>
                </div>

                {{-- Congresos con talleres (19/08/2026) — sin esto, el flag solo se
                     podía prender escribiendo directo en la BD; sin él, cualquier
                     taller con precio cargado se cobra $0 igual
                     (ResolverPrecioTallerData, ApiRestEvent). --}}
                <div class="col-span-2">
                    <label class="flex items-center gap-2 text-sm font-semibold">
                        <input type="checkbox" name="talleresConCosto" value="1"
                               {{ !empty($evento['talleresConCosto']) ? 'checked' : '' }}>
                        Talleres con costo
                        <span class="font-normal text-slate-500">
                            (si está apagado, todos los talleres del evento son gratis aunque
                            tengan un precio cargado en la pestaña "Talleres")
                        </span>
                    </label>
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-semibold mb-1">Deslinde de responsabilidad</label>
                    <textarea name="deslinde" rows="2"
                              class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">{{ old('deslinde', $evento['deslinde']) }}</textarea>
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-semibold mb-1">Deslinde (PDF, URL)</label>
                    <input type="text" name="deslinde_pdf_url" value="{{ old('deslinde_pdf_url', $evento['deslinde_pdf_url']) }}"
                           class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                </div>
            </div>
            <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white rounded-md px-4 py-2 text-sm font-semibold">
                Guardar datos del evento
            </button>
        </form>
    </div>

    {{-- 2. Categorías --}}
    <div id="panel-categorias" role="tabpanel" aria-labelledby="tab-categorias" tabindex="0" class="p-6" hidden>
        @foreach ($evento['categories'] as $categoria)
            <div class="border border-slate-200 rounded-md p-3 mb-2">
                <form method="POST" action="{{ route('admin.categorias.update', $categoria['id']) }}" class="grid grid-cols-6 gap-2 items-end">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                    <div>
                        <label class="block text-xs font-semibold mb-1">Nombre</label>
                        <input type="text" name="name" value="{{ $categoria['name'] }}" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Precio</label>
                        <input type="number" step="0.01" min="0" name="price" value="{{ $categoria['price'] }}" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    {{-- Precio USD fijo (19/08/2026) — ver brain/PLAN-PRECIO-USD-FIJO-19082026.md.
                         Vacío = esta categoría no se puede vender en USD fijo, aunque el
                         evento tenga el modo prendido (ver checkbox en la pestaña Datos). --}}
                    <div>
                        <label class="block text-xs font-semibold mb-1">
                            Precio USD <span class="font-normal text-slate-500">(opc.)</span>
                        </label>
                        <input type="number" step="0.01" min="0" name="price_usd" value="{{ $categoria['priceUsd'] }}" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-semibold mb-1">Descripción</label>
                        <input type="text" name="description" value="{{ $categoria['description'] }}" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    <div class="flex items-end gap-2">
                        <input type="color" name="color" value="{{ $categoria['color'] ?: '#022858' }}" class="flex-1 h-8 border border-slate-300 rounded">
                        <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1 rounded">Guardar</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.categorias.destroy', $categoria['id']) }}" class="mt-1 inline"
                      onsubmit="return confirm('¿Eliminar esta categoría?')">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                    <button type="submit" class="text-xs text-red-600 hover:underline">Eliminar categoría</button>
                </form>
                {{-- Precios por período (12/08/2026) — ver
                     ApiRestEvent/brain/api_rest_event/PRD-precios-periodos-fechas.md.
                     precio_vigente/periodo_vigente_nombre vienen de
                     CategoryResource; ausentes en una respuesta cacheada vieja,
                     se cae al price crudo sin período. --}}
                <p class="text-xs text-slate-500 mt-1">
                    Precio vigente hoy: <strong>Bs {{ number_format($categoria['precio_vigente'] ?? $categoria['price'], 2) }}</strong>
                    @if (!empty($categoria['periodo_vigente_nombre']))
                        <span class="text-red-600 font-semibold">({{ $categoria['periodo_vigente_nombre'] }})</span>
                    @endif
                    ·
                    <a href="{{ route('admin.categorias.periodos.index', $categoria['id']) }}?evento_id={{ $evento['id'] }}" class="text-brand-600 hover:underline">Períodos de precio →</a>
                </p>
            </div>
        @endforeach

        <div class="border border-dashed border-slate-300 rounded-md p-3 mt-3">
            <p class="text-xs font-semibold text-slate-500 mb-2">+ Agregar categoría</p>
            <form method="POST" action="{{ route('admin.categorias.store', $evento['id']) }}" class="grid grid-cols-6 gap-2 items-end">
                @csrf
                <div>
                    <input type="text" name="name" placeholder="Nombre" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                </div>
                <div>
                    <input type="number" step="0.01" min="0" name="price" placeholder="Precio" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                </div>
                <div>
                    <input type="number" step="0.01" min="0" name="price_usd" placeholder="Precio USD (opc.)" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                </div>
                <div class="col-span-2">
                    <input type="text" name="description" placeholder="Descripción" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                </div>
                <div class="flex items-end gap-2">
                    <input type="color" name="color" value="#022858" class="flex-1 h-8 border border-slate-300 rounded">
                    <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1 rounded">Agregar</button>
                </div>
            </form>
        </div>
    </div>

    {{-- 3. Tipos de formulario --}}
    <div id="panel-tipos" role="tabpanel" aria-labelledby="tab-tipos" tabindex="0" class="p-6" hidden>
        @foreach ($evento['formTypes'] as $formType)
            <div class="border border-slate-200 rounded-md p-3 mb-3">
                <form method="POST" action="{{ route('admin.formtypes.update', $formType['id']) }}" class="space-y-2">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                    <div class="grid grid-cols-4 gap-2">
                        <div>
                            <label class="block text-xs font-semibold mb-1">Nombre</label>
                            <input type="text" name="name" value="{{ $formType['name'] }}" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1">Ícono</label>
                            <input type="text" name="icon" value="{{ $formType['icon'] }}" maxlength="10" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1">
                                Imagen (URL) <span class="font-normal text-slate-500">(reemplaza al ícono)</span>
                            </label>
                            <input type="text" name="imagen_url" value="{{ $formType['imagenUrl'] ?? '' }}" placeholder="https://…" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-semibold mb-1">Descripción</label>
                            <input type="text" name="description" value="{{ $formType['description'] }}" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1">Tipo</label>
                            <select name="tipo" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                                @foreach (['deportivo','congreso','taller','corporativo','cultural','social','educativo','recreativo','religioso','gastronomico','musical','tecnologico','artes','literario','ambiental','salud','moda','teatro','cine','fotografia','danza','literatura'] as $tipo)
                                    <option value="{{ $tipo }}" @selected(($formType['tipo'] ?? 'deportivo') === $tipo)>{{ $tipo }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1">Cupo total</label>
                            <input type="number" min="0" name="cupo_total" value="{{ $formType['cupo_total'] }}" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1">Precio base</label>
                            <input type="number" step="0.01" min="0" name="precio_base" value="{{ $formType['precio_base'] }}" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1">Costo edición</label>
                            <input type="number" step="0.01" min="0" name="costo_edicion" value="{{ $formType['costo_edicion'] }}" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1">Expira (min)</label>
                            <input type="number" min="0" name="tiempo_expiracion_min" value="30" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1">Color <span class="font-normal text-slate-500">(gafetes)</span></label>
                            <input type="color" name="color" value="{{ $formType['color'] ?: '#00bad2' }}" class="w-full h-8 border border-slate-300 rounded">
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-4">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="requiere_categoria" value="1" {{ ($formType['requiereCategoria'] ?? true) ? 'checked' : '' }}>
                            Requiere categoría
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="has_team" value="1" {{ ($formType['hasTeam'] ?? false) ? 'checked' : '' }}>
                            Con equipo
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="has_delivery" value="1" {{ ($formType['hasDelivery'] ?? false) ? 'checked' : '' }}>
                            Con delivery de kit
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="has_donation" value="1" {{ ($formType['hasDonation'] ?? false) ? 'checked' : '' }}>
                            Permite donación
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="has_promo_code" value="1" {{ ($formType['hasPromoCode'] ?? false) ? 'checked' : '' }}>
                            Admite código promocional
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="es_staff" value="1" {{ ($formType['esStaff'] ?? false) ? 'checked' : '' }}>
                            Es Staff/Ayudante <span class="text-slate-400">(asignable a sesiones de congreso)</span>
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="es_ponente" value="1" {{ ($formType['esPonente'] ?? false) ? 'checked' : '' }}>
                            Es Ponente/Expositor <span class="text-slate-400">(vinculable a sesiones de congreso)</span>
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="requiere_contacto_emergencia" value="1" {{ ($formType['requiereContactoEmergencia'] ?? true) ? 'checked' : '' }}>
                            Pide contacto de emergencia <span class="text-slate-400">(desmarcar en congresos/talleres donde no aplica — oculta esos 3 campos en el formulario público y en Caja)</span>
                        </label>
                    </div>
                    <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-3 py-1.5 rounded">Guardar</button>
                </form>
                <form method="POST" action="{{ route('admin.formtypes.destroy', $formType['id']) }}" class="mt-2"
                      onsubmit="return confirm('¿Eliminar este tipo de formulario?')">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                    <button type="submit" class="text-xs text-red-600 hover:underline">Eliminar tipo de formulario</button>
                </form>

                {{-- Ítems del kit de este form_type — el modelo sigue siendo
                     Souvenir (no se renombra, ver decisión de terminología en
                     PRD-kit-tallas-stock-lista-espera.md), pero acá se le
                     dice "ítem" al organizador. --}}
                <div class="border-t border-slate-100 mt-3 pt-2">
                    <span class="text-xs font-semibold text-slate-500">Ítems del kit</span>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Marcá "Incluido" en un ítem (ej. la polera del kit) para que
                        ya venga en el precio base — no se le cobra aparte ni se le
                        pide confirmarlo, solo elige talla/sexo si aplica.
                    </p>
                    @foreach ($formType['souvenirs'] as $souvenir)
                        <div class="border border-slate-200 rounded p-2 mt-1">
                            <div class="grid grid-cols-5 gap-2 items-end">
                                <form method="POST" action="{{ route('admin.souvenirs.update', $souvenir['id']) }}" class="col-span-4 grid grid-cols-4 gap-2 items-end" id="souvenir-form-{{ $souvenir['id'] }}">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                                    <input type="text" name="name" value="{{ $souvenir['name'] }}" placeholder="Nombre" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                                    <input type="text" name="icon" value="{{ $souvenir['icon'] }}" placeholder="Ícono" maxlength="10" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                                    <input type="number" step="0.01" min="0" name="price" value="{{ $souvenir['price'] }}" placeholder="Precio" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                                    <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1 rounded">Guardar</button>
                                </form>
                                <form method="POST" action="{{ route('admin.souvenirs.destroy', $souvenir['id']) }}"
                                      onsubmit="return confirm('¿Eliminar este ítem?')">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                                    <button type="submit" class="text-xs text-red-600 hover:underline">Eliminar</button>
                                </form>
                            </div>
                            {{-- Foto + flags — mismos <input>/<checkbox> pero mandados con el
                                 form de arriba (name/icon/price) via el atributo form="..." --}}
                            <div class="grid grid-cols-5 gap-2 items-center mt-2">
                                <input type="url" name="foto_url" value="{{ $souvenir['foto_url'] ?? '' }}" placeholder="URL de foto (opcional)" form="souvenir-form-{{ $souvenir['id'] }}" class="col-span-2 w-full border border-slate-300 rounded px-2 py-1 text-xs">
                                <label class="text-xs flex items-center gap-1">
                                    <input type="checkbox" name="incluido" form="souvenir-form-{{ $souvenir['id'] }}" value="1" @checked($souvenir['incluido'] ?? false)>
                                    Incluido en el kit
                                </label>
                                <label class="text-xs flex items-center gap-1">
                                    <input type="checkbox" name="requiere_talla" form="souvenir-form-{{ $souvenir['id'] }}" value="1" @checked($souvenir['requiere_talla'] ?? false)>
                                    Talla
                                </label>
                                <label class="text-xs flex items-center gap-1">
                                    <input type="checkbox" name="requiere_sexo" form="souvenir-form-{{ $souvenir['id'] }}" value="1" @checked($souvenir['requiere_sexo'] ?? false)>
                                    Sexo
                                </label>
                            </div>
                            {{-- Gestionar stock aplica a CUALQUIER ítem, tenga o no talla/sexo
                                 (ej. una medalla): sin filas ahí, el ítem queda con
                                 "disponibilidad no controlada" — ver stock.blade.php. Antes este
                                 link solo aparecía si requiere_talla estaba tildado, dejando a
                                 los ítems simples sin forma de cargarles stock desde el panel
                                 (gap detectado 13/08/2026). --}}
                            @if (Route::has('admin.souvenirs.stock.index'))
                                <a href="{{ route('admin.souvenirs.stock.index', $souvenir['id']) }}?evento_id={{ $evento['id'] }}&nombre={{ urlencode($souvenir['name']) }}" class="text-xs text-brand-600 hover:underline">Gestionar stock →</a>
                            @endif
                        </div>
                    @endforeach

                    <form method="POST" action="{{ route('admin.souvenirs.store', $formType['id']) }}" class="grid grid-cols-5 gap-2 items-end mt-2">
                        @csrf
                        <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                        <input type="text" name="name" placeholder="Nombre" class="col-span-2 w-full border border-slate-300 rounded px-2 py-1 text-sm">
                        <input type="text" name="icon" placeholder="Ícono" maxlength="10" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                        <input type="number" step="0.01" min="0" name="price" placeholder="Precio" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                        <button type="submit" class="text-xs text-brand-600 hover:underline">+ Agregar ítem</button>
                    </form>
                </div>

                {{-- Preguntas adicionales del formulario de inscripción
                     (20/08/2026) — ya se renderizan y se guardan en
                     elascenso/event desde antes; esto es lo que faltaba,
                     crear/editarlas. 'Archivo' no está en el selector de
                     tipo porque el formulario público lo omite en
                     silencio (no hay endpoint de subida todavía). --}}
                @php($tiposInput = ['text' => 'Texto corto', 'textarea' => 'Texto largo', 'email' => 'Email', 'tel' => 'Teléfono', 'date' => 'Fecha', 'number' => 'Número', 'select' => 'Lista desplegable', 'radio' => 'Opción única', 'checkbox' => 'Casillas'])
                @php($secciones = ['personal' => 'Datos personales', 'kit' => 'Kit', 'encuesta' => 'Encuesta', 'legal' => 'Legal', 'otro' => 'Otro'])
                <div class="border-t border-slate-100 mt-3 pt-2">
                    <span class="text-xs font-semibold text-slate-500">Preguntas adicionales</span>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Se muestran agrupadas por sección, en el orden cargado, en el
                        formulario de inscripción. "Lista desplegable"/"Opción única"/
                        "Casillas" necesitan opciones (una por línea). Eliminar una
                        pregunta borra también las respuestas ya guardadas de quienes
                        ya la contestaron — no se puede deshacer.
                    </p>
                    @foreach ($formType['preguntas'] ?? [] as $pregunta)
                        <div class="border border-slate-200 rounded p-2 mt-1">
                            <form method="POST" action="{{ route('admin.preguntas.update', $pregunta['id']) }}" class="space-y-2" id="pregunta-form-{{ $pregunta['id'] }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                                <div class="grid grid-cols-4 gap-2">
                                    <select name="seccion" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                                        @foreach ($secciones as $val => $label)
                                            <option value="{{ $val }}" @selected($pregunta['seccion'] === $val)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="nombre_campo" value="{{ $pregunta['nombre_campo'] }}" placeholder="Nombre interno (ej. alergias)" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                                    <input type="text" name="etiqueta" value="{{ $pregunta['etiqueta'] }}" placeholder="Pregunta que ve el participante" class="col-span-2 w-full border border-slate-300 rounded px-2 py-1 text-sm">
                                </div>
                                <div class="grid grid-cols-4 gap-2">
                                    <select name="tipo_input" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                                        @foreach ($tiposInput as $val => $label)
                                            <option value="{{ $val }}" @selected($pregunta['tipo_input'] === $val)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="placeholder" value="{{ $pregunta['placeholder'] }}" placeholder="Texto de ayuda (opcional)" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                                    <input type="number" min="0" name="orden" value="{{ $pregunta['orden'] }}" placeholder="Orden" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                                    <div class="flex items-center gap-3 text-xs">
                                        <label class="flex items-center gap-1"><input type="checkbox" name="obligatorio" value="1" @checked($pregunta['obligatorio'])> Obligatoria</label>
                                        <label class="flex items-center gap-1"><input type="checkbox" name="visible_en_reporte" value="1" @checked($pregunta['visible_en_reporte'])> En reporte</label>
                                    </div>
                                </div>
                                <textarea name="opciones_texto" rows="2" placeholder="Una opción por línea (solo si el tipo es Lista/Opción única/Casillas)" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">{{ collect($pregunta['options'] ?? [])->sortBy('order')->pluck('option_text')->implode("\n") }}</textarea>
                                <div class="flex items-center gap-3">
                                    <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1 rounded">Guardar</button>
                                </div>
                            </form>
                            <form method="POST" action="{{ route('admin.preguntas.destroy', $pregunta['id']) }}" class="mt-1"
                                  onsubmit="return confirm('¿Eliminar esta pregunta? Si ya hay participantes inscritos que la contestaron, sus respuestas se borran también y no se pueden recuperar.')">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                                <button type="submit" class="text-xs text-red-600 hover:underline">Eliminar</button>
                            </form>
                        </div>
                    @endforeach

                    <form method="POST" action="{{ route('admin.preguntas.store', $formType['id']) }}" class="border border-dashed border-slate-300 rounded p-2 mt-2 space-y-2">
                        @csrf
                        <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                        <div class="grid grid-cols-4 gap-2">
                            <select name="seccion" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                                @foreach ($secciones as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="nombre_campo" placeholder="Nombre interno (ej. alergias)" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                            <input type="text" name="etiqueta" placeholder="Pregunta que ve el participante" class="col-span-2 w-full border border-slate-300 rounded px-2 py-1 text-sm">
                        </div>
                        <div class="grid grid-cols-4 gap-2">
                            <select name="tipo_input" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                                @foreach ($tiposInput as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="placeholder" placeholder="Texto de ayuda (opcional)" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                            <input type="number" min="0" name="orden" placeholder="Orden" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                            <div class="flex items-center gap-3 text-xs">
                                <label class="flex items-center gap-1"><input type="checkbox" name="obligatorio" value="1" checked> Obligatoria</label>
                                <label class="flex items-center gap-1"><input type="checkbox" name="visible_en_reporte" value="1" checked> En reporte</label>
                            </div>
                        </div>
                        <textarea name="opciones_texto" rows="2" placeholder="Una opción por línea (solo si el tipo es Lista/Opción única/Casillas)" class="w-full border border-slate-300 rounded px-2 py-1 text-sm"></textarea>
                        <button type="submit" class="text-xs text-brand-600 hover:underline">+ Agregar pregunta</button>
                    </form>
                </div>
            </div>
        @endforeach

        <div class="border border-dashed border-slate-300 rounded-md p-3 mt-3">
            <p class="text-xs font-semibold text-slate-500 mb-2">+ Agregar tipo de formulario</p>
            <form method="POST" action="{{ route('admin.formtypes.store', $evento['id']) }}" class="space-y-2">
                @csrf
                <div class="grid grid-cols-4 gap-2">
                    <input type="text" name="name" placeholder="Nombre" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    <input type="text" name="icon" placeholder="Ícono" value="🏃" maxlength="10" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    <input type="text" name="description" placeholder="Descripción" class="col-span-2 w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    <select name="tipo" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                        @foreach (['deportivo','congreso','taller','corporativo','cultural','social','educativo','recreativo','religioso','gastronomico','musical','tecnologico','artes','literario','ambiental','salud','moda','teatro','cine','fotografia','danza','literatura'] as $tipo)
                            <option value="{{ $tipo }}" @selected($tipo === 'deportivo')>{{ $tipo }}</option>
                        @endforeach
                    </select>
                    <input type="number" min="0" name="cupo_total" placeholder="Cupo total" value="100" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    <input type="number" step="0.01" min="0" name="precio_base" placeholder="Precio base" value="0" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    <input type="number" step="0.01" min="0" name="costo_edicion" placeholder="Costo edición" value="0" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    <input type="number" min="0" name="tiempo_expiracion_min" placeholder="Expira (min)" value="30" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    <input type="color" name="color" value="#00bad2" class="w-full h-8 border border-slate-300 rounded">
                </div>
                <div class="flex flex-wrap gap-4">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="requiere_categoria" value="1" checked>
                        Requiere categoría
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="has_team" value="1">
                        Con equipo
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="has_delivery" value="1">
                        Con delivery de kit
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="has_donation" value="1">
                        Permite donación
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="has_promo_code" value="1">
                        Admite código promocional
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="es_staff" value="1">
                        Es Staff/Ayudante <span class="text-slate-400">(asignable a sesiones de congreso)</span>
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="es_ponente" value="1">
                        Es Ponente/Expositor <span class="text-slate-400">(vinculable a sesiones de congreso)</span>
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="requiere_contacto_emergencia" value="1" checked>
                        Pide contacto de emergencia <span class="text-slate-400">(desmarcar en congresos/talleres donde no aplica)</span>
                    </label>
                </div>
                <button type="submit" class="text-sm bg-brand-600 hover:bg-brand-700 text-white px-3 py-1.5 rounded-md">Agregar tipo de formulario</button>
            </form>
        </div>
    </div>

    {{-- 4. Mapa (Coordenadas + Ruta) --}}
    <div id="panel-mapa" role="tabpanel" aria-labelledby="tab-mapa" tabindex="0" class="p-6" hidden>
        <h3 class="font-semibold mb-3">Coordenadas <span class="font-normal text-slate-500 text-sm">(punto en el mapa)</span></h3>

        @foreach ($evento['coordinates'] as $coordinate)
            <div class="border border-slate-200 rounded-md p-3 mb-2">
                <form method="POST" action="{{ route('admin.coordenadas.update', $coordinate['id']) }}" class="grid grid-cols-5 gap-2 items-end">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                    <div>
                        <label class="block text-xs font-semibold mb-1">Lat</label>
                        <input type="number" step="any" name="lat" value="{{ $coordinate['lat'] }}" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Lng</label>
                        <input type="number" step="any" name="lng" value="{{ $coordinate['lng'] }}" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    <div>
                        <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1 rounded">Guardar</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.coordenadas.destroy', $coordinate['id']) }}" class="mt-1"
                      onsubmit="return confirm('¿Eliminar esta coordenada?')">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                    <button type="submit" class="text-xs text-red-600 hover:underline">Eliminar coordenada</button>
                </form>
            </div>
        @endforeach

        <div class="border border-dashed border-slate-300 rounded-md p-3 mt-3">
            <p class="text-xs font-semibold text-slate-500 mb-2">+ Agregar coordenada</p>
            <form method="POST" action="{{ route('admin.coordenadas.store', $evento['id']) }}" class="grid grid-cols-5 gap-2 items-end">
                @csrf
                <input type="number" step="any" name="lat" placeholder="Lat" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <input type="number" step="any" name="lng" placeholder="Lng" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1 rounded">Agregar</button>
            </form>
        </div>

        <hr class="my-6 border-slate-200">

        <h3 class="font-semibold mb-3">Ruta <span class="font-normal text-slate-500 text-sm">(puntos del recorrido, en orden)</span></h3>

        @foreach ($evento['route'] as $point)
            <div class="border border-slate-200 rounded-md p-3 mb-2">
                <form method="POST" action="{{ route('admin.ruta.update', $point['id']) }}" class="grid grid-cols-5 gap-2 items-end">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                    <div>
                        <label class="block text-xs font-semibold mb-1">Lat</label>
                        <input type="number" step="any" name="lat" value="{{ $point['lat'] }}" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Lng</label>
                        <input type="number" step="any" name="lng" value="{{ $point['lng'] }}" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-semibold mb-1">Etiqueta</label>
                        <input type="text" name="label" value="{{ $point['label'] }}" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    <div>
                        <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1 rounded">Guardar</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.ruta.destroy', $point['id']) }}" class="mt-1"
                      onsubmit="return confirm('¿Eliminar este punto de ruta?')">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                    <button type="submit" class="text-xs text-red-600 hover:underline">Eliminar punto</button>
                </form>
            </div>
        @endforeach

        <div class="border border-dashed border-slate-300 rounded-md p-3 mt-3">
            <p class="text-xs font-semibold text-slate-500 mb-2">+ Agregar punto de ruta</p>
            <form method="POST" action="{{ route('admin.ruta.store', $evento['id']) }}" class="grid grid-cols-5 gap-2 items-end">
                @csrf
                <input type="number" step="any" name="lat" placeholder="Lat" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <input type="number" step="any" name="lng" placeholder="Lng" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <input type="text" name="label" placeholder="Etiqueta" required class="col-span-2 w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1 rounded">Agregar</button>
            </form>
        </div>
    </div>

    {{-- 5. Códigos promocionales --}}
    <div id="panel-promos" role="tabpanel" aria-labelledby="tab-promos" tabindex="0" class="p-6" hidden>
        @foreach ($evento['promoCodes'] as $promoCode)
            <div class="border border-slate-200 rounded-md p-3 mb-2">
                <form method="POST" action="{{ route('admin.promocodes.update', $promoCode['id']) }}" class="grid grid-cols-6 gap-2 items-end">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                    <div>
                        <label class="block text-xs font-semibold mb-1">Código</label>
                        <input type="text" name="promo_code" value="{{ $promoCode['promo_code'] }}" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Precio fijo</label>
                        <input type="number" step="0.01" min="0" name="price" value="{{ $promoCode['price'] }}" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Tipo</label>
                        <select name="discount_type" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                            <option value="fixed_price" @selected($promoCode['discount_type'] === 'fixed_price')>fixed_price</option>
                            <option value="percentage" @selected($promoCode['discount_type'] === 'percentage')>percentage</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">% descuento</label>
                        <input type="number" step="0.01" min="0" max="1" name="discount_percent" value="{{ $promoCode['discount_percent'] }}" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    <div class="text-xs text-slate-500">
                        {{ $promoCode['usado'] ? 'Usado' : 'Sin usar' }}
                    </div>
                    <div>
                        <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1 rounded">Guardar</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.promocodes.destroy', $promoCode['id']) }}" class="mt-1"
                      onsubmit="return confirm('¿Eliminar este código de promoción?')">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                    <button type="submit" class="text-xs text-red-600 hover:underline">Eliminar código</button>
                </form>
            </div>
        @endforeach

        <div class="border border-dashed border-slate-300 rounded-md p-3 mt-3">
            <p class="text-xs font-semibold text-slate-500 mb-2">+ Agregar código promocional</p>
            <form method="POST" action="{{ route('admin.promocodes.store', $evento['id']) }}" class="grid grid-cols-6 gap-2 items-end">
                @csrf
                <input type="text" name="promo_code" placeholder="Código" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <input type="number" step="0.01" min="0" name="price" placeholder="Precio fijo" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <select name="discount_type" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    <option value="fixed_price">fixed_price</option>
                    <option value="percentage">percentage</option>
                </select>
                <input type="number" step="0.01" min="0" max="1" name="discount_percent" placeholder="% descuento" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <div></div>
                <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1 rounded">Agregar</button>
            </form>
        </div>
    </div>

    {{-- 6. Auspiciadores --}}
    <div id="panel-auspiciadores" role="tabpanel" aria-labelledby="tab-auspiciadores" tabindex="0" class="p-6" hidden>
        @foreach ($evento['auspiciadores'] as $auspiciador)
            <div class="border border-slate-200 rounded-md p-3 mb-2">
                <form method="POST" action="{{ route('admin.auspiciadores.update', $auspiciador['id']) }}" class="grid grid-cols-5 gap-2 items-end">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                    <div>
                        <label class="block text-xs font-semibold mb-1">Nombre</label>
                        <input type="text" name="nombre" value="{{ $auspiciador['nombre'] }}" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-semibold mb-1">Logo (URL)</label>
                        <input type="text" name="logo_url" value="{{ $auspiciador['logo_url'] }}" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Contacto</label>
                        <input type="text" name="contacto" value="{{ $auspiciador['contacto'] }}" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    <div class="flex items-end gap-2">
                        <input type="number" name="orden" value="{{ $auspiciador['orden'] }}" placeholder="Orden" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                        <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1 rounded">Guardar</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.auspiciadores.destroy', $auspiciador['id']) }}" class="mt-1"
                      onsubmit="return confirm('¿Eliminar este auspiciador?')">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                    <button type="submit" class="text-xs text-red-600 hover:underline">Eliminar auspiciador</button>
                </form>
            </div>
        @endforeach

        <div class="border border-dashed border-slate-300 rounded-md p-3 mt-3">
            <p class="text-xs font-semibold text-slate-500 mb-2">+ Agregar auspiciador</p>
            <form method="POST" action="{{ route('admin.auspiciadores.store', $evento['id']) }}" class="grid grid-cols-5 gap-2 items-end">
                @csrf
                <input type="text" name="nombre" placeholder="Nombre" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <input type="text" name="logo_url" placeholder="Logo (URL)" required class="col-span-2 w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <input type="text" name="contacto" placeholder="Contacto" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <div class="flex items-end gap-2">
                    <input type="number" name="orden" placeholder="Orden" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1 rounded">Agregar</button>
                </div>
            </form>
        </div>
    </div>

    {{-- 7. Agenda --}}
    <div id="panel-agenda" role="tabpanel" aria-labelledby="tab-agenda" tabindex="0" class="p-6" hidden>
        @foreach ($evento['agenda'] as $item)
            <div class="border border-slate-200 rounded-md p-3 mb-2">
                <form method="POST" action="{{ route('admin.agenda.update', $item['id']) }}" class="grid grid-cols-4 gap-2 items-end">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                    <div>
                        <label class="block text-xs font-semibold mb-1">Fecha</label>
                        <input type="date" name="fecha" value="{{ $item['date'] }}" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Hora inicio</label>
                        <input type="time" name="hora_inicio" value="{{ $item['startTime'] }}" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Hora fin</label>
                        <input type="time" name="hora_fin" value="{{ $item['endTime'] }}" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Tipo de formulario</label>
                        <select name="form_type_id" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                            <option value="">General (todo el evento)</option>
                            @foreach ($evento['formTypes'] as $formType)
                                <option value="{{ $formType['id'] }}" @selected($item['formTypeId'] == $formType['id'])>{{ $formType['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-semibold mb-1">Título</label>
                        <input type="text" name="titulo" value="{{ $item['title'] }}" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-semibold mb-1">Descripción</label>
                        <input type="text" name="descripcion" value="{{ $item['description'] }}" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Ponente</label>
                        <input type="text" name="ponente" value="{{ $item['speaker'] }}" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Cargo</label>
                        <input type="text" name="ponente_cargo" value="{{ $item['speakerRole'] }}" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Sala</label>
                        <input type="text" name="sala" value="{{ $item['room'] }}" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Ícono</label>
                        <input type="text" name="icono" value="{{ $item['icon'] }}" maxlength="10" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    </div>
                    <div>
                        <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1 rounded">Guardar</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.agenda.destroy', $item['id']) }}" class="mt-1"
                      onsubmit="return confirm('¿Eliminar este ítem de agenda?')">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="evento_id" value="{{ $evento['id'] }}">
                    <button type="submit" class="text-xs text-red-600 hover:underline">Eliminar ítem</button>
                </form>
            </div>
        @endforeach

        <div class="border border-dashed border-slate-300 rounded-md p-3 mt-3">
            <p class="text-xs font-semibold text-slate-500 mb-2">+ Agregar ítem de agenda</p>
            <form method="POST" action="{{ route('admin.agenda.store', $evento['id']) }}" class="grid grid-cols-4 gap-2 items-end">
                @csrf
                <input type="date" name="fecha" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <input type="time" name="hora_inicio" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <input type="time" name="hora_fin" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <select name="form_type_id" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    <option value="">General (todo el evento)</option>
                    @foreach ($evento['formTypes'] as $formType)
                        <option value="{{ $formType['id'] }}">{{ $formType['name'] }}</option>
                    @endforeach
                </select>
                <input type="text" name="titulo" placeholder="Título" required class="col-span-2 w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <input type="text" name="descripcion" placeholder="Descripción" class="col-span-2 w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <input type="text" name="ponente" placeholder="Ponente" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <input type="text" name="ponente_cargo" placeholder="Cargo" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <input type="text" name="sala" placeholder="Sala" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <input type="text" name="icono" placeholder="Ícono" maxlength="10" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <button type="submit" class="text-xs bg-brand-600 hover:bg-brand-700 text-white px-2 py-1 rounded">Agregar</button>
            </form>
        </div>
    </div>

    {{-- 8. Zona de peligro --}}
    <div id="panel-peligro" role="tabpanel" aria-labelledby="tab-peligro" tabindex="0" class="p-6" hidden>
        <div class="border border-red-200 rounded-md p-4">
            <h3 class="font-bold mb-2 text-red-700">Zona de peligro</h3>
            <p class="text-sm text-slate-600 mb-3">
                Solo se puede eliminar un evento publicado si todavía no tiene participantes.
            </p>
            <form method="POST" action="{{ route('admin.eventos.destroy', $evento['id']) }}"
                  onsubmit="return confirm('¿Eliminar este evento? Esta acción no se puede deshacer.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-3 py-2 rounded-md">
                    Eliminar evento
                </button>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    // Menú "Herramientas" (<details>): cerrar al clickear afuera o con
    // Escape — <details> nativo no lo hace solo.
    document.addEventListener('click', function (e) {
        document.querySelectorAll('.group[open]').forEach(function (d) {
            if (!d.contains(e.target)) d.open = false;
        });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.group[open]').forEach(function (d) { d.open = false; });
        }
    });

    // Tabs — patrón ARIA APG (roving tabindex, flechas ←/→/Home/End).
    var tabs = Array.prototype.slice.call(document.querySelectorAll('[role="tab"]'));
    if (!tabs.length) return;

    function panelFor(tab) {
        return document.getElementById(tab.getAttribute('aria-controls'));
    }

    function activate(tab, opts) {
        opts = opts || {};
        tabs.forEach(function (t) {
            var selected = t === tab;
            t.setAttribute('aria-selected', selected ? 'true' : 'false');
            t.tabIndex = selected ? 0 : -1;
            panelFor(t).hidden = !selected;
        });
        if (opts.focus !== false) tab.focus();
        if (history.replaceState) history.replaceState(null, '', '#' + tab.dataset.tabId);
    }

    tabs.forEach(function (tab, i) {
        tab.addEventListener('click', function () { activate(tab); });
        tab.addEventListener('keydown', function (e) {
            var target = null;
            if (e.key === 'ArrowRight') target = tabs[(i + 1) % tabs.length];
            else if (e.key === 'ArrowLeft') target = tabs[(i - 1 + tabs.length) % tabs.length];
            else if (e.key === 'Home') target = tabs[0];
            else if (e.key === 'End') target = tabs[tabs.length - 1];
            if (target) { e.preventDefault(); activate(target); }
        });
    });

    // Restaurar la pestaña activa desde el hash de la URL — los controllers
    // que guardan algo dentro de una pestaña (categorías, tipos de
    // formulario, etc.) redirigen de vuelta con #categorias/#tipos/... para
    // no perder dónde estaba el usuario. También cubre "Volver al evento"
    // desde las pantallas de Períodos de precio / Stock.
    var fromHash = tabs.filter(function (t) { return '#' + t.dataset.tabId === location.hash; })[0];
    activate(fromHash || tabs[0], { focus: false });
})();
</script>
@endsection
