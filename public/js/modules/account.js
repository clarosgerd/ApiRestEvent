// ════════════════════════════════════════════════════════════
//  MODAL DE CUENTA (login / crear cuenta / inscripciones
//  pendientes) — Fase 2, paso 5
// ════════════════════════════════════════════════════════════
// Misma nota de ubicación que los módulos anteriores: `public/js/modules/`
// (no `resources/js/modules/`) porque no hay Vite que compile `resources/`.
// <script> clásico, mismo scope global léxico.
//
// `goToPendingRegistration()` (el click en un item de "inscripciones
// pendientes" de este modal) NO está acá — se movió a
// `registration.js` (paso 2): el grueso de su cuerpo (armar
// `existingRegistration`/`editMode`/`editCost`, tocar
// `#editModeNotice`/`#welcomeName` del formulario) pertenece al
// formulario de registro, no al modal — mismo criterio que
// `chooseFormType` en el paso 2 (el trigger vive en una pantalla, el
// cuerpo pertenece a la otra).
//
// El modal de AGENDA (`#agendaModal`) comparte este paso pero su JS
// (`renderAgendaModal`/`openAgendaModal`/`closeAgendaModal`/
// `buildAgendaListHtml`/`formatAgendaTime`/`formatAgendaDay`) ya se
// había movido a `registration.js` en el paso 2, porque depende de
// `agendaModalEvent` que se llena desde la pantalla de tipos de
// formulario — acá solo se extrajo su HTML.

// ════════════════════════════════════════════════════════════
//  LOGIN  →  API PHP
// ════════════════════════════════════════════════════════════
function toggleLoginForm(){
  document.getElementById('loginFormCollapse').classList.toggle('open');
}

async function doLogin(){
  const email = document.getElementById('loginEmail').value.trim();
  const pass  = document.getElementById('loginPassword').value;
  const errEl = document.getElementById('loginApiError');
  const btn   = document.getElementById('btnLogin');
  errEl.classList.remove('show');

  if (!email || !pass){
    errEl.textContent = 'Email and password are required.';
    errEl.classList.add('show');
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Signing in…';

  try {
    // /registrations/lookup hace login + búsqueda de inscripción existente
    // para este evento+tipo de formulario en un solo paso.
    const data = await fetchJson(`${API_BASE}/registro_lookup.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        email, password: pass,
        evento_id: currentEvent?.id,
        form_type_id: selectedFormType?.id
      })
    });

    const notice = document.getElementById('editModeNotice');
    const suffix = document.getElementById('welcomeSuffix');

    if (data.type === 'registration') {
      existingRegistration = data.data;
      editMode      = existingRegistration.pago_status === 'paid' ? 'paid' : 'pending';
      paidEditUnlocked = false;
      editCost = editMode === 'paid' ? Number(selectedFormType?.costo_edicion ?? 0) : 0;
      authToken  = null;
      loggedUser = null;

      const first = existingRegistration.participantes?.[0] || {};
      document.getElementById('welcomeName').textContent = (first.nombre || '') + ' ' + (first.apellido || '');
      suffix.style.display = 'none';
      notice.textContent = editMode === 'paid' ? t('registration.foundPaidMsg') : t('registration.foundPendingMsg');
      notice.style.display = '';

      loadExistingRegistration(existingRegistration);

    } else {
      loggedUser = { ...data.data };
      delete loggedUser.password;
      authToken  = data.token;
      editMode   = 'new';
      existingRegistration = null;

      suffix.style.display = '';
      notice.style.display = 'none';
      document.getElementById('welcomeName').textContent =
        loggedUser.nombre + ' ' + loggedUser.apellido;
      fillFromUser(loggedUser);
    }

    document.getElementById('loginFormCollapse').classList.remove('open');
    document.getElementById('loginBanner').style.display = 'none';
    document.getElementById('loginWelcome').classList.add('visible');
    refreshHeaderLoginUI();

  } catch(err){
    // El endpoint de lookup responde un único mensaje genérico
    // ("Credenciales inválidas.") tanto si el correo no existe como si la
    // contraseña es incorrecta. Para distinguir "no tiene cuenta" (y sugerir
    // continuar con el alta normal) hacemos un fallback a /persona/login,
    // que sí distingue ambos casos con mensajes distintos.
    try {
      await fetchJson(`${API_BASE}/persona_login.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password: pass })
      });
      // Las credenciales eran válidas; el fallo original fue otra cosa
      // (p.ej. evento/tipo de formulario no resuelto todavía).
      errEl.textContent = '⚠ ' + err.message;
      errEl.classList.add('show');
    } catch(loginErr){
      if (loginErr.message === 'no existe el correo.') {
        errEl.textContent = t('registration.noAccountMsg');
        errEl.classList.add('show');
      } else {
        errEl.textContent = '⚠ ' + loginErr.message;
        errEl.classList.add('show');
      }
    }
  } finally {
    btn.disabled = false;
    btn.innerHTML = 'Log in &amp; Auto-fill';
  }
}

function fillFromUser(u){
  document.getElementById('nombre').value          = u.nombre   || '';
  document.getElementById('apellido').value        = u.apellido || '';
  document.getElementById('alias').value           = u.alias    || '';
  document.getElementById('genero').value          = u.sexo     || '';
  document.getElementById('tipoDocumento').value   = u.tipoDocumento   || '';
  document.getElementById('numeroDocumento').value = u.numeroDocumento || '';
  setDateBirth(u.nacimiento?.dia, u.nacimiento?.mes, u.nacimiento?.anio);
  document.getElementById('email').value           = u.correo   || '';
  document.getElementById('direccion').value       = u.direccion|| '';
  document.getElementById('ciudad').value          = u.ciudad   || '';
  if (itiTel) itiTel.setNumber(u.telefono || '');
  else document.getElementById('telefono').value   = u.telefono || '';

  const ce = u.contacto_emergencia || {};
  document.getElementById('nombre_emergencia').value    = ce.nombre   || '';
  document.getElementById('relacion_emergencia').value  = ce.relacion || '';
  if (itiCel) itiCel.setNumber(ce.celular || u.celular || '');
  else document.getElementById('celular').value         = ce.celular || u.celular || '';
}

async function revokeAuthToken(){
  if (!authToken) return;
  try {
    await fetch(`${API_BASE}/persona_logout.php`, {
      method: 'POST',
      // X-Auth-Token: algunos hostings (mod_lsapi de CloudLinux/cPanel) no
      // reenvían el header Authorization a PHP — la API externa lo acepta
      // como respaldo (ver NormalizeAuthTokenHeader en ApiRestEvent).
      headers: { 'Authorization': `Bearer ${authToken}`, 'X-Auth-Token': authToken }
    });
  } catch (e) {}
  authToken = null;
}

async function logoutUser(){
  await revokeAuthToken();
  loggedUser = null;
  editMode             = 'new';
  existingRegistration = null;
  editCost             = 0;
  paidEditUnlocked     = false;
  participants = [];
  applyPaidLock(false);
  document.getElementById('loginWelcome').classList.remove('visible');
  document.getElementById('loginBanner').style.display = '';
  document.getElementById('participantsList').style.display = 'none';
  resetParticipantForm();
  refreshHeaderLoginUI();
}

// ════════════════════════════════════════════════════════════
//  LOGIN GLOBAL (header) — independiente del evento/formulario elegido.
//  Comparte estado (loggedUser/authToken) con el login embebido dentro del
//  formulario de inscripción (doLogin/logoutUser más arriba). Modal con 3
//  secciones: login, crear cuenta (si el correo no existe) e inscripciones
//  pendientes de pago (tras loguearse).
// ════════════════════════════════════════════════════════════
function refreshHeaderLoginUI(){
  document.getElementById('headerLoginLabel').textContent = loggedUser ? loggedUser.nombre : 'Log in';
}

function openAccountModal(){
  // Elegir la sección ANTES de openModal(): éste calcula qué elemento
  // enfocar recorriendo lo que esté visible en ese momento, así que si se
  // llamara después, foco podría caer en algo que showPendingSection()/
  // showLoginSection() está a punto de ocultar.
  if (loggedUser) {
    showPendingSection();
  } else {
    showLoginSection();
  }
  // openModal() (home.blade.php) maneja foco/Escape/trap de Tab.
  openModal(document.getElementById('accountModal'));
}

function closeAccountModal(){
  closeModal(document.getElementById('accountModal'));
}

// `aria-labelledby` del diálogo apunta siempre al <h2> de la sección
// visible — las otras dos quedan display:none, y un aria-labelledby que
// apunta a un elemento oculto no calcula ningún nombre accesible (por eso
// no alcanza con ponerlo una sola vez en el HTML, hay que actualizarlo acá
// cada vez que cambia la sección visible).
function showLoginSection(){
  document.getElementById('modalLoginSection').style.display    = '';
  document.getElementById('modalRegisterSection').style.display = 'none';
  document.getElementById('modalPendingSection').style.display  = 'none';
  document.getElementById('modalLoginError').style.display = 'none';
  document.getElementById('accountModalDialog')?.setAttribute('aria-labelledby', 'modalLoginTitle');
}

function showRegisterSection(prefillEmail){
  document.getElementById('modalLoginSection').style.display    = 'none';
  document.getElementById('modalRegisterSection').style.display = '';
  document.getElementById('modalPendingSection').style.display  = 'none';
  document.getElementById('modalRegisterError').style.display = 'none';
  document.getElementById('accountModalDialog')?.setAttribute('aria-labelledby', 'modalRegisterTitle');
  const hint = document.getElementById('modalRegisterHint');
  if (prefillEmail) {
    document.getElementById('regEmail').value = prefillEmail;
    hint.textContent = t('account.registerHint').replace('%email', prefillEmail);
  } else {
    hint.textContent = '';
  }
}

async function modalLogin(){
  const email = document.getElementById('modalLoginEmail').value.trim();
  const pass  = document.getElementById('modalLoginPassword').value;
  const errEl = document.getElementById('modalLoginError');
  errEl.style.display = 'none';

  if (!email || !pass) {
    errEl.textContent = t('account.loginRequiredFields');
    errEl.style.display = 'block';
    return;
  }

  try {
    const data = await fetchJson(`${API_BASE}/persona_login.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password: pass })
    });
    applyLoggedInSession({ ...data.data.persona }, data.data.token);
    document.getElementById('modalLoginEmail').value    = '';
    document.getElementById('modalLoginPassword').value = '';
    showPendingSection();
  } catch (err) {
    if (err.message === 'no existe el correo.') {
      showRegisterSection(email);
    } else {
      errEl.textContent = '⚠ ' + err.message;
      errEl.style.display = 'block';
    }
  }
}

async function modalRegister(){
  const errEl = document.getElementById('modalRegisterError');
  errEl.style.display = 'none';

  const nombre           = document.getElementById('regNombre').value.trim();
  const apellido          = document.getElementById('regApellido').value.trim();
  const alias             = document.getElementById('regAlias').value.trim();
  const sexo              = document.getElementById('regSexo').value;
  const tipoDocumento     = document.getElementById('regTipoDocumento').value;
  const numeroDocumento   = document.getElementById('regNumeroDocumento').value.trim();
  const fechaNacimiento   = document.getElementById('regFechaNacimiento').value;
  const email             = document.getElementById('regEmail').value.trim();
  const direccion         = document.getElementById('regDireccion').value.trim();
  const ciudad            = document.getElementById('regCiudad').value.trim();
  const telefono          = document.getElementById('regTelefono').value.trim();
  const celular           = document.getElementById('regCelular').value.trim();
  const password          = document.getElementById('regPassword').value;

  if (!nombre || !apellido || !alias || !numeroDocumento || !fechaNacimiento || !email || !direccion || !ciudad || !password) {
    errEl.textContent = t('account.registerRequiredFields');
    errEl.style.display = 'block';
    return;
  }

  try {
    const data = await fetchJson(`${API_BASE}/persona_register.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        nombre, apellido, alias, sexo,
        tipo_documento: tipoDocumento, numero_documento: numeroDocumento,
        fecha_nacimiento: fechaNacimiento,
        email, correo: email,
        direccion, ciudad, telefono, celular, password
      })
    });
    // No reutilizamos la forma del modelo crudo que devuelve /persona/register
    // (snake_case, distinto de lo que expone PersonaResource en /persona/login
    // y /persona/me) — construimos loggedUser con los mismos datos que ya
    // capturó este formulario, en el shape que espera fillFromUser().
    const [anio, mes, dia] = fechaNacimiento.split('-');
    applyLoggedInSession({
      id: data.data?.id ?? null,
      nombre, apellido, alias, sexo,
      tipoDocumento, numeroDocumento,
      nacimiento: { dia: Number(dia), mes: Number(mes), anio: Number(anio) },
      correo: email, direccion, ciudad, telefono, celular,
      contacto_emergencia: { nombre: '', celular: '', relacion: '' }
    }, data.token);
    showPendingSection();
  } catch (err) {
    errEl.textContent = '⚠ ' + err.message;
    errEl.style.display = 'block';
  }
}

// Aplica una sesión recién obtenida (login o registro) al estado global y
// sincroniza el bloque de login embebido en el formulario de inscripción
// (mismo efecto que produce doLogin() cuando no hay inscripción previa).
function applyLoggedInSession(persona, token){
  loggedUser = persona;
  delete loggedUser.password;
  authToken  = token;
  editMode   = 'new';
  existingRegistration = null;

  document.getElementById('editModeNotice').style.display = 'none';
  document.getElementById('welcomeSuffix').style.display  = '';
  document.getElementById('welcomeName').textContent = loggedUser.nombre + ' ' + loggedUser.apellido;
  fillFromUser(loggedUser);
  document.getElementById('loginFormCollapse').classList.remove('open');
  document.getElementById('loginBanner').style.display = 'none';
  document.getElementById('loginWelcome').classList.add('visible');

  refreshHeaderLoginUI();
}

async function showPendingSection(){
  document.getElementById('modalLoginSection').style.display    = 'none';
  document.getElementById('modalRegisterSection').style.display = 'none';
  document.getElementById('modalPendingSection').style.display  = '';
  document.getElementById('modalPendingName').textContent = loggedUser.nombre;
  document.getElementById('accountModalDialog')?.setAttribute('aria-labelledby', 'modalPendingTitle');

  const listEl    = document.getElementById('modalPendingList');
  const emptyEl   = document.getElementById('modalPendingEmpty');
  const loadingEl = document.getElementById('modalPendingLoading');
  listEl.innerHTML = '';
  emptyEl.style.display = 'none';
  loadingEl.style.display = '';

  try {
    const data = await fetchJson(`${API_BASE}/registros_mine.php?pago_status=pending`, {
      headers: { 'Authorization': `Bearer ${authToken}`, 'X-Auth-Token': authToken }
    });
    const registros = data.data || [];
    loadingEl.style.display = 'none';

    if (registros.length === 0) {
      emptyEl.style.display = '';
      return;
    }

    registros.forEach(reg => {
      const row = document.createElement('div');
      row.className = 'participant-row';
      row.style.cursor = 'pointer';
      row.innerHTML = `
        <div class="participant-info">
          <div class="participant-name">${escHtml(reg.evento_nombre || '')}</div>
          <div class="participant-meta">${escHtml(reg.fecha || '')} · <strong>${formatMoney(reg.totales?.grand_total || 0)}</strong> · ${escHtml(reg.tipo_pago || '')}</div>
        </div>
        <button class="btn btn-sm btn-primary">${escHtml(t('account.continueItemBtn'))}</button>`;
      row.onclick = () => goToPendingRegistration(reg);
      listEl.appendChild(row);
    });
  } catch (err) {
    loadingEl.style.display = 'none';
    emptyEl.textContent = '⚠ ' + err.message;
    emptyEl.style.display = '';
  }
}

// modalLogout vivía junto a goToPendingRegistration/goToReview en
// home.blade.php original — se trae acá porque es puramente del modal.
async function modalLogout(){
  await logoutUser();
  closeAccountModal();
}
