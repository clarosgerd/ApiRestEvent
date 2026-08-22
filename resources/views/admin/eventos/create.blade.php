@extends('admin.layouts.app')

@section('title', 'Nuevo evento — Admin Eventos')

@section('content')
<h1 class="text-lg font-bold mb-1">Nuevo evento</h1>
<p class="text-sm text-slate-600 mb-4">
    El evento nace como <strong>borrador</strong> — no es visible para participantes
    hasta que lo publiques desde el dashboard.
</p>

<form method="POST" action="{{ route('admin.eventos.store') }}" class="space-y-6">
    @csrf

    {{-- 1. Datos del evento --}}
    <section class="bg-white rounded-lg shadow p-6">
        <h2 class="font-bold mb-4">Datos del evento</h2>
        <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
                <label class="block text-sm font-semibold mb-1">Nombre</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
            </div>
            <div class="col-span-2">
                <label class="block text-sm font-semibold mb-1">
                    Link directo (slug)
                    <span class="font-normal text-slate-500">(opcional — si lo dejás vacío se genera solo desde el nombre)</span>
                </label>
                <input type="text" name="url_slug" value="{{ old('url_slug') }}"
                       placeholder="ej. maraton-santa-cruz-2026" pattern="[a-z0-9]+(-[a-z0-9]+)*"
                       title="Solo minúsculas, números y guiones (sin espacios ni acentos)"
                       class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                <p class="text-xs text-slate-500 mt-1">
                    Se usa para el link que compartís del evento (?evento=slug) en vez del id numérico.
                </p>
            </div>
            <div class="col-span-2">
                <label class="block text-sm font-semibold mb-1">Descripción corta</label>
                <input type="text" name="description" value="{{ old('description') }}" required maxlength="500"
                       class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
            </div>
            <div class="col-span-2">
                <label class="block text-sm font-semibold mb-1">Descripción larga</label>
                <textarea name="longDescription" rows="8" maxlength="10000"
                          class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">{{ old('longDescription') }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Fecha</label>
                <input type="date" name="date" value="{{ old('date') }}" required
                       class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Hora local</label>
                <input type="time" name="localTime" value="{{ old('localTime') }}"
                       class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
            </div>
            <div class="col-span-2">
                <label class="block text-sm font-semibold mb-1">Ubicación</label>
                <input type="text" name="location" value="{{ old('location') }}" required
                       class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Estado</label>
                <select name="status" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    <option value="open" @selected(old('status') === 'open')>open</option>
                    <option value="closed" @selected(old('status') === 'closed')>closed</option>
                    <option value="coming_soon" @selected(old('status', 'coming_soon') === 'coming_soon')>coming_soon</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">
                    Tipo de evento <span class="font-normal text-slate-500">(disciplina, o "Congreso / No aplica")</span>
                </label>
                <select name="tipo_evento_id" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    <option value="">Seleccionar…</option>
                    @foreach ($tiposEvento as $tipo)
                        <option value="{{ $tipo['id'] }}" @selected((string) old('tipo_evento_id') === (string) $tipo['id'])>
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
                            <option value="{{ $sub['id'] }}" @selected((string) old('subtipo_evento_id') === (string) $sub['id'])>
                                {{ $tipo['nombre'] }} — {{ $sub['nombre'] }}
                            </option>
                        @endforeach
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">
                    Organizador <span class="font-normal text-slate-500">(no editable una vez publicado)</span>
                </label>
                <select name="organizador_id" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    <option value="">Sin organizador asignado</option>
                    @foreach ($organizadores as $org)
                        <option value="{{ $org['id'] }}" @selected((string) old('organizador_id') === (string) $org['id'])>
                            {{ $org['nombre'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Video (URL)</label>
                <input type="text" name="video" value="{{ old('video') }}"
                       class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Imagen de portada (URL)</label>
                <input type="text" name="image" value="{{ old('image') }}"
                       class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">
                    Color de marca
                    <span class="font-normal text-slate-500">(certificados, no gafetes)</span>
                </label>
                <input type="color" name="colorHex" value="{{ old('colorHex', '#022858') }}"
                       class="w-full h-9 border border-slate-300 rounded-md">
            </div>
            <div class="col-span-2">
                <label class="block text-sm font-semibold mb-1">Deslinde de responsabilidad</label>
                <textarea name="deslinde" rows="2"
                          class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">{{ old('deslinde') }}</textarea>
            </div>
            <div class="col-span-2">
                <label class="block text-sm font-semibold mb-1">Deslinde (PDF, URL)</label>
                <input type="text" name="deslinde_pdf_url" value="{{ old('deslinde_pdf_url') }}"
                       class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
            </div>
        </div>
    </section>

    {{-- 2. Categorías --}}
    <section class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="font-bold">Categorías <span class="font-normal text-slate-500 text-sm">(al menos 1)</span></h2>
            <button type="button" onclick="addCategory()" class="text-sm bg-brand-600 hover:bg-brand-700 text-white px-3 py-1.5 rounded-md">
                + Agregar categoría
            </button>
        </div>
        <div id="categories-container"></div>
    </section>

    {{-- 3. Tipos de formulario --}}
    <section class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="font-bold">Tipos de formulario <span class="font-normal text-slate-500 text-sm">(opcional)</span></h2>
            <button type="button" onclick="addFormType()" class="text-sm bg-brand-600 hover:bg-brand-700 text-white px-3 py-1.5 rounded-md">
                + Agregar tipo de formulario
            </button>
        </div>
        <div id="formtypes-container"></div>
    </section>

    {{-- 4. Coordenadas --}}
    <section class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="font-bold">Coordenadas <span class="font-normal text-slate-500 text-sm">(opcional)</span></h2>
            <button type="button" onclick="addCoordinate()" class="text-sm bg-brand-600 hover:bg-brand-700 text-white px-3 py-1.5 rounded-md">
                + Agregar coordenada
            </button>
        </div>
        <div id="coordinates-container"></div>
    </section>

    {{-- 5. Ruta --}}
    <section class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="font-bold">Ruta <span class="font-normal text-slate-500 text-sm">(opcional, puntos del recorrido en orden)</span></h2>
            <button type="button" onclick="addRoutePoint()" class="text-sm bg-brand-600 hover:bg-brand-700 text-white px-3 py-1.5 rounded-md">
                + Agregar punto de ruta
            </button>
        </div>
        <div id="route-container"></div>
    </section>

    {{-- 6. Códigos promocionales --}}
    <section class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="font-bold">Códigos promocionales <span class="font-normal text-slate-500 text-sm">(opcional)</span></h2>
            <button type="button" onclick="addPromoCode()" class="text-sm bg-brand-600 hover:bg-brand-700 text-white px-3 py-1.5 rounded-md">
                + Agregar código
            </button>
        </div>
        <div id="promocodes-container"></div>
    </section>

    {{-- 7. Auspiciadores --}}
    <section class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="font-bold">Auspiciadores <span class="font-normal text-slate-500 text-sm">(opcional, carrusel de logos)</span></h2>
            <button type="button" onclick="addAuspiciador()" class="text-sm bg-brand-600 hover:bg-brand-700 text-white px-3 py-1.5 rounded-md">
                + Agregar auspiciador
            </button>
        </div>
        <div id="auspiciadores-container"></div>
    </section>

    {{-- 8. Agenda --}}
    <section class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="font-bold">Agenda <span class="font-normal text-slate-500 text-sm">(opcional, sesiones/ponentes/cronograma)</span></h2>
            <button type="button" onclick="addAgendaItem()" class="text-sm bg-brand-600 hover:bg-brand-700 text-white px-3 py-1.5 rounded-md">
                + Agregar ítem de agenda
            </button>
        </div>
        <p class="text-xs text-slate-500 mb-2">
            El campo "Tipo de formulario" es el nombre exacto de un tipo de
            formulario agregado arriba (dejar vacío para un ítem general de
            todo el evento) — todavía no existen IDs en este punto.
        </p>
        <div id="agenda-container"></div>
    </section>

    <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white rounded-md px-4 py-2 text-sm font-semibold">
        Crear evento (como borrador)
    </button>
</form>

{{-- Templates: __CAT_INDEX__ / __FT_INDEX__ / __S_INDEX__ se reemplazan por JS al clonar --}}
<template id="category-template">
    <div class="category-row border border-slate-200 rounded-md p-3 mb-2 grid grid-cols-6 gap-2 items-end">
        <div class="col-span-1">
            <label class="block text-xs font-semibold mb-1">Nombre</label>
            <input type="text" name="categories[__CAT_INDEX__][name]" required
                   class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
        </div>
        <div class="col-span-1">
            <label class="block text-xs font-semibold mb-1">Precio</label>
            <input type="number" step="0.01" min="0" name="categories[__CAT_INDEX__][price]" required
                   class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
        </div>
        <div class="col-span-1">
            <label class="block text-xs font-semibold mb-1">
                Precio USD <span class="font-normal text-slate-500">(opcional)</span>
            </label>
            <input type="number" step="0.01" min="0" name="categories[__CAT_INDEX__][price_usd]"
                   class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
        </div>
        <div class="col-span-2">
            <label class="block text-xs font-semibold mb-1">Descripción</label>
            <input type="text" name="categories[__CAT_INDEX__][description]"
                   class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
        </div>
        <div class="flex items-end gap-2">
            <div class="flex-1">
                <label class="block text-xs font-semibold mb-1">Color</label>
                <input type="color" name="categories[__CAT_INDEX__][color]" value="#022858"
                       class="w-full h-8 border border-slate-300 rounded">
            </div>
            <button type="button" onclick="this.closest('.category-row').remove()" class="text-red-600 text-xs whitespace-nowrap">
                Quitar
            </button>
        </div>
    </div>
</template>

<template id="formtype-template">
    <div class="formtype-row border border-slate-200 rounded-md p-3 mb-3" data-ft-index="__FT_INDEX__" data-souvenir-index="0">
        <div class="flex justify-between items-start mb-2">
            <span class="text-xs font-semibold text-slate-500">Tipo de formulario</span>
            <button type="button" onclick="this.closest('.formtype-row').remove()" class="text-red-600 text-xs">Quitar</button>
        </div>
        <div class="grid grid-cols-4 gap-2 mb-2">
            <div>
                <label class="block text-xs font-semibold mb-1">Nombre</label>
                <input type="text" name="formTypes[__FT_INDEX__][name]" required
                       class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Ícono (emoji)</label>
                <input type="text" name="formTypes[__FT_INDEX__][icon]" value="🏃" maxlength="10"
                       class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">
                    Imagen (URL) <span class="font-normal text-slate-500">(opcional, reemplaza al ícono)</span>
                </label>
                <input type="text" name="formTypes[__FT_INDEX__][imagen_url]" placeholder="https://…"
                       class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-semibold mb-1">Descripción</label>
                <input type="text" name="formTypes[__FT_INDEX__][description]"
                       class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Tipo</label>
                <select name="formTypes[__FT_INDEX__][tipo]" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                    @foreach (['deportivo','congreso','taller','corporativo','cultural','social','educativo','recreativo','religioso','gastronomico','musical','tecnologico','artes','literario','ambiental','salud','moda','teatro','cine','fotografia','danza','literatura'] as $tipo)
                        <option value="{{ $tipo }}" @selected($tipo === 'deportivo')>{{ $tipo }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Cupo total</label>
                <input type="number" min="0" name="formTypes[__FT_INDEX__][cupo_total]" value="100" required
                       class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Precio base</label>
                <input type="number" step="0.01" min="0" name="formTypes[__FT_INDEX__][precio_base]" value="0" required
                       class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Costo edición</label>
                <input type="number" step="0.01" min="0" name="formTypes[__FT_INDEX__][costo_edicion]" value="0"
                       class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Expira (min)</label>
                <input type="number" min="0" name="formTypes[__FT_INDEX__][tiempo_expiracion_min]" value="30"
                       class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">
                    Color <span class="font-normal text-slate-500">(gafetes)</span>
                </label>
                <input type="color" name="formTypes[__FT_INDEX__][color]" value="#00bad2"
                       class="w-full h-8 border border-slate-300 rounded">
            </div>
        </div>
        <div class="flex flex-wrap gap-4 mb-3">
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="formTypes[__FT_INDEX__][requiere_categoria]" value="1" checked>
                Requiere categoría
            </label>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="formTypes[__FT_INDEX__][hasTeam]" value="1">
                Con equipo
            </label>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="formTypes[__FT_INDEX__][hasDelivery]" value="1">
                Con delivery de kit
            </label>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="formTypes[__FT_INDEX__][hasDonation]" value="1">
                Permite donación
            </label>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="formTypes[__FT_INDEX__][hasPromoCode]" value="1">
                Admite código promocional
            </label>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="formTypes[__FT_INDEX__][esStaff]" value="1">
                Es Staff/Ayudante <span class="text-slate-400">(asignable a sesiones de congreso)</span>
            </label>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="formTypes[__FT_INDEX__][esPonente]" value="1">
                Es Ponente/Expositor <span class="text-slate-400">(vinculable a sesiones de congreso)</span>
            </label>
        </div>
        <div class="border-t border-slate-100 pt-2">
            <div class="flex justify-between items-center mb-2">
                <span class="text-xs font-semibold text-slate-500">Souvenirs</span>
                <button type="button" onclick="addSouvenir(this)" class="text-xs text-brand-600 hover:underline">+ Agregar souvenir</button>
            </div>
            <div class="souvenirs-container"></div>
        </div>
    </div>
</template>

<template id="souvenir-template">
    <div class="souvenir-row grid grid-cols-4 gap-2 mb-1 items-end">
        <div class="col-span-2">
            <input type="text" name="formTypes[__FT_INDEX__][souvenirs][__S_INDEX__][name]" placeholder="Nombre (ej. Medalla)"
                   class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
        </div>
        <div>
            <input type="text" name="formTypes[__FT_INDEX__][souvenirs][__S_INDEX__][icon]" placeholder="Ícono" maxlength="10"
                   class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
        </div>
        <div class="flex gap-2">
            <input type="number" step="0.01" min="0" name="formTypes[__FT_INDEX__][souvenirs][__S_INDEX__][price]" placeholder="Precio"
                   class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
            <button type="button" onclick="this.closest('.souvenir-row').remove()" class="text-red-600 text-xs">Quitar</button>
        </div>
    </div>
</template>

<template id="coordinate-template">
    <div class="coordinate-row border border-slate-200 rounded-md p-3 mb-2 grid grid-cols-5 gap-2 items-end">
        <div>
            <label class="block text-xs font-semibold mb-1">Lat</label>
            <input type="number" step="any" name="coordinates[__C_INDEX__][lat]" required
                   class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
        </div>
        <div>
            <label class="block text-xs font-semibold mb-1">Lng</label>
            <input type="number" step="any" name="coordinates[__C_INDEX__][lng]" required
                   class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
        </div>
        <div>
            <button type="button" onclick="this.closest('.coordinate-row').remove()" class="text-red-600 text-xs">Quitar</button>
        </div>
    </div>
</template>

<template id="route-template">
    <div class="route-row border border-slate-200 rounded-md p-3 mb-2 grid grid-cols-5 gap-2 items-end">
        <div>
            <label class="block text-xs font-semibold mb-1">Lat</label>
            <input type="number" step="any" name="route[__R_INDEX__][lat]" required
                   class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
        </div>
        <div>
            <label class="block text-xs font-semibold mb-1">Lng</label>
            <input type="number" step="any" name="route[__R_INDEX__][lng]" required
                   class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
        </div>
        <div class="col-span-2">
            <label class="block text-xs font-semibold mb-1">Etiqueta</label>
            <input type="text" name="route[__R_INDEX__][label]" required
                   class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
        </div>
        <div>
            <button type="button" onclick="this.closest('.route-row').remove()" class="text-red-600 text-xs">Quitar</button>
        </div>
    </div>
</template>

<template id="promocode-template">
    <div class="promocode-row border border-slate-200 rounded-md p-3 mb-2 grid grid-cols-5 gap-2 items-end">
        <div>
            <label class="block text-xs font-semibold mb-1">Código</label>
            <input type="text" name="promoCodes[__P_INDEX__][promo_code]" required
                   class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
        </div>
        <div>
            <label class="block text-xs font-semibold mb-1">Precio fijo</label>
            <input type="number" step="0.01" min="0" name="promoCodes[__P_INDEX__][price]"
                   class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
        </div>
        <div>
            <label class="block text-xs font-semibold mb-1">Tipo</label>
            <select name="promoCodes[__P_INDEX__][discount_type]" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
                <option value="fixed_price">fixed_price</option>
                <option value="percentage">percentage</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold mb-1">% descuento</label>
            <input type="number" step="0.01" min="0" max="1" name="promoCodes[__P_INDEX__][discount_percent]"
                   class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
        </div>
        <div>
            <button type="button" onclick="this.closest('.promocode-row').remove()" class="text-red-600 text-xs">Quitar</button>
        </div>
    </div>
</template>

<template id="auspiciador-template">
    <div class="auspiciador-row border border-slate-200 rounded-md p-3 mb-2 grid grid-cols-5 gap-2 items-end">
        <div>
            <label class="block text-xs font-semibold mb-1">Nombre</label>
            <input type="text" name="auspiciadores[__A_INDEX__][nombre]" required
                   class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
        </div>
        <div class="col-span-2">
            <label class="block text-xs font-semibold mb-1">Logo (URL)</label>
            <input type="text" name="auspiciadores[__A_INDEX__][logo_url]" required
                   class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
        </div>
        <div>
            <label class="block text-xs font-semibold mb-1">Contacto</label>
            <input type="text" name="auspiciadores[__A_INDEX__][contacto]"
                   class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
        </div>
        <div class="flex items-end gap-2">
            <input type="number" name="auspiciadores[__A_INDEX__][orden]" placeholder="Orden"
                   class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
            <button type="button" onclick="this.closest('.auspiciador-row').remove()" class="text-red-600 text-xs">Quitar</button>
        </div>
    </div>
</template>

<template id="agendaitem-template">
    <div class="agendaitem-row border border-slate-200 rounded-md p-3 mb-2 grid grid-cols-4 gap-2 items-end">
        <div>
            <label class="block text-xs font-semibold mb-1">Fecha</label>
            <input type="date" name="agenda[__AG_INDEX__][date]" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
        </div>
        <div>
            <label class="block text-xs font-semibold mb-1">Hora inicio</label>
            <input type="time" name="agenda[__AG_INDEX__][startTime]" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
        </div>
        <div>
            <label class="block text-xs font-semibold mb-1">Hora fin</label>
            <input type="time" name="agenda[__AG_INDEX__][endTime]" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
        </div>
        <div>
            <label class="block text-xs font-semibold mb-1">Tipo de formulario</label>
            <input type="text" name="agenda[__AG_INDEX__][formTypeName]" placeholder="(vacío = general)" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
        </div>
        <div class="col-span-2">
            <label class="block text-xs font-semibold mb-1">Título</label>
            <input type="text" name="agenda[__AG_INDEX__][title]" required class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
        </div>
        <div class="col-span-2">
            <label class="block text-xs font-semibold mb-1">Descripción</label>
            <input type="text" name="agenda[__AG_INDEX__][description]" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
        </div>
        <div>
            <label class="block text-xs font-semibold mb-1">Ponente</label>
            <input type="text" name="agenda[__AG_INDEX__][speaker]" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
        </div>
        <div>
            <label class="block text-xs font-semibold mb-1">Cargo</label>
            <input type="text" name="agenda[__AG_INDEX__][speakerRole]" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
        </div>
        <div>
            <label class="block text-xs font-semibold mb-1">Sala</label>
            <input type="text" name="agenda[__AG_INDEX__][room]" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
        </div>
        <div>
            <label class="block text-xs font-semibold mb-1">Ícono</label>
            <input type="text" name="agenda[__AG_INDEX__][icon]" maxlength="10" class="w-full border border-slate-300 rounded px-2 py-1 text-sm">
        </div>
        <div>
            <button type="button" onclick="this.closest('.agendaitem-row').remove()" class="text-red-600 text-xs">Quitar</button>
        </div>
    </div>
</template>

<script>
    let categoryIndex = 0;
    let formTypeIndex = 0;
    let coordinateIndex = 0;
    let routeIndex = 0;
    let promoCodeIndex = 0;
    let auspiciadorIndex = 0;
    let agendaItemIndex = 0;

    function addCategory() {
        const tpl = document.getElementById('category-template').innerHTML.replaceAll('__CAT_INDEX__', categoryIndex++);
        document.getElementById('categories-container').insertAdjacentHTML('beforeend', tpl);
    }

    function addFormType() {
        const idx = formTypeIndex++;
        const tpl = document.getElementById('formtype-template').innerHTML.replaceAll('__FT_INDEX__', idx);
        document.getElementById('formtypes-container').insertAdjacentHTML('beforeend', tpl);
    }

    function addSouvenir(button) {
        const row = button.closest('.formtype-row');
        const ftIndex = row.dataset.ftIndex;
        const sIndex = parseInt(row.dataset.souvenirIndex || '0', 10);
        const tpl = document.getElementById('souvenir-template').innerHTML
            .replaceAll('__FT_INDEX__', ftIndex)
            .replaceAll('__S_INDEX__', sIndex);
        row.querySelector('.souvenirs-container').insertAdjacentHTML('beforeend', tpl);
        row.dataset.souvenirIndex = sIndex + 1;
    }

    function addCoordinate() {
        const tpl = document.getElementById('coordinate-template').innerHTML.replaceAll('__C_INDEX__', coordinateIndex++);
        document.getElementById('coordinates-container').insertAdjacentHTML('beforeend', tpl);
    }

    function addRoutePoint() {
        const tpl = document.getElementById('route-template').innerHTML.replaceAll('__R_INDEX__', routeIndex++);
        document.getElementById('route-container').insertAdjacentHTML('beforeend', tpl);
    }

    function addPromoCode() {
        const tpl = document.getElementById('promocode-template').innerHTML.replaceAll('__P_INDEX__', promoCodeIndex++);
        document.getElementById('promocodes-container').insertAdjacentHTML('beforeend', tpl);
    }

    function addAuspiciador() {
        const tpl = document.getElementById('auspiciador-template').innerHTML.replaceAll('__A_INDEX__', auspiciadorIndex++);
        document.getElementById('auspiciadores-container').insertAdjacentHTML('beforeend', tpl);
    }

    function addAgendaItem() {
        const tpl = document.getElementById('agendaitem-template').innerHTML.replaceAll('__AG_INDEX__', agendaItemIndex++);
        document.getElementById('agenda-container').insertAdjacentHTML('beforeend', tpl);
    }

    // Arranca con 1 categoría visible — categories es required|array|min:1 en la API.
    addCategory();
</script>
@endsection
