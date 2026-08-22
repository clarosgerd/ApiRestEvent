// ════════════════════════════════════════════════════════════
//  PANTALLAS: MIS RESULTADOS + LANDING DE CLUB (Fase 2, paso 6)
// ════════════════════════════════════════════════════════════
// Misma nota de ubicación que los módulos anteriores: `public/js/modules/`
// (no `resources/js/modules/`) porque no hay Vite que compile `resources/`.
// <script> clásico, mismo scope global léxico.
//
// A diferencia de los pasos 2 y 5, este bloque estaba contiguo en el
// archivo original (ya no había funciones de login/cuenta intercaladas
// — esas se habían movido en el paso 5) — se extrajo de una sola pieza,
// sin huecos. `clubAuthToken`/`loggedUser`/`authToken` siguen declarados
// en home.blade.php: son estado compartido con el resto de la SPA
// (header, modal de cuenta), no exclusivos de estas dos pantallas.
//
// `screen-results` y `screen-club-login`/`screen-club-landing` se
// migraron juntas (HTML en un solo partial) porque comparten clases CSS
// (`.results-card`, `.result-table`) y no tiene sentido partirlas — ver
// brain/PLAN-CLUBES-31072026.md.
//
// Actualizado 10/08/2026 (retomando la migración tras la pausa): portado el
// rediseño de "Mis Resultados" del 09/08/2026 (§4.7 del plan) —
// `buildResultCard`/`showResultsSection` reescritas + 3 funciones nuevas
// (`initSearchableTable`, `leaderboardToolbarHtml`, `initLeaderboardCard`)
// para el leaderboard completo por categoría (stats Salieron/Terminaron/
// DNF/DNS, rank general + de género, buscador + paginación, selector de
// categoría, misma tabla de equipos con buscador/paginación). Las funciones
// de club (`clubLogin` en adelante) no cambiaron, siguen igual.

// Resultados de carrera del participante logueado — pantalla propia (no un
// panel dentro del modal), una tarjeta por evento donde ya tiene un
// resultado cargado: resultado individual, comparativo por categoría y,
// si corresponde, equipo + ranking agregado. Ver
// brain/PLAN-RESULTADOS-EQUIPOS-31072026.md §4/§5.
function resultStatBlock(value, label){
  return `<div class="result-stat"><div class="result-stat-num">${escHtml(value)}</div><div class="result-stat-label">${escHtml(label)}</div></div>`;
}

function resultTableRows(rows){
  return rows.map(r => `
    <tr class="${r.esPropio ? 'own' : ''}">
      <td>${r.posicion != null ? '#' + escHtml(String(r.posicion)) : ''}</td>
      <td>${escHtml(r.nombre)}</td>
      <td>${escHtml(r.valor)}</td>
    </tr>`).join('');
}

// Cuadro comparativo entre eventos (misma categoría/distancia exacta,
// ver App\Support\ProgresoHistorico en el backend) — indicador corto de
// mejora/empeora contra la participación anterior cronológica.
function tiempoDiffCorto(segundos){
  segundos = Math.abs(Math.round(segundos));
  const m = Math.floor(segundos / 60), s = segundos % 60;
  return `${m}:${String(s).padStart(2, '0')}`;
}

function progresoIndicador(progreso){
  if (!progreso || progreso.length < 2) return '';
  const ultimo = progreso[progreso.length - 1];
  if (ultimo.mejora === true)  return ` · ▲ -${tiempoDiffCorto(ultimo.diferenciaSegundos)}`;
  if (ultimo.mejora === false) return ` · ▼ +${tiempoDiffCorto(ultimo.diferenciaSegundos)}`;
  return '';
}

// ── Leaderboard completo (buscador + paginación), reusado para la tabla de
// competidores y la de equipos — rediseño 09/08/2026 inspirado en
// sites.chronotrack.com (ver §4.7 del plan de migración y
// brain/groovy-chasing-ladybug.md Parte C en elascenso/event).
function initSearchableTable({ containerId, rows, columns, searchKeys, pageSize = 25 }){
  const container = document.getElementById(containerId);
  if (!container) return;
  // El input de búsqueda vive en el toolbar, hermano justo antes de este div
  // en el HTML de buildResultCard() — no adentro — por eso se busca ahí y no
  // con container.querySelector() (bug real, mismo en elascenso/event: antes
  // de este fix el buscador no filtraba nada, el oninput nunca se conectaba).
  const searchInput = container.previousElementSibling?.querySelector('.lb-search');
  const tbody = container.querySelector('tbody');
  const paginationEl = container.querySelector('.leaderboard-pagination');
  let filtered = rows;
  let page = 1;

  function renderRow(row){
    const cls = row.esPropio ? ' class="own"' : '';
    const cells = columns.map(c => `<td class="${c.num ? 'num' : ''}">${c.render(row)}</td>`).join('');
    return `<tr${cls}>${cells}</tr>`;
  }

  function render(){
    const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
    page = Math.min(page, totalPages);
    const start = (page - 1) * pageSize;
    const pageRows = filtered.slice(start, start + pageSize);
    tbody.innerHTML = pageRows.length
      ? pageRows.map(renderRow).join('')
      : `<tr><td colspan="${columns.length}" class="leaderboard-empty">${escHtml(t('results.noMatches'))}</td></tr>`;
    if (paginationEl) {
      paginationEl.innerHTML = totalPages > 1 ? `
        <button type="button" data-dir="-1" ${page <= 1 ? 'disabled' : ''}>‹</button>
        <span>${page} / ${totalPages}</span>
        <button type="button" data-dir="1" ${page >= totalPages ? 'disabled' : ''}>›</button>
      ` : '';
      paginationEl.querySelectorAll('button').forEach(b => {
        b.onclick = () => { page += parseInt(b.dataset.dir, 10); render(); };
      });
    }
  }

  if (searchInput) {
    searchInput.oninput = () => {
      const q = searchInput.value.trim().toLowerCase();
      filtered = !q ? rows : rows.filter(row => searchKeys.some(k => String(row[k] ?? '').toLowerCase().includes(q)));
      page = 1;
      render();
    };
  }

  render();
}

function leaderboardToolbarHtml(searchClass, placeholder, selectHtml){
  return `
    <div class="leaderboard-toolbar">
      <input type="search" class="${searchClass}" placeholder="${escHtml(placeholder)}">
      ${selectHtml || ''}
    </div>`;
}

function buildResultCard(r, leaderboardData){
  const posGeneral  = r.resultado.posicionGeneral   != null ? '#' + r.resultado.posicionGeneral   : '–';
  const posCategoria = r.resultado.posicionCategoria != null ? '#' + r.resultado.posicionCategoria : '–';
  const categorias = leaderboardData?.categorias || [];
  // Categoría propia por default — coincide con r.categoria (id) si viene del
  // backend nuevo, o con el nombre si no (fallback defensivo).
  const propiaIdx = Math.max(0, categorias.findIndex(c => String(c.categoriaId) === String(r.categoriaId ?? r.categoria)));
  const cardId = `results-${r.eventoId}`;

  let progresoHtml = '';
  if (r.progreso && r.progreso.length > 1) {
    const progresoRows = resultTableRows(r.progreso.map(p => {
      let indicador = '';
      if (p.mejora === true)  indicador = ` · ▲ -${tiempoDiffCorto(p.diferenciaSegundos)}`;
      if (p.mejora === false) indicador = ` · ▼ +${tiempoDiffCorto(p.diferenciaSegundos)}`;
      return {
        posicion: null, nombre: p.eventoNombre,
        valor: `${p.tiempoOficial || ''}${indicador}`,
        esPropio: p.eventoId === r.eventoId
      };
    }));
    progresoHtml = `
      <div class="results-section-label">${escHtml(t('results.progresoLabel'))}</div>
      <table class="result-table"><tbody>${progresoRows}</tbody></table>`;
  }

  let equipoHtml = '';
  if (r.equipo) {
    const integrantesRows = resultTableRows((r.equipo.integrantes || []).map(i => ({
      posicion: null, nombre: i.nombre, valor: `${i.tiempoOficial || ''} · ${i.estado}`, esPropio: i.esPropio
    })));
    const posEquipo = r.equipo.ranking?.posicion ?? '–';
    const totalEquipos = r.equipo.ranking?.totalEquipos ?? '–';

    equipoHtml = `
      <div class="team-block">
        <div class="results-section-label">${escHtml(t('results.teamLabel'))}: ${escHtml(r.equipo.nombre)}</div>
        <table class="result-table"><tbody>${integrantesRows}</tbody></table>
        <div class="results-section-label">${escHtml(t('results.teamRankingLabel'))} (${posEquipo}/${totalEquipos})</div>
        ${leaderboardToolbarHtml('lb-search', t('results.searchTeamPlaceholder'))}
        <div id="${cardId}-teams">
          <table class="leaderboard-table">
            <thead><tr><th class="num">${escHtml(t('results.rankGeneral'))}</th><th>${escHtml(t('results.teamLabel'))}</th><th class="num">${escHtml(t('results.timeLabel'))}</th></tr></thead>
            <tbody></tbody>
          </table>
          <div class="leaderboard-pagination"></div>
        </div>
      </div>`;
  }

  // Selector de categoría (si el evento tiene más de una) — permite mirar el
  // leaderboard de otras distancias del mismo evento, no solo la propia.
  const catSelect = categorias.length > 1 ? `
    <select class="lb-category-select">
      ${categorias.map((c, i) => `<option value="${i}" ${i === propiaIdx ? 'selected' : ''}>${escHtml(c.categoriaNombre)}</option>`).join('')}
    </select>` : '';

  return `
    <div class="results-card">
      <div class="results-hero">
        <div class="results-hero-name">${escHtml(r.eventoNombre)}</div>
        <div class="results-hero-meta">${escHtml(r.categoria)}</div>
      </div>
      <div class="result-stats">
        ${resultStatBlock(r.resultado.tiempoOficial || '–', t('results.timeLabel'))}
        ${resultStatBlock(posGeneral, t('results.overallLabel'))}
        ${resultStatBlock(posCategoria, t('results.categoryLabel'))}
      </div>
      <div id="${cardId}-race-stats" class="leaderboard-race-stats"></div>
      <div class="results-section-label">${escHtml(t('results.leaderboardLabel'))}</div>
      ${leaderboardToolbarHtml('lb-search', t('results.searchPlaceholder'), catSelect)}
      <div id="${cardId}-leaderboard">
        <table class="leaderboard-table">
          <thead>
            <tr>
              <th class="num">${escHtml(t('results.rankGeneral'))}</th>
              <th>${escHtml(t('results.bibLabel'))}</th>
              <th></th>
              <th class="num">${escHtml(t('results.rankGender'))}</th>
              <th class="num">${escHtml(t('results.timeLabel'))}</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
        <div class="leaderboard-pagination"></div>
      </div>
      ${progresoHtml}
      ${equipoHtml}
    </div>`;
}

// Wiring interactivo post-inserción: stats de la categoría activa, tabla de
// competidores (buscador + paginación) y, si aplica, tabla de equipos.
function initLeaderboardCard(r, leaderboardData){
  const cardId = `results-${r.eventoId}`;
  const categorias = leaderboardData?.categorias || [];
  if (categorias.length === 0) return;

  const propiaIdx = Math.max(0, categorias.findIndex(c => String(c.categoriaId) === String(r.categoriaId ?? r.categoria)));
  const cardEl = document.getElementById(`${cardId}-leaderboard`)?.closest('.results-card');
  const select = cardEl?.querySelector('.lb-category-select');

  function renderStats(cat){
    const statsEl = document.getElementById(`${cardId}-race-stats`);
    if (!statsEl) return;
    const started = cat.inscritos - cat.dns;
    statsEl.innerHTML = `
      <div class="leaderboard-race-stat">${escHtml(t('results.startedLabel'))}: <b>${started}</b></div>
      <div class="leaderboard-race-stat">${escHtml(t('results.finishedLabel'))}: <b>${cat.finished}</b></div>
      ${cat.dnf ? `<div class="leaderboard-race-stat">${escHtml(t('results.dnfLabel'))}: <b>${cat.dnf}</b></div>` : ''}
      ${cat.dns ? `<div class="leaderboard-race-stat">${escHtml(t('results.dnsLabel'))}: <b>${cat.dns}</b></div>` : ''}
    `;
  }

  function renderLeaderboard(cat){
    renderStats(cat);
    initSearchableTable({
      containerId: `${cardId}-leaderboard`,
      rows: cat.leaderboard || [],
      searchKeys: ['nombre', 'bib'],
      columns: [
        { num: true, render: row => '#' + row.posicionGeneral },
        { render: row => escHtml(row.bib || '') },
        { render: row => escHtml(row.nombre) },
        { num: true, render: row => `<span class="leaderboard-badge">#${row.posicionGenero}</span>` },
        { num: true, render: row => escHtml(row.tiempoOficial || '') },
      ],
    });
  }

  renderLeaderboard(categorias[propiaIdx] || categorias[0]);

  if (select) {
    select.onchange = () => renderLeaderboard(categorias[parseInt(select.value, 10)]);
  }

  if (r.equipo) {
    initSearchableTable({
      containerId: `${cardId}-teams`,
      rows: (r.equipo.ranking?.tabla || []).map((eq, i) => ({ posicion: i + 1, nombre: eq.nombre, tiempoTotal: eq.tiempoTotal, esPropio: eq.esPropio })),
      searchKeys: ['nombre'],
      columns: [
        { num: true, render: row => '#' + row.posicion },
        { render: row => escHtml(row.nombre) },
        { num: true, render: row => escHtml(row.tiempoTotal || '') },
      ],
    });
  }
}

async function showResultsSection(){
  closeAccountModal();
  showScreen('screen-results');

  const listEl    = document.getElementById('resultsList');
  const emptyEl   = document.getElementById('resultsEmpty');
  const loadingEl = document.getElementById('resultsLoading');
  listEl.innerHTML = '';
  emptyEl.style.display = 'none';
  loadingEl.style.display = '';

  try {
    const authHeaders = { 'Authorization': `Bearer ${authToken}`, 'X-Auth-Token': authToken };
    const data = await fetchJson(`${API_BASE}/resultados_mios.php`, { headers: authHeaders });
    const resultados = data.data || [];
    loadingEl.style.display = 'none';

    if (resultados.length === 0) {
      emptyEl.style.display = '';
      return;
    }

    // Leaderboard completo de cada evento, en paralelo — si alguno falla
    // (ej. evento sin categorías sincronizadas todavía) la tarjeta igual se
    // muestra con los datos personales de siempre, solo sin la tabla nueva.
    const leaderboards = await Promise.all(resultados.map(r =>
      fetchJson(`${API_BASE}/resultados_evento.php?evento_id=${encodeURIComponent(r.eventoId)}`, { headers: authHeaders })
        .then(res => res.data)
        .catch(() => null)
    ));

    listEl.innerHTML = resultados.map((r, i) => buildResultCard(r, leaderboards[i])).join('');
    resultados.forEach((r, i) => initLeaderboardCard(r, leaderboards[i]));
  } catch (err) {
    loadingEl.style.display = 'none';
    emptyEl.textContent = '⚠ ' + err.message;
    emptyEl.style.display = '';
  }
}

// ── Landing de club (login propio, historial + ranking privado) ──
// Ver brain/PLAN-CLUBES-31072026.md — audiencia distinta de Persona,
// token separado (clubAuthToken) contra los endpoints /club/* del backend.
async function clubLogin(){
  const email = document.getElementById('clubLoginEmail').value.trim();
  const pass  = document.getElementById('clubLoginPassword').value;
  const errEl = document.getElementById('clubLoginError');
  errEl.style.display = 'none';

  if (!email || !pass) {
    errEl.textContent = t('club.loginRequiredFields');
    errEl.style.display = 'block';
    return;
  }

  try {
    const data = await fetchJson(`${API_BASE}/club_login.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password: pass })
    });
    clubAuthToken = data.data.token;
    document.getElementById('clubLoginEmail').value    = '';
    document.getElementById('clubLoginPassword').value = '';
    await showClubLandingScreen(data.data.club.nombre);
  } catch (err) {
    errEl.textContent = '⚠ ' + err.message;
    errEl.style.display = 'block';
  }
}

function buildClubHistoryCard(h){
  const posicion = h.ranking?.posicion ?? '–';
  const totalEquipos = h.ranking?.totalEquipos ?? '–';

  const integrantesRows = resultTableRows((h.integrantes || []).map(i => ({
    posicion: null, nombre: i.nombre,
    valor: `${i.tiempoOficial || ''}${i.estado ? ' · ' + i.estado : ''}${progresoIndicador(i.progreso)}`,
    esPropio: false
  })));

  return `
    <div class="results-card">
      <div class="results-hero">
        <div class="results-hero-name">${escHtml(h.eventoNombre)}</div>
        <div class="results-hero-meta">${escHtml(h.equipoNombre)}</div>
      </div>
      <div class="result-stats">
        ${resultStatBlock(posicion, t('club.rankingLabel'))}
        ${resultStatBlock(totalEquipos, 'Total')}
      </div>
      <div class="results-section-label">${escHtml(t('club.membersLabel'))}</div>
      <table class="result-table"><tbody>${integrantesRows}</tbody></table>
    </div>`;
}

async function showClubLandingScreen(clubNombre){
  showScreen('screen-club-landing');
  if (clubNombre) {
    document.getElementById('clubLandingTitle').textContent = clubNombre;
  }

  const listEl    = document.getElementById('clubLandingList');
  const emptyEl   = document.getElementById('clubLandingEmpty');
  const loadingEl = document.getElementById('clubLandingLoading');
  listEl.innerHTML = '';
  emptyEl.style.display = 'none';
  loadingEl.style.display = '';

  try {
    const data = await fetchJson(`${API_BASE}/club_landing.php`, {
      headers: { 'Authorization': `Bearer ${clubAuthToken}`, 'X-Auth-Token': clubAuthToken }
    });
    if (!clubNombre) {
      document.getElementById('clubLandingTitle').textContent = data.club?.nombre || t('club.pageTitle');
    }
    const historial = data.historial || [];
    loadingEl.style.display = 'none';

    if (historial.length === 0) {
      emptyEl.style.display = '';
      return;
    }

    listEl.innerHTML = historial.map(buildClubHistoryCard).join('');
  } catch (err) {
    loadingEl.style.display = 'none';
    emptyEl.textContent = '⚠ ' + err.message;
    emptyEl.style.display = '';
  }
}

async function clubLogout(){
  try {
    await fetch(`${API_BASE}/club_logout.php`, {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${clubAuthToken}`, 'X-Auth-Token': clubAuthToken }
    });
  } catch (err) { /* best-effort: igual limpiamos el estado local */ }
  clubAuthToken = null;
  showScreen('screen-club-login');
}
