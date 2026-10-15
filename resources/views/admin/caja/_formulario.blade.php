{{--
    Formulario compartido de la Caja (alta nueva / edición) — ver
    ApiRestEvent/brain/api_rest_event/PLAN-CAJA-COBRO-PRESENCIAL-14082026.md,
    "Actualización 2": mismo detalle de campos que el formulario público
    (categoría, identidad, contacto, equipo, delivery, souvenirs con
    talla/sexo, donación, promo), no el subset reducido tipo carga CSV.

    Variables esperadas: $evento (array), $modo ('nueva'|'editar'),
    $formTypeFijo (int|null), $prefill (array|null), $costoEdicion
    (float|null), $actionUrl (string), $pagoStatus ('pending'|'paid'|null).
--}}
@php
    $prefill ??= [];
    $formTypes = $evento['formTypes'] ?? [];
@endphp

<form id="cajaForm" method="POST" action="{{ $actionUrl }}" class="space-y-6">
    @csrf
    @if ($modo === 'editar')
        <input type="hidden" name="pago_status" value="{{ $pagoStatus }}">
    @endif
    <input type="hidden" name="participante_json" id="participante_json">
    <input type="hidden" name="totales_json" id="totales_json">

    @if ($modo === 'editar' && $pagoStatus === 'paid')
        {{-- Agregar talleres / cambiar categoría en inscripción pagada
             (25/08/2026) — el monto real ya no es siempre el fijo de acá
             (ver ApiRestEvent ActualizarInscripcionPagadaAction): si se
             agrega un taller o se cambia de categoría, se suma/resta la
             diferencia real, que puede terminar en una DEVOLUCIÓN
             (categoría más barata). El monto exacto (a cobrar o a
             devolver) recién se conoce al guardar — este aviso ya no
             promete un número fijo. --}}
        <div class="bg-amber-50 border border-amber-300 text-amber-900 rounded-md p-4 text-sm">
            <p class="font-semibold mb-1">Esta inscripción ya está pagada.</p>
            <p>
                Guardar los cambios cobra un cargo base de edición de
                <strong>{{ number_format($costoEdicion ?? 0, 2) }}</strong>, más la diferencia real de
                cualquier taller agregado, cambio de categoría, {{-- Quitar/cambiar un taller ya
                pagado (29/09/2026) — antes solo mencionaba categoría. --}}
                o taller ya pagado que se quite o cambie por otro — cualquiera de estos puede
                terminar en una <strong>devolución</strong> al participante si lo nuevo sale más
                barato. El monto final se confirma al guardar.
            </p>
            <label class="inline-flex items-center gap-2 mt-2">
                <input type="checkbox" id="confirmarAdicional">
                Entiendo y confirmo el cobro o devolución que corresponda
            </label>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow p-5 caja-section">
        <div class="flex items-center gap-2 mb-3">
            <h2 class="font-semibold">Tipo de inscripción</h2>
            {{-- Color por tipo de inscripción (20/08/2026) — reusa
                 form_types.color (mismo campo que ya se usa en gafetes),
                 así la cajera distingue de un vistazo qué tipo está
                 cargando sin depender de leer el nombre completo. El
                 acento se repite en cada tarjeta del formulario (ver
                 applyFormTypeColor()) para que siga a la vista al
                 scrollear, no solo acá arriba. --}}
            <span id="formTypeBadge" class="text-xs font-semibold px-2 py-0.5 rounded-full" style="display:none"></span>
        </div>
        @if ($modo === 'nueva')
            <select id="formTypesId" name="form_types_id" required
                    class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                <option value="">— seleccionar —</option>
                @foreach ($formTypes as $ft)
                    <option value="{{ $ft['id'] }}">{{ $ft['name'] }}</option>
                @endforeach
            </select>
        @else
            @php($ftActual = collect($formTypes)->firstWhere('id', $formTypeFijo))
            <p class="text-sm">{{ $ftActual['name'] ?? ('#'.$formTypeFijo) }}</p>
            <input type="hidden" id="formTypesId" value="{{ $formTypeFijo }}">
        @endif
    </div>

    <div id="categorySection" class="bg-white rounded-lg shadow p-5 caja-section" style="display:none">
        <h2 class="font-semibold mb-3">Categoría</h2>
        <select id="categoria" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></select>
    </div>

    <div class="bg-white rounded-lg shadow p-5 caja-section">
        <h2 class="font-semibold mb-3">Datos del participante</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div><label class="block text-xs font-semibold mb-1">Nombre *</label>
                <input type="text" id="f_nombre" value="{{ $prefill['nombre'] ?? '' }}" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
            <div id="f_apellidoGroup"><label class="block text-xs font-semibold mb-1">Apellido *</label>
                <input type="text" id="f_apellido" value="{{ $prefill['apellido'] ?? '' }}" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
            <div id="f_aliasGroup">
                <label class="block text-xs font-semibold mb-1" id="f_aliasLabel">Alias</label>
                <input type="text" id="f_alias" value="{{ $prefill['alias'] ?? '' }}" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                {{-- Título de congreso (20/08/2026) — mismo mecanismo que
                     elascenso/event: reusa el campo `alias` existente, sin
                     columna nueva. Ver toggleAliasTituloMode() más abajo. --}}
                <select id="f_aliasTitulo" style="display:none" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm mt-1">
                    <option value="">— seleccionar —</option>
                    <option value="Dr.">Dr.</option>
                    <option value="Dra.">Dra.</option>
                    <option value="Lic.">Lic.</option>
                    <option value="Ing.">Ing.</option>
                    <option value="Msc.">Msc.</option>
                    <option value="Mgr.">Mgr.</option>
                    <option value="Est.">Est.</option>
                    <option value="PhD.">PhD.</option>
                    <option value="Otro">Otro</option>
                </select>
                <input type="text" id="f_aliasTituloOtro" placeholder="Especificar título" style="display:none" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm mt-1">
            </div>
            <div id="f_generoGroup"><label class="block text-xs font-semibold mb-1">Género *</label>
                {{-- Bug real 25/08/2026 (reportado por el usuario: "muchas
                     personas de sexo femenino registraron masculino") — este
                     select no tenía opción vacía ni `required`, y "Masculino"
                     era la primera opción: si el cajero no lo tocaba, quedaba
                     Masculino por defecto del navegador aunque la persona
                     fuera mujer. Ahora exige elección explícita, igual que
                     el resto de los campos obligatorios de este formulario. --}}
                <select id="f_genero" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    <option value="" @selected(empty($prefill['genero']))>Seleccionar…</option>
                    <option value="Masculino" @selected(($prefill['genero'] ?? '') === 'Masculino')>Masculino</option>
                    <option value="Femenino" @selected(($prefill['genero'] ?? '') === 'Femenino')>Femenino</option>
                </select></div>
            <div><label class="block text-xs font-semibold mb-1">Tipo documento</label>
                <select id="f_tipoDocumento" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    <option value="DNI" @selected(($prefill['tipoDocumento'] ?? 'DNI') === 'DNI')>DNI</option>
                    <option value="Pasaporte" @selected(($prefill['tipoDocumento'] ?? '') === 'Pasaporte')>Pasaporte</option>
                    <option value="Carnet" @selected(($prefill['tipoDocumento'] ?? '') === 'Carnet')>Carnet</option>
                </select></div>
            <div><label class="block text-xs font-semibold mb-1">N° documento *</label>
                <input type="text" id="f_numeroDocumento" value="{{ $prefill['numeroDocumento'] ?? '' }}" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                {{-- Duplicado por documento (20/08/2026) — solo en alta
                     nueva; en editar ya se está tocando esa misma
                     inscripción, no tiene sentido avisar contra sí misma.
                     Ver checkDocumentoDuplicado() más abajo. --}}
                @if ($modo === 'nueva')
                    <p id="documentoWarning" class="text-xs text-red-600 mt-1" style="display:none"></p>
                    {{-- Prellenado desde `personas` (20/08/2026) — solo se
                         consulta si NO hubo duplicado en este evento, ver
                         checkDocumentoDuplicado(). --}}
                    <p id="personaFoundBanner" class="text-xs text-brand-700 mt-1" style="display:none"></p>
                @endif
            </div>
            <div class="f-campo-nacimiento"><label class="block text-xs font-semibold mb-1">Día nac. *</label>
                <input type="number" min="1" max="31" id="f_nacDia" value="{{ $prefill['nacimientoDia'] ?? '' }}" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
            <div class="f-campo-nacimiento"><label class="block text-xs font-semibold mb-1">Mes nac. *</label>
                <input type="number" min="1" max="12" id="f_nacMes" value="{{ $prefill['nacimientoMes'] ?? '' }}" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
            <div class="f-campo-nacimiento"><label class="block text-xs font-semibold mb-1">Año nac. *</label>
                <input type="number" min="1900" max="{{ date('Y') }}" id="f_nacAnio" value="{{ $prefill['nacimientoAnio'] ?? '' }}" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
            <div><label class="block text-xs font-semibold mb-1">Correo *</label>
                <input type="email" id="f_correo" value="{{ $prefill['correo'] ?? '' }}" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
            <div id="f_telefonoGroup"><label class="block text-xs font-semibold mb-1">Teléfono</label>
                <input type="text" id="f_telefono" value="{{ $prefill['telefono'] ?? '' }}" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
            <div id="f_direccionGroup"><label class="block text-xs font-semibold mb-1">Dirección</label>
                <input type="text" id="f_direccion" value="{{ $prefill['direccion'] ?? '' }}" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
            <div id="f_ciudadGroup"><label class="block text-xs font-semibold mb-1">Ciudad</label>
                <input type="text" id="f_ciudad" value="{{ $prefill['ciudad'] ?? '' }}" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
        </div>
    </div>

    {{-- Contacto de emergencia — oculto cuando el tipo de inscripción no
         lo pide (form_types.requiereContactoEmergencia=false, típicamente
         congresos/talleres donde no aplica). El `required` estático de
         acá es el default de carga inicial; renderContactoEmergencia()
         lo saca/pone a mano según el form_type elegido. --}}
    <div id="emergencySection" class="bg-white rounded-lg shadow p-5 caja-section">
        <h2 class="font-semibold mb-3">Contacto de emergencia</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div><label class="block text-xs font-semibold mb-1">Nombre *</label>
                <input type="text" id="f_ceNombre" value="{{ $prefill['contactoNombre'] ?? '' }}" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
            <div><label class="block text-xs font-semibold mb-1">Celular *</label>
                <input type="text" id="f_ceCelular" value="{{ $prefill['contactoCelular'] ?? '' }}" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
            <div><label class="block text-xs font-semibold mb-1">Relación *</label>
                <input type="text" id="f_ceRelacion" value="{{ $prefill['contactoRelacion'] ?? '' }}" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
        </div>
    </div>

    {{-- Talleres/sesiones de congreso (20/08/2026) — mismo dato que
         elascenso/event: `participante.talleres[]` con
         {taller_id, sesion_congreso_id, ...}, revalidado y repreciado
         server-side por ApiRestEvent (pertenencia/cupo/solape/requerido),
         acá solo se arma la selección y un total orientativo. --}}
    <div id="talleresSection" class="bg-white rounded-lg shadow p-5 caja-section" style="display:none">
        <h2 class="font-semibold mb-1">Talleres / sesiones de congreso</h2>
        <p class="text-xs text-slate-400 mb-3">Los obligatorios van primero. El precio final lo recalcula el servidor al confirmar.</p>
        <div id="talleresGrid" class="space-y-2"></div>
        <p id="talleresRequiredWarning" class="text-xs text-red-600 mt-2" style="display:none">Faltan talleres obligatorios por seleccionar.</p>

        {{-- Quitar/cambiar un taller ya pagado (29/09/2026) — pedido
             explícito del usuario: mostrar el cambio bien documentado (qué
             se quita, qué se agrega, la diferencia), no solo un número
             suelto en el resumen de abajo. Solo aparece en edición de una
             inscripción pagada, y solo si de verdad se destildó algo. --}}
        @if ($modo === 'editar' && $pagoStatus === 'paid')
            <div id="tallerCambioResumen" class="mt-3 border border-slate-200 rounded p-2 text-xs" style="display:none"></div>
            <div id="tallerMotivoGroup" class="mt-3" style="display:none">
                <label class="block text-xs font-semibold mb-1" for="f_motivo">
                    Motivo del cambio de taller <span class="text-red-600">*</span>
                </label>
                <textarea id="f_motivo" name="motivo" rows="2" maxlength="500"
                          placeholder="Ej.: no se concretó el ponente, se pasó a otro taller."
                          class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></textarea>
            </div>
        @endif
    </div>

    <div id="equipoSection" class="bg-white rounded-lg shadow p-5 caja-section" style="display:none">
        <h2 class="font-semibold mb-3">Equipo</h2>
        <select id="f_equipoId" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
            <option value="">— seleccionar —</option>
            @foreach ($evento['equipos'] ?? [] as $eq)
                <option value="{{ $eq['id'] }}" @selected(($prefill['equipoId'] ?? null) == $eq['id'])>{{ $eq['nombre'] }}</option>
            @endforeach
        </select>
    </div>

    <div id="deliverySection" class="bg-white rounded-lg shadow p-5 caja-section" style="display:none">
        <h2 class="font-semibold mb-3">Delivery del kit</h2>
        <label class="inline-flex items-center gap-2 text-sm mb-3">
            <input type="checkbox" id="f_quiereDelivery" @checked($prefill['quiereDelivery'] ?? false)>
            Quiere delivery
        </label>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="block text-xs font-semibold mb-1">Latitud (opcional)</label>
                <input type="number" step="0.000001" id="f_deliveryLat" value="{{ $prefill['deliveryLat'] ?? '' }}" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
            <div><label class="block text-xs font-semibold mb-1">Longitud (opcional)</label>
                <input type="number" step="0.000001" id="f_deliveryLng" value="{{ $prefill['deliveryLng'] ?? '' }}" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
        </div>
    </div>

    <div id="souvenirsSection" class="bg-white rounded-lg shadow p-5 caja-section" style="display:none">
        <h2 class="font-semibold mb-3">Kit / souvenirs</h2>
        <div id="souvenirsList" class="space-y-3"></div>
    </div>

    <div id="donationSection" class="bg-white rounded-lg shadow p-5 caja-section" style="display:none">
        <h2 class="font-semibold mb-3">Donación</h2>
        <input type="number" step="0.01" min="0" id="f_donacion" value="{{ $prefill['donacion'] ?? 0 }}" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
    </div>

    <div id="promoSection" class="bg-white rounded-lg shadow p-5 caja-section" style="display:none">
        <h2 class="font-semibold mb-3">Código promocional</h2>
        <input type="text" id="f_promoCodigo" value="{{ $prefill['promoCodigo'] ?? '' }}" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
        <p id="promoMsg" class="text-xs mt-1"></p>
    </div>

    <div class="bg-white rounded-lg shadow p-5 caja-section">
        <h2 class="font-semibold mb-3">Resumen de cobro</h2>
        {{-- Precio USD fijo en Caja (12/09/2026) — mismo aviso corto que ya
             usa el registro público para estos eventos. --}}
        <p id="usdFijoAviso" class="text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1 mb-2" style="display:none">
            Este evento cobra en USD fijo — cobrá en efectivo en dólares, el monto exacto de abajo.
        </p>
        <dl class="text-sm space-y-1">
            <div class="flex justify-between"><dt>Inscripción</dt><dd id="r_inscripcion">0.00</dd></div>
            {{-- Filas condicionales (20/08/2026) — ocultas si el tipo de
                 inscripción/evento no ofrece esa opción, no solo si el
                 monto da 0. Ver updateResumenVisibility(). --}}
            <div id="r_talleres_row" class="flex justify-between" style="display:none"><dt>Talleres</dt><dd id="r_talleres">0.00</dd></div>
            <div id="r_souvenirs_row" class="flex justify-between" style="display:none"><dt>Souvenirs</dt><dd id="r_souvenirs">0.00</dd></div>
            <div id="r_descuento_row" class="flex justify-between" style="display:none"><dt>Descuento promo</dt><dd id="r_descuento">-0.00</dd></div>
            <div id="r_donacion_row" class="flex justify-between" style="display:none"><dt>Donación</dt><dd id="r_donacion">0.00</dd></div>
            {{-- Cargo de edición (17/09/2026) — fijo por form_type
                 (formType.costo_edicion), cobrado por ActualizarInscripcionPagadaAction
                 en TODA edición de una inscripción pagada, sin importar si además
                 hay delta de categoría/talleres. El resumen no lo mostraba (bug real,
                 incidente CIACRUZ) — el "Total a cobrar" quedaba sistemáticamente por
                 debajo de lo que el backend cobraba de verdad. --}}
            <div id="r_costoEdicion_row" class="flex justify-between" style="display:none"><dt>Cargo de edición</dt><dd id="r_costoEdicion">0.00</dd></div>
            <div class="flex justify-between"><dt>Cargo de servicio</dt><dd id="r_fee">0.00</dd></div>
            <div class="flex justify-between font-bold text-base border-t pt-1 mt-1"><dt>Total a cobrar</dt><dd id="r_total">0.00</dd></div>
        </dl>

        {{-- Método de pago en Caja (18/09/2026) — Efectivo o QR (QR bancario
             único del evento, ya impreso/generado de antes, no uno nuevo por
             transacción). Solo tiene sentido cuando esta pantalla
             efectivamente cobra algo: alta nueva, o edición de una
             inscripción YA pagada (el adicional real) — edición de una
             pendiente no cobra nada acá (ver CajaController::editarPendiente()). --}}
        @if ($modo === 'nueva' || $pagoStatus === 'paid')
            <div class="mt-3 pt-3 border-t border-slate-100">
                <p class="text-xs font-semibold mb-1">Método de pago</p>
                {{-- Métodos nuevos (30/09/2026) — Depósito/Organizador son
                     dinero real por fuera del cajón (mismo criterio que QR
                     para "monto esperado"); Cortesía pone el total en $0
                     sin importar categoría/talleres elegidos (ver
                     CajaController::aplicarCortesia() en ApiRestEvent). --}}
                <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                    <label class="inline-flex items-center gap-1.5">
                        <input type="radio" name="metodo_pago" value="EFECTIVO" id="mp_efectivo" checked>
                        Efectivo
                    </label>
                    <label class="inline-flex items-center gap-1.5">
                        <input type="radio" name="metodo_pago" value="QR" id="mp_qr">
                        QR
                    </label>
                    <label class="inline-flex items-center gap-1.5">
                        <input type="radio" name="metodo_pago" value="DEPOSITO" id="mp_deposito">
                        Depósito
                    </label>
                    <label class="inline-flex items-center gap-1.5">
                        <input type="radio" name="metodo_pago" value="ORGANIZADOR" id="mp_organizador">
                        Organizador
                    </label>
                    <label class="inline-flex items-center gap-1.5">
                        <input type="radio" name="metodo_pago" value="CORTESIA" id="mp_cortesia">
                        Cortesía
                    </label>
                </div>
                <p id="cortesiaAviso" class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1 mt-2" style="display:none">
                    El total de esta inscripción queda en <strong>Bs 0.00</strong> — la categoría/talleres elegidos se conservan igual, solo no se cobra nada.
                </p>

                {{-- Observaciones (07/10/2026) — nota libre y opcional,
                     para cualquier método de pago (Efectivo/QR/Depósito/
                     Organizador/Cortesía). Campo plano con `name`, viaja
                     con el resto del <form> normal: no toca
                     participante_json/totales_json ni el JS de cálculo. --}}
                <div class="mt-3">
                    <label class="block text-xs font-semibold mb-1" for="f_observaciones">Observaciones (opcional)</label>
                    {{-- autocomplete="off" (08/10/2026) — bug real reportado
                         por el usuario: sin esto, el navegador recuerda lo
                         último tipeado acá y lo autocompleta solo en la
                         inscripción siguiente; si el cajero no se da cuenta,
                         queda guardado en una persona distinta. --}}
                    <textarea id="f_observaciones" name="observaciones" rows="2" maxlength="1000"
                              autocomplete="off"
                              placeholder="Ej.: pagó con billete de Bs 200, se le dio el vuelto."
                              class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></textarea>
                </div>
            </div>
        @endif
    </div>

    <button type="submit" id="btnSubmit"
            class="w-full bg-brand-600 hover:bg-brand-700 text-white rounded-md px-3 py-3 text-sm font-semibold">
        {{ $modo === 'nueva' ? 'Cobrar y confirmar' : ($pagoStatus === 'paid' ? 'Confirmar y cobrar adicional' : 'Guardar cambios') }}
    </button>
</form>

<script>
(function () {
    const EVENTO = {!! json_encode($evento) !!};
    // Duplicado por documento (20/08/2026) — reusa el mismo buscador de
    // "Buscar / cobrar / editar" (caja.buscar.resultados), ya filtra por
    // evento y por numero_documento server-side; no hace falta endpoint
    // nuevo. Solo se usan en modo 'nueva' (ver checkDocumentoDuplicado()).
    const BUSCAR_URL = @json($modo === 'nueva' ? route('admin.caja.buscar.resultados', $evento['id']) : null);
    const EDITAR_URL_BASE = @json($modo === 'nueva' ? route('admin.caja.editar', [$evento['id'], '__REF__']) : null);
    const ETICKET_URL_BASE = @json($modo === 'nueva' ? route('admin.caja.eticket', [$evento['id'], '__REF__']) : null);
    // Prellenado desde `personas` (20/08/2026) — solo se consulta si el
    // documento NO está duplicado en este evento, ver checkPersonaConocida().
    const PERSONA_URL = @json($modo === 'nueva' ? route('admin.caja.persona', $evento['id']) : null);
    const PREFILL_SOUVENIRS = {!! json_encode($prefill['souvenirs'] ?? []) !!};
    // Congresos con talleres desde Caja (20/08/2026) — mismo shape que
    // ParticipanteTallerSesionResource: sesionCongresoId/tallerId.
    const PREFILL_TALLERES = {!! json_encode($prefill['talleres'] ?? []) !!};
    // Cobro correcto al editar una inscripción pagada (17/09/2026, bug
    // real en UAT: incidente CIACRUZ LA-CD34EA70) — el "Resumen de cobro"
    // mostraba el precio VIGENTE de la categoría y la suma de TODOS los
    // talleres seleccionados (incluidos los ya pagados de antes), en vez
    // del delta real a cobrar. Ver calcular() más abajo — mismo criterio
    // que EdicionPagadaCategoriaData::resolver() del lado de ApiRestEvent:
    // sin cambio de categoría, delta 0 (sin importar si el precio de
    // catálogo subió desde que se pagó).
    const MODO = @json($modo);
    const PAGO_STATUS = @json($pagoStatus ?? null);
    const ES_EDICION_PAGADA = MODO === 'editar' && PAGO_STATUS === 'paid';
    const PRECIO_ANTERIOR = @json($prefill['precioCategoria'] ?? null);
    const CATEGORIA_ANTERIOR = @json($prefill['categoria'] ?? null);
    const TALLERES_ANTERIORES_IDS = new Set((PREFILL_TALLERES || []).map(t => Number(t.sesionCongresoId)));
    // Cargo de edición (17/09/2026, mismo incidente) — fijo por form_type,
    // cobrado siempre por ActualizarInscripcionPagadaAction en cualquier
    // edición de pagada; el resumen no lo sumaba. Ver calcular().
    const COSTO_EDICION = @json((float) ($costoEdicion ?? 0));
    // Precio USD fijo en Caja (12/09/2026) — ver
    // CurrencyResolverData::resolverPrecioFijo() (ApiRestEvent) y
    // brain/PLAN-CAJA-USD-FIJO-EXTRANJEROS-27082026.md. Mismo criterio que
    // el registro público: solo inscripción por categoría, sin talleres/
    // souvenirs/donación en este modo todavía (alcance acotado a lo
    // esencial — el servidor igual rechaza cualquier intento de colarse).
    const USD_PRECIO_FIJO = !!EVENTO.usdPrecioFijo;
    document.getElementById('usdFijoAviso').style.display = USD_PRECIO_FIJO ? '' : 'none';
    const FEE_PCT = Number(EVENTO.fee_pct || 0);
    const FEE_INCLUYE_TALLERES = EVENTO.feeIncluyeTalleres !== false;
    const TALLERES_CON_COSTO = !!EVENTO.talleresConCosto;
    const TALLAS = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
    const SEXOS = ['Masculino', 'Femenino'];
    const TALLER_CUPO_CRITICAL_THRESHOLD = 3;
    const TALLER_CUPO_LOW_THRESHOLD = 10;

    function formTypeActual() {
        const id = document.getElementById('formTypesId').value;
        return (EVENTO.formTypes || []).find(ft => String(ft.id) === String(id));
    }

    function renderCategoria(ft) {
        const section = document.getElementById('categorySection');
        const select = document.getElementById('categoria');
        if (!ft || !ft.requiereCategoria) { section.style.display = 'none'; select.innerHTML = ''; return; }
        section.style.display = '';
        // Categorías por form_type (27/08/2026) — formulario_id null =
        // compartida por todos los form_types del evento (comportamiento
        // previo, sin cambios); con un valor, solo se muestra para ese
        // form_type. Mismo filtro que el formulario público
        // (elascenso/event/index.php).
        const categoriasDelFormType = (EVENTO.categories || []).filter(
            c => c.formulario_id == null || String(c.formulario_id) === String(ft.id)
        );
        select.innerHTML = '<option value="">— seleccionar —</option>' + categoriasDelFormType.map(c =>
            `<option value="${c.id}" data-precio="${c.precio_vigente}" data-precio-usd="${c.precio_usd_vigente ?? ''}">${c.name} (${Number(c.precio_vigente).toFixed(2)})</option>`
        ).join('');
        // Bug real 27/08/2026 (reportado por el usuario: "no me muestra la
        // opción de modificar/adición de talleres incluso cuando hago
        // click en checkbox") — acá usaba la directiva Blade de echo
        // ESCAPADO en vez de la de echo SIN escapar (mismo criterio que
        // EVENTO/PREFILL_TALLERES más arriba). Cuando `categoria` es un
        // string (el caso normal — categorias.id se expone como string),
        // Blade convertía las comillas del JSON en la entidad HTML
        // correspondiente, produciendo JS inválido que rompía el <script>
        // ENTERO por error de sintaxis — nada de lo que viene después
        // (talleres, souvenirs, equipo, alias de congreso, etc.) llegaba
        // a ejecutarse nunca, no solo esta función.
        const pre = @json($prefill['categoria'] ?? null);
        if (pre) select.value = String(pre);
    }

    // Campos ocultables por form_type (13/10/2026, pedido del usuario:
    // "los formularios de caja deberian ser iguales a lo que se configuro
    // en el admin") — mismo mecanismo que ya usa el formulario público
    // (elascenso/event/index.php: CAMPOS_OCULTABLES_IDS/APELLIDO_NEUTRO/
    // GENERO_NEUTRO/NACIMIENTO_NEUTRO/applyCamposOcultos()), portado acá
    // porque Caja lo ignoraba por completo — los 7 campos quedaban siempre
    // visibles y varios `required` a nivel HTML, aunque el form_type (ej.
    // staff/ponente) los tuviera marcados como ocultos en admin-eventos.
    const CAMPOS_OCULTABLES_GROUPS = {
        apellido:   ['f_apellidoGroup'],
        alias:      ['f_aliasGroup'],
        genero:     ['f_generoGroup'],
        nacimiento: null, // usa la clase .f-campo-nacimiento, ver abajo
        telefono:   ['f_telefonoGroup'],
        direccion:  ['f_direccionGroup'],
        ciudad:     ['f_ciudadGroup'],
    };
    const CAMPOS_OCULTABLES_REQUIRED_IDS = {
        apellido:   ['f_apellido'],
        genero:     ['f_genero'],
        nacimiento: ['f_nacDia', 'f_nacMes', 'f_nacAnio'],
    };
    const APELLIDO_NEUTRO   = '-';
    const GENERO_NEUTRO     = 'Otro';
    const NACIMIENTO_NEUTRO = { dia: 1, mes: 1, anio: 1900 };

    function campoOculto(ft, campo) {
        return !!(ft && ft.camposOcultos && ft.camposOcultos.includes(campo));
    }

    function applyCamposOcultos(ft) {
        Object.keys(CAMPOS_OCULTABLES_GROUPS).forEach(campo => {
            const oculto = campoOculto(ft, campo);
            const groupIds = CAMPOS_OCULTABLES_GROUPS[campo];
            if (groupIds) {
                groupIds.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.style.display = oculto ? 'none' : '';
                });
            } else if (campo === 'nacimiento') {
                document.querySelectorAll('.f-campo-nacimiento').forEach(el => { el.style.display = oculto ? 'none' : ''; });
            }
            // Un input oculto no puede seguir siendo `required` a nivel
            // HTML, o el navegador bloquea el submit con un campo
            // invisible marcado obligatorio (distinto del formulario
            // público, donde estos inputs nunca tuvieron `required` nativo).
            (CAMPOS_OCULTABLES_REQUIRED_IDS[campo] || []).forEach(id => {
                const el = document.getElementById(id);
                if (el) el.required = !oculto;
            });
        });
    }

    function renderEquipoDelivery(ft) {
        document.getElementById('equipoSection').style.display = (ft && ft.hasTeam) ? '' : 'none';
        document.getElementById('deliverySection').style.display = (ft && ft.hasDelivery) ? '' : 'none';
        document.getElementById('donationSection').style.display = (ft && ft.hasDonation) ? '' : 'none';
        document.getElementById('promoSection').style.display = (ft && ft.hasPromoCode) ? '' : 'none';
    }

    // Resumen de cobro (20/08/2026) — oculta filas que no aplican a este
    // tipo de inscripción/evento (no solo cuando el monto da 0), mismo
    // criterio que ya deciden renderSouvenirs()/renderEquipoDelivery()/
    // renderTalleresSelector() para sus propias secciones — acá solo se
    // refleja esa misma decisión en el resumen.
    function updateResumenVisibility(ft) {
        const show = (id, visible) => { document.getElementById(id).style.display = visible ? '' : 'none'; };
        show('r_talleres_row', getEventTalleres().length > 0);
        show('r_souvenirs_row', !!(ft && ft.souvenirs && ft.souvenirs.length > 0));
        show('r_descuento_row', !!(ft && ft.hasPromoCode));
        show('r_donacion_row', !!(ft && ft.hasDonation));
    }

    // Color por tipo de inscripción (20/08/2026) — reusa form_types.color.
    // Blanco/negro simple según luminancia para que el texto del badge se
    // lea bien sobre cualquier color configurado (los colores de gafetes
    // van desde pasteles hasta oscuros, no se puede asumir uno solo).
    function textColorFor(bgColor) {
        const hex = (bgColor || '').replace('#', '');
        if (hex.length !== 6) return '#fff';
        const r = parseInt(hex.substr(0, 2), 16), g = parseInt(hex.substr(2, 2), 16), b = parseInt(hex.substr(4, 2), 16);
        const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
        return luminance > 0.6 ? '#1e293b' : '#fff';
    }

    // La categoría es más específica que el tipo de formulario (ej. "5K"
    // vs "10K" dentro de un mismo tipo "Individual") — si el tipo pide
    // categoría y hay una elegida, esa manda; si no, cae al color/nombre
    // del tipo de formulario. `categories` ya trae `color` propio
    // (CategoryResource), mismo campo que gafetes, reusado acá también.
    function currentDisplayInfo(ft) {
        if (ft && ft.requiereCategoria) {
            const catId = document.getElementById('categoria')?.value;
            const cat = catId ? (EVENTO.categories || []).find(c => String(c.id) === String(catId)) : null;
            if (cat) return { name: cat.name, color: cat.color || ft.color || '' };
        }
        return ft ? { name: ft.name, color: ft.color || '' } : null;
    }

    function applyFormTypeColor(ft) {
        const info = currentDisplayInfo(ft);
        const color = info ? info.color : '';
        document.querySelectorAll('.caja-section').forEach(el => {
            el.style.borderTop = color ? `4px solid ${color}` : '';
        });
        const badge = document.getElementById('formTypeBadge');
        if (!badge) return;
        if (info) {
            badge.style.display = '';
            badge.style.background = color || '#64748b';
            badge.style.color = textColorFor(color);
            badge.textContent = info.name;
        } else {
            badge.style.display = 'none';
        }
    }

    // Duplicado por documento (20/08/2026) — el cajero necesita saber
    // ANTES de terminar de cargar todo el formulario si esta persona ya
    // está inscrita en este evento (el backend igual lo rechaza al
    // confirmar — CrearInscripcionAction::validateParticipantRegistration()
    // — pero recién ahí, después de cargar categoría/talleres/souvenirs/
    // contacto de emergencia; avisar temprano ahorra ese trabajo repetido).
    // Filtra por coincidencia EXACTA de numeroDocumento — /caja/buscar usa
    // LIKE %q%, que también matchea substrings de nombre/referencia.
    let docCheckTimer = null;
    function onNumeroDocumentoInput() {
        if (!BUSCAR_URL) return; // modo 'editar' — no aplica
        clearTimeout(docCheckTimer);
        const doc = document.getElementById('f_numeroDocumento').value.trim();
        const warn = document.getElementById('documentoWarning');
        if (!warn) return;
        if (doc.length < 4) { warn.style.display = 'none'; return; }
        docCheckTimer = setTimeout(() => checkDocumentoDuplicado(doc), 400);
    }

    async function checkDocumentoDuplicado(doc) {
        const warn = document.getElementById('documentoWarning');
        const personaBanner = document.getElementById('personaFoundBanner');
        if (!warn) return;
        if (personaBanner) personaBanner.style.display = 'none';
        try {
            const resp = await fetch(BUSCAR_URL + '?q=' + encodeURIComponent(doc));
            const data = await resp.json();
            // Puede haber quedado obsoleto si el cajero siguió tipeando
            // mientras la búsqueda estaba en vuelo.
            if (document.getElementById('f_numeroDocumento').value.trim() !== doc) return;

            const match = (data.data || []).find(r =>
                (r.participantes || []).some(p => (p.numeroDocumento || '').trim() === doc)
            );
            if (match) {
                const estado = match.pago_status === 'paid' ? 'pagada' : (match.pago_status === 'pending' ? 'pendiente de pago' : match.pago_status);
                const editarUrl = EDITAR_URL_BASE.replace('__REF__', match.referencia);
                const eticketUrl = ETICKET_URL_BASE.replace('__REF__', match.referencia);
                warn.innerHTML = `⚠ Este documento ya está registrado en este evento — <strong>${match.referencia}</strong> (${estado}). ` +
                    `<a href="${editarUrl}" class="underline">Editar esa inscripción</a> · ` +
                    `<a href="${eticketUrl}" target="_blank" class="underline">Ver comprobante</a>`;
                warn.style.display = '';
                return; // ya está en este evento — no hace falta buscar en `personas`
            }
            warn.style.display = 'none';
            checkPersonaConocida(doc);
        } catch (e) {
            // Silencioso — un fallo de red acá no debe bloquear la carga,
            // el chequeo real y definitivo sigue siendo el del servidor
            // al confirmar.
        }
    }

    // Prellenado desde `personas` (20/08/2026) — solo corre si el
    // documento NO está duplicado en este evento (ver arriba). No
    // autocompleta solo: muestra un aviso con un botón, la cajera decide
    // — así no pisa datos que ya haya empezado a tipear a mano.
    async function checkPersonaConocida(doc) {
        const banner = document.getElementById('personaFoundBanner');
        if (!banner || !PERSONA_URL) return;
        try {
            const resp = await fetch(PERSONA_URL + '?numero_documento=' + encodeURIComponent(doc));
            const data = await resp.json();
            if (document.getElementById('f_numeroDocumento').value.trim() !== doc) return;
            if (!data.success || !data.data) { banner.style.display = 'none'; return; }

            const persona = data.data;
            banner.innerHTML = `👤 Encontramos a <strong>${persona.nombre} ${persona.apellido}</strong> — se inscribió antes a otro evento. ` +
                `<button type="button" id="btnAutocompletarPersona" class="underline font-semibold">Autocompletar sus datos</button>`;
            banner.style.display = '';
            document.getElementById('btnAutocompletarPersona').addEventListener('click', () => autocompletarDesdePersona(persona));
        } catch (e) {
            // Silencioso — es solo un ahorro de tipeo, no bloquea nada.
        }
    }

    function autocompletarDesdePersona(persona) {
        document.getElementById('f_nombre').value = persona.nombre || '';
        document.getElementById('f_apellido').value = persona.apellido || '';
        document.getElementById('f_alias').value = persona.alias || '';
        if (persona.genero) document.getElementById('f_genero').value = persona.genero;
        if (persona.tipoDocumento) document.getElementById('f_tipoDocumento').value = persona.tipoDocumento;
        document.getElementById('f_correo').value = persona.correo || '';
        document.getElementById('f_direccion').value = persona.direccion || '';
        document.getElementById('f_ciudad').value = persona.ciudad || '';
        document.getElementById('f_telefono').value = persona.telefono || '';
        if (persona.nacimiento) {
            document.getElementById('f_nacDia').value = persona.nacimiento.dia || '';
            document.getElementById('f_nacMes').value = persona.nacimiento.mes || '';
            document.getElementById('f_nacAnio').value = persona.nacimiento.anio || '';
        }
        if (persona.contacto_emergencia) {
            document.getElementById('f_ceNombre').value = persona.contacto_emergencia.nombre || '';
            document.getElementById('f_ceCelular').value = persona.contacto_emergencia.celular || '';
            document.getElementById('f_ceRelacion').value = persona.contacto_emergencia.relacion || '';
        }
        // Por si el tipo es congreso: el alias recién cargado puede
        // matchear un título del selector (ver toggleAliasTituloMode()).
        syncAliasTituloUI();
        document.getElementById('personaFoundBanner').style.display = 'none';
        calcular();
    }

    document.getElementById('f_numeroDocumento')?.addEventListener('input', onNumeroDocumentoInput);

    // Caja para eventos tipo congreso (20/08/2026) — oculta los 3 campos
    // de contacto de emergencia cuando el tipo de inscripción no los pide
    // (form_types.requiereContactoEmergencia=false). Default true si el
    // form_type no trae el campo (compatibilidad con datos viejos).
    // El `required` se saca a mano en vez de confiar en que un campo
    // oculto (display:none) quede afuera de la validación del navegador
    // — no es algo garantizado entre navegadores, mejor explícito.
    function renderContactoEmergencia(ft) {
        const requiere = !ft || ft.requiereContactoEmergencia !== false;
        document.getElementById('emergencySection').style.display = requiere ? '' : 'none';
        ['f_ceNombre', 'f_ceCelular', 'f_ceRelacion'].forEach(id => {
            document.getElementById(id).required = requiere;
        });
    }

    // ────────────────────────────────────────────────────────────
    // Título de congreso (20/08/2026) — mismo mecanismo que
    // elascenso/event (toggleAliasTituloMode() en index.php): reusa el
    // campo `alias` existente sin agregar columna nueva. Para form_types
    // tipo 'congreso' el campo "Alias" se muestra como selector de título.
    // ────────────────────────────────────────────────────────────
    function toggleAliasTituloMode(ft) {
        const isCongreso = !!(ft && ft.tipo === 'congreso');
        // Campos ocultables por form_type (13/10/2026) gana siempre, incluso
        // en congresos — bug real encontrado (15/10/2026): con 'alias' en
        // camposOcultos, applyCamposOcultos() ya ocultó #f_aliasGroup (el
        // contenedor de este select), pero esta función igual seguía
        // marcando `select.required = true` para cualquier tipo congreso,
        // sin mirar camposOcultos — un campo required oculto bloquea el
        // submit en el navegador sin ningún mensaje visible (no es
        // "ignorado" como decía el comentario viejo de acá — mismo motivo
        // por el que renderContactoEmergencia() ya lo saca a mano). Nunca
        // puede quedar required un campo que el organizador marcó oculto.
        const oculto = campoOculto(ft, 'alias');
        const label = document.getElementById('f_aliasLabel');
        const input = document.getElementById('f_alias');
        const select = document.getElementById('f_aliasTitulo');
        const otro = document.getElementById('f_aliasTituloOtro');

        label.textContent = isCongreso ? 'Título *' : 'Alias';
        input.style.display = isCongreso ? 'none' : '';
        select.style.display = isCongreso ? '' : 'none';

        // Título obligatorio en congresos (03/09/2026, mismo pedido que en
        // elascenso/event/index.php::validateForm()), salvo que el
        // organizador haya marcado 'alias' como campo oculto para este
        // form_type (13/10/2026) — ver comentario arriba.
        select.required = isCongreso && !oculto;

        if (isCongreso) {
            syncAliasTituloUI();
        } else {
            otro.style.display = 'none';
            otro.required = false;
        }
    }

    // Refleja el valor actual de #f_alias en el <select> de título (y en
    // el input "Otro" si no matchea ninguna opción) — necesario al
    // prellenar una inscripción existente para editar, porque cargar el
    // value del input no dispara el evento change del select.
    function syncAliasTituloUI() {
        const select = document.getElementById('f_aliasTitulo');
        const otro = document.getElementById('f_aliasTituloOtro');
        const current = (document.getElementById('f_alias').value || '').trim();
        const opciones = [...select.options].map(o => o.value).filter(v => v && v !== 'Otro');

        if (opciones.includes(current)) {
            select.value = current;
            otro.style.display = 'none';
            otro.required = false;
            otro.value = '';
        } else if (current) {
            select.value = 'Otro';
            otro.value = current;
            otro.style.display = '';
            otro.required = select.required; // solo exige si el título ya es obligatorio en este modo
        } else {
            select.value = '';
            otro.style.display = 'none';
            otro.required = false;
            otro.value = '';
        }
    }

    function onAliasTituloChange() {
        const sel = document.getElementById('f_aliasTitulo').value;
        const otro = document.getElementById('f_aliasTituloOtro');
        if (sel === 'Otro') {
            otro.style.display = '';
            otro.required = true;
            otro.focus();
            document.getElementById('f_alias').value = otro.value.trim();
        } else {
            otro.style.display = 'none';
            otro.required = false;
            otro.value = '';
            document.getElementById('f_alias').value = sel;
        }
    }

    function onAliasTituloOtroInput() {
        document.getElementById('f_alias').value = document.getElementById('f_aliasTituloOtro').value.trim();
    }

    document.getElementById('f_aliasTitulo').addEventListener('change', onAliasTituloChange);
    document.getElementById('f_aliasTituloOtro').addEventListener('input', onAliasTituloOtroInput);

    // ────────────────────────────────────────────────────────────
    // Talleres / sesiones de congreso (20/08/2026) — mismo criterio que
    // registration.js de elascenso/event: obligatorios primero, cupo con
    // urgencia, conflicto de horario solo como aviso (el bloqueo real es
    // servidor, ver ValidarSeleccionesTallerAction en ApiRestEvent).
    // ────────────────────────────────────────────────────────────
    function getEventTalleres() {
        // Staff/ponente/expositor nunca se inscriben como asistentes a
        // talleres (13/10/2026, mismo criterio que elascenso/event/index.php
        // — ver comentario ahí) — sin esto, Caja dejaba elegir talleres
        // para estos form_types y el alta se rechazaba con 422 del lado
        // de ApiRestEvent (FormType::sinTalleres()), un callejón sin salida.
        const ft = formTypeActual();
        if (ft && (ft.esExpositor || ft.esStaff || ft.esPonente)) return [];
        return (EVENTO.talleres || []).filter(t => t.activo !== false);
    }

    function formatCupoAvailability(cupo, ocupados) {
        if (cupo === null || cupo === undefined) return { text: 'Sin límite', level: 'unlimited' };
        const disponibles = Math.max(0, cupo - (ocupados || 0));
        if (disponibles <= 0) return { text: 'AGOTADO', level: 'soldout' };
        if (disponibles <= TALLER_CUPO_CRITICAL_THRESHOLD) return { text: `Últimos ${disponibles} cupos`, level: 'critical' };
        if (disponibles <= TALLER_CUPO_LOW_THRESHOLD) return { text: `Quedan ${disponibles} cupos`, level: 'low' };
        return { text: `${disponibles} disponibles`, level: 'plenty' };
    }

    function tallerSesionPrefillIds() {
        return new Set(PREFILL_TALLERES.map(x => Number(x.sesionCongresoId)));
    }

    /**
     * Combos de talleres por categoría (13/10/2026, bug real confirmado:
     * "Taller 1 + Taller 2" tenía un combo cargado a Bs1.00 para la
     * categoría del participante, pero Caja cobraba Bs2.00 — el "Resumen
     * de cobro" nunca implementó combos, a diferencia del formulario
     * público (ver resolverCombosTallerJS en elascenso/event/index.php,
     * réplica EXACTA de este mismo algoritmo). El backend
     * (CrearInscripcionAction/RegistrationService) siempre recalculó el
     * precio real con combo aplicado — el número que mostraba y cobraba
     * Caja simplemente no coincidía con lo que quedaba persistido.
     * ApiRestEvent sigue siendo la única autoridad real, esto es preview.
     */
    function precioComboParaCategoriaJS(combo, categoriaId) {
        const precios = combo.precios || [];
        let defaultPrecio = null;
        for (const p of precios) {
            if (p.categoriaId === null) { defaultPrecio = Number(p.precio); continue; }
            if (categoriaId !== null && categoriaId !== undefined && String(p.categoriaId) === String(categoriaId)) {
                return Number(p.precio);
            }
        }
        return defaultPrecio;
    }

    function resolverCombosTallerJS(selecciones, categoriaId) {
        const combos = (EVENTO.tallerCombos || [])
            .filter(c => c.vigente)
            .map(c => ({ ...c, _precioResuelto: precioComboParaCategoriaJS(c, categoriaId) }))
            .filter(c => c._precioResuelto !== null);
        if (!combos.length || !selecciones.length) return selecciones;

        const indicePorTaller = {};
        selecciones.forEach((s, i) => {
            const tid = s.taller_id;
            if (!(tid in indicePorTaller) || s.unit_price < selecciones[indicePorTaller[tid]].unit_price) {
                indicePorTaller[tid] = i;
            }
        });

        const candidatos = [];
        combos.forEach(combo => {
            const tallerIds = (combo.tallerIds || []).slice().sort((a, b) => a - b);
            if (!tallerIds.length || !tallerIds.every(tid => tid in indicePorTaller)) return;
            const suma = tallerIds.reduce((a, tid) => a + selecciones[indicePorTaller[tid]].unit_price, 0);
            candidatos.push({ combo, tallerIds, suma, ahorro: suma - combo._precioResuelto });
        });
        candidatos.sort((a, b) => (b.ahorro - a.ahorro) || (a.combo.id - b.combo.id));

        const consumidos = new Set();
        candidatos.forEach(c => {
            if (c.tallerIds.some(tid => consumidos.has(tid))) return;

            const descuentoTotal = Math.max(0, Math.round(c.ahorro * 100) / 100);
            const indices = c.tallerIds.map(tid => indicePorTaller[tid]);
            let acumulado = 0;
            indices.forEach((idx, pos) => {
                const unit = selecciones[idx].unit_price;
                let discount;
                if (pos === indices.length - 1) discount = Math.round((descuentoTotal - acumulado) * 100) / 100;
                else if (c.suma > 0) discount = Math.round((descuentoTotal * unit / c.suma) * 100) / 100;
                else discount = 0;
                acumulado += discount;

                selecciones[idx].discount = discount;
                selecciones[idx].total = Math.round((unit - discount) * 100) / 100;
                selecciones[idx].taller_combo_id = c.combo.id;
            });
            c.tallerIds.forEach(tid => consumidos.add(tid));
        });

        return selecciones;
    }

    function collectSelectedTalleres() {
        const talleres = getEventTalleres();
        const porId = Object.fromEntries(talleres.map(t => [String(t.id), t]));
        const out = [];
        document.querySelectorAll('.taller-sesion-cb:checked').forEach(cb => {
            const sesionId = Number(cb.dataset.sesionId);
            const tallerId = Number(cb.dataset.tallerId);
            const taller = porId[String(tallerId)];
            const sesion = (taller?.sesiones || []).find(s => Number(s.id) === sesionId);
            if (!taller || !sesion) return;

            let unitPrice = 0;
            if (TALLERES_CON_COSTO) {
                unitPrice = (sesion.precio !== null && sesion.precio !== undefined) ? Number(sesion.precio)
                    : (taller.precio !== null && taller.precio !== undefined) ? Number(taller.precio) : 0;
            }

            out.push({
                taller_id: tallerId,
                sesion_congreso_id: sesionId,
                fecha: sesion.fecha,
                hora_inicio: (sesion.horaInicio || '').slice(0, 5),
                hora_fin: (sesion.horaFin || '').slice(0, 5),
                unit_price: unitPrice,
                discount: 0,
                total: unitPrice,
                taller_combo_id: null,
            });
        });
        const categoriaIdActual = document.getElementById('categoria')?.value || null;
        return resolverCombosTallerJS(out, categoriaIdActual);
    }

    function timeToMinutes(t) {
        if (!t) return 0;
        const [h, m] = t.split(':').map(Number);
        return h * 60 + (m || 0);
    }

    function detectTallerConflicts() {
        const seleccionadas = collectSelectedTalleres();
        const porFecha = {};
        seleccionadas.forEach(s => { if (s.fecha) (porFecha[s.fecha] = porFecha[s.fecha] || []).push(s); });

        const conflictos = new Set();
        Object.values(porFecha).forEach(lista => {
            for (let i = 0; i < lista.length; i++) {
                for (let j = i + 1; j < lista.length; j++) {
                    const a = lista[i], b = lista[j];
                    if (timeToMinutes(a.hora_inicio) < timeToMinutes(b.hora_fin) && timeToMinutes(a.hora_fin) > timeToMinutes(b.hora_inicio)) {
                        conflictos.add(a.sesion_congreso_id);
                        conflictos.add(b.sesion_congreso_id);
                    }
                }
            }
        });
        return conflictos;
    }

    function allRequiredTalleresSelected() {
        const requeridos = getEventTalleres().filter(t => t.modalidad === 'REQUIRED');
        if (requeridos.length === 0) return true;
        const seleccionadas = new Set(Array.from(document.querySelectorAll('.taller-sesion-cb:checked')).map(cb => Number(cb.dataset.sesionId)));
        return requeridos.every(t => (t.sesiones || []).some(s => seleccionadas.has(Number(s.id))));
    }

    function updateTallerConflictsUI() {
        const conflictos = detectTallerConflicts();
        document.querySelectorAll('[data-conflict-for]').forEach(el => {
            el.style.display = conflictos.has(Number(el.dataset.conflictFor)) ? '' : 'none';
        });
        const warn = document.getElementById('talleresRequiredWarning');
        if (warn) warn.style.display = allRequiredTalleresSelected() ? 'none' : '';
        renderTallerCambioResumen();
        calcular();
    }

    /**
     * Quitar/cambiar un taller ya pagado (29/09/2026) — lista, en texto
     * simple, qué taller se quita (con su precio ya pagado, de
     * PREFILL_TALLERES) y qué taller se agrega (con el precio real de la
     * sesión), para que la cajera vea el cambio antes de confirmar — no
     * solo un total agregado. También muestra/exige el campo de motivo. No
     * calcula nada que ya no calcule calcular(); es puramente informativo.
     */
    function tallerCambio() {
        if (!ES_EDICION_PAGADA) return { removidos: [], agregados: [] };
        const seleccionadasIds = new Set(collectSelectedTalleres().map(s => Number(s.sesion_congreso_id)));
        const removidos = PREFILL_TALLERES.filter(t => !seleccionadasIds.has(Number(t.sesionCongresoId)));
        const agregados = collectSelectedTalleres().filter(s => !TALLERES_ANTERIORES_IDS.has(Number(s.sesion_congreso_id)));
        return { removidos, agregados };
    }

    function renderTallerCambioResumen() {
        const resumen = document.getElementById('tallerCambioResumen');
        const motivoGroup = document.getElementById('tallerMotivoGroup');
        const motivoInput = document.getElementById('f_motivo');
        if (!resumen || !motivoGroup) return;

        const { removidos, agregados } = tallerCambio();
        if (removidos.length === 0) {
            resumen.style.display = 'none';
            motivoGroup.style.display = 'none';
            return;
        }

        const talleresPorSesion = {};
        getEventTalleres().forEach(t => (t.sesiones || []).forEach(s => { talleresPorSesion[Number(s.id)] = { taller: t, sesion: s }; }));

        const lineas = [];
        removidos.forEach(t => lineas.push(`− Se quita: ${t.tallerNombre || ('Taller #' + t.tallerId)} (Bs ${Number(t.total || 0).toFixed(2)})`));
        agregados.forEach(a => {
            const nombre = talleresPorSesion[Number(a.sesion_congreso_id)]?.taller?.nombre || ('Taller #' + a.taller_id);
            lineas.push(`+ Se agrega: ${nombre} (Bs ${Number(a.total ?? a.unit_price ?? 0).toFixed(2)})`);
        });
        const removidoTotal = removidos.reduce((sum, t) => sum + Number(t.total || 0), 0);
        // Combos de talleres (13/10/2026) — mismo criterio que
        // talleresAgregadoTotal en calcular(): sumar `total` post-combo.
        const agregadoTotal = agregados.reduce((sum, a) => sum + Number(a.total ?? a.unit_price ?? 0), 0);
        const diferencia = Math.round((agregadoTotal - removidoTotal) * 100) / 100;
        lineas.push(`<strong>Diferencia por talleres: Bs ${diferencia.toFixed(2)} ${diferencia < 0 ? '(devolución)' : diferencia > 0 ? '(a cobrar)' : ''}</strong>`);

        resumen.innerHTML = lineas.join('<br>');
        resumen.style.display = '';
        motivoGroup.style.display = '';
        if (motivoInput) motivoInput.required = true;
    }

    function renderTalleresSelector(ft) {
        const section = document.getElementById('talleresSection');
        const grid = document.getElementById('talleresGrid');
        const talleres = getEventTalleres();
        if (!ft || talleres.length === 0) { section.style.display = 'none'; grid.innerHTML = ''; return; }

        const ordenados = [...talleres].sort((a, b) => {
            if (a.modalidad === b.modalidad) return (a.orden || 0) - (b.orden || 0);
            return a.modalidad === 'REQUIRED' ? -1 : 1;
        });

        grid.innerHTML = ordenados.map(taller => {
            const sesiones = taller.sesiones || [];
            if (!sesiones.length) return '';
            const filas = sesiones.map(s => {
                const full = s.cupo !== null && s.disponibles !== null && s.disponibles <= 0;
                const cupoInfo = formatCupoAvailability(s.cupo, s.ocupados);
                const precioTxt = TALLERES_CON_COSTO
                    ? ((s.precio ?? taller.precio) !== null && (s.precio ?? taller.precio) !== undefined
                        ? ` — Bs ${Number(s.precio ?? taller.precio).toFixed(2)}` : '')
                    : '';
                return `
                    <label class="flex items-start gap-2 text-xs py-1 ${full ? 'opacity-50' : ''}">
                        <input type="checkbox" class="taller-sesion-cb mt-0.5" data-sesion-id="${s.id}" data-taller-id="${taller.id}" ${full ? 'disabled' : ''}>
                        <span>
                            ${s.titulo ? `<strong class="block text-slate-700">${s.titulo}</strong>` : ''}
                            ${s.horaInicio || ''}–${s.horaFin || ''} · ${s.sala || ''} ·
                            <span class="cupo-${cupoInfo.level}">${cupoInfo.text}</span>${precioTxt}
                            <span class="text-red-600" data-conflict-for="${s.id}" style="display:none"> ⚠ conflicto de horario</span>
                        </span>
                    </label>`;
            }).join('');

            return `
                <div class="border border-slate-200 rounded p-2">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-[10px] font-semibold uppercase px-1.5 py-0.5 rounded ${taller.modalidad === 'REQUIRED' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600'}">${taller.modalidad === 'REQUIRED' ? 'Obligatorio' : 'Opcional'}</span>
                        <strong class="text-sm">${taller.nombre}</strong>
                    </div>
                    ${filas}
                </div>`;
        }).join('');
        section.style.display = '';

        const prefillIds = tallerSesionPrefillIds();
        document.querySelectorAll('.taller-sesion-cb').forEach(cb => {
            if (prefillIds.has(Number(cb.dataset.sesionId))) cb.checked = true;
            cb.addEventListener('change', updateTallerConflictsUI);
        });
        updateTallerConflictsUI();
    }

    function souvenirPrefillFor(id) {
        return PREFILL_SOUVENIRS.find(s => String(s.id) === String(id)) || null;
    }

    function renderSouvenirs(ft) {
        const section = document.getElementById('souvenirsSection');
        const list = document.getElementById('souvenirsList');
        const souvenirs = (ft && ft.souvenirs) || [];
        if (souvenirs.length === 0) { section.style.display = 'none'; list.innerHTML = ''; return; }
        section.style.display = '';
        list.innerHTML = souvenirs.map(sv => {
            const pre = souvenirPrefillFor(sv.id);
            const checked = pre ? 'checked' : '';
            let variantHtml = '';
            if (sv.requiere_talla || sv.requiere_sexo) {
                const tallaOpts = TALLAS.map(t => `<option value="${t}" ${pre && pre.talla === t ? 'selected' : ''}>${t}</option>`).join('');
                const sexoOpts = SEXOS.map(s => `<option value="${s}" ${pre && pre.sexo === s ? 'selected' : ''}>${s}</option>`).join('');
                variantHtml = `<span class="ml-3 inline-flex gap-2">` +
                    (sv.requiere_talla ? `<select class="sv-talla border border-slate-300 rounded text-xs px-1 py-0.5" data-sv="${sv.id}"><option value="">Talla</option>${tallaOpts}</select>` : '') +
                    (sv.requiere_sexo ? `<select class="sv-sexo border border-slate-300 rounded text-xs px-1 py-0.5" data-sv="${sv.id}"><option value="">Sexo</option>${sexoOpts}</select>` : '') +
                    `</span>`;
            }
            return `<label class="flex items-center text-sm">
                <input type="checkbox" class="sv-check mr-2" data-sv="${sv.id}" data-precio="${sv.price}" data-incluido="${sv.incluido ? 1 : 0}" data-nombre="${sv.name}" data-aplica-cargo="${sv.aplica_cargo_servicio ? 1 : 0}" ${checked}>
                ${sv.name} — ${sv.incluido ? 'incluido' : Number(sv.price).toFixed(2)}
                ${variantHtml}
            </label>`;
        }).join('');
    }

    function souvenirsSeleccionados() {
        return Array.from(document.querySelectorAll('.sv-check:checked')).map(chk => {
            const id = chk.dataset.sv;
            const talla = document.querySelector(`.sv-talla[data-sv="${id}"]`);
            const sexo = document.querySelector(`.sv-sexo[data-sv="${id}"]`);
            return {
                id: Number(id),
                nombre: chk.dataset.nombre,
                precio: chk.dataset.incluido === '1' ? 0 : Number(chk.dataset.precio),
                talla: talla ? (talla.value || null) : null,
                sexo: sexo ? (sexo.value || null) : null,
                // Cargo de servicio por souvenir individual (01/09/2026) —
                // mismo criterio que CrearInscripcionAction::validateFeePct()/
                // EdicionPagadaSouvenirsData del lado servidor.
                aplicaCargoServicio: chk.dataset.aplicaCargo === '1',
            };
        });
    }

    function promoAplicable() {
        const codigo = (document.getElementById('f_promoCodigo')?.value || '').trim();
        const msg = document.getElementById('promoMsg');
        if (!codigo) { if (msg) msg.textContent = ''; return null; }
        const promo = (EVENTO.promoCodes || []).find(p => p.promo_code === codigo);
        if (!promo) { if (msg) { msg.textContent = 'Código no encontrado.'; msg.className = 'text-xs mt-1 text-red-600'; } return null; }
        if (promo.usado) { if (msg) { msg.textContent = 'Este código ya fue usado.'; msg.className = 'text-xs mt-1 text-red-600'; } return null; }
        if (msg) { msg.textContent = 'Código válido.'; msg.className = 'text-xs mt-1 text-green-600'; }
        return promo;
    }

    function calcular() {
        const ft = formTypeActual();
        let inscripcion = 0;
        let inscripcionUsd = 0;
        // Precio ABSOLUTO de categoría (11/10/2026, bug real confirmado:
        // LA-2B6689CA) — `inscripcion` más abajo se convierte en un DELTA
        // en edición pagada, y nunca debe usarse para persistir
        // participante.precioCategoria/subtotal. Sin cambio de categoría,
        // el precio absoluto real sigue siendo PRECIO_ANTERIOR (lo que
        // esta persona pagó de verdad, en el período que corría cuando se
        // inscribió) — NO el precio vigente de hoy, que puede ser otro
        // período. Mandar el delta (0) ahí hacía que
        // SnapshotInscripcionPagadaData::recalcular() (ApiRestEvent), al
        // no reconocer 0 como un precio real, cayera al precio vigente de
        // HOY como mejor estimación — silenciosamente subía/bajaba el
        // precio de categoría de alguien cuya categoría nunca se tocó,
        // solo porque Caja editó un taller.
        let inscripcionAbsoluta = 0;
        if (ft) {
            if (ft.requiereCategoria) {
                const opt = document.getElementById('categoria')?.selectedOptions?.[0];
                inscripcion = opt ? Number(opt.dataset.precio || 0) : 0;
                inscripcionAbsoluta = inscripcion;
                inscripcionUsd = (opt && opt.dataset.precioUsd !== '') ? Number(opt.dataset.precioUsd) : 0;

                // Edición de inscripción pagada (17/09/2026) — "Inscripción"
                // acá debe ser el DELTA a cobrar, no el precio completo:
                // sin cambio de categoría, 0 (mismo criterio que
                // EdicionPagadaCategoriaData::resolver() en el backend, que
                // ni siquiera mira el precio vigente si la categoría no
                // cambió). Con cambio, la diferencia real contra lo que ya
                // se pagó — puede dar negativo (Caja permite bajar de
                // categoría). Si no hay PRECIO_ANTERIOR (dato faltante),
                // cae al comportamiento de siempre (precio completo) en vez
                // de arriesgar un cálculo con datos incompletos.
                if (ES_EDICION_PAGADA && PRECIO_ANTERIOR !== null) {
                    const categoriaId = document.getElementById('categoria')?.value || null;
                    if (categoriaId === String(CATEGORIA_ANTERIOR)) {
                        inscripcion = 0;
                        inscripcionAbsoluta = Number(PRECIO_ANTERIOR);
                    } else {
                        inscripcion = Math.round((inscripcionAbsoluta - Number(PRECIO_ANTERIOR)) * 100) / 100;
                        // inscripcionAbsoluta ya queda en el precio vigente
                        // de la categoría NUEVA (correcto: sí cambió de
                        // verdad, el precio real de hoy es el que aplica).
                    }
                }
            } else {
                inscripcion = Number(ft.precio_base || 0);
                inscripcionAbsoluta = inscripcion;
            }
        }

        const souvenirsTotal = souvenirsSeleccionados().reduce((sum, s) => sum + s.precio, 0);
        // Cargo de servicio por souvenir individual (01/09/2026) — bug real
        // (02/10/2026): esta base nunca sumaba el souvenir con
        // aplica_cargo_servicio=true al fee, a diferencia del servidor
        // (CrearInscripcionAction::validateFeePct()/EdicionPagadaSouvenirsData),
        // que sí lo hace siempre — con un souvenir con cargo seleccionado, el
        // fee estimado acá quedaba más bajo que el real y
        // validateFeePct() rechazaba el alta con "El cargo de servicio no
        // coincide con el vigente para este evento".
        const souvenirsConCargo = souvenirsSeleccionados()
            .filter(s => s.aplicaCargoServicio)
            .reduce((sum, s) => sum + s.precio, 0);
        // Talleres de congreso (20/08/2026) — mismo criterio que
        // CrearInscripcionAction::validateFeePct(): el fee se calcula
        // sobre inscripción + talleres salvo que el evento tenga
        // feeIncluyeTalleres=false.
        // Edición de inscripción pagada (17/09/2026) — solo cuenta el
        // delta (talleres NUEVOS); los ya existentes (TALLERES_ANTERIORES_IDS)
        // no se vuelven a sumar, ya se cobraron o siguen figurando como
        // pendientes de una edición previa sin tocar acá.
        const talleresSeleccionados = collectSelectedTalleres();
        const talleresParaTotal = ES_EDICION_PAGADA
            ? talleresSeleccionados.filter(s => !TALLERES_ANTERIORES_IDS.has(Number(s.sesion_congreso_id)))
            : talleresSeleccionados;
        // Combos de talleres (13/10/2026) — sumar `total` (post-descuento
        // de combo, resuelto por resolverCombosTallerJS() en
        // collectSelectedTalleres()), no `unit_price` crudo.
        const talleresAgregadoTotal = talleresParaTotal.reduce((sum, s) => sum + Number(s.total ?? s.unit_price ?? 0), 0);
        // Quitar/cambiar un taller ya pagado (29/09/2026, solo Caja) — mismo
        // criterio que el backend (ActualizarInscripcionPagadaAction): resta
        // el precio YA PAGADO (persistido en PREFILL_TALLERES, no
        // recalculado) de cualquier taller anterior que se haya destildado.
        // Puede quedar negativo (devolución) — solo una estimación en vivo,
        // el monto real siempre lo recalcula el servidor.
        const talleresRemovidoTotal = ES_EDICION_PAGADA ? tallerCambio().removidos.reduce((sum, t) => sum + Number(t.total || 0), 0) : 0;
        const talleresTotal = Math.round((talleresAgregadoTotal - talleresRemovidoTotal) * 100) / 100;
        const donacion = ft && ft.hasDonation ? Number(document.getElementById('f_donacion')?.value || 0) : 0;

        let descuento = 0;
        const promo = ft && ft.hasPromoCode ? promoAplicable() : null;
        if (promo) {
            descuento = promo.discount_type === 'percentage'
                ? Math.round(inscripcion * Number(promo.discount_percent || 0) * 100) / 100
                : Math.max(0, Math.round((inscripcion - Number(promo.price || 0)) * 100) / 100);
        }

        // El fee SIEMPRE se calcula sobre la inscripción completa, nunca
        // reducida por un cupón/promo — mismo criterio que
        // CrearInscripcionAction::validateFeePct() y el formulario público
        // (_registro_validacion.php: $baseFee usa $totalInscripcion crudo,
        // jamás resta $totalDescuento; el descuento solo se resta al final,
        // sobre el grand_total). Bug real (02/10/2026, mismo síntoma que el
        // souvenir con cargo): esto restaba `descuento` antes de calcular
        // el fee — con un cupón real aplicado, el fee estimado acá quedaba
        // más bajo que el que exige el servidor, y el alta se rechazaba con
        // "El cargo de servicio no coincide con el vigente para este
        // evento". El clamp a ≥0 se mantiene (no para el cupón, sino para
        // edición: ahí `inscripcion` puede venir negativa como delta de una
        // bajada de categoría — el fee nunca "devuelve" comisión ya
        // cobrada, mismo criterio que EdicionPagadaFeeData::calcular()).
        const inscripcionParaFee = Math.max(0, inscripcion);
        const talleresTotalParaFee = Math.max(0, talleresTotal);
        const baseFee = inscripcionParaFee + (FEE_INCLUYE_TALLERES ? talleresTotalParaFee : 0) + souvenirsConCargo;
        const fee = Math.round(baseFee * FEE_PCT * 100) / 100;
        // Cargo de edición (17/09/2026) — fijo, cobrado por el backend en
        // TODA edición de una inscripción pagada (ver ActualizarInscripcionPagadaAction,
        // $costoAdicion siempre incluye $costoEdicion), sin importar si
        // además hay delta de categoría/talleres/souvenirs.
        const costoEdicion = ES_EDICION_PAGADA ? Number(COSTO_EDICION || 0) : 0;
        // grand_total (Bs) es SIEMPRE el bookkeeping real de la categoría,
        // sin importar en qué moneda se cobre de verdad — ver
        // CurrencyResolverData::resolverPrecioFijo() (ApiRestEvent):
        // "sin tocar para nada el bookkeeping en BOB". Nunca se pisa con
        // el número en USD.
        //
        // Bug real (30/09/2026, 2 casos confirmados en producción) — acá
        // usaba `baseConDescuento` (clampeado a ≥0, correcto SOLO para la
        // base del fee, igual que EdicionPagadaFeeData del lado servidor)
        // para el TOTAL también — una bajada real de categoría (ej. -1100,
        // una devolución legítima) se mostraba como "Total a cobrar: 10.00"
        // en vez de "-1090.00", aunque el servidor sí cobraba/devolvía bien.
        // El total tiene que usar la diferencia real (inscripcion-descuento),
        // sin clampear — el clamp es solo para que el fee nunca "devuelva"
        // comisión ya cobrada, no para esconder una devolución real.
        const total = Math.round(((inscripcion - descuento) + souvenirsTotal + talleresTotal + donacion + fee + costoEdicion) * 100) / 100;

        // Precio USD fijo en Caja (12/09/2026) — total REAL a cobrar en
        // efectivo, en paralelo al bookkeeping de arriba. Mismo alcance
        // que el registro público hoy: solo inscripción por categoría,
        // sin souvenirs/donación/talleres en este modo (el submit más
        // abajo bloquea si hay alguno de esos, antes de llegar al 422 del
        // servidor).
        const feeUsd = Math.round(inscripcionUsd * FEE_PCT * 100) / 100;
        const totalUsd = Math.round((inscripcionUsd + feeUsd) * 100) / 100;

        // Cortesía (30/09/2026) — el servidor fuerza el total a 0 sin
        // importar lo que se elija (ver CajaController::aplicarCortesia()),
        // así que el resumen en vivo ya lo muestra en 0 para no confundir
        // al cajero con un número que después no se va a cobrar.
        const cortesiaEl = document.getElementById('mp_cortesia');
        const esCortesia = !!(cortesiaEl && cortesiaEl.checked);
        const avisoCortesia = document.getElementById('cortesiaAviso');
        if (avisoCortesia) avisoCortesia.style.display = esCortesia ? '' : 'none';

        document.getElementById('r_inscripcion').textContent = esCortesia ? '0.00' : (USD_PRECIO_FIJO ? inscripcionUsd : inscripcion).toFixed(2);
        document.getElementById('r_talleres').textContent = esCortesia ? '0.00' : talleresTotal.toFixed(2);
        document.getElementById('r_souvenirs').textContent = esCortesia ? '0.00' : souvenirsTotal.toFixed(2);
        document.getElementById('r_descuento').textContent = esCortesia ? '-0.00' : '-' + descuento.toFixed(2);
        document.getElementById('r_donacion').textContent = esCortesia ? '0.00' : donacion.toFixed(2);
        document.getElementById('r_costoEdicion_row').style.display = ES_EDICION_PAGADA ? '' : 'none';
        document.getElementById('r_costoEdicion').textContent = esCortesia ? '0.00' : costoEdicion.toFixed(2);
        document.getElementById('r_fee').textContent = esCortesia ? '0.00' : (USD_PRECIO_FIJO ? feeUsd : fee).toFixed(2);
        document.getElementById('r_total').textContent = esCortesia ? '0.00' : (USD_PRECIO_FIJO ? totalUsd : total).toFixed(2);

        return {
            inscripcion, inscripcionAbsoluta, donacion, souvenirs: souvenirsTotal, talleres: talleresTotal, fee,
            descuento, descuento_registrante: 0, grand_total: total,
            moneda_pago: USD_PRECIO_FIJO ? 'USD' : 'BOB',
            tipo_cambio_aplicado: null,
            total_pagado: USD_PRECIO_FIJO ? totalUsd : null,
        };
    }

    /**
     * Precio USD fijo en Caja (12/09/2026) — mismo alcance que el
     * registro público: solo inscripción por categoría, todavía sin
     * souvenirs/donación/talleres-con-costo. Bloquea ANTES de mandar el
     * request (el servidor igual lo rechaza, esto solo evita un 422 crudo
     * y le da al cajero un mensaje claro de qué sacar del carrito).
     */
    function errorUsdFijoCarritoInvalido() {
        if (!USD_PRECIO_FIJO) return null;
        const donacion = Number(document.getElementById('f_donacion')?.value || 0);
        if (donacion > 0) return 'Este evento cobra en USD solo la inscripción — sacá la donación antes de cobrar.';
        if (souvenirsSeleccionados().length > 0) return 'Este evento cobra en USD solo la inscripción — sacá los souvenirs antes de cobrar.';
        if (collectSelectedTalleres().length > 0) return 'Este evento cobra en USD solo la inscripción — sacá los talleres antes de cobrar.';
        return null;
    }

    function actualizarSecciones() {
        const ft = formTypeActual();
        renderCategoria(ft);
        applyCamposOcultos(ft);
        renderEquipoDelivery(ft);
        renderContactoEmergencia(ft);
        toggleAliasTituloMode(ft);
        renderSouvenirs(ft);
        renderTalleresSelector(ft);
        applyFormTypeColor(ft);
        updateResumenVisibility(ft);
        calcular();
    }

    document.getElementById('formTypesId').addEventListener('change', actualizarSecciones);
    document.addEventListener('change', (e) => {
        if (!e.target.closest('#cajaForm')) return;
        calcular();
        // Elegir categoría también actualiza el badge/acento de color —
        // formTypeActual() sigue siendo el mismo, solo cambia qué color
        // resuelve currentDisplayInfo() una vez que hay categoría elegida.
        if (e.target.id === 'categoria') applyFormTypeColor(formTypeActual());
    });
    document.addEventListener('input', (e) => {
        if (e.target.closest('#cajaForm')) calcular();
    });

    document.getElementById('cajaForm').addEventListener('submit', function (e) {
        const gate = document.getElementById('confirmarAdicional');
        if (gate && !gate.checked) {
            e.preventDefault();
            alert('Tenés que confirmar el cobro del adicional antes de guardar.');
            return;
        }

        // Quitar/cambiar un taller ya pagado (29/09/2026) — guard de UX,
        // no de seguridad real (la validación real es la de ApiRestEvent,
        // ver ActualizarInscripcionPagadaAction) — solo evita un 422 crudo
        // por olvidarse de escribir el motivo.
        if (ES_EDICION_PAGADA && tallerCambio().removidos.length > 0) {
            const motivo = (document.getElementById('f_motivo')?.value || '').trim();
            if (!motivo) {
                e.preventDefault();
                alert('Tenés que indicar un motivo para quitar un taller ya pagado.');
                return;
            }
        }

        const errorUsdFijo = errorUsdFijoCarritoInvalido();
        if (errorUsdFijo) {
            e.preventDefault();
            alert(errorUsdFijo);
            return;
        }

        const totales = calcular();
        const ft = formTypeActual();
        const categoriaSelect = document.getElementById('categoria');
        // Valores neutros para campos ocultos (13/10/2026) — mismo
        // criterio que applyCamposOcultos()/saveParticipant() en
        // elascenso/event/index.php: la BD exige apellido/género/fecha de
        // nacimiento (NOT NULL), así que un campo oculto manda un
        // sentinel neutro en vez de lo que haya quedado en el input
        // (vacío, o lo que sea que tenía antes de ocultarse).
        const participante = {
            nombre: document.getElementById('f_nombre').value,
            apellido: campoOculto(ft, 'apellido') ? APELLIDO_NEUTRO : document.getElementById('f_apellido').value,
            alias: campoOculto(ft, 'alias') ? '' : document.getElementById('f_alias').value,
            genero: campoOculto(ft, 'genero') ? GENERO_NEUTRO : document.getElementById('f_genero').value,
            tipoDocumento: document.getElementById('f_tipoDocumento').value,
            numeroDocumento: document.getElementById('f_numeroDocumento').value,
            polera: '', precioPolera: 0,
            nacimiento: campoOculto(ft, 'nacimiento') ? { ...NACIMIENTO_NEUTRO } : {
                dia: Number(document.getElementById('f_nacDia').value),
                mes: Number(document.getElementById('f_nacMes').value),
                anio: Number(document.getElementById('f_nacAnio').value),
            },
            edad: campoOculto(ft, 'nacimiento') ? 0 : Math.max(0, new Date().getFullYear() - Number(document.getElementById('f_nacAnio').value || 0)),
            correo: document.getElementById('f_correo').value,
            direccion: campoOculto(ft, 'direccion') ? '' : document.getElementById('f_direccion').value,
            ciudad: campoOculto(ft, 'ciudad') ? '' : document.getElementById('f_ciudad').value,
            telefono: campoOculto(ft, 'telefono') ? '' : document.getElementById('f_telefono').value,
            contacto_emergencia: {
                nombre: document.getElementById('f_ceNombre').value,
                celular: document.getElementById('f_ceCelular').value,
                relacion: document.getElementById('f_ceRelacion').value,
            },
            souvenirs: souvenirsSeleccionados(),
            talleres: collectSelectedTalleres(),
            answers: [],
            categoria: categoriaSelect && categoriaSelect.value ? categoriaSelect.value : '0',
            // precioCategoria/subtotal (11/10/2026, fix bug real
            // LA-2B6689CA) — precio ABSOLUTO, nunca el delta
            // (`totales.inscripcion`, que en edición pagada es 0 cuando la
            // categoría no cambió). Ver comentario de inscripcionAbsoluta
            // en calcular().
            precioCategoria: totales.inscripcionAbsoluta,
            donacion: totales.donacion,
            promoDescuento: totales.descuento,
            promoCodigo: document.getElementById('f_promoCodigo')?.value || '',
            subtotal: totales.inscripcionAbsoluta,
            equipoId: document.getElementById('f_equipoId')?.value ? Number(document.getElementById('f_equipoId').value) : null,
            quiereDelivery: !!document.getElementById('f_quiereDelivery')?.checked,
            deliveryLat: document.getElementById('f_deliveryLat')?.value ? Number(document.getElementById('f_deliveryLat').value) : null,
            deliveryLng: document.getElementById('f_deliveryLng')?.value ? Number(document.getElementById('f_deliveryLng').value) : null,
        };

        document.getElementById('participante_json').value = JSON.stringify(participante);
        document.getElementById('totales_json').value = JSON.stringify(totales);
    });

    // Facilidad de manejo por teclado (20/08/2026) — la cajera carga
    // muchos participantes seguidos; Enter en un campo de texto/número
    // avanza al siguiente campo visible en vez de enviar el formulario a
    // mitad de carga (el envío real sigue siendo el botón "Cobrar y
    // confirmar"). Los campos de una sección oculta (display:none, ej.
    // contacto de emergencia desactivado) quedan afuera solos porque
    // `offsetParent` es null mientras están ocultos — no hace falta
    // gestionar tabindex a mano.
    document.getElementById('cajaForm').addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        const el = e.target;
        if (el.tagName !== 'INPUT' || ['checkbox', 'radio', 'submit', 'button'].includes(el.type)) return;
        e.preventDefault();
        const focusables = Array.from(this.querySelectorAll('input, select, textarea, button'))
            .filter(f => !f.disabled && f.offsetParent !== null);
        const idx = focusables.indexOf(el);
        if (idx > -1 && idx < focusables.length - 1) focusables[idx + 1].focus();
    });

    actualizarSecciones();
    (document.querySelector('#formTypesId:not([type=hidden])') || document.getElementById('f_nombre'))?.focus();
})();
</script>
