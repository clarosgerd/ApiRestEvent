// ════════════════════════════════════════════════════════════
//  PANTALLA 0-C: REVISIÓN Y PAGO (Fase 2, paso 3)
// ════════════════════════════════════════════════════════════
// Misma nota de ubicación que event-list.js/registration.js: va en
// `public/js/modules/` (no `resources/js/modules/`) porque no hay Vite
// que compile `resources/`. <script> clásico, mismo scope global léxico.
//
// `goToReview()`/`goBackToForm()`/`buildSummary()`… en realidad
// `buildSummary` SÍ se movió acá (es el cuerpo real de la pantalla de
// revisión); lo que se dejó en home.blade.php fue `goToReview` y
// `goBackToForm`, las bisagras hacia la pantalla de formulario — mismo
// criterio que `selectEvent` en el paso 1. `onPaymentConfirmed()` se
// dejó también en home.blade.php: es la bisagra hacia la pantalla de
// confirmación/e-ticket (paso 4), aunque la dispare el polling de esta
// pantalla. `startOver()` tampoco se movió: resetea todo el flujo desde
// el botón de la pantalla de confirmación (paso 4) y llama a
// `displayTicketQr()` (paso 4) — no pertenece a revisión/pago.

function buildSummary(){
  const body = document.getElementById('summaryBody');
  body.innerHTML = '';
  let totIns = 0, totDon = 0, totSouv = 0, totDisc = 0;

  const showDonation = !!currentEvent.hasDonation;
  const showDiscount = currentEvent.hasPromoCode == 1;

  // Mostrar/ocultar columnas y filas de footer condicionales
  document.getElementById('thDonation').style.display    = showDonation ? '' : 'none';
  document.getElementById('ftRowDonation').style.display = showDonation ? '' : 'none';
  document.getElementById('thDiscount').style.display    = showDiscount ? '' : 'none';
  document.getElementById('ftRowDiscount').style.display = showDiscount ? '' : 'none';

  // colspan de las celdas de etiqueta = columnas totales - 1 (valor)
  const colspan = 6 - (showDonation ? 0 : 1) - (showDiscount ? 0 : 1);
  document.querySelectorAll('.ft-label-cell').forEach(td => td.colSpan = colspan);

  participants.forEach(p => {
    const ins       = p.precioCategoria + p.precioPolera;
    const souvTotal = p.souvenirs.reduce((a, s) => a + s.precio, 0);
    const souvTxt   = p.souvenirs.length
      ? p.souvenirs.map(s => `${escHtml(s.nombre)} (${formatMoney(s.precio)})`).join(', ')
      : '—';
    const disc      = p.promoDescuento > 0 ? p.promoDescuento : 0;
    const sub       = ins + p.donacion + souvTotal - disc;

    totIns  += ins;
    totDon  += p.donacion;
    totSouv += souvTotal;
    totDisc += disc;

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><strong>${escHtml(p.alias)}</strong><br>
          <small style="color:var(--muted)">${escHtml(p.nombre)} ${escHtml(p.apellido)}</small></td>
      <td>${escHtml(p.categoriaNombre || p.categoria)}</td>
      <td>${formatMoney(ins)}</td>
      ${showDonation ? `<td>${formatMoney(p.donacion)}</td>` : ''}
      <td>${souvTxt}</td>
      ${showDiscount ? `<td style="color:var(--success)">${disc > 0 ? '-'+formatMoney(disc) : '—'}</td>` : ''}
      <td><strong>${formatMoney(sub)}</strong></td>`;
    body.appendChild(tr);
  });

  const fee   = Math.round(totIns * 0.05 * 100) / 100;
  const extra = editMode === 'paid' ? (editCost || 0) : 0;
  const group = getGroupRules();
  const showGroupDiscount = group.enabled && group.max > 0 && participants.length >= group.max;
  const groupDiscount     = showGroupDiscount ? Math.round(totIns * group.pct * 100) / 100 : 0;
  const grand = totIns + totDon + totSouv + fee - totDisc - groupDiscount + extra;

  document.getElementById('ftInscription').textContent = formatMoney(totIns);
  document.getElementById('ftDonation').textContent    = formatMoney(totDon);
  document.getElementById('ftSouvenirs').textContent   = formatMoney(totSouv);
  document.getElementById('ftFee').textContent         = formatMoney(fee);
  document.getElementById('ftDiscount').textContent    = '-' + formatMoney(totDisc);
  document.getElementById('ftRowGroupDiscount').style.display = showGroupDiscount ? '' : 'none';
  document.getElementById('ftGroupDiscountLabel').textContent = `Group discount (${group.max} participants)`;
  document.getElementById('ftGroupDiscount').textContent = '-' + formatMoney(groupDiscount);
  document.getElementById('ftRowEditCost').style.display = extra > 0 ? '' : 'none';
  document.getElementById('ftEditCost').textContent    = formatMoney(extra);
  document.getElementById('ftTotal').textContent       = formatMoney(grand);

  // Deslinde de responsabilidad (texto/PDF del evento) — aplica a los tres editMode.
  renderDeslindeBlock();

  // Método de pago / confirmación, según editMode
  const btnConfirm = document.getElementById('btnConfirmPayment');
  if (editMode === 'pending') {
    document.getElementById('paymentMethodBlock').style.display = 'none';
    document.getElementById('paidConfirmBlock').style.display   = 'none';
    const note = document.getElementById('paymentMethodFixedNote');
    note.style.display = '';
    const fixedMethod = existingRegistration?.tipo_pago || '';
    const fixedFp = findFormaPago(fixedMethod);
    note.textContent = t('registration.paymentMethodFixed') + ' ' +
      (isPagoDiferido(fixedMethod) ? t('registration.paymentPendingLabel') : (fixedFp?.nombre || fixedMethod));
    btnConfirm.textContent = t('registration.updateBtn');
  } else if (editMode === 'paid') {
    document.getElementById('paymentMethodBlock').style.display     = 'none';
    document.getElementById('paymentMethodFixedNote').style.display = 'none';
    const block = document.getElementById('paidConfirmBlock');
    block.style.display = '';
    document.getElementById('paidConfirmMsg').textContent =
      t('registration.paidEditNotice') + ' $' + (editCost || 0).toFixed(2);
    document.getElementById('paidConfirmCheckbox').checked = false;
    btnConfirm.textContent = t('registration.updateBtn');
  } else {
    // Escenario 1: ya no se exige login previo — confirmPayment() da de
    // alta la persona automáticamente si hace falta (autoRegisterPersona).
    document.getElementById('paymentMethodFixedNote').style.display = 'none';
    document.getElementById('paidConfirmBlock').style.display       = 'none';
    document.getElementById('paymentMethodBlock').style.display     = '';
    // Inscripción en BOB y USD (20/08/2026) — el selector solo aparece
    // en alta nueva (no en edición pending/paid) y solo si el evento
    // acepta USD; siempre arranca en BOB.
    resetCurrencySelector(!!currentEvent?.aceptaUsd);
    btnConfirm.textContent = t('registration.confirmPaymentBtn');
  }
  updateConfirmButtonState();

  // Tarjetas de revisión
  const rev = document.getElementById('reviewParticipants');
  rev.innerHTML = '';
  participants.forEach((p, i) => {
    const souvTotal = (p.souvenirs || []).reduce((s, sv) => s + parseFloat(sv.precio || 0), 0);
    const subtotal  = Math.max(0, (p.precioCategoria || 0) + (p.precioPolera || 0) + souvTotal + (p.donacion || 0) - (p.promoDescuento || 0));
    const shirtTxt  = p.polera && p.polera !== 'No shirt' ? ` · <strong>Talla: ${escHtml(p.polera)}</strong>` : '';
    rev.innerHTML += `
      <div class="participant-row">
        <div class="participant-info">
          <div class="participant-name">${escHtml(p.nombre)} ${escHtml(p.apellido)}</div>
          <div class="participant-meta"><strong>${escHtml(p.categoriaNombre || p.categoria)}</strong>${shirtTxt} · <strong>${formatMoney(subtotal)}</strong></div>
        </div>
        <button class="btn btn-sm btn-secondary"
                onclick="goBackToForm();editParticipant(${i})">✏ Edit</button>
      </div>`;
  });
}

// Deslinde de responsabilidad: texto y PDF vienen de currentEvent.deslinde /
// currentEvent.deslinde_pdf_url (API externa). Si el evento no cargó texto,
// el bloque se oculta y no bloquea el flujo (compatibilidad con eventos
// existentes que aún no tienen este campo cargado).
function renderDeslindeBlock(){
  const block = document.getElementById('deslindeBlock');
  const text  = (currentEvent?.deslinde || '').trim();
  document.getElementById('deslindeCheckbox').checked = false;

  if (!text) { block.style.display = 'none'; return; }

  block.style.display = '';
  document.getElementById('deslindeText').textContent = text;

  const pdfUrl  = currentEvent?.deslinde_pdf_url || '';
  const pdfLink = document.getElementById('btnDeslindePdf');
  pdfLink.href = pdfUrl || '#';
  pdfLink.style.display = pdfUrl ? 'inline-block' : 'none';
}

// Único punto que decide si "Confirmar Pago" está habilitado — combina la
// condición propia de cada editMode con la aceptación del deslinde (cuando
// el bloque está visible). Se llama desde buildSummary() y desde los
// onchange de los checkboxes de deslinde/paidConfirm.
function updateConfirmButtonState(){
  const btnConfirm = document.getElementById('btnConfirmPayment');
  let disabled;

  if (editMode === 'pending') {
    disabled = false;
  } else if (editMode === 'paid') {
    disabled = !document.getElementById('paidConfirmCheckbox').checked;
  } else {
    const metodos = (currentEvent?.formasPago || [])
      .filter(fp => !UNSUPPORTED_PASARELAS.includes(fp.pasarela));
    disabled = metodos.length === 0;
  }

  if (document.getElementById('deslindeBlock').style.display !== 'none') {
    disabled = disabled || !document.getElementById('deslindeCheckbox').checked;
  }

  btnConfirm.disabled = disabled;
}

// ════════════════════════════════════════════════════════════
//  MÉTODO DE PAGO
// ════════════════════════════════════════════════════════════
// true si el método (slug de formasPago) no tiene pasarela automática —
// sea nuestro 'pendiente' o un método manual propio de un organizador.
// El fallback por string cubre registros creados antes de este cambio
// (tipo_pago guardado como 'Pendiente'/'EFECTIVO', valores legados).
function isPagoDiferido(method){
  const fp = findFormaPago(method);
  return fp ? fp.tipo === 'manual' : (method === 'Pendiente' || method === 'EFECTIVO');
}

// Busca el método de pago (por slug) dentro de los que la API devolvió para
// currentEvent.formasPago — es la fuente de verdad de qué pasarela/tipo
// corresponde a cada opción (sistema o propia de un organizador).
function findFormaPago(slug){
  return (currentEvent?.formasPago || []).find(fp => fp.slug === slug) || null;
}

// Íconos para métodos conocidos del sistema; cualquier método propio de un
// organizador (slug arbitrario que no reconocemos) usa el genérico.
const PAYMENT_METHOD_ICONS = { sip: '📱', pendiente: '🕒', multipago: '💳' };
const PAYMENT_METHOD_ICON_FALLBACK = '💰';

// Pasarelas que la API ya puede tener habilitadas (formasPago) pero cuyo
// flujo en esta SPA todavía no está terminado — se ocultan del selector
// hasta terminar ese wiring. Multipago ya está completo (iframe + polling +
// callback), pero multipago-payment-integration/.env todavía tiene
// credenciales placeholder (CAMBIAR_PROVIDER/CAMBIAR_UID/
// CAMBIAR_SERVICE_CODE) — createPayOrder() falla contra el staging real
// hasta que se confirmen las credenciales reales con Multipago.
const UNSUPPORTED_PASARELAS = [];

// Inscripción en BOB y USD (20/08/2026, portado de elascenso/event) — ver
// PLAN-INSCRIPCION-BOB-USD-IMPLEMENTACION.md. Selecciona la moneda de
// cobro real (default BOB; USD solo si el evento aceptaUsd=true y el
// participante lo eligió). Re-renderiza el selector de métodos: cuando
// USD, esconde los métodos manuales del organizador (solo QR y Multipago
// aceptan USD).
const _USD_PASARELAS = ['sip', 'multipago'];

// Muestra/oculta #currencySelectorBlock y fuerza la selección a BOB —
// llamado cada vez que se entra al paso de pago (buildSummary()) para que
// el bloque solo aparezca cuando el evento acepta USD, y nunca arrastre
// una elección de USD hecha en una inscripción anterior en la misma sesión.
function resetCurrencySelector(show){
  const block = document.getElementById('currencySelectorBlock');
  if (block) block.style.display = show ? '' : 'none';
  selectCurrency('BOB');
}

function selectCurrency(cur){
  cur = (cur === 'USD') ? 'USD' : 'BOB';
  paymentCurrency = cur;
  document.querySelectorAll('.currency-option').forEach(el => {
    const inp = el.querySelector('input');
    const isThis = inp && inp.value === cur;
    el.classList.toggle('selected', isThis);
    if (inp) inp.checked = isThis;
  });
  renderPaymentMethods();
  const hint = document.getElementById('currencyHint');
  if (hint) hint.style.display = cur === 'USD' ? '' : 'none';
}

// Devuelve la lista de formasPago del evento filtrada según la moneda de
// cobro elegida. En USD solo quedan las pasarelas reales (sip/multipago);
// en BOB no se filtra nada.
function _filterFormasPagoByCurrency(metodos){
  if (paymentCurrency !== 'USD') return metodos;
  return (metodos || []).filter(fp => _USD_PASARELAS.includes(fp.pasarela));
}

// Reconstruye el selector de métodos de pago a partir de currentEvent.formasPago
// (llamado desde goToReview() cuando se muestra el paso de pago de una
// inscripción nueva). Antes esto era HTML fijo (QR/Tigo/Pendiente); ahora lo
// decide la API según lo que el organizador haya configurado.
function renderPaymentMethods(){
  const cont = document.getElementById('paymentMethodsContainer');
  const emptyMsg = document.getElementById('noPaymentMethodsMsg');
  cont.innerHTML = '';

  const metodos = _filterFormasPagoByCurrency(
    (currentEvent?.formasPago || []).filter(fp => !UNSUPPORTED_PASARELAS.includes(fp.pasarela))
  );

  emptyMsg.style.display = metodos.length ? 'none' : '';

  metodos.forEach((fp, idx) => {
    const label = document.createElement('label');
    label.className = 'payment-method' + (idx === 0 ? ' selected' : '');
    label.id = 'pm-' + fp.slug;
    label.innerHTML = `
      <input type="radio" name="tipoPago" value="${escHtml(fp.slug)}" ${idx === 0 ? 'checked' : ''}>
      <div class="payment-icon">${PAYMENT_METHOD_ICONS[fp.slug] || PAYMENT_METHOD_ICON_FALLBACK}</div>
      <div class="payment-label">${escHtml(fp.nombre)}</div>
    `;
    // El input real ya no es display:none (ver app.css) — con esto el
    // radio nativo del navegador ya maneja flechas/Espacio solo, no hace
    // falta un keydown propio. Escuchar 'change' en el input en vez de
    // 'click' en el label cubre mouse y teclado por igual (auditoría
    // 10/08/2026 §1).
    label.querySelector('input').addEventListener('change', () => selectPayment(fp.slug));
    cont.appendChild(label);
  });

  return metodos;
}

function selectPayment(val){
  document.querySelectorAll('.payment-method').forEach(m => {
    const inp = m.querySelector('input');
    const isThis = inp && inp.value === val;
    m.classList.toggle('selected', isThis);
    if (inp) inp.checked = isThis;
  });
}

// Escenario 1: usuario nuevo (sin cuenta persona) completa el formulario de
// inscripción y confirma sin haberse logueado antes. Damos de alta la cuenta
// persona con los datos ya cargados del primer participante — correo como
// login y número de documento como password (se hashea en la API externa,
// igual que cualquier password elegido a mano en modalRegister) — y
// aplicamos la sesión resultante para poder continuar el submit normal.
async function autoRegisterPersona(p){
  const { anio, mes, dia } = p.nacimiento || {};
  const fechaNacimiento = (anio && mes && dia)
    ? `${anio}-${String(mes).padStart(2,'0')}-${String(dia).padStart(2,'0')}`
    : '';

  const data = await fetchJsonWithRetry(`${API_BASE}/persona_register.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      nombre: p.nombre, apellido: p.apellido, alias: p.alias, sexo: p.genero,
      tipo_documento: p.tipoDocumento, numero_documento: p.numeroDocumento,
      fecha_nacimiento: fechaNacimiento,
      email: p.correo, correo: p.correo,
      direccion: p.direccion, ciudad: p.ciudad,
      telefono: p.telefono, celular: p.telefono,
      password: p.numeroDocumento
    })
  });

  applyLoggedInSession({
    id: data.data?.id ?? null,
    nombre: p.nombre, apellido: p.apellido, alias: p.alias, sexo: p.genero,
    tipoDocumento: p.tipoDocumento, numeroDocumento: p.numeroDocumento,
    nacimiento: { dia: Number(dia), mes: Number(mes), anio: Number(anio) },
    correo: p.correo, direccion: p.direccion, ciudad: p.ciudad,
    telefono: p.telefono, celular: p.telefono,
    contacto_emergencia: p.contacto_emergencia || { nombre: '', celular: '', relacion: '' }
  }, data.token);
}

// ════════════════════════════════════════════════════════════
//  CONFIRMAR PAGO  →  API PHP
// ════════════════════════════════════════════════════════════
async function confirmPayment(){
  const errEl   = document.getElementById('paymentApiError');
  const btn     = document.getElementById('btnConfirmPayment');
  const btnLabelAfter = btn.textContent;
  errEl.classList.remove('show');

  if (editMode === 'new' && !loggedUser) {
    try {
      await autoRegisterPersona(participants[0]);
    } catch (err) {
      const isNetworkError = err instanceof TypeError;
      // El backend externo solo devuelve sus mensajes de validación en español;
      // en vez de traducir el texto (frágil, depende del idioma del backend),
      // usamos el campo estructurado 'errors.email' que Laravel siempre envía
      // en el mismo formato sin importar el idioma del mensaje.
      const isEmailTaken = !!(err.errors && err.errors.email);
      let msg;
      if (isNetworkError) msg = t('registration.autoRegisterNetworkError');
      else if (isEmailTaken) msg = t('registration.autoRegisterEmailTaken') + ' ' + t('registration.autoRegisterFailedHint');
      else msg = err.message + ' ' + t('registration.autoRegisterFailedHint');
      errEl.textContent = '⚠ ' + msg;
      errEl.classList.add('show');
      goBackToForm();
      if (!isNetworkError) toggleLoginForm();
      return;
    }
  }

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Processing…';

  try {
    let data, method;

    if (editMode === 'pending') {
      method = existingRegistration.tipo_pago;
      data = await fetchJson(`${API_BASE}/registro_actualizar.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          referencia:    existingRegistration.referencia,
          evento_id:     currentEvent.id,
          form_type_id:  selectedFormType?.id,
          participantes: participants
        })
      });
    } else if (editMode === 'paid') {
      method = existingRegistration.tipo_pago;
      data = await fetchJson(`${API_BASE}/registro_actualizar_pagada.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          referencia:    existingRegistration.referencia,
          evento_id:     currentEvent.id,
          form_type_id:  selectedFormType?.id,
          participantes: participants,
          confirmacion:  true
        })
      });
      editCost = data.costo_adicion ?? editCost;
      data.totales.grand_total = (data.totales.grand_total || 0) + editCost;
    } else {
      method = document.querySelector('input[name="tipoPago"]:checked')?.value || '';
      data = await fetchJson(`${API_BASE}/registro.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          evento_id:     currentEvent.id,
          form_type_id:  selectedFormType?.id,
          tipoPago:      method,
          participantes: participants,
          auth_token:    authToken,
          // Inscripción en BOB y USD (20/08/2026) — el backend nunca
          // confía en esto para el precio (revalida server-side), pero
          // decide con qué moneda arma el QR/orden de pago.
          moneda_pago:   paymentCurrency
        })
      });
    }

    const referencia = data.referencia || existingRegistration?.referencia;
    const totales     = data.totales;

    const summaryHtml = `
      <div style="background:var(--light);border-radius:var(--radius);padding:16px 20px;">
        <p style="margin-bottom:6px;font-size:13px;"><strong>Event:</strong> ${escHtml(currentEvent.name)}</p>
        <p style="margin-bottom:6px;font-size:13px;"><strong>Payment method:</strong> ${escHtml(isPagoDiferido(method) ? t('registration.paymentPendingLabel') : (findFormaPago(method)?.nombre || method))}</p>
        ${totales.descuento_registrante > 0 ? `<p style="margin-bottom:6px;font-size:13px;color:var(--success);"><strong>Group discount:</strong> -${formatMoney(totales.descuento_registrante)}</p>` : ''}
        <p style="margin-bottom:6px;font-size:13px;"><strong>Grand total:</strong> ${formatMoney(totales.grand_total)}</p>
        <p style="font-size:13px;"><strong>Participants:</strong>
          ${participants.map(p => escHtml(p.alias) + ' (' + escHtml(p.categoria) + ')').join(', ')}
        </p>
      </div>`;

    lastQrImage = data.qr_image || null;
    refQrImage  = generateReferenceQr(referencia);

    // editMode 'paid' no elige método (ya está pagado, solo se confirma una
    // edición) — para 'new'/'pending' el método viene de formasPago del
    // evento, sea del sistema o propio del organizador.
    const metodoInfo = editMode === 'paid' ? null : findFormaPago(method);

    if (metodoInfo?.pasarela === 'sip') {
      document.getElementById('confirmPending').style.display = 'block';
      document.getElementById('confirmPendingManual').style.display = 'none';
      document.getElementById('confirmPendingMultipago').style.display = 'none';
      document.getElementById('confirmPaid').style.display    = 'none';
      document.getElementById('confirmRefPending').textContent = referencia;
      document.getElementById('confirmSummaryPending').innerHTML = summaryHtml;
      displayQrCode(lastQrImage, referencia);
      displayTicketQr(refQrImage);
      showScreen('screen-confirmation');
      setStep(3);
      startPaymentPolling(referencia, method, totales);
    } else if (metodoInfo?.pasarela === 'multipago') {
      // Gateway de redirección/iframe: Multipago devuelve url_to_pay, el
      // cliente elige método (Tigo Money, tarjeta, QR, punto físico) dentro
      // del iframe. El polling reusa startPaymentPolling() tal cual — es
      // agnóstico de método, pago_status.php resuelve la rama del lado del
      // servidor según pay_order_number.
      document.getElementById('confirmPending').style.display = 'none';
      document.getElementById('confirmPendingManual').style.display = 'none';
      document.getElementById('confirmPaid').style.display    = 'none';
      document.getElementById('confirmPendingMultipago').style.display = 'block';
      document.getElementById('confirmRefPendingMultipago').textContent = referencia;
      document.getElementById('confirmSummaryPendingMultipago').innerHTML = summaryHtml;
      document.getElementById('multipagoIframe').src = data.url_to_pay || '';
      showScreen('screen-confirmation');
      setStep(3);
      startPaymentPolling(referencia, method, totales);
    } else if (metodoInfo?.tipo === 'manual') {
      // El usuario elige (o el método del organizador es) sin pasarela
      // automática — puede completarlo después iniciando sesión (Escenario 3),
      // o el organizador lo confirma manualmente por su cuenta.
      document.getElementById('confirmPending').style.display = 'none';
      document.getElementById('confirmPendingMultipago').style.display = 'none';
      document.getElementById('confirmPaid').style.display    = 'none';
      document.getElementById('confirmPendingManual').style.display = 'block';
      document.getElementById('confirmRefPendingManual').textContent = referencia;
      document.getElementById('confirmSummaryPendingManual').innerHTML = summaryHtml;
      showScreen('screen-confirmation');
      setStep(3);
    } else if (editMode === 'paid') {
      document.getElementById('confirmPending').style.display = 'none';
      document.getElementById('confirmPendingManual').style.display = 'none';
      document.getElementById('confirmPendingMultipago').style.display = 'none';
      document.getElementById('confirmPaid').style.display    = 'block';
      buildETicket(referencia, method, totales);
      showScreen('screen-confirmation');
      setStep(4);
      // Escenarios 3/4: al editar un registro existente (pendiente o ya
      // pagado) el token de sesión se revoca apenas se confirma el pago, por
      // seguridad — no cuando es una inscripción nueva (editMode 'new').
      if (editMode === 'pending' || editMode === 'paid') {
        await revokeAuthToken();
        loggedUser = null;
        refreshHeaderLoginUI();
      }
    } else {
      // No debería pasar: el selector solo ofrece métodos de formasPago del
      // evento. Si llega acá es que el método no se pudo resolver — no se
      // asume "pagado" por seguridad.
      throw new Error(t('registration.unknownPaymentMethod'));
    }

  } catch(err){
    errEl.textContent = '⚠ ' + err.message;
    errEl.classList.add('show');
  } finally {
    btn.disabled = (editMode === 'paid' && !document.getElementById('paidConfirmCheckbox')?.checked);
    btn.textContent = btnLabelAfter;
  }
}
// ════════════════════════════════════════════════════════════
//  PAYMENT POLLING (every 30s)
// ════════════════════════════════════════════════════════════
let paymentPollTimer    = null;
let paymentCountdown    = null;

function startPaymentPolling(referencia, method, totales){
  stopPaymentPolling();
  scheduleNextPoll(referencia, method, totales);
}

function stopPaymentPolling(){
  if (paymentPollTimer)  { clearTimeout(paymentPollTimer);   paymentPollTimer  = null; }
  if (paymentCountdown)  { clearInterval(paymentCountdown);  paymentCountdown  = null; }
}

function scheduleNextPoll(referencia, method, totales){
  let remaining = 30;
  updatePollText(remaining);

  paymentCountdown = setInterval(() => {
    remaining--;
    if (remaining > 0) updatePollText(remaining);
  }, 1000);

  paymentPollTimer = setTimeout(() => {
    clearInterval(paymentCountdown);
    checkPaymentStatus(referencia, method, totales);
  }, 30000);
}

// Ambas pantallas de espera (QR de SIP, iframe de Multipago) comparten el
// mismo polling agnóstico de método — cada una tiene su propio elemento de
// texto de estado, así que se actualizan los dos (el oculto no se nota).
function updatePollText(sec){
  document.querySelectorAll('#pollStatusText, #pollStatusTextMultipago').forEach(el => {
    el.textContent = t('registration.pollNext').replace('%d', sec);
  });
}

async function checkPaymentStatus(referencia, method, totales){
  document.querySelectorAll('#pollStatusText, #pollStatusTextMultipago').forEach(el => {
    el.textContent = t('registration.pollChecking');
  });

  try {
    const data = await fetchJson(`${API_BASE}/pago_status.php?referencia=${encodeURIComponent(referencia)}`);

    if (data.status === 'paid') {
      onPaymentConfirmed(referencia, method, totales);
      return;
    }
  } catch(e) {}

  scheduleNextPoll(referencia, method, totales);
}

