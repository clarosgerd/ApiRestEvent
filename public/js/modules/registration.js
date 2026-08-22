// ════════════════════════════════════════════════════════════
//  PANTALLA 0-B: TIPOS DE FORMULARIO + FORMULARIO DE REGISTRO
//  (Fase 2, paso 2)
// ════════════════════════════════════════════════════════════
// Misma nota de ubicación que event-list.js: va en `public/js/modules/`
// (no `resources/js/modules/` como decía el plan) porque no hay Vite que
// compile `resources/`. <script> clásico, mismo scope global léxico que
// el resto — el resto del código en home.blade.php sigue llamando estas
// funciones sin cambios.
//
// `selectEvent()` (dispara esta pantalla) y `goToReview()`/`buildSummary()`
// (a los que esta pantalla entrega el control al terminar) se dejaron
// donde estaban a propósito: sus cuerpos pertenecen a las pantallas
// vecinas (listado de eventos y revisión/pago respectivamente), no a
// ésta — mismo criterio ya aplicado en el paso 1 con `selectEvent`.

// Renderizar tarjetas de tipos de formulario
function renderFormTypes(formTypes){
  const grid = document.getElementById('formTypesGrid');
  grid.innerHTML = '';

  formTypes.forEach(ft => {
    const card = document.createElement('div');
    card.className    = 'form-type-card';
    card.dataset.id   = ft.id;
    card.dataset.name = ft.name;
    card.dataset.desc = ft.description;
    card.dataset.precio_base=ft.precio_base;
    card.style.setProperty('--ft-color', ft.color || 'var(--primary)');

    // Kit/tallas/stock (11/08/2026) — un form_type con cupo lleno seguía
    // ofreciéndose sin límite. `ft.activo` puede venir undefined en una
    // respuesta cacheada vieja — solo se trata como agotado si es
    // explícitamente false.
    const soldOut = ft.activo === false;

    // Tarjeta simplificada (19/08/2026, portado de elascenso/event) —
    // solo nombre, descripción e ícono/imagen; se sacaron precio base/
    // cupo/talla. Si la imagen falla al cargar, cae al emoji de `icon`
    // (mismo patrón onerror que las tarjetas de evento).
    const iconContent = ft.imagenUrl
      ? `<img src="${escHtml(ft.imagenUrl)}" alt=""
              style="width:100%;height:100%;object-fit:cover;border-radius:50%;"
              onerror="this.style.display='none';this.nextElementSibling.style.display='';">
         <span style="display:none;">${escHtml(ft.icon)}</span>`
      : escHtml(ft.icon);

    card.innerHTML = `
      <div class="form-type-icon" style="background:${ft.color}22;">${iconContent}</div>
      <div class="form-type-name">${escHtml(ft.name)}</div>
      <div class="form-type-desc">${escHtml(ft.description)}</div>
      ${soldOut ? renderWaitlistBlock(ft) : ''}`;

    if (soldOut) {
      card.classList.add('form-type-soldout');
      card.style.opacity = '0.85';
      if (ft.permite_lista_espera) {
        card.querySelector('.ft-waitlist-submit')?.addEventListener('click', (e) => {
          e.stopPropagation();
          submitWaitlist(ft, card);
        });
      }
    } else {
      enableCardKeyboard(card, () => chooseFormType(card, ft));
    }
    grid.appendChild(card);
  });
}

// Bloque de "cupo lleno" + mini-formulario para anotarse a la lista de
// espera (kit/tallas/stock, 20/08/2026 portado de elascenso/event) —
// inline en la tarjeta, no un modal aparte.
function renderWaitlistBlock(ft){
  let html = `<div class="ft-waitlist" style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border);">
    <div style="font-weight:700;color:#c0392b;font-size:13px;margin-bottom:6px;">🚫 ${escHtml(t('formTypes.soldOut'))}</div>`;

  if (ft.permite_lista_espera) {
    html += `
    <input type="text" class="ft-waitlist-nombre" placeholder="${escHtml(t('formTypes.waitlistName'))}"
           style="width:100%;margin-bottom:4px;padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:12px;box-sizing:border-box;">
    <input type="email" class="ft-waitlist-correo" placeholder="${escHtml(t('formTypes.waitlistEmail'))}"
           style="width:100%;margin-bottom:6px;padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:12px;box-sizing:border-box;">
    <button type="button" class="ft-waitlist-submit btn-primary" style="width:100%;padding:7px;font-size:12px;">
      ${escHtml(t('formTypes.waitlistSubmit'))}
    </button>`;
  }

  html += '</div>';
  return html;
}

async function submitWaitlist(ft, card){
  const nombre = card.querySelector('.ft-waitlist-nombre')?.value.trim();
  const correo = card.querySelector('.ft-waitlist-correo')?.value.trim();
  if (!nombre || !correo) { alert(t('registration.errRequired')); return; }

  const btn = card.querySelector('.ft-waitlist-submit');
  btn.disabled = true;

  try {
    await fetchJson(`${API_BASE}/lista_espera.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        evento_id: currentEvent?.id,
        form_types_id: ft.id,
        nombre, correo,
      }),
    });
    card.querySelector('.ft-waitlist').innerHTML =
      `<div style="font-size:12px;color:var(--success);">✓ ${escHtml(t('formTypes.waitlistOk'))}</div>`;
  } catch (err) {
    alert(err.message || 'No se pudo completar la anotación.');
    btn.disabled = false;
  }
}

// Seleccionar tipo de formulario
function chooseFormType(card, ft){
  // Hallazgo 12/08 (portado de elascenso/event) — mismo guard que
  // selectEvent(): cambiar de tipo de formulario dentro del mismo evento
  // también invalida los participantes ya agregados (categoría/souvenirs
  // son por form_type).
  if (selectedFormType && selectedFormType.id !== ft.id && participants.length > 0) {
    if (!confirm(t('registration.confirmSwitchEvent'))) return;
    discardStaleParticipants();
  }
  selectedFormType = ft;
  proceedToRegistration();
}

// Descarta participantes agregados para un evento/form_type que ya no es
// el actual — ver selectEvent() (home.blade.php) / chooseFormType()
// (hallazgo 12/08).
function discardStaleParticipants(){
  participants = [];
  appliedPromoType  = 'fixed_price';
  appliedPromoValue = 0;
  renderParticipantsList();
  document.getElementById('participantsList').style.display = 'none';
}

// Volver al listado de eventos
function backToEventList(){
  currentEvent     = null;
  selectedFormType = null;
  document.querySelectorAll('.event-card').forEach(c => c.classList.remove('selected'));
  const screen = document.getElementById('screen-form-types');
  if (screen) {
    screen.style.background = 'linear-gradient(135deg, var(--secondary) 0%, var(--primary-dk) 100%)';
  }
  showScreen('screen-event-list');
}

// ════════════════════════════════════════════════════════════
//  KIT/TALLAS/STOCK (11/08/2026, portado de elascenso/event 12/08/2026) —
//  ver ApiRestEvent/brain/api_rest_event/PRD-kit-tallas-stock-lista-espera.md
// ════════════════════════════════════════════════════════════
// `sv.tallas` viene de SouvenirResource: array de {talla, sexo,
// disponible} si el ítem tiene stock cargado (item_stock), o vacío si no
// — "vacío" es "disponibilidad no controlada", no "agotado". Sin stock
// cargado no hay catálogo real de tallas para ofrecer, así que se cae a
// un catálogo genérico (mismo que la polera legacy) solo para no dejar
// el selector vacío — el organizador que quiera un catálogo distinto
// carga stock real desde el panel.
function souvenirTallaCatalogo(sv){
  if (sv.tallas && sv.tallas.length){
    return [...new Set(sv.tallas.filter(t => t.disponible > 0).map(t => t.talla).filter(Boolean))];
  }
  return ['XS','S','M','L','XL','XXL'];
}

function souvenirSexoCatalogo(sv, talla){
  if (sv.tallas && sv.tallas.length){
    return [...new Set(sv.tallas.filter(t => t.disponible > 0 && (t.talla || null) === (talla || null)).map(t => t.sexo).filter(Boolean))];
  }
  return ['masculino','femenino','unisex'];
}

function renderSouvenirTallaSexoPicker(sv){
  if (!sv.requiere_talla && !sv.requiere_sexo) return '';
  // Apilados (no lado a lado) y con label propio — en una tarjeta angosta
  // (souvenir-card llega a max-width:180px), 2 selects en flex quedaban
  // tan chicos que el texto se truncaba ("masc...") y casi no se notaban
  // como campos con acción pendiente. Fondo + borde propios para que se
  // distingan del resto de la tarjeta.
  let html = `<div class="souvenir-talla-sexo"
      style="margin-top:10px;padding:8px;background:#fff8e6;border:1.5px solid #f0c040;border-radius:8px;text-align:left;"
      onclick="event.stopPropagation()">`;
  if (sv.requiere_talla){
    const tallas = souvenirTallaCatalogo(sv);
    html += `<label style="display:block;font-size:10px;font-weight:700;color:var(--secondary);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;">
      ${escHtml(t('registration.shirtSize'))}
    </label>
    <select class="souvenir-talla-select"
            style="width:100%;padding:6px 8px;font-size:13px;border:1.5px solid var(--primary);border-radius:6px;background:var(--white);margin-bottom:6px;">
      <option value="">${escHtml(t('registration.selectSize'))}</option>
      ${tallas.map(ta => `<option value="${escHtml(ta)}">${escHtml(ta)}</option>`).join('')}
    </select>`;
  }
  if (sv.requiere_sexo){
    html += `<label style="display:block;font-size:10px;font-weight:700;color:var(--secondary);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;">
      Sexo
    </label>
    <select class="souvenir-sexo-select"
            style="width:100%;padding:6px 8px;font-size:13px;border:1.5px solid var(--primary);border-radius:6px;background:var(--white);">
      <option value="">${escHtml(t('registration.selectSex'))}</option>
    </select>`;
  }
  html += '</div>';
  return html;
}

// Repuebla el <select> de sexo según la talla elegida (solo combinaciones
// con disponible > 0) — se llama al cambiar la talla y una vez al armar
// la tarjeta.
function refreshSouvenirSexoOptions(sv, cardEl){
  const sexoSelect = cardEl.querySelector('.souvenir-sexo-select');
  if (!sexoSelect) return;
  const talla = cardEl.querySelector('.souvenir-talla-select')?.value || null;
  const prev  = sexoSelect.value;
  const sexos = souvenirSexoCatalogo(sv, talla);
  sexoSelect.innerHTML = `<option value="">${escHtml(t('registration.selectSex'))}</option>` +
    sexos.map(s => `<option value="${escHtml(s)}">${escHtml(s)}</option>`).join('');
  if (sexos.includes(prev)) sexoSelect.value = prev;
}

// Fotos de los ítems `incluido=true` del kit (uno por nombre único, del
// evento entero) — pantalla previa a elegir un tipo de formulario.
function renderKitGallery(ev){
  const container = document.getElementById('ftKitGalleryContainer');
  const track      = document.getElementById('ftKitGalleryTrack');
  track.innerHTML = '';

  const vistos = new Set();
  const items = [];
  (ev.formTypes || []).forEach(ft => {
    (ft.souvenirs || []).forEach(sv => {
      if (sv.incluido && sv.foto_url && !vistos.has(sv.name)) {
        vistos.add(sv.name);
        items.push(sv);
      }
    });
  });

  if (items.length === 0) { container.style.display = 'none'; return; }

  container.style.display = 'block';
  items.forEach(sv => {
    track.insertAdjacentHTML('beforeend', `
      <div style="flex:0 0 auto;width:120px;text-align:center;">
        <img src="${escHtml(sv.foto_url)}" alt="${escHtml(sv.name)}"
             style="width:120px;height:90px;object-fit:cover;border-radius:8px;display:block;"
             onerror="this.closest('div').style.display='none'">
        <div style="font-size:11px;color:var(--secondary);font-weight:600;margin-top:4px;">${escHtml(sv.name)}</div>
      </div>`);
  });
}

// ════════════════════════════════════════════════════════════
//  VIDEO / IMAGEN DE LA CARRERA
// ════════════════════════════════════════════════════════════
function renderEventMedia(ev){
  const container = document.getElementById('ftMediaContainer');
  const content   = document.getElementById('ftMediaContent');
  const title     = document.getElementById('ftMediaTitle');
  const videoId   = (ev.video || '').trim();
  const image     = (ev.image || '').trim();

  if (!videoId && !image) {
    container.style.display = 'none';
    return;
  }

  container.style.display = 'block';

  if (videoId) {
    title.textContent = t('formTypes.mediaTitle');
    content.innerHTML = `
      <div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:var(--radius);">
        <iframe src="https://www.youtube-nocookie.com/embed/${escHtml(videoId)}?rel=0"
                style="position:absolute;top:0;left:0;width:100%;height:100%;border:none;border-radius:var(--radius);"
                allow="accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture"
                allowfullscreen
                loading="lazy"
                title="Race video">
        </iframe>
      </div>`;
  } else {
    title.textContent = t('formTypes.mediaTitle');
    content.innerHTML = `
      <img src="${escHtml(image)}" alt="${escHtml(ev.name)}"
           style="width:100%;height:auto;max-height:400px;object-fit:cover;border-radius:var(--radius);display:block;"
           onerror="this.parentElement.parentElement.parentElement.style.display='none'">`;
  }
}

// ════════════════════════════════════════════════════════════
//  AUSPICIADORES (carrusel de logos, antes del mapa de ruta)
// ════════════════════════════════════════════════════════════
// Carrusel simple con scroll horizontal nativo (overflow-x:auto) — sin
// librería nueva, logos a tamaño fijo chico para no ocupar mucho alto de
// pantalla. currentEvent.auspiciadores viene tal cual de la API externa
// (normalizeEvento() usa spread, no hace falta mapear nada acá).
function renderSponsorsCarousel(ev){
  const container = document.getElementById('ftSponsorsContainer');
  const track      = document.getElementById('ftSponsorsTrack');
  const sponsors   = ev.auspiciadores || [];
  track.innerHTML = '';

  if (sponsors.length === 0) { container.style.display = 'none'; return; }

  container.style.display = 'block';
  sponsors.forEach(sp => {
    const img = `<img src="${escHtml(sp.logo_url)}" alt="${escHtml(sp.nombre)}" title="${escHtml(sp.nombre)}"
                      style="height:56px;max-width:140px;object-fit:contain;flex:0 0 auto;"
                      onerror="this.closest('.sponsor-item').style.display='none'">`;
    const inner = sp.contacto
      ? `<a class="sponsor-item" href="${escHtml(sp.contacto)}" target="_blank" rel="noopener" style="display:flex;flex-direction:column;align-items:center;gap:4px;text-decoration:none;">${img}<span style="font-size:11px;color:var(--secondary);font-weight:600;" data-i18n="formTypes.contactSponsor">Contact</span></a>`
      : `<div class="sponsor-item" style="display:flex;">${img}</div>`;
    track.insertAdjacentHTML('beforeend', inner);
  });
}

// ════════════════════════════════════════════════════════════
//  MAPA DE RUTA (Leaflet + OpenStreetMap)
// ════════════════════════════════════════════════════════════
let routeMap = null;

function renderRouteMap(ev){
  const container = document.getElementById('ftRouteMapContainer');
  const mapEl     = document.getElementById('ftRouteMap');
  const titleEl   = container?.querySelector('p:first-of-type');
  const subtitleEl = container?.querySelector('p:last-of-type');
  const route     = ev.route || [];
  const coordsArr = ev.coordinates;
  const coords    = Array.isArray(coordsArr) ? coordsArr[0] : coordsArr;

  if (titleEl) titleEl.textContent = t('formTypes.routeTitle');
  if (subtitleEl) subtitleEl.textContent = t('formTypes.routeSubtitle');

  const hasCoords = coords && coords.lat != null && coords.lng != null;
  if (route.length === 0 && !hasCoords) {
    container.style.display = 'none';
    return;
  }

  container.style.display = 'block';

  if (routeMap) {
    routeMap.remove();
    routeMap = null;
  }

  const points = route.length > 0
    ? route.map(p => [p.lat, p.lng])
    : [[coords.lat, coords.lng]];

  routeMap = L.map(mapEl);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 18
  }).addTo(routeMap);

  if (route.length > 1) {
    const polyline = L.polyline(points, {
      color: '#00bad2', weight: 4, opacity: 0.9
    }).addTo(routeMap);

    route.forEach((p, i) => {
      const isStart = i === 0;
      const isEnd   = i === route.length - 1;

      const icon = L.divIcon({
        className: '',
        html: `<div style="
          width:${isStart || isEnd ? 18 : 12}px;
          height:${isStart || isEnd ? 18 : 12}px;
          border-radius:50%;
          background:${isStart ? '#258f36' : isEnd ? '#c0392b' : '#00bad2'};
          border:3px solid #fff;
          box-shadow:0 2px 6px rgba(0,0,0,.3);
        "></div>`,
        iconSize: [isStart || isEnd ? 18 : 12, isStart || isEnd ? 18 : 12],
        iconAnchor: [isStart || isEnd ? 9 : 6, isStart || isEnd ? 9 : 6]
      });

      L.marker([p.lat, p.lng], { icon })
        .bindPopup(`<strong>${escHtml(p.label || 'Point ' + (i+1))}</strong>`)
        .addTo(routeMap);
    });

    routeMap.fitBounds(polyline.getBounds(), { padding: [30, 30] });
  } else {
    routeMap.setView(points[0], 14);
    L.marker(points[0]).addTo(routeMap)
      .bindPopup(`<strong>${escHtml(ev.name)}</strong><br>${escHtml(ev.location)}`);
  }
}

// ════════════════════════════════════════════════════════════
//  DELIVERY: MAPA DE UBICACIÓN (12/08/2026, portado de elascenso/event)
//  — opcional, complementa la dirección de texto que ya llena el
//  participante, no la reemplaza. Mismo patrón técnico que
//  renderRouteMap() (Leaflet + OpenStreetMap), pero con un pin
//  arrastrable en vez de solo lectura.
// ════════════════════════════════════════════════════════════
let deliveryMap = null;
let deliveryLat = null;
let deliveryLng = null;
let deliveryMarker = null;

// Se llama recién al tildar "quiero delivery" — un mapa Leaflet
// instanciado sobre un contenedor todavía display:none mide 0x0 y queda
// roto. Si el checkbox se destilda y se vuelve a tildar, reusa el mapa
// ya creado (invalidateSize() para que recalcule el tamaño visible) en
// vez de duplicar instancias.
function initDeliveryMap(){
  const mapEl = document.getElementById('deliveryMap');
  if (deliveryMap) {
    setTimeout(() => deliveryMap.invalidateSize(), 0);
    return;
  }

  // Centro por defecto: coordenadas del evento si existen (mismo dato
  // que usa renderRouteMap), si no un fallback genérico — el pin igual
  // se puede arrastrar a cualquier lado.
  const coordsArr = currentEvent?.coordinates;
  const coords    = Array.isArray(coordsArr) ? coordsArr[0] : coordsArr;
  const center     = (coords && coords.lat != null && coords.lng != null)
    ? [coords.lat, coords.lng]
    : [-16.5, -68.15];

  deliveryMap = L.map(mapEl).setView(center, 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 18
  }).addTo(deliveryMap);

  deliveryMarker = L.marker(center, { draggable: true }).addTo(deliveryMap);
  deliveryLat = center[0];
  deliveryLng = center[1];

  deliveryMarker.on('dragend', () => {
    const pos = deliveryMarker.getLatLng();
    deliveryLat = pos.lat;
    deliveryLng = pos.lng;
  });
  // Click en cualquier punto del mapa también reposiciona el pin — no
  // todos los participantes van a pensar en "arrastrar" espontáneamente.
  deliveryMap.on('click', (e) => {
    deliveryMarker.setLatLng(e.latlng);
    deliveryLat = e.latlng.lat;
    deliveryLng = e.latlng.lng;
  });

  setTimeout(() => deliveryMap.invalidateSize(), 0);
}

// ════════════════════════════════════════════════════════════
//  AGENDA DEL EVENTO (sesiones/ponentes/salas para congresos;
//  cronograma del día — kits, calentamiento, largada, premiación,
//  after party — para carreras). Un mismo componente sirve para
//  ambos: cada item muestra ponente/sala solo si vienen cargados,
//  sin depender de un "tipo de evento" global (ver brain, la
//  distinción deportivo/congreso vive por form_type, no por evento).
// ════════════════════════════════════════════════════════════
function formatAgendaTime(t){
  if (!t) return '';
  return t.slice(0, 5); // "HH:MM:SS" -> "HH:MM"
}

function formatAgendaDay(fecha){
  const locale = currentLang === 'es' ? 'es-BO' : currentLang === 'pt' ? 'pt-BR' : 'en-US';
  const parsed = new Date(fecha + 'T00:00:00');
  if (isNaN(parsed.getTime())) return fecha;
  return parsed.toLocaleDateString(locale, { weekday: 'long', day: 'numeric', month: 'long' });
}

// Guarda el evento cuya agenda está actualmente cargada en el modal, para
// poder reconstruir el contenido si cambia el idioma mientras está abierto.
let agendaModalEvent = null;

function renderEventAgenda(ev){
  const container = document.getElementById('ftAgendaContainer');
  const summaryEl = document.getElementById('ftAgendaSummary');
  const titleEl   = document.getElementById('ftAgendaTitle');
  const agenda    = ev.agenda || [];

  if (titleEl) titleEl.textContent = t('formTypes.agendaTitle');
  agendaModalEvent = agenda.length > 0 ? ev : null;

  if (agenda.length === 0) {
    container.style.display = 'none';
    document.getElementById('agendaModal').style.display = 'none';
    return;
  }

  container.style.display = 'flex';

  const pdfLink = document.getElementById('ftAgendaPdfLink');
  if (pdfLink) pdfLink.href = `${API_BASE}/agenda_pdf.php?id=${encodeURIComponent(ev.id)}`;

  const dayCount = new Set(agenda.map(item => item.date || '')).size;
  summaryEl.textContent = dayCount > 1
    ? t('formTypes.agendaSummaryDays').replace('%count', agenda.length).replace('%days', dayCount)
    : t('formTypes.agendaSummary').replace('%count', agenda.length);

  // Si el modal ya estaba abierto (ej. cambio de idioma), reconstruirlo.
  if (document.getElementById('agendaModal').style.display === 'flex') {
    renderAgendaModal();
  }
}

// Arma el HTML de una lista de items agrupada por día (encabezados de día
// solo si hay más de una fecha distinta dentro de esa lista).
function buildAgendaListHtml(agenda){
  const groups = new Map();
  agenda.forEach(item => {
    const key = item.date || '';
    if (!groups.has(key)) groups.set(key, []);
    groups.get(key).push(item);
  });
  const showDayHeaders = groups.size > 1;

  let html = '';
  groups.forEach((items, fecha) => {
    if (showDayHeaders) {
      html += `<div class="agenda-day-header">${fecha ? escHtml(formatAgendaDay(fecha)) : ''}</div>`;
    }
    items.forEach(item => {
      const time = item.endTime
        ? `${formatAgendaTime(item.startTime)} – ${formatAgendaTime(item.endTime)}`
        : formatAgendaTime(item.startTime);
      const chips = [
        item.speaker ? `<span class="agenda-chip">🎤 ${escHtml(item.speaker)}${item.speakerRole ? ' · ' + escHtml(item.speakerRole) : ''}</span>` : '',
        item.room    ? `<span class="agenda-chip">📍 ${escHtml(item.room)}</span>` : ''
      ].join('');

      html += `
        <div class="agenda-item">
          <div class="agenda-time">${escHtml(time)}</div>
          <div class="agenda-body">
            <div class="agenda-title">${item.icon ? escHtml(item.icon) + ' ' : ''}${escHtml(item.title)}</div>
            ${item.description ? `<div class="agenda-desc">${escHtml(item.description)}</div>` : ''}
            ${chips ? `<div class="agenda-chips">${chips}</div>` : ''}
          </div>
        </div>`;
    });
  });

  return html;
}

// Reconstruye el contenido del modal a partir de agendaModalEvent. Si la
// agenda tiene items propios de tipos de formulario específicos, se arma en
// dos bloques: los generales (formTypeId null) en una franja compartida
// arriba, y una columna por cada tipo con items propios debajo — evita
// repetir los generales en cada columna. Si no hay diferenciación por tipo,
// se muestra la lista única de siempre.
function renderAgendaModal(){
  if (!agendaModalEvent) return;
  const agenda    = agendaModalEvent.agenda || [];
  const formTypes = agendaModalEvent.formTypes || [];
  const content   = document.getElementById('agendaModalContent');

  document.getElementById('agendaModalTitle').textContent = t('formTypes.agendaTitle');

  const typeIds = [...new Set(agenda.map(i => i.formTypeId).filter(id => id != null))];

  if (typeIds.length === 0) {
    content.innerHTML = buildAgendaListHtml(agenda);
    return;
  }

  const generalItems = agenda.filter(i => i.formTypeId == null);
  let html = '';
  if (generalItems.length > 0) {
    html += `<div class="agenda-general">${buildAgendaListHtml(generalItems)}</div>`;
  }

  html += '<div class="agenda-columns">';
  typeIds.forEach(id => {
    const ft    = formTypes.find(f => String(f.id) === String(id));
    const label = ft ? ft.name : ('#' + id);
    const items = agenda.filter(i => String(i.formTypeId) === String(id));
    html += `
      <div class="agenda-column">
        <div class="agenda-column-header">${escHtml(label)}</div>
        ${buildAgendaListHtml(items)}
      </div>`;
  });
  html += '</div>';

  content.innerHTML = html;
}

function openAgendaModal(){
  if (!agendaModalEvent) return;
  renderAgendaModal();
  // openModal() (home.blade.php) maneja foco/Escape/trap de Tab.
  openModal(document.getElementById('agendaModal'));
}

function closeAgendaModal(){
  closeModal(document.getElementById('agendaModal'));
}

// ════════════════════════════════════════════════════════════
//  AGREGAR AL CALENDARIO (20/08/2026, portado de elascenso/event) —
//  .ics descargable (Outlook/Apple/Gmail) + link directo a Google
//  Calendar. Aplica a CUALQUIER evento (con o sin agenda_items) — el
//  backend ya arma un .ics con un solo VEVENT cuando no hay agenda.
// ════════════════════════════════════════════════════════════

// Zona horaria fija de plataforma usada para el link de Google Calendar
// — debe coincidir con config('app.event_ics_timezone') en ApiRestEvent
// (EVENT_ICS_TIMEZONE en su .env). No existe hoy un campo de zona
// horaria por evento.
const EVENT_CALENDAR_TIMEZONE = 'America/La_Paz';

function toGCalDateTime(dateStr, timeStr){
  if (!dateStr) return null;
  const time = (timeStr || '00:00:00').slice(0, 8).replace(/:/g, '').padEnd(6, '0');
  return dateStr.slice(0, 10).replace(/-/g, '') + 'T' + time;
}

// dt: 'YYYYMMDDTHHMMSS' (sin zona, floating — coherente con EVENT_CALENDAR_TIMEZONE)
function addHoursToGCalDateTime(dt, hours){
  const y = +dt.slice(0, 4), mo = +dt.slice(4, 6) - 1, d = +dt.slice(6, 8);
  const h = +dt.slice(9, 11), mi = +dt.slice(11, 13), s = +dt.slice(13, 15);
  const date = new Date(y, mo, d, h, mi, s);
  date.setHours(date.getHours() + hours);
  const pad = n => String(n).padStart(2, '0');
  return `${date.getFullYear()}${pad(date.getMonth() + 1)}${pad(date.getDate())}T${pad(date.getHours())}${pad(date.getMinutes())}${pad(date.getSeconds())}`;
}

// Arma { icsUrl, gcalUrl } para un evento. El link de Google solo puede
// representar UN evento en su URL — si hay agenda de congreso, arma un
// único bloque que cubre desde el primer hasta el último ítem, con una
// nota de que el .ics trae el detalle sesión por sesión.
function getCalendarLinksFor(ev){
  if (!ev || !ev.id) return null;

  const icsUrl = `${API_BASE}/agenda_ics.php?id=${encodeURIComponent(ev.id)}`;
  const agenda = (ev.agenda || []).filter(item => item.date);

  let title, startDt, endDt, details, location;

  if (agenda.length > 0) {
    const byStart = [...agenda].sort((a, b) => (a.date + (a.startTime || '')).localeCompare(b.date + (b.startTime || '')));
    const byEnd   = [...agenda].sort((a, b) => (a.date + (a.endTime || a.startTime || '')).localeCompare(b.date + (b.endTime || b.startTime || '')));
    const first = byStart[0];
    const last  = byEnd[byEnd.length - 1];

    title   = `${ev.name} — ${t('formTypes.agendaTitle')}`;
    startDt = toGCalDateTime(first.date, first.startTime);
    endDt   = last.endTime ? toGCalDateTime(last.date, last.endTime) : addHoursToGCalDateTime(startDt, 1);
    details = t('formTypes.calendarAgendaNote');
    location = '';
  } else {
    title   = ev.name;
    startDt = toGCalDateTime(ev.date, ev.localTime);
    endDt   = startDt ? addHoursToGCalDateTime(startDt, 4) : null;
    details = ev.description || '';
    location = ev.location || '';
  }

  if (!startDt) return { icsUrl, gcalUrl: null };

  const params = new URLSearchParams({
    action: 'TEMPLATE',
    text: title,
    dates: `${startDt}/${endDt}`,
    details,
    location,
    ctz: EVENT_CALENDAR_TIMEZONE,
  });

  return { icsUrl, gcalUrl: `https://calendar.google.com/calendar/render?${params.toString()}` };
}

function renderAddToCalendar(ev){
  const container = document.getElementById('ftCalendarContainer');
  const icsLink   = document.getElementById('ftCalendarIcsLink');
  const gcalLink  = document.getElementById('ftCalendarGCalLink');
  if (!container) return;

  const links = getCalendarLinksFor(ev);
  if (!links) {
    container.style.display = 'none';
    return;
  }

  container.style.display = 'flex';
  if (icsLink) icsLink.href = links.icsUrl;
  if (gcalLink) {
    gcalLink.style.display = links.gcalUrl ? '' : 'none';
    if (links.gcalUrl) gcalLink.href = links.gcalUrl;
  }
}

// ════════════════════════════════════════════════════════════
//  PROCEDER AL FORMULARIO DE REGISTRO
// ════════════════════════════════════════════════════════════
function proceedToRegistration(){
  if (!currentEvent)    { alert('Please select an event.'); return; }
  if (!selectedFormType){ alert('Please select a registration type.'); return; }
  buildEventUI();
  showScreen('screen-registration');
  setStep(1);
}

function buildEventUI(){
  resetParticipantForm();
  document.getElementById('eventBarName').textContent = currentEvent.name;
  document.getElementById('eventBarDate').textContent =
    currentEvent.date + (selectedFormType ? '  ·  ' + selectedFormType.name : '');

  // Categorías: sección condicional, solo si el form_type la requiere
  // (default true para no cambiar el comportamiento de form_types
  // existentes que nunca mandaron este flag).
  const categorySection = document.getElementById('categorySection');
  const requiereCategoria = selectedFormType.requiereCategoria ?? true;
  categorySection.style.display = requiereCategoria ? '' : 'none';

  const grid = document.getElementById('categoryGrid');
  grid.innerHTML = '';
  (requiereCategoria ? currentEvent.categories : []).forEach(cat => {
    const div = document.createElement('div');
    div.className     = 'category-card';
    div.dataset.id    = cat.id;
    div.dataset.name  = cat.name;
    div.dataset.price = cat.price;
    div.innerHTML = `
      <div class="cat-name">${escHtml(cat.name)}</div>
      <div class="cat-price">${formatMoney(cat.price)}</div>
      <div class="cat-desc">${escHtml(cat.description)}</div>`;
    div.setAttribute('aria-pressed', 'false');
    enableCardKeyboard(div, () => {
      document.querySelectorAll('.category-card').forEach(c => {
        c.classList.remove('selected');
        c.setAttribute('aria-pressed', 'false');
      });
      div.classList.add('selected');
      div.setAttribute('aria-pressed', 'true');
      document.getElementById('catError').classList.remove('visible');
    });
    grid.appendChild(div);
  });

  // Souvenirs
  // Kit/tallas/stock (11/08/2026, portado 12/08/2026) — antes esto
  // guardaba sv.name (el nombre, no el id real) como "id", y era una
  // tarjeta genérica sin foto/talla/sexo/incluido. Ver
  // PRD-kit-tallas-stock-lista-espera.md (ApiRestEvent) y la deprecación
  // de hasshirt/costo_polera del 12/08 en elascenso/event.
  const sg = document.getElementById('souvenirsGrid');
  sg.innerHTML = '';
  (selectedFormType.souvenirs || []).forEach(sv => {
    const div = document.createElement('div');
    // Un souvenir incluido=true (ej. la "Polera de kit" migrada desde
    // hasshirt) ya viene en precio_base: nace marcado (opt-in por
    // defecto) pero se puede destildar — igual que la sección legacy
    // dejaba elegir "Sin camiseta" — y no muestra precio (se fuerza a 0
    // al armar el payload, ver más abajo) para no cobrarlo dos veces.
    div.className    = 'souvenir-card' + (sv.incluido ? ' checked' : '');
    div.dataset.id       = sv.id;
    div.dataset.name     = sv.name;
    div.dataset.price    = sv.price;
    div.dataset.incluido = sv.incluido ? '1' : '0';

    const foto = sv.foto_url
      ? `<img src="${escHtml(sv.foto_url)}" alt="${escHtml(sv.name)}" style="width:100%;height:80px;object-fit:cover;border-radius:8px;margin-bottom:6px;" onerror="this.style.display='none'">`
      : '';

    const priceOrIncluded = sv.incluido
      ? `<div class="souvenir-included">${escHtml(t('registration.itemIncluded'))}</div>`
      : `<div class="souvenir-price">${formatMoney(sv.price)}</div>`;

    div.innerHTML = `
      <div class="souvenir-check">✓</div>
      ${foto}
      <div class="souvenir-icon">${sv.icon}</div>
      <div class="souvenir-name">${escHtml(sv.name)}</div>
      ${priceOrIncluded}
      ${renderSouvenirTallaSexoPicker(sv)}`;
    div.setAttribute('aria-pressed', sv.incluido ? 'true' : 'false');
    enableCardKeyboard(div, (e) => {
      // Los selects de talla/sexo no deben togglear la tarjeta al
      // abrirlos/elegir una opción (activación por click trae `e`;
      // por teclado, enableCardKeyboard llama sin argumento).
      if (e && e.target && e.target.closest('select')) return;
      const checked = div.classList.toggle('checked');
      div.setAttribute('aria-pressed', String(checked));
    });
    sg.appendChild(div);

    if (sv.requiere_talla) {
      div.querySelector('.souvenir-talla-select')?.addEventListener('change', () => refreshSouvenirSexoOptions(sv, div));
      refreshSouvenirSexoOptions(sv, div);
    }
  });

  // Título de congreso reusando `alias` (20/08/2026, portado de
  // elascenso/event) — ver toggleAliasTituloMode() más abajo.
  toggleAliasTituloMode();

  // Talleres (congresos, 20/08/2026, portado de elascenso/event) — los
  // talleres son del evento, no del form_type (un congreso los ofrece a
  // cualquier tipo de inscripción).
  renderTalleresSelector();

  // Preguntas dinámicas del tipo de formulario
  renderDynamicQuestions(selectedFormType);

  // Donación condicional — hasDonation pasó de evento a form_type (QA
  // visual 10/08): un evento con 2 tipos de formulario puede permitir
  // donación en uno y no en el otro.
  document.getElementById('donationSection').style.display =
    selectedFormType.hasDonation ? 'block' : 'none';

  // Promo code: solo visible si el form_type admite hasPromoCode = 1
  document.getElementById('promoSection').style.display =
    selectedFormType.hasPromoCode == 1 ? 'block' : 'none';

  // Shirt: ocultar sección si el tipo de formulario no incluye camiseta
  const shirtSection = document.getElementById('shirtSection');
  if (!selectedFormType.hasshirt) {
    shirtSection.style.display = 'none';
    document.getElementById('sinPolera').checked = true;
    document.getElementById('shirtSizeContainer').style.display = 'none';
    syncRadioStyles();
  } else {
    shirtSection.style.display = '';
  }

  // Equipo: solo para form_types con hasTeam (inscripción individual con
  // pertenencia a un equipo precargado por el organizador, distinto de la
  // inscripción grupal — ver brain/PLAN-RESULTADOS-EQUIPOS-31072026.md §3).
  const teamSection = document.getElementById('teamSection');
  const equipoSelect = document.getElementById('equipo_id');
  if (selectedFormType.hasTeam) {
    teamSection.style.display = '';
    const equipos = currentEvent.equipos || [];
    equipoSelect.innerHTML = `<option value="" data-i18n-opt="registration.teamSelect">${t('registration.teamSelect')}</option>` +
      equipos.map(eq => `<option value="${eq.id}">${escHtml(eq.nombre)}</option>`).join('');
  } else {
    teamSection.style.display = 'none';
    equipoSelect.innerHTML = '';
  }

  // Delivery del kit: opt-in del participante, solo si el form_type lo
  // ofrece (brain/PLAN-DELIVERY-31072026.md).
  const deliverySection = document.getElementById('deliverySection');
  if (selectedFormType.hasDelivery) {
    deliverySection.style.display = '';
  } else {
    deliverySection.style.display = 'none';
    document.getElementById('quiere_delivery').checked = false;
  }
}

// ────────────────────────────────────────────────────────────
// Título de congreso reusando el campo `alias` (20/08/2026, portado de
// elascenso/event) — para form_types tipo 'congreso' el campo "Alias" se
// muestra como "Título" (Dr., Dra., Lic., ...) en vez de texto libre,
// pero sigue escribiendo en el mismo #alias oculto: no se creó columna
// nueva en la BD, el backend recibe exactamente el mismo `alias` de
// siempre.
// ────────────────────────────────────────────────────────────
function toggleAliasTituloMode(){
  const isCongreso = !!(selectedFormType && selectedFormType.tipo === 'congreso');
  const aliasInput  = document.getElementById('alias');
  const aliasTitulo = document.getElementById('aliasTitulo');
  const aliasOtro   = document.getElementById('aliasTituloOtro');
  const aliasLabel  = document.getElementById('aliasLabel');

  aliasLabel.setAttribute('data-i18n', isCongreso ? 'registration.titulo' : 'registration.alias');
  aliasLabel.textContent = t(isCongreso ? 'registration.titulo' : 'registration.alias');

  aliasInput.style.display  = isCongreso ? 'none' : '';
  aliasTitulo.style.display = isCongreso ? '' : 'none';
  // El contenedor es angosto (120px) porque "Alias" es corto — "Select…"
  // y "PhD." necesitan un poco más de aire.
  document.getElementById('aliasGroup').style.maxWidth = isCongreso ? '160px' : '120px';

  if (isCongreso) {
    syncAliasTituloUI();
  } else {
    aliasOtro.style.display = 'none';
  }
}

// Refleja el valor actual de #alias en el <select> de título (y en el
// input "Otro" si no matchea ninguna opción) — se llama tanto al entrar
// al formulario (toggleAliasTituloMode) como al precargar un participante
// existente para editar (editParticipant), porque ninguno de los dos
// dispara el evento change del select.
function syncAliasTituloUI(){
  if (!selectedFormType || selectedFormType.tipo !== 'congreso') return;
  const aliasInput  = document.getElementById('alias');
  const aliasTitulo = document.getElementById('aliasTitulo');
  const aliasOtro   = document.getElementById('aliasTituloOtro');
  const current = (aliasInput.value || '').trim();
  const opciones = [...aliasTitulo.options].map(o => o.value).filter(v => v && v !== 'Otro');

  if (opciones.includes(current)) {
    aliasTitulo.value = current;
    aliasOtro.style.display = 'none';
    aliasOtro.value = '';
  } else if (current) {
    aliasTitulo.value = 'Otro';
    aliasOtro.value = current;
    aliasOtro.style.display = '';
  } else {
    aliasTitulo.value = '';
    aliasOtro.style.display = 'none';
    aliasOtro.value = '';
  }
}

function onAliasTituloChange(){
  const sel  = document.getElementById('aliasTitulo').value;
  const otro = document.getElementById('aliasTituloOtro');
  if (sel === 'Otro') {
    otro.style.display = '';
    otro.focus();
    document.getElementById('alias').value = otro.value.trim();
  } else {
    otro.style.display = 'none';
    otro.value = '';
    document.getElementById('alias').value = sel;
  }
}

function onAliasOtroInput(){
  document.getElementById('alias').value = document.getElementById('aliasTituloOtro').value.trim();
}

// ════════════════════════════════════════════════════════════
//  TALLERES (congresos, 20/08/2026, portado de elascenso/event) — ver
//  ApiRestEvent/brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md.
//  currentEvent.talleres viene embebido en GET /event/{id} (proxy puro,
//  sin filtrado de campos) — no hace falta un endpoint aparte.
// ════════════════════════════════════════════════════════════
function getEventTalleres() {
  const ev = currentEvent || {};
  return (ev.talleres || []).filter(t => t.activo !== false);
}

// Disponibilidad de cupos con mensajes de urgencia — mismo helper
// reusado en el selector de talleres (.taller-sesion-cupo) y en la
// agenda de talleres en popup, para que ambos muestren exactamente el
// mismo criterio. Umbrales: ≤10 disponibles "Quedan N cupos", ≤3
// "Últimos N cupos", 0 "AGOTADO". `cupo=null` es "sin límite" — nunca
// entra en la escala de urgencia.
const TALLER_CUPO_LOW_THRESHOLD = 10;
const TALLER_CUPO_CRITICAL_THRESHOLD = 3;

function formatCupoAvailability(cupo, ocupados){
  if (cupo === null || cupo === undefined) {
    return { text: t('registration.tallerSinLimite'), level: 'unlimited' };
  }
  const disponibles = Math.max(0, cupo - (ocupados || 0));

  if (disponibles <= 0) {
    return { text: t('registration.tallerAgotado'), level: 'soldout' };
  }
  if (disponibles <= TALLER_CUPO_CRITICAL_THRESHOLD) {
    return { text: t('registration.tallerCuposUltimos').replace('%d', disponibles), level: 'critical' };
  }
  if (disponibles <= TALLER_CUPO_LOW_THRESHOLD) {
    return { text: t('registration.tallerCuposQuedan').replace('%d', disponibles), level: 'low' };
  }
  return { text: t('registration.tallerCuposDisponibles').replace('%d', disponibles), level: 'plenty' };
}

function formatTallerPrice(amountBOB, amountUsd){
  const bobTxt = formatMoney(amountBOB);
  if (amountUsd === null || amountUsd === undefined) return bobTxt;
  return `${bobTxt} (US$${Number(amountUsd).toFixed(2)})`;
}

// Precio USD efectivo de una sesión (override) o su taller (fallback) —
// null si ninguno tiene precio USD cargado.
function resolverTallerPrecioUsd(taller, sesion){
  if (sesion.precioUsd !== null && sesion.precioUsd !== undefined) return Number(sesion.precioUsd);
  if (taller.precioUsd !== null && taller.precioUsd !== undefined) return Number(taller.precioUsd);
  return null;
}

function getSelectedTallerSesionIds() {
  return Array.from(document.querySelectorAll('.taller-sesion-cb:checked'))
    .map(cb => Number(cb.dataset.sesionId));
}

/**
 * Devuelve la estructura `talleres[]` lista para enviar al backend: cada
 * elemento con `sesion_congreso_id`/`taller_id` y los datos visibles. El
 * `unit_price` se manda como referencia, pero el backend siempre
 * recalcula (mismo criterio que precioCategoria).
 */
function collectSelectedTalleresForParticipant() {
  const talleres = getEventTalleres();
  const talleresById = Object.fromEntries(talleres.map(t => [String(t.id), t]));
  const conCosto = !!(currentEvent && currentEvent.talleresConCosto);

  const out = [];
  document.querySelectorAll('.taller-sesion-cb:checked').forEach(cb => {
    const sesionId = Number(cb.dataset.sesionId);
    const tallerId = Number(cb.dataset.tallerId);
    const taller = talleresById[String(tallerId)];
    const sesion = (taller?.sesiones || []).find(s => Number(s.id) === sesionId);
    if (!taller || !sesion) return;

    let unitPrice = 0;
    let unitPriceUsd = 0;
    let unitPriceUsdFaltante = false;
    if (conCosto) {
      if (sesion.precio !== null && sesion.precio !== undefined) unitPrice = Number(sesion.precio);
      else if (taller.precio !== null && taller.precio !== undefined) unitPrice = Number(taller.precio);

      if (currentEvent && currentEvent.usdPrecioFijo) {
        if (sesion.precioUsd !== null && sesion.precioUsd !== undefined) unitPriceUsd = Number(sesion.precioUsd);
        else if (taller.precioUsd !== null && taller.precioUsd !== undefined) unitPriceUsd = Number(taller.precioUsd);
        else unitPriceUsdFaltante = true;
      }
    }

    out.push({
      taller_id: tallerId,
      sesion_congreso_id: sesionId,
      taller_nombre: taller.nombre,
      modalidad: taller.modalidad,
      titulo: sesion.titulo,
      sala: sesion.sala || '',
      fecha: sesion.fecha,
      hora_inicio: (sesion.horaInicio || '').slice(0, 5),
      hora_fin: (sesion.horaFin || '').slice(0, 5),
      unit_price: unitPrice,
      unit_price_usd: unitPriceUsd,
      unit_price_usd_faltante: unitPriceUsdFaltante,
    });
  });
  return out;
}

/**
 * Detección de conflictos de horario entre las sesiones seleccionadas —
 * agrupa por fecha y evalúa pares por intersección de intervalos
 * (a.start < b.end && a.end > b.start). Devuelve un Set de sesion_id en
 * conflicto (para que la UI los marque).
 */
function detectTallerConflicts() {
  const seleccionadas = collectSelectedTalleresForParticipant();
  const porFecha = {};
  seleccionadas.forEach(s => {
    if (!s.fecha) return;
    (porFecha[s.fecha] = porFecha[s.fecha] || []).push(s);
  });

  const conflictos = new Set();
  Object.values(porFecha).forEach(lista => {
    for (let i = 0; i < lista.length; i++) {
      for (let j = i + 1; j < lista.length; j++) {
        const a = lista[i], b = lista[j];
        const aStart = timeToMinutes(a.hora_inicio);
        const aEnd = timeToMinutes(a.hora_fin);
        const bStart = timeToMinutes(b.hora_inicio);
        const bEnd = timeToMinutes(b.hora_fin);
        if (aStart < bEnd && aEnd > bStart) {
          conflictos.add(a.sesion_congreso_id);
          conflictos.add(b.sesion_congreso_id);
        }
      }
    }
  });
  return conflictos;
}

function timeToMinutes(tm) {
  if (!tm) return 0;
  const [h, m] = tm.split(':').map(Number);
  return h * 60 + (m || 0);
}

/**
 * True si TODOS los talleres REQUIRED activos tienen al menos una sesión
 * seleccionada. True trivialmente si no hay ninguno REQUIRED.
 */
function allRequiredTalleresSelected() {
  const talleres = getEventTalleres().filter(t => t.modalidad === 'REQUIRED');
  if (talleres.length === 0) return true;
  const selected = new Set(getSelectedTallerSesionIds());
  return talleres.every(t => (t.sesiones || []).some(s => selected.has(Number(s.id))));
}

/**
 * Renderiza el selector de talleres para el participante que se está
 * editando. Se llama desde buildEventUI() y desde editParticipant() para
 * repintar las selecciones previas.
 */
function renderTalleresSelector() {
  const section = document.getElementById('talleres-section');
  const grid = document.getElementById('talleresGrid');
  if (!section || !grid) return;

  const talleres = getEventTalleres();
  if (!talleres || talleres.length === 0) {
    section.style.display = 'none';
    grid.innerHTML = '';
    return;
  }

  const usdFijoAviso = document.getElementById('talleres-usd-fijo-aviso');
  if (usdFijoAviso) usdFijoAviso.style.display = (currentEvent && currentEvent.usdPrecioFijo) ? '' : 'none';

  const conCosto = !!(currentEvent && currentEvent.talleresConCosto);
  const requiredFirst = [...talleres].sort((a, b) => {
    if (a.modalidad === b.modalidad) return (a.orden || 0) - (b.orden || 0);
    return a.modalidad === 'REQUIRED' ? -1 : 1;
  });

  const html = requiredFirst.map(taller => {
    const sesiones = (taller.sesiones || []);
    if (!sesiones.length) return '';

    const sesionesHtml = sesiones.map(s => {
      const full = s.cupo !== null && s.disponibles !== null && s.disponibles <= 0;
      const precioUsdEfectivo = resolverTallerPrecioUsd(taller, s);
      const priceTxt = conCosto
        ? (s.precio !== null && s.precio !== undefined
            ? `${t('registration.tallerPrice')}: ${formatTallerPrice(Number(s.precio), precioUsdEfectivo)}`
            : (taller.precio !== null && taller.precio !== undefined
              ? `${t('registration.tallerPrice')}: ${formatTallerPrice(Number(taller.precio), precioUsdEfectivo)}`
              : ''))
        : '';
      const cupoInfo = formatCupoAvailability(s.cupo, s.ocupados);
      return `
        <label class="taller-sesion-row ${full ? 'is-full' : ''}" data-sesion-id="${s.id}">
          <input type="checkbox" class="taller-sesion-cb"
                 data-sesion-id="${s.id}" data-taller-id="${taller.id}"
                 ${full ? 'disabled' : ''}>
          <span class="taller-sesion-time">${escHtml((s.horaInicio || '').slice(0,5))}–${escHtml((s.horaFin || '').slice(0,5))}</span>
          <span class="taller-sesion-room">${escHtml(s.sala || '')}</span>
          <span class="taller-sesion-cupo cupo-${cupoInfo.level}">${escHtml(cupoInfo.text)}</span>
          ${priceTxt ? `<span class="taller-sesion-price">${escHtml(priceTxt)}</span>` : ''}
          <span class="taller-sesion-conflict" data-conflict-for="${s.id}" style="display:none;">⚠ ${t('registration.tallerConflict')}</span>
        </label>`;
    }).join('');

    return `
      <div class="taller-block taller-${taller.modalidad.toLowerCase()}" data-taller-id="${taller.id}">
        <div class="taller-block-header">
          <span class="taller-badge taller-badge-${taller.modalidad.toLowerCase()}">${taller.modalidad === 'REQUIRED' ? t('registration.tallerRequerido') : t('registration.tallerOpcional')}</span>
          <h4>${escHtml(taller.nombre)}</h4>
        </div>
        ${taller.descripcion ? `<p class="taller-block-desc">${escHtml(taller.descripcion)}</p>` : ''}
        <div class="taller-sesiones">${sesionesHtml}</div>
      </div>`;
  }).join('');

  grid.innerHTML = html;
  section.style.display = '';

  // Reflejar selecciones del participante que se está editando.
  const editIdx = parseInt(document.getElementById('editIndex').value, 10);
  if (editIdx >= 0 && participants[editIdx]) {
    const sel = new Set(
      (participants[editIdx].talleres || []).map(x => Number(x.sesion_congreso_id))
    );
    document.querySelectorAll('.taller-sesion-cb').forEach(cb => {
      if (sel.has(Number(cb.dataset.sesionId))) cb.checked = true;
    });
  }

  document.querySelectorAll('.taller-sesion-cb').forEach(cb => {
    cb.addEventListener('change', () => updateTallerConflictsUI());
  });

  updateTallerConflictsUI();
}

/**
 * Repinta los badges de conflicto y el aviso de REQUIRED faltante según
 * las selecciones actuales. Llamar después de cualquier cambio en los
 * checkboxes.
 */
function updateTallerConflictsUI() {
  const conflictos = detectTallerConflicts();
  document.querySelectorAll('[data-conflict-for]').forEach(el => {
    const id = Number(el.dataset.conflictFor);
    el.style.display = conflictos.has(id) ? '' : 'none';
  });
  const reqWarn = document.getElementById('talleres-required-warning');
  if (reqWarn) reqWarn.style.display = allRequiredTalleresSelected() ? 'none' : '';
}

// ════════════════════════════════════════════════════════════
//  AGENDA DE TALLERES EN POPUP — distinta de #agendaModal (esa es la
//  agenda GENERAL del evento). Reusa formatAgendaTime()/formatAgendaDay()
//  y las clases .agenda-item/.agenda-chips ya definidas para la agenda
//  general (mismo look).
// ════════════════════════════════════════════════════════════
function renderTalleresAgendaModal(){
  const content = document.getElementById('talleresAgendaModalContent');
  if (!content) return;

  document.getElementById('talleresAgendaModalTitle').textContent = t('registration.talleresAgendaTitle');

  const talleres = getEventTalleres();
  const conCosto = !!(currentEvent && currentEvent.talleresConCosto);

  const items = [];
  talleres.forEach(taller => {
    (taller.sesiones || []).forEach(s => items.push({ taller, sesion: s }));
  });
  items.sort((a, b) => {
    const ka = (a.sesion.fecha || '') + (a.sesion.horaInicio || '');
    const kb = (b.sesion.fecha || '') + (b.sesion.horaInicio || '');
    return ka.localeCompare(kb);
  });

  if (items.length === 0) {
    content.innerHTML = `<p style="font-size:13px;color:var(--muted);">${t('registration.talleresAgendaEmpty')}</p>`;
    return;
  }

  const groups = new Map();
  items.forEach(it => {
    const key = it.sesion.fecha || '';
    if (!groups.has(key)) groups.set(key, []);
    groups.get(key).push(it);
  });
  const showDayHeaders = groups.size > 1;

  let html = '';
  groups.forEach((dayItems, fecha) => {
    if (showDayHeaders) {
      html += `<div class="agenda-day-header">${fecha ? escHtml(formatAgendaDay(fecha)) : ''}</div>`;
    }
    dayItems.forEach(({ taller, sesion: s }) => {
      const time = (s.horaInicio && s.horaFin)
        ? `${formatAgendaTime(s.horaInicio)} – ${formatAgendaTime(s.horaFin)}`
        : formatAgendaTime(s.horaInicio);
      const modalidadChip = `<span class="agenda-chip">${taller.modalidad === 'REQUIRED' ? t('registration.tallerRequerido') : t('registration.tallerOpcional')}</span>`;
      const roomChip = s.sala ? `<span class="agenda-chip">📍 ${escHtml(s.sala)}</span>` : '';
      let priceChip = '';
      if (conCosto) {
        const precio = (s.precio !== null && s.precio !== undefined) ? Number(s.precio)
          : (taller.precio !== null && taller.precio !== undefined ? Number(taller.precio) : 0);
        const precioUsdEfectivo = resolverTallerPrecioUsd(taller, s);
        priceChip = `<span class="agenda-chip">${escHtml(formatTallerPrice(precio, precioUsdEfectivo))}</span>`;
      }
      const cupoInfo = formatCupoAvailability(s.cupo, s.ocupados);
      const cupoChip = cupoInfo.level !== 'unlimited'
        ? `<span class="agenda-chip cupo-${cupoInfo.level}">${escHtml(cupoInfo.text)}</span>`
        : '';

      html += `
        <div class="agenda-item">
          <div class="agenda-time">${escHtml(time)}</div>
          <div class="agenda-body">
            <div class="agenda-title">${escHtml(taller.nombre)}</div>
            ${taller.descripcion ? `<div class="agenda-desc">${escHtml(taller.descripcion)}</div>` : ''}
            <div class="agenda-chips">${modalidadChip}${roomChip}${priceChip}${cupoChip}</div>
          </div>
        </div>`;
    });
  });

  content.innerHTML = html;
}

function openTalleresAgendaModal(){
  renderTalleresAgendaModal();
  openModal(document.getElementById('talleresAgendaModal'));
}

function closeTalleresAgendaModal(){
  closeModal(document.getElementById('talleresAgendaModal'));
}

// ════════════════════════════════════════════════════════════
//  PREGUNTAS DINÁMICAS (formTypes[].preguntas)
// ════════════════════════════════════════════════════════════

// Renderiza el bloque de preguntas dinámicas del tipo de formulario elegido,
// agrupadas por `seccion` (orden de primera aparición) y ordenadas por `orden`.
function renderDynamicQuestions(ft){
  const section   = document.getElementById('dynamicQuestionsSection');
  const container = document.getElementById('dynamicQuestionsContainer');
  container.innerHTML = '';

  const preguntas = Array.isArray(ft?.preguntas) ? ft.preguntas : [];
  if (preguntas.length === 0) {
    section.style.display = 'none';
    return;
  }
  section.style.display = '';

  const groups = [];
  const groupIndex = {};
  preguntas.forEach(q => {
    const key = q.seccion || '';
    if (!(key in groupIndex)) { groupIndex[key] = groups.length; groups.push({ seccion: key, items: [] }); }
    groups[groupIndex[key]].items.push(q);
  });
  groups.forEach(g => g.items.sort((a, b) => (a.orden ?? 0) - (b.orden ?? 0)));

  groups.forEach(g => {
    if (g.seccion) {
      const heading = document.createElement('div');
      heading.style.cssText = 'font-size:13px;font-weight:700;color:var(--secondary);text-transform:uppercase;margin:14px 0 8px;';
      heading.textContent = g.seccion;
      container.appendChild(heading);
    }
    g.items.forEach(q => {
      const el = renderDynamicField(q);
      if (el) container.appendChild(el);
    });
  });
}

// tipo_input=file queda fuera de alcance (no hay endpoint de subida de archivos
// en el backend todavía) — se omite silenciosamente.
const DYNAMIC_FIELD_NATIVE_TYPES = ['text', 'email', 'date', 'tel', 'number', 'url'];

function renderDynamicField(q){
  const type = (q.tipo_input || '').toLowerCase();
  if (type === 'file') return null;

  const req = q.obligatorio ? ' <span class="req">*</span>' : '';

  if (type === 'radio' || type === 'checkbox') {
    const opts = (q.options || []).slice().sort((a, b) => (a.order ?? 0) - (b.order ?? 0));
    const wrap = document.createElement('div');
    wrap.style.marginBottom = '16px';
    wrap.innerHTML = `
      <label>${escHtml(q.etiqueta)}${req}</label>
      <div class="radio-group">
        ${opts.map(o => `
          <label class="radio-option">
            <input type="${type}" name="dynq_${q.id}" value="${escHtml(o.option_text)}"
                   id="dynq_${q.id}_opt_${o.id}" onchange="syncRadioStyles()">
            <span>${escHtml(o.option_text)}</span>
          </label>`).join('')}
      </div>
      <span class="field-error" id="err-dynq-${q.id}" data-i18n="registration.errRequired">${escHtml(t('registration.errRequired'))}</span>`;
    return wrap;
  }

  const wrap = document.createElement('div');
  wrap.className = 'form-row';

  if (type === 'select') {
    const opts = (q.options || []).slice().sort((a, b) => (a.order ?? 0) - (b.order ?? 0));
    wrap.innerHTML = `
      <div class="form-group full">
        <label>${escHtml(q.etiqueta)}${req}</label>
        <select id="dynq_${q.id}">
          <option value="">${escHtml(q.placeholder || t('registration.relSelect'))}</option>
          ${opts.map(o => `<option value="${escHtml(o.option_text)}">${escHtml(o.option_text)}</option>`).join('')}
        </select>
        <span class="field-error" id="err-dynq-${q.id}" data-i18n="registration.errRequired">${escHtml(t('registration.errRequired'))}</span>
      </div>`;
    return wrap;
  }

  if (type === 'textarea') {
    wrap.innerHTML = `
      <div class="form-group full">
        <label>${escHtml(q.etiqueta)}${req}</label>
        <textarea id="dynq_${q.id}" placeholder="${escHtml(q.placeholder || '')}"></textarea>
        <span class="field-error" id="err-dynq-${q.id}" data-i18n="registration.errRequired">${escHtml(t('registration.errRequired'))}</span>
      </div>`;
    return wrap;
  }

  const inputType = DYNAMIC_FIELD_NATIVE_TYPES.includes(type) ? type : 'text';
  wrap.innerHTML = `
    <div class="form-group full">
      <label>${escHtml(q.etiqueta)}${req}</label>
      <input type="${inputType}" id="dynq_${q.id}" placeholder="${escHtml(q.placeholder || '')}">
      <span class="field-error" id="err-dynq-${q.id}" data-i18n="registration.errRequired">${escHtml(t('registration.errRequired'))}</span>
    </div>`;
  return wrap;
}

// ════════════════════════════════════════════════════════════
//  DONACIÓN
// ════════════════════════════════════════════════════════════
function setDonation(amount){
  document.getElementById('donacion').value = amount;
  document.querySelectorAll('.donation-btn').forEach(b => {
    b.classList.toggle('active', parseInt(b.textContent.replace('$','')) === amount);
  });
}
function clearDonationBtns(){
  document.querySelectorAll('.donation-btn').forEach(b => b.classList.remove('active'));
}

// ════════════════════════════════════════════════════════════
//  PROMO CODE  →  valida código contra endpoint externo
// ════════════════════════════════════════════════════════════
// Solo para preview en UI — el cálculo real y definitivo siempre viene del
// backend (_registro_validacion.php), que vuelve a resolver el código contra
// evento.promoCodes en vez de confiar en lo que mande el cliente.
function calcPromoDescuento(baseAmount){
  if (appliedPromoValue <= 0) return 0;
  if (appliedPromoType === 'percentage') {
    return Math.round(baseAmount * appliedPromoValue * 100) / 100;
  }
  return Math.max(0, Math.round((baseAmount - appliedPromoValue) * 100) / 100);
}

async function applyPromo(){
  const code   = document.getElementById('entered_promotion_code').value.trim().toUpperCase();
  const okEl   = document.getElementById('promoOk');
  const failEl = document.getElementById('promoFail');
  const btn    = document.getElementById('btnApplyPromo');

  okEl.style.display = failEl.style.display = 'none';
  appliedPromoType  = 'fixed_price';
  appliedPromoValue = 0;
  if (!code) return;

  if (btn) { btn.disabled = true; btn.textContent = '…'; }

  try {
    const eventId = currentEvent?.id;
    if (!eventId) throw new Error('No event selected');

    const data = await fetchJson(
      `${API_BASE}/promo.php?event_id=${encodeURIComponent(eventId)}&code=${encodeURIComponent(code)}`
    );

    const promo = Array.isArray(data.data) ? data.data[0] : null;
    const type  = promo?.discount_type || 'fixed_price';
    const value = type === 'percentage' ? parseFloat(promo?.discount_percent) : parseFloat(promo?.price);

    if (!data.success || !promo || isNaN(value)) {
      failEl.textContent = '✗ Invalid or expired promo code.';
      failEl.style.display = 'block';
    } else {
      appliedPromoType  = type;
      appliedPromoValue = value;
      okEl.textContent  = type === 'percentage'
        ? `✓ Promo applied: ${(value * 100).toFixed(0)}% off`
        : `✓ Promo applied: ${formatMoney(value)} per registration`;
      okEl.style.display = 'block';
    }
  } catch(e) {
    // fetchJson() lanza cuando la API devuelve success:false (código inválido,
    // ya utilizado, etc.) — e.message trae el motivo real (evento['error']).
    failEl.textContent = '✗ ' + (e.message || 'Could not validate promo code. Try again.');
    failEl.style.display = 'block';
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = 'Apply'; }
  }
}

// ════════════════════════════════════════════════════════════
//  VALIDACIÓN DEL FORMULARIO
// ════════════════════════════════════════════════════════════
function showErr(id, msg){ const e = document.getElementById(id); if(msg) e.textContent = msg; e.classList.add('visible'); }
function hideErr(id){ document.getElementById(id)?.classList.remove('visible'); }
function markInvalid(id){ document.getElementById(id)?.classList.add('is-invalid'); }
function markValid(id){ document.getElementById(id)?.classList.remove('is-invalid'); }

// Asigna día/mes/año en los selects de fecha de nacimiento.
// Normaliza el valor con parseInt para manejar enteros, ceros a la
// izquierda ("05" → "5") y undefined sin romper el select.
function setDateBirth(dia, mes, anio){
  const d = parseInt(dia,  10);
  const m = parseInt(mes,  10);
  const y = parseInt(anio, 10);
  document.getElementById('date_birth_day').value   = isNaN(d) ? '' : String(d);
  document.getElementById('date_birth_month').value = isNaN(m) ? '' : String(m);
  document.getElementById('date_birth_year').value  = isNaN(y) ? '' : String(y);
  calcularEdad();
}

function calcularEdad(){
  const d = parseInt(document.getElementById('date_birth_day').value);
  const m = parseInt(document.getElementById('date_birth_month').value);
  const y = parseInt(document.getElementById('date_birth_year').value);
  const el = document.getElementById('edad_display');
  const hidden = document.getElementById('edad_calculada');
  if (!d || !m || !y){ el.textContent = ''; hidden.value = ''; return; }
  const hoy = new Date();
  let edad = hoy.getFullYear() - y;
  if (hoy.getMonth() + 1 < m || (hoy.getMonth() + 1 === m && hoy.getDate() < d)) edad--;
  el.textContent = edad + ' years';
  hidden.value = edad;
}
['date_birth_day','date_birth_month','date_birth_year'].forEach(function(id){
  document.getElementById(id).addEventListener('change', calcularEdad);
});

function validateForm(){
  let ok = true;

  // Categoría (solo validar si el tipo de formulario la requiere — default
  // true para no cambiar el comportamiento de los form_types existentes).
  const requiereCategoria = selectedFormType?.requiereCategoria ?? true;
  if (requiereCategoria && !document.querySelector('.category-card.selected')){
    document.getElementById('catError').classList.add('visible');
    ok = false;
  } else {
    document.getElementById('catError').classList.remove('visible');
  }

  const required = [
    ['nombre','err-nombre'], ['apellido','err-apellido'], ['alias','err-alias'],
    ['genero','err-genero'], ['tipoDocumento','err-tipoDoc'], ['numeroDocumento','err-numDoc'],
    ['email','err-email'], ['direccion','err-dir'], ['ciudad','err-ciudad'],
    ['celular','err-cel'], ['nombre_emergencia','err-emerg'], ['relacion_emergencia','err-rel']
  ];
  required.forEach(([fid, eid]) => {
    const v = (document.getElementById(fid)?.value || '').trim();
    if (!v){ markInvalid(fid); showErr(eid); ok = false; }
    else   { markValid(fid);   hideErr(eid); }
  });

  // DOB
  const dobFilled = document.getElementById('date_birth_day').value &&
                    document.getElementById('date_birth_month').value &&
                    document.getElementById('date_birth_year').value;
  if (!dobFilled){ showErr('err-dob'); ok = false; } else { hideErr('err-dob'); }

  // Equipo (solo validar si el tipo de formulario tiene hasTeam)
  if (selectedFormType?.hasTeam) {
    const v = document.getElementById('equipo_id').value;
    if (!v){ markInvalid('equipo_id'); showErr('err-equipo'); ok = false; }
    else   { markValid('equipo_id');   hideErr('err-equipo'); }
  }

  // Camiseta (solo validar si el tipo de formulario incluye camiseta)
  if (selectedFormType?.hasshirt) {
    const poleraOp = document.querySelector('input[name="polera_opcion"]:checked');
    if (!poleraOp){ ok = false; }
    else if (poleraOp.value === 'con' && !document.getElementById('tamanioPolera').value){
      showErr('err-shirt'); ok = false;
    } else { hideErr('err-shirt'); }
  }

  // Email format
  const em = document.getElementById('email').value;
  if (em && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)){
    markInvalid('email'); showErr('err-email', 'Invalid email format.'); ok = false;
  }

  // Preguntas dinámicas obligatorias
  (selectedFormType?.preguntas || []).forEach(q => {
    if (!q.obligatorio) return;
    const type  = (q.tipo_input || '').toLowerCase();
    const errId = 'err-dynq-' + q.id;
    if (type === 'file') return; // fuera de alcance, no se renderiza ni se exige
    if (type === 'radio' || type === 'checkbox') {
      const anyChecked = !!document.querySelector(`input[name="dynq_${q.id}"]:checked`);
      if (!anyChecked) { showErr(errId); ok = false; } else { hideErr(errId); }
    } else {
      const fid = 'dynq_' + q.id;
      const v = (document.getElementById(fid)?.value || '').trim();
      if (!v) { markInvalid(fid); showErr(errId); ok = false; } else { markValid(fid); hideErr(errId); }
    }
  });

  return ok;
}

// Reglas de inscripción grupal del form_type elegido — vienen de la API
// externa (form_types.permite_inscripcion_grupal/max_integrantes_grupo/
// descuento_registrante_pct), no de una constante local. Si el form_type no
// permite inscripción grupal, no hay tope ni descuento de grupo.
function getGroupRules(){
  const ft = selectedFormType || {};
  return {
    enabled: !!ft.permite_inscripcion_grupal,
    max: Number(ft.max_integrantes_grupo) || 0,
    pct: Number(ft.descuento_registrante_pct) || 0,
  };
}

// ════════════════════════════════════════════════════════════
//  GUARDAR / ACTUALIZAR PARTICIPANTE
// ════════════════════════════════════════════════════════════
function saveParticipant(e){
  e.preventDefault();
  const editIndexVal = parseInt(document.getElementById('editIndex').value);
  const group = getGroupRules();
  if (editIndexVal < 0 && group.enabled && group.max > 0 && participants.length >= group.max) {
    alert(`Has alcanzado el máximo de ${group.max} participantes por inscripción.`);
    return;
  }
  const summaryEl = document.getElementById('err-form-summary');
  if (!validateForm()) {
    summaryEl.style.display = 'inline';
    return;
  }
  summaryEl.style.display = 'none';

  const catEl     = document.querySelector('.category-card.selected');
  const poleraOp  = document.querySelector('input[name="polera_opcion"]:checked');
  const polera    = poleraOp ? poleraOp.value : 'sin';
  const shirtSize = polera === 'con' ? document.getElementById('tamanioPolera').value : null;

  // Kit/tallas/stock (11/08/2026, portado 12/08/2026) — id ahora es el id
  // numérico real (antes guardaba el nombre); nombre/precio se leen de
  // data-* en vez de textContent para no depender del layout de la
  // tarjeta. talla/sexo viajan solo si el ítem los requiere — el backend
  // los revalida contra el stock real.
  const souvenirs = [];
  let souvenirPickerOk = true;
  document.querySelectorAll('.souvenir-card.checked').forEach(sc => {
    const tallaSel = sc.querySelector('.souvenir-talla-select');
    const sexoSel  = sc.querySelector('.souvenir-sexo-select');
    const talla    = tallaSel ? tallaSel.value : '';
    const sexo     = sexoSel ? sexoSel.value : '';
    if ((tallaSel && !talla) || (sexoSel && !sexo)) souvenirPickerOk = false;

    // Deprecación hasshirt/costo_polera (12/08) — un ítem incluido=true
    // (ya viene en precio_base) nunca suma al total de souvenirs, sin
    // importar lo que diga data-price; el backend revalida esto mismo en
    // RegistroValidacionService.
    const incluido = sc.dataset.incluido === '1';
    souvenirs.push({
      id: Number(sc.dataset.id),
      nombre: sc.dataset.name,
      precio: incluido ? 0 : parseFloat(sc.dataset.price),
      talla: talla || null,
      sexo: sexo || null,
    });
  });
  if (!souvenirPickerOk) {
    alert(t('registration.errItemSize'));
    return;
  }

  const donVal   = selectedFormType.hasDonation
    ? (parseFloat(document.getElementById('donacion').value) || 0)
    : 0;

  const answers = [];
  (selectedFormType?.preguntas || []).forEach(q => {
    const type = (q.tipo_input || '').toLowerCase();
    if (type === 'file') return;
    if (type === 'checkbox') {
      document.querySelectorAll(`input[name="dynq_${q.id}"]:checked`).forEach(c => {
        answers.push({ form_types_id: Number(selectedFormType.id), question_id: Number(q.id), value: c.value });
      });
    } else if (type === 'radio') {
      const sel = document.querySelector(`input[name="dynq_${q.id}"]:checked`);
      if (sel) answers.push({ form_types_id: Number(selectedFormType.id), question_id: Number(q.id), value: sel.value });
    } else {
      const v = (document.getElementById('dynq_' + q.id)?.value || '').trim();
      if (v) answers.push({ form_types_id: Number(selectedFormType.id), question_id: Number(q.id), value: v });
    }
  });

  const participant = {
    nombre:     document.getElementById('nombre').value.trim(),
    apellido:   document.getElementById('apellido').value.trim(),
    alias:      document.getElementById('alias').value.trim(),
    genero:     document.getElementById('genero').value,
    tipoDocumento:   document.getElementById('tipoDocumento').value,
    numeroDocumento: document.getElementById('numeroDocumento').value.trim(),
    polera:     polera === 'con' ? shirtSize : 'No shirt',
    precioPolera: polera === 'con' ? Number(selectedFormType?.costo_polera ?? 0) : 0,
    nacimiento: {
      dia:  document.getElementById('date_birth_day').value,
      mes:  document.getElementById('date_birth_month').value,
      anio: document.getElementById('date_birth_year').value
    },
    edad: parseInt(document.getElementById('edad_calculada').value) || 0,
    correo:     document.getElementById('email').value.trim(),
    direccion:  document.getElementById('direccion').value.trim(),
    ciudad:     document.getElementById('ciudad').value.trim(),
    telefono:   itiTel ? itiTel.getNumber() : document.getElementById('telefono').value,
    contacto_emergencia: {
      nombre:  document.getElementById('nombre_emergencia').value.trim(),
      celular: itiCel ? itiCel.getNumber() : document.getElementById('celular').value,
      relacion: document.getElementById('relacion_emergencia').value
    },
    donacion:         donVal,
    souvenirs:        souvenirs,
    equipoId:         selectedFormType?.hasTeam ? (Number(document.getElementById('equipo_id').value) || null) : null,
    quiereDelivery:   selectedFormType?.hasDelivery ? document.getElementById('quiere_delivery').checked : false,
    // Mapa de ubicación (12/08/2026, portado de elascenso/event) —
    // opcional: null si nunca se tildó el checkbox (initDeliveryMap()
    // nunca corrió) o si se destildó después de haber marcado un pin.
    deliveryLat:      (selectedFormType?.hasDelivery && document.getElementById('quiere_delivery').checked) ? deliveryLat : null,
    deliveryLng:      (selectedFormType?.hasDelivery && document.getElementById('quiere_delivery').checked) ? deliveryLng : null,
    // Sin categoría (form_type con requiereCategoria=false): no hay
    // catEl seleccionado, se usa el nombre del form_type y precio 0.
    categoria:        catEl ? catEl.dataset.id : (selectedFormType?.name || 'General'),
    categoriaNombre:  catEl ? (catEl.dataset.name || '') : (selectedFormType?.name || ''),
    precioCategoria:  catEl ? parseFloat(catEl.dataset.price) : 0,
    promoDescuento:   calcPromoDescuento((catEl ? parseFloat(catEl.dataset.price) : 0) + (polera === 'con' ? Number(selectedFormType?.costo_polera ?? 0) : 0)),
    promoDiscountType:  appliedPromoValue > 0 ? appliedPromoType  : null,
    promoDiscountValue: appliedPromoValue > 0 ? appliedPromoValue : null,
    promoCodigo:      document.getElementById('entered_promotion_code').value.trim().toUpperCase(),
    answers:          answers,
    // Talleres (congresos, 20/08/2026) — igual que conflictos de horario,
    // no bloquea el guardado del participante acá (solo aviso visual); el
    // backend es la autoridad real (capacidad/solape/requeridos).
    talleres:         collectSelectedTalleresForParticipant()
  };

  const idx = parseInt(document.getElementById('editIndex').value);
  if (idx >= 0) participants[idx] = participant;
  else          participants.push(participant);

  resetParticipantForm();
  renderParticipantsList();
  document.getElementById('participantsList').style.display = 'block';
  document.getElementById('btnGuardar').textContent = 'Save Participant';
  document.getElementById('btnCancelar').style.display = 'none';
  document.getElementById('editIndex').value = '-1';
  document.getElementById('participantsList').scrollIntoView({ behavior: 'smooth' });
}

function resetParticipantForm(){
  document.getElementById('participantForm').reset();
  // intl-tel-input mantiene su propio estado interno y no escucha el evento
  // nativo `reset` del form — sin esto, el teléfono del participante anterior
  // (seteado por editParticipant()/fillFromUser() vía setNumber()) queda visible.
  if (itiTel) itiTel.setNumber('');
  if (itiCel) itiCel.setNumber('');
  document.querySelectorAll('.category-card').forEach(c => { c.classList.remove('selected'); c.setAttribute('aria-pressed', 'false'); });
  // Los ítems incluido=true (dataset.incluido='1') vuelven a su default
  // "marcado" para el próximo participante (opt-in por defecto, pero se
  // puede destildar — ver deprecación hasshirt/costo_polera, 12/08).
  document.querySelectorAll('.souvenir-card').forEach(c => { c.classList.remove('checked'); c.setAttribute('aria-pressed', 'false'); });
  document.querySelectorAll('.souvenir-card[data-incluido="1"]').forEach(c => { c.classList.add('checked'); c.setAttribute('aria-pressed', 'true'); });
  document.getElementById('shirtSizeContainer').style.display = 'none';
  // Mapa de delivery (12/08/2026, portado de elascenso/event) —
  // .reset() destilda #quiere_delivery pero no dispara 'change', así que
  // el mapa no se esconde solo; el pin se resetea a null para el
  // próximo participante (cada uno puede querer una ubicación distinta).
  document.getElementById('deliveryMapContainer').style.display = 'none';
  deliveryLat = null;
  deliveryLng = null;
  document.getElementById('promoOk').style.display   = 'none';
  document.getElementById('promoFail').style.display = 'none';
  // .reset() ya vuelve a marcar "sin polera" como checked (tiene el atributo
  // `checked` en el HTML) — sincronizamos la clase visual en vez de borrarla
  // a ciegas, para no dejar la opción por defecto sin resaltar.
  syncRadioStyles();
  appliedPromoType  = 'fixed_price';
  appliedPromoValue = 0;
  document.getElementById('edad_display').textContent = '';
  document.getElementById('edad_calculada').value = '';
}

// ════════════════════════════════════════════════════════════
//  LISTA DE PARTICIPANTES
// ════════════════════════════════════════════════════════════
function isPaidEditLocked(){
  return editMode === 'paid' && !paidEditUnlocked;
}

function renderParticipantsList(){
  const cont   = document.getElementById('participantsContainer');
  const locked = isPaidEditLocked();
  cont.innerHTML = '';
  participants.forEach((p, i) => {
    const row = document.createElement('div');
    row.className = 'participant-row';
    row.id = 'prow-' + i;
    const souvTotal = (p.souvenirs || []).reduce((s, sv) => s + parseFloat(sv.precio || 0), 0);
    const subtotal  = Math.max(0, (p.precioCategoria || 0) + (p.precioPolera || 0) + souvTotal + (p.donacion || 0) - (p.promoDescuento || 0));
    const shirtTxt  = p.polera && p.polera !== 'No shirt'
      ? `<strong>Talla: ${escHtml(p.polera)}</strong> · ` : '';
    const actionsHtml = locked ? '' : `
      <div class="participant-actions">
        <button class="btn btn-sm btn-secondary" onclick="editParticipant(${i})">✏ Edit</button>
        <button class="btn btn-sm btn-danger"    onclick="removeParticipant(${i})">✕ Remove</button>
      </div>`;

    row.innerHTML = `
      <div class="participant-info">
        <div class="participant-name">${escHtml(p.nombre)} ${escHtml(p.apellido)}</div>
        <div class="participant-meta">
          <strong>${escHtml(p.categoriaNombre || p.categoria)}</strong> · ${shirtTxt}<strong>${formatMoney(subtotal)}</strong>
        </div>
      </div>${actionsHtml}`;
    cont.appendChild(row);
  });

  document.getElementById('participantsActions').style.display = locked ? 'none' : '';

  const group    = getGroupRules();
  const atMax    = group.enabled && group.max > 0 && participants.length >= group.max;
  const addBtn   = document.getElementById('btnAddAnother');
  const maxNote  = document.getElementById('maxParticipantsNote');
  addBtn.style.display  = atMax ? 'none' : '';
  maxNote.style.display = atMax ? '' : 'none';
  maxNote.textContent   = atMax
    ? `Has alcanzado el máximo de ${group.max} participantes por inscripción — ¡descuento de grupo aplicado!`
    : '';

  const gate = document.getElementById('paidEditGate');
  if (locked) {
    document.getElementById('paidEditGateMsg').textContent =
      t('registration.paidEditNotice') + ' ' + formatMoney(editCost);
    const currentTotal = existingRegistration?.totales?.grand_total || 0;
    document.getElementById('paidEditGateSummary').innerHTML =
      `${t('registration.grandTotal')}: ${formatMoney(currentTotal)} + ${t('registration.editCostLabel')}: ${formatMoney(editCost)} = <strong>${formatMoney(currentTotal + editCost)}</strong>`;
    gate.style.display = '';
  } else {
    gate.style.display = 'none';
  }
}

// Bloquea/desbloquea el formulario de participante para el modo solo-lectura
// de inscripciones pagadas (campos e inputs de categoría/souvenirs incluidos).
function applyPaidLock(locked){
  const area = document.getElementById('participantEditableArea');
  area.classList.toggle('locked-readonly', locked);
  area.querySelectorAll('input, select, textarea').forEach(el => { el.disabled = locked; });
  document.getElementById('participantFormActions').style.display = locked ? 'none' : '';
}

// Prellena participants[] con una inscripción existente encontrada vía
// /registrations/lookup (pending/failed/cancelled o paid).
function loadExistingRegistration(reg){
  participants = (reg.participantes || []).map(p => {
    const cat = (currentEvent?.categories || []).find(c => String(c.id) === String(p.categoria));
    return { ...p, categoriaNombre: cat?.name || p.categoriaNombre || p.categoria };
  });
  renderParticipantsList();
  document.getElementById('participantsList').style.display = 'block';

  if (editMode === 'paid' && participants.length > 0) {
    // Inscripción pagada: mostrar todos los datos del primer participante
    // prellenados, pero bloqueados hasta que se confirme el costo de edición.
    editParticipant(0);
    applyPaidLock(true);
  } else {
    resetParticipantForm();
    document.getElementById('editIndex').value = '-1';
  }
  document.getElementById('participantsList').scrollIntoView({ behavior: 'smooth' });
}

// El participante confirma el costo adicional de editar una inscripción ya
// pagada; a partir de aquí se habilitan Edit/Remove/Add en la lista y los
// campos del formulario (ya prellenados) quedan editables.
function requestPaidChanges(){
  if (!confirm(t('registration.paidEditConfirmLabel') + ' ' + formatMoney(editCost))) return;
  paidEditUnlocked = true;
  applyPaidLock(false);
  renderParticipantsList();
}

function editParticipant(i){
  const p = participants[i];
  document.getElementById('editIndex').value         = i;
  document.getElementById('nombre').value            = p.nombre;
  document.getElementById('apellido').value          = p.apellido;
  document.getElementById('alias').value             = p.alias;
  syncAliasTituloUI(); // no-op fuera de form_types tipo congreso
  document.getElementById('genero').value            = p.genero;
  document.getElementById('tipoDocumento').value     = p.tipoDocumento || '';
  document.getElementById('numeroDocumento').value   = p.numeroDocumento || '';
  setDateBirth(p.nacimiento?.dia, p.nacimiento?.mes, p.nacimiento?.anio);
  document.getElementById('email').value             = p.correo;
  document.getElementById('direccion').value         = p.direccion;
  document.getElementById('ciudad').value            = p.ciudad;
  document.getElementById('nombre_emergencia').value = p.contacto_emergencia.nombre;
  document.getElementById('relacion_emergencia').value= p.contacto_emergencia.relacion;
  document.getElementById('donacion').value          = p.donacion;
  document.getElementById('entered_promotion_code').value = p.promoCodigo || '';
  if (p.promoDescuento > 0 && p.promoDiscountType && p.promoDiscountValue) {
    appliedPromoType  = p.promoDiscountType;
    appliedPromoValue = p.promoDiscountValue;
  } else if (p.promoDescuento > 0) {
    // Participante guardado antes de soportar % — se reconstruye como precio
    // fijo equivalente (mismo comportamiento que existía antes de este cambio).
    appliedPromoType  = 'fixed_price';
    appliedPromoValue = p.precioCategoria + p.precioPolera - p.promoDescuento;
  } else {
    appliedPromoType  = 'fixed_price';
    appliedPromoValue = 0;
  }

  if (itiTel) itiTel.setNumber(p.telefono || '');
  else document.getElementById('telefono').value = p.telefono || '';
  if (itiCel) itiCel.setNumber(p.contacto_emergencia.celular || '');
  else document.getElementById('celular').value  = p.contacto_emergencia.celular || '';

  // Categoría
  document.querySelectorAll('.category-card').forEach(c => {
    const isThis = c.dataset.id === p.categoria;
    c.classList.toggle('selected', isThis);
    c.setAttribute('aria-pressed', String(isThis));
  });
  // Equipo
  if (selectedFormType?.hasTeam) {
    document.getElementById('equipo_id').value = p.equipoId || '';
  }
  // Delivery
  if (selectedFormType?.hasDelivery) {
    document.getElementById('quiere_delivery').checked = !!p.quiereDelivery;
    // Mapa de ubicación (12/08/2026, portado de elascenso/event) —
    // restaura el pin guardado si el participante había marcado uno; si
    // no, deja el mapa oculto (mismo estado que un participante nuevo
    // sin tocar el checkbox).
    if (p.quiereDelivery) {
      document.getElementById('deliveryMapContainer').style.display = 'block';
      initDeliveryMap();
      if (p.deliveryLat != null && p.deliveryLng != null) {
        deliveryLat = p.deliveryLat;
        deliveryLng = p.deliveryLng;
        deliveryMarker?.setLatLng([p.deliveryLat, p.deliveryLng]);
        deliveryMap?.setView([p.deliveryLat, p.deliveryLng], 15);
      }
    } else {
      document.getElementById('deliveryMapContainer').style.display = 'none';
    }
  }
  // Shirt
  if (p.polera !== 'No shirt'){
    document.getElementById('conPolera').checked = true;
    document.getElementById('shirtSizeContainer').style.display = 'block';
    document.getElementById('tamanioPolera').value = p.polera;
  } else {
    document.getElementById('sinPolera').checked  = true;
    document.getElementById('shirtSizeContainer').style.display = 'none';
  }
  syncRadioStyles();
  // Souvenirs — comparación con Number() en ambos lados: sc.dataset.id
  // es siempre string (atributo HTML), s.id es number desde saveParticipant()
  // (ver mismo fix en elascenso/event, 12/08 — antes comparaba string vs.
  // number acá y nunca matcheaba nada).
  document.querySelectorAll('.souvenir-card').forEach(sc => {
    const guardado = p.souvenirs.find(s => Number(s.id) === Number(sc.dataset.id));
    // Un ítem incluido=true es opt-in por defecto pero se puede declinar
    // (ver deprecación hasshirt/costo_polera, 12/08) — al reabrir un
    // participante ya guardado se respeta lo que eligió, igual que
    // cualquier otro souvenir.
    const isChecked = !!guardado;
    sc.classList.toggle('checked', isChecked);
    sc.setAttribute('aria-pressed', String(isChecked));
    if (guardado) {
      const tallaSel = sc.querySelector('.souvenir-talla-select');
      const sexoSel  = sc.querySelector('.souvenir-sexo-select');
      if (tallaSel && guardado.talla) tallaSel.value = guardado.talla;
      if (sexoSel) {
        // Repoblar el select de sexo según la talla restaurada antes de
        // asignarle el valor guardado — si no, el <option> todavía no existe.
        const svData = (selectedFormType.souvenirs || []).find(sv => Number(sv.id) === Number(sc.dataset.id));
        if (svData) refreshSouvenirSexoOptions(svData, sc);
        if (guardado.sexo) sexoSel.value = guardado.sexo;
      }
    }
  });
  // Talleres (congresos, 20/08/2026) — re-renderiza el grid completo
  // (igual que en buildEventUI()); renderTalleresSelector() lee
  // #editIndex internamente y restaura las sesiones ya elegidas.
  renderTalleresSelector();

  // Preguntas dinámicas
  (selectedFormType?.preguntas || []).forEach(q => {
    const type = (q.tipo_input || '').toLowerCase();
    if (type === 'file') return;
    const qAnswers = (p.answers || []).filter(a => Number(a.question_id) === Number(q.id));
    if (type === 'checkbox') {
      const vals = qAnswers.map(a => a.value);
      document.querySelectorAll(`input[name="dynq_${q.id}"]`).forEach(c => { c.checked = vals.includes(c.value); });
    } else if (type === 'radio') {
      const val = qAnswers[0]?.value;
      document.querySelectorAll(`input[name="dynq_${q.id}"]`).forEach(r => { r.checked = (r.value === val); });
    } else {
      const el = document.getElementById('dynq_' + q.id);
      if (el) el.value = qAnswers[0]?.value || '';
    }
  });
  syncRadioStyles();

  document.getElementById('btnGuardar').textContent = 'Update Participant';
  document.getElementById('btnCancelar').style.display = 'inline-flex';
  document.querySelectorAll('.participant-row').forEach(r => r.classList.remove('editing'));
  document.getElementById('prow-' + i)?.classList.add('editing');
  document.getElementById('participantForm').scrollIntoView({ behavior: 'smooth' });
}

function cancelEdit(){
  resetParticipantForm();
  document.getElementById('editIndex').value = '-1';
  document.getElementById('btnGuardar').textContent = 'Save Participant';
  document.getElementById('btnCancelar').style.display = 'none';
  document.querySelectorAll('.participant-row').forEach(r => r.classList.remove('editing'));
}

function removeParticipant(i){
  if (!confirm('Remove this participant?')) return;
  participants.splice(i, 1);
  renderParticipantsList();
  if (participants.length === 0)
    document.getElementById('participantsList').style.display = 'none';
}

function addAnotherParticipant(){
  resetParticipantForm();
  document.getElementById('editIndex').value = '-1';
  document.getElementById('participantForm').scrollIntoView({ behavior: 'smooth' });
}

// ════════════════════════════════════════════════════════════
//  Agregado en Fase 2, paso 5: goToPendingRegistration()
// ════════════════════════════════════════════════════════════
// Disparada desde el modal de cuenta ("inscripciones pendientes"), pero
// el grueso de su cuerpo es puramente del formulario de registro
// (arma existingRegistration/editMode/editCost, llena
// #editModeNotice/#welcomeName, llama a proceedToRegistration()/
// loadExistingRegistration()) — mismo criterio que chooseFormType: el
// trigger vive en otra pantalla, el cuerpo pertenece a ésta.

// Salta directamente al formulario de una inscripción pendiente elegida
// desde la lista del modal, sin pasar por la selección manual de evento.
async function goToPendingRegistration(reg){
  try {
    const data = await fetchJson(`${API_BASE}/eventos.php?id=${encodeURIComponent(reg.evento_id)}`);
    currentEvent     = normalizeEvento(data.eventos);
    selectedFormType = (currentEvent.formTypes || []).find(ft => String(ft.id) === String(reg.form_types_id)) || null;

    existingRegistration = reg;
    editMode          = reg.pago_status === 'paid' ? 'paid' : 'pending';
    paidEditUnlocked  = false;
    editCost          = editMode === 'paid' ? Number(selectedFormType?.costo_edicion ?? 0) : 0;

    const notice = document.getElementById('editModeNotice');
    notice.textContent = editMode === 'paid' ? t('registration.foundPaidMsg') : t('registration.foundPendingMsg');
    notice.style.display = '';
    document.getElementById('welcomeSuffix').style.display = 'none';
    const first = reg.participantes?.[0] || {};
    document.getElementById('welcomeName').textContent = (first.nombre || '') + ' ' + (first.apellido || '');

    closeAccountModal();
    proceedToRegistration();
    loadExistingRegistration(existingRegistration);
  } catch (err) {
    alert('⚠ ' + err.message);
  }
}
