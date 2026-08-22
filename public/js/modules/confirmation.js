// ════════════════════════════════════════════════════════════
//  PANTALLA 0-D: CONFIRMACIÓN + E-TICKET (Fase 2, paso 4)
// ════════════════════════════════════════════════════════════
// Misma nota de ubicación que los módulos anteriores: `public/js/modules/`
// (no `resources/js/modules/`) porque no hay Vite que compile `resources/`.
// <script> clásico, mismo scope global léxico.
//
// A diferencia de los pasos 1-3, acá SÍ se movieron las dos funciones
// "bisagra" que se habían dejado pendientes en revision-payment.js:
// `startOver()` (resetea todo el flujo, botón "Registrar otro
// participante" de esta misma pantalla) y `onPaymentConfirmed()`
// (dispara la transición pago→confirmado y arma el e-ticket) — ambas
// pertenecen de lleno a esta pantalla, no a la de revisión/pago.
// `lastTicketArgs` (usado por `buildETicket` acá y por
// `refreshVisibleMoneyDisplays` en home.blade.php al cambiar de moneda)
// sigue declarado en home.blade.php — es estado compartido entre esta
// pantalla y el selector de moneda del header, no exclusivo de acá.

// ════════════════════════════════════════════════════════════
//  START OVER
// ════════════════════════════════════════════════════════════
async function startOver(){
  stopPaymentPolling();
  await revokeAuthToken();
  participants      = [];
  loggedUser        = null;
  appliedPromoType  = 'fixed_price';
  appliedPromoValue = 0;
  currentEvent      = null;
  selectedFormType  = null;
  lastQrImage       = null;
  refQrImage        = null;
  editMode              = 'new';
  existingRegistration  = null;
  editCost              = 0;
  paidEditUnlocked      = false;
  applyPaidLock(false);

  document.getElementById('loginWelcome').classList.remove('visible');
  document.getElementById('loginBanner').style.display = '';
  document.getElementById('participantsList').style.display = 'none';
  displayTicketQr(null);
  document.querySelectorAll('.event-card').forEach(c => c.classList.remove('selected'));
  document.querySelectorAll('.form-type-card').forEach(c => {
    c.classList.remove('selected'); c.style.borderColor = 'transparent';
  });
  resetParticipantForm();
  showSub('sub-form');
  showScreen('screen-event-list');
  setStep(1);
}

// (funciones movidas a public/js/modules/review-payment.js)
async function onPaymentConfirmed(referencia, method, totales){
  stopPaymentPolling();
  document.getElementById('confirmPending').style.display  = 'none';
  document.getElementById('confirmPaid').style.display     = 'block';
  buildETicket(referencia, method, totales);
  // El correo de confirmación de pago ya no lo dispara el frontend — lo
  // envía ApiRestEvent al procesar la transición a pago_status=paid (ver
  // brain/PLAN-NOTIFICACIONES.md §0/§1).
  setStep(4);
  // Mismo criterio de seguridad que en confirmPayment(): revocar el token
  // solo cuando esto confirma la edición de un registro existente.
  if (editMode === 'pending' || editMode === 'paid') {
    await revokeAuthToken();
    loggedUser = null;
    refreshHeaderLoginUI();
  }
}

// ════════════════════════════════════════════════════════════
//  E-TICKET
// ════════════════════════════════════════════════════════════
function buildETicket(referencia, method, totales){
  lastTicketArgs = [referencia, method, totales];
  const now = new Date();
  const fecha = now.toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });
  const hora  = now.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit' });

  let participantsHtml = '';
  participants.forEach(p => {
    const souvTxt = p.souvenirs.length
      ? p.souvenirs.map(s => `${escHtml(s.nombre)} (${formatMoney(s.precio)})`).join(', ')
      : 'None';
    participantsHtml += `
      <div class="eticket-participant">
        <div class="eticket-participant-name">${escHtml(p.alias)} — ${escHtml(p.nombre)} ${escHtml(p.apellido)}</div>
        <div class="eticket-participant-detail">
          Category: <strong>${escHtml(p.categoria)}</strong> · ${formatMoney(p.precioCategoria)}<br>
          Document: <strong>${escHtml(p.tipoDocumento || '')} ${escHtml(p.numeroDocumento || '')}</strong><br>
          Shirt: <strong>${escHtml(p.polera)}</strong><br>
          Souvenirs: ${souvTxt}<br>
          ${p.donacion > 0 ? 'Donation: <strong>' + formatMoney(p.donacion) + '</strong><br>' : ''}
          ${p.promoDescuento > 0 ? 'Promo: <strong>' + escHtml(p.promoCodigo) + ' (-' + formatMoney(p.promoDescuento) + ')</strong>' : ''}
        </div>
      </div>`;
  });

  const paymentLabels = { QR: 'QR Code', Tigo: 'Tigo Money' };

  // Agenda propia del participante: solo para pagos confirmados, y filtrada
  // al tipo de formulario con el que se inscribió (más los items generales
  // del evento, sin importar el tipo) — no la agenda completa del evento.
  const myAgenda = (currentEvent && currentEvent.agenda)
    ? currentEvent.agenda.filter(item =>
        item.formTypeId == null || (selectedFormType && String(item.formTypeId) === String(selectedFormType.id))
      )
    : [];
  const myAgendaHtml = myAgenda.length > 0 ? `
    <hr class="eticket-divider">
    <div class="eticket-body">
      <p style="font-size:12px;font-weight:700;color:var(--secondary);margin-bottom:10px;text-transform:uppercase;">${escHtml(t('formTypes.agendaTitle'))}</p>
      ${buildAgendaListHtml(myAgenda)}
    </div>` : '';

  const html = `
    <div class="eticket-header">
      <h3>${escHtml(currentEvent ? currentEvent.name : 'Event')}</h3>
      <p>${escHtml(currentEvent ? currentEvent.date + ' · ' + (currentEvent.localTime || '') + ' · ' + currentEvent.location : '')}</p>
    </div>
    <div class="eticket-ref">
      <p>REFERENCE</p>
      <span>${escHtml(referencia)}</span>
    </div>
    <div class="eticket-body">
      <div class="eticket-row">
        <span class="eticket-label">Date</span>
        <span class="eticket-value">${fecha} · ${hora}</span>
      </div>
      <div class="eticket-row">
        <span class="eticket-label">Payment</span>
        <span class="eticket-value">${escHtml(paymentLabels[method] || method)}</span>
      </div>
      <div class="eticket-row">
        <span class="eticket-label">Status</span>
        <span class="eticket-value" style="color:var(--success);font-weight:700;">✓ Paid</span>
      </div>
    </div>
    <hr class="eticket-divider">
    <div class="eticket-body">
      <p style="font-size:12px;font-weight:700;color:var(--secondary);margin-bottom:10px;text-transform:uppercase;">Participants (${participants.length})</p>
      ${participantsHtml}
    </div>
    ${myAgendaHtml}
    <hr class="eticket-divider">
    <div class="eticket-body" style="padding-bottom:0;">
      <div class="eticket-row">
        <span class="eticket-label">Inscription</span>
        <span class="eticket-value">${formatMoney(totales.inscripcion)}</span>
      </div>
      <div class="eticket-row">
        <span class="eticket-label">Donation</span>
        <span class="eticket-value">${formatMoney(totales.donacion)}</span>
      </div>
      <div class="eticket-row">
        <span class="eticket-label">Souvenirs</span>
        <span class="eticket-value">${formatMoney(totales.souvenirs)}</span>
      </div>
      <div class="eticket-row">
        <span class="eticket-label">Service Fee (5%)</span>
        <span class="eticket-value">${formatMoney(totales.fee)}</span>
      </div>
      ${totales.descuento > 0 ? `
      <div class="eticket-row">
        <span class="eticket-label">Discount</span>
        <span class="eticket-value" style="color:var(--success);">-${formatMoney(totales.descuento)}</span>
      </div>` : ''}
      ${totales.descuento_registrante > 0 ? `
      <div class="eticket-row">
        <span class="eticket-label">Group discount (${getGroupRules().max} participants)</span>
        <span class="eticket-value" style="color:var(--success);">-${formatMoney(totales.descuento_registrante)}</span>
      </div>` : ''}
    </div>
    <div class="eticket-total">
      <p>GRAND TOTAL</p>
      <span>${formatMoney(totales.grand_total)}</span>
    </div>
    ${refQrImage ? `
    <div style="text-align:center;padding:20px 24px;border-top:2px dashed var(--border);">
      <p style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;margin:0 0 10px;letter-spacing:1px;">Ticket Reference QR</p>
      <img src="data:image/png;base64,${refQrImage}"
           alt="QR de referencia" style="width:160px;height:160px;border:1px solid var(--border);border-radius:8px;">
      <p style="font-size:11px;color:var(--muted);margin:8px 0 0;">${escHtml(referencia)}</p>
    </div>` : ''}
    <div class="eticket-footer">
      Keep this e-ticket as proof of payment · ${escHtml(referencia)}
    </div>`;

  document.getElementById('eticketContainer').innerHTML = html;
}

// ════════════════════════════════════════════════════════════
//  TICKET QR DISPLAY  (referencia QR, shown while pending too)
// ════════════════════════════════════════════════════════════
function displayTicketQr(base64Image){
  const block = document.getElementById('ticketQrBlock');
  const img   = document.getElementById('ticketQrImage');
  if (!block || !img) return;
  if (base64Image) {
    img.src = 'data:image/png;base64,' + base64Image;
    block.style.display = 'block';
  } else {
    block.style.display = 'none';
  }
}

// ════════════════════════════════════════════════════════════
//  QR CODE DISPLAY  (real SIP image or simulated fallback)
// ════════════════════════════════════════════════════════════
function displayQrCode(base64Image, ref){
  const img      = document.getElementById('qrImage');
  const canvas   = document.getElementById('qrCanvas');
  const subtitle = document.getElementById('qrSubtitle');

  if (base64Image) {
    img.src          = 'data:image/png;base64,' + base64Image;
    // inline-block, no block: así respeta el text-align:center del
    // contenedor (mismo motivo por el que el QR de referencia de abajo
    // ya se veía centrado — ese es un <img> sin display:block forzado).
    img.style.display   = 'inline-block';
    canvas.style.display = 'none';
    subtitle.textContent = 'Escanee el código QR con su app bancaria para pagar';
  } else {
    // Fallback: QR simulado (cuando SIP no está configurado)
    canvas.style.display = 'inline-block';
    img.style.display    = 'none';
    subtitle.textContent = 'Simulated QR — for demonstration purposes only';
    _drawSimulatedQR(canvas, ref);
  }
}

function _drawSimulatedQR(canvas, ref){
  const ctx    = canvas.getContext('2d');
  const size   = canvas.width;
  const cells  = 21;
  const cell   = Math.floor(size / cells);
  const offset = Math.floor((size - cells * cell) / 2);

  ctx.fillStyle = '#ffffff';
  ctx.fillRect(0, 0, size, size);
  ctx.fillStyle = '#000000';

  let seed = 0;
  for (let i = 0; i < ref.length; i++) seed = ((seed << 5) - seed + ref.charCodeAt(i)) | 0;
  function rng(){ seed = (seed * 16807 + 0) % 2147483647; return seed / 2147483647; }

  function drawFinderPattern(r, c){
    for (let dr = 0; dr < 7; dr++){
      for (let dc = 0; dc < 7; dc++){
        const fill = dr === 0 || dr === 6 || dc === 0 || dc === 6 ||
                     (dr >= 2 && dr <= 4 && dc >= 2 && dc <= 4);
        if (fill) ctx.fillRect(offset + (c+dc)*cell, offset + (r+dr)*cell, cell, cell);
      }
    }
  }

  drawFinderPattern(0, 0);
  drawFinderPattern(0, cells - 7);
  drawFinderPattern(cells - 7, 0);

  for (let r = 0; r < cells; r++){
    for (let c = 0; c < cells; c++){
      const inFinder = (r < 8 && c < 8) || (r < 8 && c >= cells-8) || (r >= cells-8 && c < 8);
      if (!inFinder && rng() > 0.5) ctx.fillRect(offset + c*cell, offset + r*cell, cell, cell);
    }
  }
}

// ════════════════════════════════════════════════════════════
//  TICKET REFERENCE QR  (real, scannable — encodes the referencia,
//  independiente del proveedor de pago SIP/nueva API)
// ════════════════════════════════════════════════════════════
function generateReferenceQr(referencia){
  try {
    const qr = qrcode(0, 'M');
    qr.addData(referencia);
    qr.make();

    const count  = qr.getModuleCount();
    const size   = 200;
    const cell   = Math.floor(size / count);
    const offset = Math.floor((size - cell * count) / 2);

    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = size;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, size, size);
    ctx.fillStyle = '#000000';
    for (let r = 0; r < count; r++){
      for (let c = 0; c < count; c++){
        if (qr.isDark(r, c)) ctx.fillRect(offset + c*cell, offset + r*cell, cell, cell);
      }
    }

    const dataUrl = canvas.toDataURL('image/png');
    return dataUrl.substring(dataUrl.indexOf('base64,') + 7);
  } catch(e) {
    console.error('No se pudo generar el QR de referencia:', e);
    return null;
  }
}
