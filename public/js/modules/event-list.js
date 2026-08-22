// ════════════════════════════════════════════════════════════
//  PANTALLA 0-A: LISTADO DE EVENTOS (Fase 2, paso 1)
// ════════════════════════════════════════════════════════════
// Nota de ubicación: el plan decía `resources/js/modules/`, pero al no
// haber Vite/build step nada compila `resources/`, así que va en
// `public/js/modules/` — mismo motivo/deviation ya documentado para
// `public/css/app.css` en Fase 1.
//
// Es un <script> clásico (no type="module"): las variables `let`/`const`
// de nivel superior y las `function` declaradas acá comparten el mismo
// scope global léxico que el resto de los <script> del documento, así
// que el resto del código (home.blade.php) sigue llamando estas
// funciones exactamente igual que antes de la extracción — solo cambió
// en qué archivo viven.

let allEvents          = [];    // eventos de la página actual del catálogo (ya filtrados/paginados por el servidor)
let eventFilterDebounce = null;
let eventFilterRequestId = 0;   // descarta respuestas de fetch obsoletas (filtros/paginación)
let eventsCurrentPage   = 1;
let eventsPerPage       = 12;
let eventsPagination    = null; // { total, per_page, current_page, last_page, from, to } de la última respuesta

// Cargar la primera página del catálogo (sin filtros activos)
async function loadAllEvents(){
  eventsCurrentPage = 1;
  await fetchEventsPage(1);
}

// ════════════════════════════════════════════════════════════
//  BUSCADOR, FILTROS Y PAGINACIÓN DE EVENTOS (server-side)
// ════════════════════════════════════════════════════════════
function onEventFilterTextInput(){
  clearTimeout(eventFilterDebounce);
  eventFilterDebounce = setTimeout(applyEventFilters, 300);
}

function toggleAdvancedFilters(){
  const panel = document.getElementById('advancedFiltersPanel');
  const btn   = document.getElementById('advancedToggleBtn');
  const show  = panel.style.display === 'none';
  panel.style.display = show ? 'grid' : 'none';
  btn.classList.toggle('active', show);
}

function clearEventFilters(){
  document.getElementById('eventSearchInput').value = '';
  document.getElementById('filterStatus').value     = '';
  document.getElementById('filterLocation').value   = '';
  document.getElementById('filterType').value       = '';
  document.getElementById('filterPriceMin').value   = '';
  document.getElementById('filterPriceMax').value   = '';
  document.getElementById('filterDateFrom').value   = '';
  document.getElementById('filterDateTo').value     = '';
  applyEventFilters();
}

// Cualquier cambio de filtro vuelve a la página 1
async function applyEventFilters(){
  await fetchEventsPage(1);
}

// Pide al servidor una página del catálogo con los filtros activos aplicados.
// Único punto de llamada a GET /event para la pantalla de catálogo — reemplaza
// tanto la carga inicial como los filtros (todos se resuelven server-side).
async function fetchEventsPage(page){
  const grid    = document.getElementById('eventsGrid');
  const loading = document.getElementById('eventsLoading');
  const errEl   = document.getElementById('eventsError');
  const noRes   = document.getElementById('eventsNoResults');

  const searchText = document.getElementById('eventSearchInput').value.trim();
  const status     = document.getElementById('filterStatus').value;
  const location   = document.getElementById('filterLocation').value.trim();
  const type       = document.getElementById('filterType').value;
  const priceMin   = parseFloat(document.getElementById('filterPriceMin').value);
  const priceMax   = parseFloat(document.getElementById('filterPriceMax').value);
  const dateFrom   = document.getElementById('filterDateFrom').value;
  const dateTo     = document.getElementById('filterDateTo').value;

  const hasActiveFilter = !!(searchText || status || location || type ||
    !isNaN(priceMin) || !isNaN(priceMax) || dateFrom || dateTo);
  document.getElementById('clearFiltersBtn').style.display = hasActiveFilter ? 'inline-flex' : 'none';

  const params = new URLSearchParams();
  params.set('publicado[eq]', '1');
  params.set('page', page);
  params.set('per_page', eventsPerPage);
  if (searchText) params.set('search', searchText);
  if (status)     params.set('estado_evento_id[eq]', status);
  if (location)   params.set('direccion[li]', `%${location}%`);
  if (type)       params.set('tipo[eq]', type);
  if (!isNaN(priceMin)) params.set('price[gte]', priceMin);
  if (!isNaN(priceMax)) params.set('price[lte]', priceMax);
  if (dateFrom)   params.set('fecha_inicio[gte]', dateFrom);
  if (dateTo)     params.set('fecha_inicio[lte]', dateTo);

  grid.style.display    = 'none';
  errEl.style.display   = 'none';
  noRes.style.display   = 'none';
  loading.style.display = 'block';

  const requestId = ++eventFilterRequestId;
  try {
    const data = await fetchJson(`${API_BASE}/eventos.php?${params.toString()}`);
    if (requestId !== eventFilterRequestId) return; // llegó una petición más nueva mientras esperábamos

    loading.style.display = 'none';
    allEvents         = (data.eventos || []).map(normalizeEvento);
    eventsPagination   = data.pagination || null;
    eventsCurrentPage  = eventsPagination ? eventsPagination.current_page : page;

    renderEventCards(allEvents);
    renderPagination();
    noRes.style.display = allEvents.length === 0 ? 'block' : 'none';
    grid.style.display  = allEvents.length === 0 ? 'none' : 'grid';

  } catch(err) {
    if (requestId !== eventFilterRequestId) return;
    loading.style.display = 'none';
    errEl.style.display   = 'block';
    errEl.querySelector('p').textContent = '⚠ ' + err.message;
  }
}

// Renderizar tarjetas de eventos
function renderEventCards(eventos){
  const grid = document.getElementById('eventsGrid');
  grid.innerHTML = '';

  eventos.forEach(ev => {
    const isClosed     = ev.status === 'closed';
    const statusLabel  = getStatusLabel(ev.status);
    const evCoords  = Array.isArray(ev.coordinates) ? ev.coordinates[0] : ev.coordinates;
    const hasCoords = evCoords && evCoords.lat != null && evCoords.lng != null;
    const mapUrl    = hasCoords
      ? `https://www.google.com/maps?q=${encodeURIComponent(evCoords.lat)},${encodeURIComponent(evCoords.lng)}`
      : ev.location
        ? `https://www.google.com/maps?q=${encodeURIComponent(ev.location)}`
        : '#';

    // Imagen o placeholder
    const imgHtml = ev.image
      ? `<img src="${escHtml(ev.image)}" alt="${escHtml(ev.name)}"
              class="event-card-img"
              onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">`
      : '';
    const placeholderHtml = `
      <div class="event-card-img-placeholder" ${ev.image ? 'style="display:none"' : ''}>🏃</div>`;

    // Píldoras de categorías
    const catPills = ev.categories
      .map(c => `<span>${escHtml(c.name)}</span>`).join('');

    // Compartir (link directo al evento vía ?evento=<id o url_slug>,
    // 20/08/2026 portado de elascenso/event, ver DEEP_LINK_EVENT_ID) —
    // prefiere el slug legible sobre el id numérico cuando el evento
    // tiene uno cargado; ApiRestEvent resuelve ambos (Evento::
    // resolveRouteBinding()).
    const shareUrl  = `${window.location.origin}${window.location.pathname}?evento=${encodeURIComponent(ev.urlSlug || ev.id)}`;
    const shareText = `${ev.name} — ${ev.date}${ev.location ? ' · ' + ev.location : ''}`;
    const waShareUrl = `https://wa.me/?text=${encodeURIComponent(shareText + ' ' + shareUrl)}`;
    const fbShareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareUrl)}`;
    const xShareUrl  = `https://twitter.com/intent/tweet?text=${encodeURIComponent(shareText)}&url=${encodeURIComponent(shareUrl)}`;
    const shareHtml = `
      <div class="event-card-share" onclick="event.stopPropagation()">
        <a class="share-btn share-whatsapp" href="${escHtml(waShareUrl)}" target="_blank" rel="noopener"
           title="${t('events.shareWhatsapp')}" aria-label="${t('events.shareWhatsapp')}">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38c1.45.79 3.08 1.21 4.79 1.21 5.46 0 9.91-4.45 9.91-9.91S17.5 2 12.04 2zm5.71 14.14c-.24.68-1.19 1.24-1.95 1.4-.52.11-1.2.2-3.48-.75-2.92-1.21-4.8-4.17-4.95-4.36-.14-.19-1.18-1.57-1.18-3 0-1.43.75-2.12 1.02-2.41.24-.26.62-.38.99-.38.12 0 .23 0 .33.01.29.01.44.03.63.49.24.58.81 2.01.88 2.16.07.15.12.32.02.51-.09.19-.14.31-.28.48-.14.16-.29.36-.42.49-.14.14-.28.29-.12.57.16.28.71 1.17 1.53 1.9 1.05.94 1.94 1.23 2.22 1.37.28.14.44.12.6-.07.16-.19.68-.79.87-1.06.18-.27.36-.22.61-.13.25.09 1.58.75 1.85.88.27.13.45.2.51.31.07.12.07.66-.17 1.34z"/></svg>
        </a>
        <a class="share-btn share-facebook" href="${escHtml(fbShareUrl)}" target="_blank" rel="noopener"
           title="${t('events.shareFacebook')}" aria-label="${t('events.shareFacebook')}">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.99 3.66 9.13 8.44 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99C18.34 21.13 22 16.99 22 12z"/></svg>
        </a>
        <a class="share-btn share-x" href="${escHtml(xShareUrl)}" target="_blank" rel="noopener"
           title="${t('events.shareX')}" aria-label="${t('events.shareX')}">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.9 2H22l-7.6 8.68L23.3 22H16.7l-5.16-6.75L5.6 22H2.5l8.14-9.3L1.5 2h6.8l4.67 6.17L18.9 2zm-1.16 18h1.72L7.35 3.9H5.5l12.24 16.1z"/></svg>
        </a>
        <button type="button" class="share-btn share-copy"
                title="${t('events.shareCopy')}" aria-label="${t('events.shareCopy')}"
                onclick="navigator.clipboard.writeText('${shareUrl}');this.classList.add('copied');setTimeout(()=>this.classList.remove('copied'),1500);">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.07 0l2.83-2.83a5 5 0 0 0-7.07-7.07l-1.5 1.5"/><path d="M14 11a5 5 0 0 0-7.07 0L4.1 13.83a5 5 0 0 0 7.07 7.07l1.5-1.5"/></svg>
        </button>
      </div>`;

    const card = document.createElement('div');
    card.className = `event-card status-${escHtml(ev.status)}`;
    card.dataset.id = ev.id;
    card.innerHTML = `
      <div class="event-img-wrapper">
        ${imgHtml}${placeholderHtml}
        <span class="event-status-badge">${statusLabel}</span>
      </div>
      <div class="event-card-body">
        <div class="event-card-name">${escHtml(ev.name)}</div>
        <div class="event-card-desc">${escHtml(ev.description || '')}</div>
        <div class="event-card-meta">
          <div class="event-card-meta-row">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
              <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            ${escHtml(ev.date)} &nbsp;·&nbsp; ${escHtml(ev.localTime || '')}
          </div>
          <div class="event-card-meta-row">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/>
              <circle cx="12" cy="10" r="3"/>
            </svg>
            ${escHtml(ev.location)}
            <a href="${mapUrl}" target="_blank" rel="noopener"
               onclick="event.stopPropagation()">${t('events.viewMap')}</a>
          </div>
        </div>
        ${shareHtml}
      </div>
      <div class="event-card-footer">
        <div class="event-card-cats">${catPills}</div>
        ${!isClosed
          ? `<button class="btn btn-primary btn-sm"
                onclick="event.stopPropagation();selectEvent('${escHtml(ev.id)}')">
               ${t('events.registerButton')}
             </button>`
          : `<span style="font-size:12px;color:var(--danger);font-weight:600;">${t('events.registrationClosed')}</span>`
        }
      </div>`;

    if (!isClosed) {
      card.addEventListener('click', () => selectEvent(ev.id));
    }
    grid.appendChild(card);
  });
}

// Renderizar controles de paginación a partir de eventsPagination
// ({ total, per_page, current_page, last_page, from, to })
function renderPagination(){
  const wrap      = document.getElementById('paginationWrap');
  const nav       = document.getElementById('paginationNav');
  const summaryEl = document.getElementById('paginationSummary');

  if (!eventsPagination || !eventsPagination.total) {
    wrap.style.display = 'none';
    return;
  }

  const { total, current_page, last_page, from, to } = eventsPagination;

  summaryEl.textContent = t('events.paginationSummary')
    .replace('%from', from ?? 0).replace('%to', to ?? 0).replace('%total', total);
  wrap.style.display = 'flex';
  nav.innerHTML = '';

  if (last_page <= 1) return;

  const goTo = (page) => {
    document.getElementById('screen-event-list').scrollIntoView({ behavior: 'smooth', block: 'start' });
    fetchEventsPage(page);
  };

  const addBtn = (label, page, opts = {}) => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'pagination-btn' + (opts.active ? ' active' : '');
    btn.textContent = label;
    btn.disabled = !!opts.disabled;
    if (!opts.disabled && !opts.active) btn.onclick = () => goTo(page);
    nav.appendChild(btn);
  };
  const addEllipsis = () => {
    const span = document.createElement('span');
    span.className = 'pagination-ellipsis';
    span.textContent = '…';
    nav.appendChild(span);
  };

  addBtn(t('events.paginationPrev'), current_page - 1, { disabled: current_page <= 1 });

  // Siempre muestra primera, última, la actual y sus vecinas; el resto colapsa en "…"
  const pages = new Set([1, last_page, current_page, current_page - 1, current_page + 1]);
  const sorted = [...pages].filter(p => p >= 1 && p <= last_page).sort((a, b) => a - b);

  let prevPage = 0;
  sorted.forEach(p => {
    if (prevPage && p - prevPage > 1) addEllipsis();
    addBtn(String(p), p, { active: p === current_page });
    prevPage = p;
  });

  addBtn(t('events.paginationNext'), current_page + 1, { disabled: current_page >= last_page });
}
