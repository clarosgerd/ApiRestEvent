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
        <div class="bg-amber-50 border border-amber-300 text-amber-900 rounded-md p-4 text-sm">
            <p class="font-semibold mb-1">Esta inscripción ya está pagada.</p>
            <p>
                Guardar los cambios cobra un adicional configurado de
                <strong>{{ number_format($costoEdicion ?? 0, 2) }}</strong>.
            </p>
            <label class="inline-flex items-center gap-2 mt-2">
                <input type="checkbox" id="confirmarAdicional">
                Confirmo que voy a cobrar ese adicional
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
            <div><label class="block text-xs font-semibold mb-1">Apellido *</label>
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
            <div><label class="block text-xs font-semibold mb-1">Género</label>
                <select id="f_genero" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
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
            <div><label class="block text-xs font-semibold mb-1">Día nac. *</label>
                <input type="number" min="1" max="31" id="f_nacDia" value="{{ $prefill['nacimientoDia'] ?? '' }}" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
            <div><label class="block text-xs font-semibold mb-1">Mes nac. *</label>
                <input type="number" min="1" max="12" id="f_nacMes" value="{{ $prefill['nacimientoMes'] ?? '' }}" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
            <div><label class="block text-xs font-semibold mb-1">Año nac. *</label>
                <input type="number" min="1900" max="{{ date('Y') }}" id="f_nacAnio" value="{{ $prefill['nacimientoAnio'] ?? '' }}" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
            <div><label class="block text-xs font-semibold mb-1">Correo *</label>
                <input type="email" id="f_correo" value="{{ $prefill['correo'] ?? '' }}" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
            <div><label class="block text-xs font-semibold mb-1">Teléfono</label>
                <input type="text" id="f_telefono" value="{{ $prefill['telefono'] ?? '' }}" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
            <div><label class="block text-xs font-semibold mb-1">Dirección</label>
                <input type="text" id="f_direccion" value="{{ $prefill['direccion'] ?? '' }}" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
            <div><label class="block text-xs font-semibold mb-1">Ciudad</label>
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
        <dl class="text-sm space-y-1">
            <div class="flex justify-between"><dt>Inscripción</dt><dd id="r_inscripcion">0.00</dd></div>
            {{-- Filas condicionales (20/08/2026) — ocultas si el tipo de
                 inscripción/evento no ofrece esa opción, no solo si el
                 monto da 0. Ver updateResumenVisibility(). --}}
            <div id="r_talleres_row" class="flex justify-between" style="display:none"><dt>Talleres</dt><dd id="r_talleres">0.00</dd></div>
            <div id="r_souvenirs_row" class="flex justify-between" style="display:none"><dt>Souvenirs</dt><dd id="r_souvenirs">0.00</dd></div>
            <div id="r_descuento_row" class="flex justify-between" style="display:none"><dt>Descuento promo</dt><dd id="r_descuento">-0.00</dd></div>
            <div id="r_donacion_row" class="flex justify-between" style="display:none"><dt>Donación</dt><dd id="r_donacion">0.00</dd></div>
            <div class="flex justify-between"><dt>Cargo de servicio</dt><dd id="r_fee">0.00</dd></div>
            <div class="flex justify-between font-bold text-base border-t pt-1 mt-1"><dt>Total a cobrar</dt><dd id="r_total">0.00</dd></div>
        </dl>
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
        select.innerHTML = '<option value="">— seleccionar —</option>' + (EVENTO.categories || []).map(c =>
            `<option value="${c.id}" data-precio="${c.precio_vigente}">${c.name} (${Number(c.precio_vigente).toFixed(2)})</option>`
        ).join('');
        const pre = {{ json_encode($prefill['categoria'] ?? null) }};
        if (pre) select.value = String(pre);
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
        const label = document.getElementById('f_aliasLabel');
        const input = document.getElementById('f_alias');
        const select = document.getElementById('f_aliasTitulo');
        const otro = document.getElementById('f_aliasTituloOtro');

        label.textContent = isCongreso ? 'Título' : 'Alias';
        input.style.display = isCongreso ? 'none' : '';
        select.style.display = isCongreso ? '' : 'none';

        if (isCongreso) {
            syncAliasTituloUI();
        } else {
            otro.style.display = 'none';
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
            otro.value = '';
        } else if (current) {
            select.value = 'Otro';
            otro.value = current;
            otro.style.display = '';
        } else {
            select.value = '';
            otro.style.display = 'none';
            otro.value = '';
        }
    }

    function onAliasTituloChange() {
        const sel = document.getElementById('f_aliasTitulo').value;
        const otro = document.getElementById('f_aliasTituloOtro');
        if (sel === 'Otro') {
            otro.style.display = '';
            otro.focus();
            document.getElementById('f_alias').value = otro.value.trim();
        } else {
            otro.style.display = 'none';
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
            });
        });
        return out;
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
        calcular();
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
                <input type="checkbox" class="sv-check mr-2" data-sv="${sv.id}" data-precio="${sv.price}" data-incluido="${sv.incluido ? 1 : 0}" data-nombre="${sv.name}" ${checked}>
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
        if (ft) {
            if (ft.requiereCategoria) {
                const opt = document.getElementById('categoria')?.selectedOptions?.[0];
                inscripcion = opt ? Number(opt.dataset.precio || 0) : 0;
            } else {
                inscripcion = Number(ft.precio_base || 0);
            }
        }

        const souvenirsTotal = souvenirsSeleccionados().reduce((sum, s) => sum + s.precio, 0);
        // Talleres de congreso (20/08/2026) — mismo criterio que
        // CrearInscripcionAction::validateFeePct(): el fee se calcula
        // sobre inscripción + talleres salvo que el evento tenga
        // feeIncluyeTalleres=false.
        const talleresTotal = collectSelectedTalleres().reduce((sum, s) => sum + Number(s.unit_price || 0), 0);
        const donacion = ft && ft.hasDonation ? Number(document.getElementById('f_donacion')?.value || 0) : 0;

        let descuento = 0;
        const promo = ft && ft.hasPromoCode ? promoAplicable() : null;
        if (promo) {
            descuento = promo.discount_type === 'percentage'
                ? Math.round(inscripcion * Number(promo.discount_percent || 0) * 100) / 100
                : Math.max(0, Math.round((inscripcion - Number(promo.price || 0)) * 100) / 100);
        }

        const baseConDescuento = Math.max(0, inscripcion - descuento);
        const baseFee = baseConDescuento + (FEE_INCLUYE_TALLERES ? talleresTotal : 0);
        const fee = Math.round(baseFee * FEE_PCT * 100) / 100;
        const total = Math.round((baseConDescuento + souvenirsTotal + talleresTotal + donacion + fee) * 100) / 100;

        document.getElementById('r_inscripcion').textContent = inscripcion.toFixed(2);
        document.getElementById('r_talleres').textContent = talleresTotal.toFixed(2);
        document.getElementById('r_souvenirs').textContent = souvenirsTotal.toFixed(2);
        document.getElementById('r_descuento').textContent = '-' + descuento.toFixed(2);
        document.getElementById('r_donacion').textContent = donacion.toFixed(2);
        document.getElementById('r_fee').textContent = fee.toFixed(2);
        document.getElementById('r_total').textContent = total.toFixed(2);

        return {
            inscripcion, donacion, souvenirs: souvenirsTotal, talleres: talleresTotal, fee,
            descuento, descuento_registrante: 0, grand_total: total,
        };
    }

    function actualizarSecciones() {
        const ft = formTypeActual();
        renderCategoria(ft);
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

        const totales = calcular();
        const categoriaSelect = document.getElementById('categoria');
        const participante = {
            nombre: document.getElementById('f_nombre').value,
            apellido: document.getElementById('f_apellido').value,
            alias: document.getElementById('f_alias').value,
            genero: document.getElementById('f_genero').value,
            tipoDocumento: document.getElementById('f_tipoDocumento').value,
            numeroDocumento: document.getElementById('f_numeroDocumento').value,
            polera: '', precioPolera: 0,
            nacimiento: {
                dia: Number(document.getElementById('f_nacDia').value),
                mes: Number(document.getElementById('f_nacMes').value),
                anio: Number(document.getElementById('f_nacAnio').value),
            },
            edad: Math.max(0, new Date().getFullYear() - Number(document.getElementById('f_nacAnio').value || 0)),
            correo: document.getElementById('f_correo').value,
            direccion: document.getElementById('f_direccion').value,
            ciudad: document.getElementById('f_ciudad').value,
            telefono: document.getElementById('f_telefono').value,
            contacto_emergencia: {
                nombre: document.getElementById('f_ceNombre').value,
                celular: document.getElementById('f_ceCelular').value,
                relacion: document.getElementById('f_ceRelacion').value,
            },
            souvenirs: souvenirsSeleccionados(),
            talleres: collectSelectedTalleres(),
            answers: [],
            categoria: categoriaSelect && categoriaSelect.value ? categoriaSelect.value : '0',
            precioCategoria: totales.inscripcion,
            donacion: totales.donacion,
            promoDescuento: totales.descuento,
            promoCodigo: document.getElementById('f_promoCodigo')?.value || '',
            subtotal: totales.inscripcion,
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
