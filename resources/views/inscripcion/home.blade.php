<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $pageTitle }}</title>
  <meta name="description" content="{{ $pageDescription }}" />
  <link rel="canonical" href="{{ $pageUrl }}" />

  <meta property="og:type" content="website" />
  <meta property="og:site_name" content="Pass2Go" />
  <meta property="og:title" content="{{ $pageTitle }}" />
  <meta property="og:description" content="{{ $pageDescription }}" />
  <meta property="og:url" content="{{ $pageUrl }}" />
  @if ($pageImage)
  <meta property="og:image" content="{{ $pageImage }}" />
  @endif

  <meta name="twitter:card" content="{{ $pageImage ? 'summary_large_image' : 'summary' }}" />
  <meta name="twitter:title" content="{{ $pageTitle }}" />
  <meta name="twitter:description" content="{{ $pageDescription }}" />
  @if ($pageImage)
  <meta name="twitter:image" content="{{ $pageImage }}" />
  @endif
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/css/intlTelInput.css"/>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/intlTelInput.min.js"></script>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}" />
</head>
<body>

<!-- ══ NAVBAR ══ -->
<nav class="navbar">
  <div class="navbar-inner">
    <a href="#" class="brand-link" onclick="event.preventDefault(); showScreen('screen-event-list');">
      <div class="brand-mark">P</div>
      <div class="brand-text">
        <strong>Pass2Go</strong>
        <span>Event Registration</span>
      </div>
    </a>
    <div class="navbar-actions">
      <div class="language-switcher" role="group" aria-label="Language selector">
        <button class="lang-btn" type="button" data-lang="en" onclick="setLanguage('en')">EN</button>
        <button class="lang-btn active" type="button" data-lang="es" onclick="setLanguage('es')">ES</button>
        <button class="lang-btn" type="button" data-lang="pt" onclick="setLanguage('pt')">PT</button>
      </div>
      <div class="language-switcher" role="group" aria-label="Currency selector" id="currencySwitcher">
        <button class="lang-btn active" type="button" data-currency="BOB" onclick="setCurrency('BOB')">Bs</button>
        <button class="lang-btn" type="button" data-currency="USD" onclick="setCurrency('USD')">$</button>
        <button class="lang-btn" type="button" data-currency="BRL" onclick="setCurrency('BRL')">R$</button>
      </div>
      <button id="animToggle" class="lang-btn" type="button" onclick="toggleAnimations()" aria-pressed="false">Anim: On</button>

      <button class="btn-help" type="button" onclick="showScreen('screen-club-login')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/>
          <circle cx="10" cy="7" r="4"/>
          <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
        <span data-i18n="club.accessLink">Clubs</span>
      </button>

      <button class="btn-help" id="headerLoginBtn" type="button" onclick="openAccountModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <circle cx="12" cy="8" r="3.5"/>
          <path d="M5 20c0-3.5 3-6 7-6s7 2.5 7 6"/>
        </svg>
        <span id="headerLoginLabel">Log in</span>
      </button>

      <button class="btn-help" onclick="showHelp()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <circle cx="12" cy="12" r="10"/>
          <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
          <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        <span data-i18n="common.help">Need Help?</span>
      </button>
    </div>
  </div>
</nav>

@include('inscripcion.partials.account-modal')

@include('inscripcion.partials.agenda-modal')

@include('inscripcion.partials.talleres-agenda-modal')

<!-- ══ HERO ══ -->
<div class="hero">
  <div class="hero-brand">
    <div class="brand-mark">P</div>
    <div>
      <h1>Pass2Go</h1>
      <p data-i18n="hero.subtitle">Professional event registration experience</p>
    </div>
  </div>
</div>

<!-- ══ MAIN WRAPPER ══ -->
<div class="main-wrapper">

  <!-- ────────────────────────────────────────
       PANTALLA 0-A: LISTADO DE EVENTOS
  ──────────────────────────────────────── -->
  @include('inscripcion.partials.screen-event-list')


  <!-- ────────────────────────────────────────
       PANTALLA 0-B: TIPOS DE FORMULARIO
  ──────────────────────────────────────── -->
  @include('inscripcion.partials.screen-form-types')


  <!-- ────────────────────────────────────────
       PANTALLA 1: REGISTRO
  ──────────────────────────────────────── -->
  <div class="screen" id="screen-registration">
    <div class="card">

      <!-- Banner de vista previa (25/08/2026, portado de elascenso/event) — mismo
           criterio que previewBannerFormTypes, se mantiene visible en todos los
           sub-pasos (form/revisión/pago) porque vive afuera de los includes de
           abajo, dentro del mismo .card. -->
      <div id="previewBannerRegistration" style="display:none;background:#fff8e6;border:1px solid #f0c040;border-radius:12px;padding:14px 20px;margin:16px 16px 0;font-size:14px;color:#7a5400;font-weight:600;" data-i18n="registration.previewBannerMsg"></div>

      <!-- Stepper -->
      <div class="stepper" id="stepper">
        <div class="step active" id="step-1" onclick="stepperNavigate(1)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();stepperNavigate(1);}">
          <div class="step-circle">1</div>
          <span class="step-label" data-i18n="registration.stepParticipant">Participant</span>
        </div>
        <div class="step-line" id="line-1"></div>
        <div class="step" id="step-2" onclick="stepperNavigate(2)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();stepperNavigate(2);}">
          <div class="step-circle">2</div>
          <span class="step-label" data-i18n="registration.stepReview">Review</span>
        </div>
        <div class="step-line" id="line-2"></div>
        <div class="step" id="step-3" onclick="stepperNavigate(3)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();stepperNavigate(3);}">
          <div class="step-circle">3</div>
          <span class="step-label" data-i18n="registration.stepPayment">Payment</span>
        </div>
        <div class="step-line" id="line-3"></div>
        <div class="step" id="step-4">
          <div class="step-circle">✓</div>
          <span class="step-label" data-i18n="registration.stepConfirm">Confirm</span>
        </div>
      </div>

      <div class="card-body">

        <!-- ══ SUB-PANTALLA A: FORMULARIO ══ -->
        @include('inscripcion.partials.registration-form')


        <!-- ══ SUB-PANTALLA B: REVIEW & PAGO ══ -->
        @include('inscripcion.partials.review-payment')

      </div><!-- /card-body -->
    </div><!-- /card -->
  </div><!-- /screen-registration -->


  <!-- ────────────────────────────────────────
       PANTALLA 2: CONFIRMACIÓN
  ──────────────────────────────────────── -->
  @include('inscripcion.partials.screen-confirmation')

  <!-- ══ PANTALLA: MIS RESULTADOS ══ -->
  @include('inscripcion.partials.results-club')

</div><!-- /main-wrapper -->


<!-- ══ JAVASCRIPT ══ -->
<!-- Módulos por pantalla (Fase 2 de la migración) — script clásico, comparten
     scope global léxico con el <script> de abajo, así que el orden entre
     ambos no importa para las funciones (solo se invocan después del parse
     completo del documento vía event handlers / DOMContentLoaded). -->
<script src="{{ asset('js/i18n.js') }}"></script>
<script src="{{ asset('js/modules/event-list.js') }}"></script>
<script src="{{ asset('js/modules/registration.js') }}"></script>
<script src="{{ asset('js/modules/review-payment.js') }}"></script>
<script src="{{ asset('js/modules/confirmation.js') }}"></script>
<script src="{{ asset('js/modules/account.js') }}"></script>
<script src="{{ asset('js/modules/results-club.js') }}"></script>
<script>
// ── Config inyectada desde PHP ── EXTERNAL_API_BASE ya NO se expone acá: el
// navegador llama a proxies locales (api/eventos.php, api/persona_login.php,
// etc.) que reenvían server-to-server — ver brain/PLAN-PROXY-API-EXTERNA.md.
const API_BASE = '<?= $apiBase ?>';
const EXTERNAL_API_RETRIES       = <?= $externalApiRetries ?>;
const EXTERNAL_API_RETRY_MS      = <?= $externalApiRetryMs ?>;
// Evento pedido vía ?evento=<id> en la URL (link compartido) — si viene, se
// abre directo su pantalla de tipos de formulario al cargar (ver INIT abajo).
const DEEP_LINK_EVENT_ID = <?= json_encode($eventoIdParam !== '' ? $eventoIdParam : null) ?>;
// ── Estado global ───────────────────────────────────────────
let currentEvent      = null;
let selectedFormType  = null;
let participants      = [];
let loggedUser        = null;
let authToken         = null;
let clubAuthToken     = null;
let appliedPromoType  = 'fixed_price'; // 'fixed_price' | 'percentage'
let appliedPromoValue = 0;             // precio final (fixed_price) o fracción 0-1 (percentage)
let editMode             = 'new';  // 'new' | 'pending' | 'paid'
let existingRegistration = null;   // registro devuelto por /registrations/lookup (type:"registration")
let editCost              = 0;     // costo_adicion a sumar cuando editMode === 'paid'
let paidEditUnlocked      = false; // true cuando el usuario confirmó el costo y desbloqueó la edición
let lastQrImage       = null;   // base64 del QR de pago (SIP/nueva API) al registrar
let refQrImage         = null;   // base64 del QR de referencia (ticket), generado localmente
// allEvents/eventsPagination/eventsCurrentPage/eventsPerPage/eventFilterDebounce/
// eventFilterRequestId ahora se declaran en public/js/modules/event-list.js
// (Fase 2, paso 1) — <script> clásico, mismo scope global léxico, así que
// siguen siendo accesibles acá tal cual antes de la extracción.
let itiTel = null, itiCel = null;
let currentLang = 'es';
// Moneda de visualización — los montos SIEMPRE se calculan y cobran en BOB;
// esto solo cambia cómo se muestran (ver formatMoney()).
let currentCurrency = 'BOB';
let exchangeRates    = { USD: 1, BRL: 1 }; // bolivianos -> moneda (se cargan de api/tipo_cambio.php)
// Inscripción en BOB y USD (20/08/2026, portado de elascenso/event) —
// distinto de currentCurrency (solo visual): esta es la moneda de cobro
// real, 'BOB' o 'USD'. Solo puede ser 'USD' si el evento tiene
// aceptaUsd=true y el participante lo eligió explícitamente en el paso
// de pago — el default siempre es BOB.
let paymentCurrency = 'BOB';
let countdownInterval = null;
let prevCountdownValues = { days: null, hours: null, minutes: null, seconds: null };
let animationsEnabled = true;

// (objeto de traducciones movido a public/js/i18n.js — Fase 3)

function t(key) {
  const parts = key.split('.');
  let value = translations[currentLang];
  for (const part of parts) {
    value = value?.[part];
    if (value === undefined) break;
  }
  return value ?? key;
}

function setLanguage(lang) {
  currentLang = lang;
  document.querySelectorAll('.lang-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.lang === lang);
  });
  applyTranslations();
  if (currentEvent) {
    renderEventCountdown(currentEvent);
    renderFormTypes(currentEvent.formTypes || []);
    renderEventMedia(currentEvent);
    renderRouteMap(currentEvent);
    renderEventAgenda(currentEvent);
  }
  if (loggedUser && document.getElementById('modalPendingSection').style.display !== 'none') {
    showPendingSection();
  }
}

// ════════════════════════════════════════════════════════════
//  MONEDA DE VISUALIZACIÓN (BOB/USD/BRL)
//  Los montos SIEMPRE se calculan y cobran en BOB (fuente de verdad en el
//  backend) — esto solo formatea cómo se muestran en pantalla.
// ════════════════════════════════════════════════════════════
let lastTicketArgs = null; // [referencia, method, totales] del último e-ticket armado, para poder reformatearlo si cambia la moneda

async function loadExchangeRates(){
  try {
    const data = await fetchJson(`${API_BASE}/tipo_cambio.php`);
    if (data.rates) exchangeRates = data.rates;
  } catch (e) {
    // Se queda con el default 1:1 — el selector de USD/BRL mostrará una
    // conversión desactualizada en vez de romper la pantalla.
  }
}

function formatMoney(amountBOB){
  const amt = Number(amountBOB) || 0;
  if (currentCurrency === 'BOB') return `Bs${amt.toFixed(2)}`;
  const rate   = exchangeRates[currentCurrency] || 1;
  const symbol = currentCurrency === 'USD' ? '$' : 'R$';
  return `${symbol}${(amt * rate).toFixed(2)}`;
}

function setCurrency(cur){
  currentCurrency = cur;
  document.querySelectorAll('#currencySwitcher .lang-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.currency === cur);
  });
  refreshVisibleMoneyDisplays();
}

// Re-renderiza los montos de lo que esté visible ahora mismo, igual patrón
// que setLanguage() ya usa para refrescar el idioma de lo visible.
function refreshVisibleMoneyDisplays(){
  if (currentEvent) renderFormTypes(currentEvent.formTypes || []);
  if (participants.length > 0) renderParticipantsList();
  if (document.getElementById('sub-review')?.classList.contains('active')) {
    buildSummary();
  }
  if (document.getElementById('screen-confirmation')?.classList.contains('active') && lastTicketArgs) {
    buildETicket(...lastTicketArgs);
  }
  if (loggedUser && document.getElementById('modalPendingSection').style.display !== 'none') {
    showPendingSection();
  }
}

function applyTranslations() {
  document.querySelectorAll('[data-i18n]').forEach(el => {
    const key = el.getAttribute('data-i18n');
    const value = t(key);
    if (typeof value === 'string') el.textContent = value;
  });
  document.querySelectorAll('[data-i18n-ph]').forEach(el => {
    const key = el.getAttribute('data-i18n-ph');
    const value = t(key);
    if (typeof value === 'string') el.placeholder = value;
  });
  document.querySelectorAll('[data-i18n-opt]').forEach(el => {
    const key = el.getAttribute('data-i18n-opt');
    const value = t(key);
    if (typeof value === 'string') el.textContent = value;
  });
  document.querySelectorAll('[data-i18n-aria]').forEach(el => {
    const key = el.getAttribute('data-i18n-aria');
    const value = t(key);
    if (typeof value === 'string') el.setAttribute('aria-label', value);
  });
  updateMonthNames();
  document.querySelectorAll('.lang-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.lang === currentLang);
  });
}

function updateMonthNames() {
  const months = t('registration.months');
  if (!Array.isArray(months)) return;
  document.querySelectorAll('#date_birth_month option[data-month-index]').forEach(opt => {
    const idx = parseInt(opt.getAttribute('data-month-index'), 10);
    if (!isNaN(idx) && months[idx]) opt.textContent = months[idx];
  });
}

function getStatusLabel(status) {
  const labels = {
    en: { open: '● Open', closed: '✕ Closed', coming_soon: '◑ Coming Soon' },
    es: { open: '● Abierto', closed: '✕ Cerrado', coming_soon: '◑ Próximamente' },
    pt: { open: '● Aberto', closed: '✕ Encerrado', coming_soon: '◑ Em breve' }
  };
  return labels[currentLang][status] || status;
}

function parseEventDate(dateValue) {
  if (!dateValue) return null;
  const normalized = String(dateValue).replace(' ', 'T');
  const parsed = new Date(normalized);
  return Number.isNaN(parsed.getTime()) ? null : parsed;
}

function renderEventCountdown(ev) {
  const countdownWrap = document.getElementById('ftEventCountdown');
  const card = document.getElementById('ftCountdownCard');
  const footer = document.getElementById('ftCountdownFooter');
  if (!countdownWrap || !card || !footer) return;

  // Clear any previous interval
  if (countdownInterval) {
    clearInterval(countdownInterval);
    countdownInterval = null;
  }

  const eventDate = parseEventDate(ev.date);
  if (!eventDate) {
    countdownWrap.style.display = 'none';
    card.style.display = 'none';
    footer.style.display = 'none';
    return;
  }

  // Helper to pad numbers
  const pad = (n) => String(n).padStart(2, '0');

  function update() {
    const now = new Date();
    let diff = Math.max(0, Math.floor((eventDate.getTime() - now.getTime()) / 1000));
    if (diff <= 0) {
      // event started
      document.getElementById('cdDays').textContent = '00';
      document.getElementById('cdHours').textContent = '00';
      document.getElementById('cdMinutes').textContent = '00';
      document.getElementById('cdSeconds').textContent = '00';
      footer.textContent = t('formTypes.countdownStarted');
      footer.style.display = 'block';
      return;
    }

    const days = Math.floor(diff / (24 * 3600));
    diff -= days * 24 * 3600;
    const hours = Math.floor(diff / 3600);
    diff -= hours * 3600;
    const minutes = Math.floor(diff / 60);
    const seconds = diff - minutes * 60;

    // Update DOM and animate digits that changed
    const newVals = { days: pad(days), hours: pad(hours), minutes: pad(minutes), seconds: pad(seconds) };
    Object.keys(newVals).forEach(key => {
      const id = key === 'days' ? 'cdDays' : key === 'hours' ? 'cdHours' : key === 'minutes' ? 'cdMinutes' : 'cdSeconds';
      const el = document.getElementById(id);
      if (!el) return;
      if (prevCountdownValues[key] !== newVals[key]) {
        // set text then animate
        el.textContent = newVals[key];
        animateDigit(id);
      } else {
        el.textContent = newVals[key];
      }
      prevCountdownValues[key] = newVals[key];
    });

    // Footer: human readable target
    const locale = currentLang === 'es' ? 'es-BO' : currentLang === 'pt' ? 'pt-BR' : 'en-US';
    footer.textContent = `${t('registration.countdownTimeLeft')} ${eventDate.toLocaleString(locale)}`;
    footer.style.display = 'block';
  }

  // Initial render and start interval
  card.style.display = 'flex';
  countdownWrap.style.display = 'block';
  update();
  countdownInterval = setInterval(update, 1000);
}

function animateDigit(id) {
  const el = document.getElementById(id);
  if (!el) return;
  if (!animationsEnabled) return;
  el.classList.remove('digit-flip');
  // trigger reflow to restart animation
  void el.offsetWidth;
  el.classList.add('digit-flip');
}

function applyEventBackground(ev) {
  const screen = document.getElementById('screen-form-types');
  if (!screen) return;
  const image = (ev.image || '').trim();
  if (image) {
    screen.style.background = `linear-gradient(135deg, rgba(2,40,88,.82) 0%, rgba(0,186,210,.50) 100%), url('${image}') center/cover no-repeat`;
  } else {
    screen.style.background = 'linear-gradient(135deg, var(--secondary) 0%, var(--primary-dk) 100%)';
  }
}

// ════════════════════════════════════════════════════════════
//  INIT
// ════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
  initPhoneInputs();
  applyTranslations();
  // Detect mobile and disable animations by default on small screens
  animationsEnabled = !window.matchMedia('(max-width:600px)').matches;
  updateAnimButton();
  loadAllEvents();   // Cargar lista de eventos al iniciar
  loadExchangeRates(); // Tipo de cambio para el selector BOB/USD/BRL del header

  // Link compartido (?evento=<id>): saltar directo a ese evento en vez de
  // dejar a la persona en el listado genérico.
  if (DEEP_LINK_EVENT_ID) {
    selectEvent(DEEP_LINK_EVENT_ID);
  }

  // Shirt toggle
  document.querySelectorAll('input[name="polera_opcion"]').forEach(r => {
    r.addEventListener('change', () => {
      const show = r.value === 'con' && r.checked;
      document.getElementById('shirtSizeContainer').style.display = show ? 'block' : 'none';
      if (!show) document.getElementById('tamanioPolera').value = '';
      syncRadioStyles();
    });
  });

  // Delivery: mapa de ubicación (12/08/2026, portado de elascenso/event)
  // — se instancia recién al tildar el checkbox, no al construir la
  // pantalla (Leaflet no inicializa bien sobre un contenedor todavía
  // oculto).
  document.getElementById('quiere_delivery').addEventListener('change', (e) => {
    document.getElementById('deliveryMapContainer').style.display = e.target.checked ? 'block' : 'none';
    if (e.target.checked) initDeliveryMap();
  });

  document.getElementById('participantForm').addEventListener('submit', saveParticipant);

  // Selector de moneda de cobro (20/08/2026, portado de elascenso/event)
  // — mismo patrón accesible que el resto de los radios de esta pantalla
  // (auditoría 10/08/2026 §1): 'change' en el input real, no onclick en
  // el label.
  document.querySelectorAll('#currencyOptionsContainer input[name="monedaPago"]').forEach(r => {
    r.addEventListener('change', () => selectCurrency(r.value));
  });

  // Agenda de talleres en popup (20/08/2026, portado de elascenso/event).
  document.getElementById('btnTalleresAgenda')?.addEventListener('click', openTalleresAgendaModal);

  // Título de congreso reusando `alias` (20/08/2026, portado de
  // elascenso/event).
  document.getElementById('aliasTitulo')?.addEventListener('change', onAliasTituloChange);
  document.getElementById('aliasTituloOtro')?.addEventListener('input', onAliasOtroInput);
});

function toggleAnimations() {
  animationsEnabled = !animationsEnabled;
  updateAnimButton();
}

function updateAnimButton() {
  const btn = document.getElementById('animToggle');
  if (!btn) return;
  btn.setAttribute('aria-pressed', String(animationsEnabled));
  btn.textContent = animationsEnabled ? 'Anim: On' : 'Anim: Off';
  btn.classList.toggle('active', animationsEnabled);
}

function initPhoneInputs(){
  const opts = {
    initialCountry: 'bo',
    preferredCountries: ['bo','us','ar','cl','pe'],
    separateDialCode: false,
    nationalMode: false,
    autoPlaceholder: 'polite',
    utilsScript: 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/utils.js'
  };
  itiTel = window.intlTelInput(document.getElementById('telefono'), opts);
  itiCel = window.intlTelInput(document.getElementById('celular'),  opts);
}

function syncRadioStyles(){
  document.querySelectorAll('.radio-option').forEach(lbl => {
    const inp = lbl.querySelector('input');
    lbl.classList.toggle('checked', inp && inp.checked);
  });
}

// ════════════════════════════════════════════════════════════
//  PANTALLAS
// ════════════════════════════════════════════════════════════
function showScreen(id){
  document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
  document.getElementById(id).classList.add('active');
  window.scrollTo({ top: 0, behavior: 'smooth' });
  // Ajustar fondo del wrapper según pantalla
  const wrapper = document.querySelector('.main-wrapper');
  const isP0 = (id === 'screen-event-list' || id === 'screen-form-types');
  wrapper.style.maxWidth = isP0 ? '1100px' : '820px';
}

function showSub(id){
  document.querySelectorAll('.sub-screen').forEach(s => s.classList.remove('active'));
  document.getElementById(id).classList.add('active');
}

// ════════════════════════════════════════════════════════════
//  STEPPER
// ════════════════════════════════════════════════════════════
function setStep(n){
  for (let i = 1; i <= 4; i++){
    const el   = document.getElementById('step-' + i);
    const line = document.getElementById('line-' + i);
    if (!el) continue;
    el.classList.remove('active', 'done');
    if (i < n)       el.classList.add('done');
    else if (i === n) el.classList.add('active');
    if (line) line.classList.toggle('done', i < n);
    // Solo un paso ya completado es una parada real a la que se puede volver
    const isDone = i < n;
    el.setAttribute('tabindex', isDone ? '0' : '-1');
    el.setAttribute('role', isDone ? 'button' : 'presentation');
    el.setAttribute('aria-disabled', isDone ? 'false' : 'true');
  }
}

// Click/Enter en un bubble del stepper: solo navega hacia atrás, a un paso
// ya completado — nunca permite saltar adelante sin pasar por la validación
// normal de cada pantalla.
function stepperNavigate(n){
  const el = document.getElementById('step-' + n);
  if (!el || !el.classList.contains('done')) return;
  if (n === 1) goBackToForm();
  // n === 2/3: reservado para cuando el paso de pago también muestre el
  // stepper (hoy screen-confirmation no lo incluye, ver plan de migración).
}

// ════════════════════════════════════════════════════════════
//  PANTALLA 0 — LISTADO DE EVENTOS
// ════════════════════════════════════════════════════════════

// Labels de estado
const STATUS_LABELS = {
  open:        '● Open',
  closed:      '✕ Closed',
  coming_soon: '◑ Coming Soon'
};

// Normaliza un evento recibido de la API externa
function normalizeEvento(ev){
  return { ...ev, id: String(ev.id), status: ev.status || 'coming_soon' };
}

async function parseJsonResponse(res, source){
  const text = await res.text();
  const contentType = (res.headers.get('content-type') || '').toLowerCase();
  const preview = text.trim().slice(0, 240).replace(/\s+/g, ' ');

  if (!contentType.includes('application/json')) {
    throw new Error(`Expected JSON from ${source} but received ${contentType || 'non-JSON'}: ${preview}`);
  }

  try {
    return JSON.parse(text);
  } catch (err) {
    throw new Error(`Invalid JSON from ${source}: ${preview}`);
  }
}

async function fetchJson(url, options = {}){
  // Sin 'Accept: application/json' Laravel no detecta la petición como AJAX/JSON
  // (expectsJson() = false) y ante un error de validación responde con un 302
  // redirect en vez de un 422 JSON — el navegador bloquea esa redirección
  // cross-origin y lo reporta como un falso error de CORS.
  options = {
    ...options,
    headers: { 'Accept': 'application/json', ...(options.headers || {}) }
  };
  const res = await fetch(url, options);
  const data = await parseJsonResponse(res, url);
  if (!res.ok || (data && data.success === false)) {
    const err = new Error(data.error || data.message || `HTTP ${res.status}`);
    err.errors = data.errors || null; // errores de validación por campo (Laravel), p.ej. { email: [...] }
    err.status = res.status;
    throw err;
  }
  return data;
}

// Igual que fetchJson pero reintenta ante una falla transitoria (red caída o
// 5xx del servidor externo — típicamente la API externa sin conexión
// momentánea a su base de datos). Un 4xx (validación, email duplicado, token
// inválido) es un error real y no se reintenta. Cantidad y espera
// configurables via EXTERNAL_API_RETRIES / EXTERNAL_API_RETRY_MS (.env).
async function fetchJsonWithRetry(url, options = {}, retries = EXTERNAL_API_RETRIES, delayMs = EXTERNAL_API_RETRY_MS){
  try {
    return await fetchJson(url, options);
  } catch (err) {
    const isNetworkError = err instanceof TypeError;
    const isServerError  = typeof err.status === 'number' && err.status >= 500;
    if (retries <= 0 || (!isNetworkError && !isServerError)) throw err;
    await new Promise(r => setTimeout(r, delayMs));
    return fetchJsonWithRetry(url, options, retries - 1, delayMs);
  }
}

// loadAllEvents/onEventFilterTextInput/toggleAdvancedFilters/clearEventFilters/
// applyEventFilters/fetchEventsPage/renderEventCards/renderPagination ahora
// viven en public/js/modules/event-list.js (Fase 2, paso 1) — ver nota arriba.

// Seleccionar evento → ir a tipos de formulario
async function selectEvent(eventId){
  // Hallazgo 12/08 (portado de elascenso/event): si el usuario había
  // agregado participantes a OTRO evento (sin llegar a pagar/confirmar)
  // y navega acá, `participants[]` seguía viva en memoria — quedaban
  // mezclados con el evento nuevo (categoría/souvenirs de un form_type
  // que ya no aplica). Se descartan explícitamente al cambiar de
  // evento, con confirmación.
  if (currentEvent && currentEvent.id !== eventId && participants.length > 0) {
    if (!confirm(t('registration.confirmSwitchEvent'))) return;
    discardStaleParticipants();
  }

  const loadingEl = document.getElementById('eventsLoading');
  const grid      = document.getElementById('eventsGrid');

  // Marcar tarjeta seleccionada visualmente
  document.querySelectorAll('.event-card').forEach(c =>
    c.classList.toggle('selected', c.dataset.id === eventId)
  );

  // Si el evento ya está cargado en memoria, usarlo
  let ev = null;
  if (currentEvent && currentEvent.id === eventId) {
    ev = currentEvent;
  } else {
    // Cargar detalles completos (con promoCodes y souvenirs)
    grid.style.opacity = '.5';
    try {
      const data = await fetchJson(`${API_BASE}/eventos.php?id=${encodeURIComponent(eventId)}`);
      ev = normalizeEvento(data.eventos);
    } catch(err) {
      alert('⚠ ' + err.message);
      document.querySelectorAll('.event-card').forEach(c => c.classList.remove('selected'));
      grid.style.opacity = '1';
      return;
    } finally {
      grid.style.opacity = '1';
    }
  }

  currentEvent     = ev;
  selectedFormType = null;
  applyEventBackground(ev);
  renderPreviewBanner(ev);

  // Poblar cabecera de tipos de formulario
  document.getElementById('ftEventName').textContent =
    ev.name;
  document.getElementById('ftEventMeta').textContent =
    `${ev.date}  ·  ${ev.localTime || ''}  ·  ${ev.location}`;
  document.getElementById('ftEventDescText').textContent =
    ev.longDescription || ev.description || '';
  renderEventCountdown(ev);

  // Video o imagen de la carrera
  renderEventMedia(ev);

  // Auspiciadores (carrusel de logos, antes del mapa de ruta)
  renderSponsorsCarousel(ev);

  // Kit/tallas/stock (11/08/2026, portado 12/08/2026) — fotos de los ítems del kit incluido.
  renderKitGallery(ev);

  // Mapa de la ruta
  renderRouteMap(ev);

  // Agenda del evento (sesiones/ponentes/salas o cronograma del día)
  renderEventAgenda(ev);

  // Agregar al calendario (.ics + Google Calendar, 20/08/2026, portado de
  // elascenso/event) — a diferencia de la agenda, se muestra siempre,
  // tenga o no agenda_items cargada.
  renderAddToCalendar(ev);

  // Renderizar tipos de formulario
  renderFormTypes(ev.formTypes || []);

  // Orden configurable de secciones (25/08/2026, portado de elascenso/event) —
  // después de todos los render* de arriba, para que la visibilidad de cada
  // bloque ya esté decidida antes de reordenarlos.
  applySeccionesOrder(ev);

  showScreen('screen-form-types');
}

// Vista previa de borrador (25/08/2026, portado de elascenso/event) — muestra
// el aviso de solo-lectura en ambos banners (selección de tipo de formulario +
// flujo de inscripción) cuando el evento no está publicado todavía.
// updateConfirmButtonState() y confirmPayment() son quienes efectivamente
// bloquean el submit; esta función solo se encarga del aviso visual.
function renderPreviewBanner(ev){
  const isPreview = ev?.publicado === false;
  document.querySelectorAll('#previewBannerFormTypes, #previewBannerRegistration').forEach(el => {
    el.style.display = isPreview ? 'block' : 'none';
  });
}

// Claves fijas de los 9 bloques reordenables de #screen-form-types y su id de
// contenedor (25/08/2026, portado de elascenso/event). `form-types-header`
// (cabecera nombre/fecha/ubicación) y el banner de preview quedan siempre
// primero, no participan de este orden.
const SECTION_BLOCK_IDS = {
  description: 'ftEventDescription',
  calendar:    'ftCalendarContainer',
  countdown:   'ftEventCountdown',
  media:       'ftMediaContainer',
  sponsors:    'ftSponsorsContainer',
  kitGallery:  'ftKitGalleryContainer',
  routeMap:    'ftRouteMapContainer',
  agenda:      'ftAgendaContainer',
  formTypes:   'ftFormTypesSection',
};
const DEFAULT_SECTIONS_ORDER = Object.keys(SECTION_BLOCK_IDS);

// Aplica el orden configurado por el organizador (ev.seccionesOrden, ver
// admin-eventos/Admin\EventoController → pestaña Datos) a los bloques de
// #screen-form-types. No toca qué bloques están visibles/ocultos (eso ya lo
// decidió cada render* de arriba según si el evento tiene datos), solo su
// posición relativa. Sin config (caso de todo evento existente hoy) usa
// DEFAULT_SECTIONS_ORDER — mismo orden que siempre tuvo el HTML, cero cambio
// visual.
function applySeccionesOrder(ev){
  const configurado = Array.isArray(ev?.seccionesOrden) && ev.seccionesOrden.length
    ? ev.seccionesOrden.filter(k => SECTION_BLOCK_IDS[k])
    : [];
  const orden = [...configurado];
  DEFAULT_SECTIONS_ORDER.forEach(k => { if (!orden.includes(k)) orden.push(k); });

  const screen = document.getElementById('screen-form-types');
  orden.forEach(key => {
    const el = document.getElementById(SECTION_BLOCK_IDS[key]);
    if (el) screen.appendChild(el);
  });
}

// (funciones movidas a public/js/modules/registration.js y a
// public/js/modules/account.js — pasos 2 y 5)

// (funciones movidas a public/js/modules/results-club.js)

// (funciones movidas a public/js/modules/registration.js y a
// public/js/modules/account.js — pasos 2 y 5)

// ════════════════════════════════════════════════════════════
//  REVIEW
// ════════════════════════════════════════════════════════════
function goToReview(){
  if (participants.length === 0){ alert('Add at least one participant.'); return; }
  buildSummary();
  showSub('sub-review');
  setStep(2);
  document.getElementById('screen-registration').scrollIntoView({ behavior: 'smooth' });
}

function goBackToForm(){
  showSub('sub-form');
  setStep(1);
}

// (funciones movidas a public/js/modules/review-payment.js)

// (funciones movidas a public/js/modules/confirmation.js)

// ════════════════════════════════════════════════════════════
//  MODALES — foco/teclado compartido entre accountModal y agendaModal
//  (auditoría de accesibilidad 10/08/2026, ver
//  elascenso-blade/brain/AUDIT-FRONTEND-UI-ENGINEERING-10082026.md §2).
//  Antes cada modal solo alternaba style.display: el foco no se movía al
//  abrir/cerrar, no había Escape, y Tab podía salirse del modal hacia
//  contenido tapado detrás. openModal()/closeModal() son el único punto
//  de apertura/cierre — openAccountModal/openAgendaModal delegan acá.
// ════════════════════════════════════════════════════════════
let lastModalTrigger = null;

function getFocusableIn(container){
  return Array.from(container.querySelectorAll(
    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
  )).filter(el => el.offsetParent !== null); // visibles únicamente
}

function trapModalFocus(e, modalEl){
  if (e.key !== 'Tab') return;
  const focusables = getFocusableIn(modalEl);
  if (!focusables.length) return;
  const first = focusables[0];
  const last  = focusables[focusables.length - 1];
  if (e.shiftKey && document.activeElement === first) {
    e.preventDefault(); last.focus();
  } else if (!e.shiftKey && document.activeElement === last) {
    e.preventDefault(); first.focus();
  }
}

function openModal(modalEl){
  lastModalTrigger = document.activeElement;
  modalEl.style.display = 'flex';
  const focusables = getFocusableIn(modalEl);
  (focusables[0] || modalEl).focus();
  modalEl._a11yKeyHandler = (e) => {
    if (e.key === 'Escape') { e.preventDefault(); closeModal(modalEl); }
    else { trapModalFocus(e, modalEl); }
  };
  document.addEventListener('keydown', modalEl._a11yKeyHandler);
}

function closeModal(modalEl){
  modalEl.style.display = 'none';
  if (modalEl._a11yKeyHandler) {
    document.removeEventListener('keydown', modalEl._a11yKeyHandler);
    modalEl._a11yKeyHandler = null;
  }
  // Vuelve el foco a lo que lo abrió (botón del header, etc.) — si ya no
  // existe en el DOM (ej. re-render de la pantalla), no rompe nada.
  if (lastModalTrigger && document.body.contains(lastModalTrigger)) {
    lastModalTrigger.focus();
  }
  lastModalTrigger = null;
}

// Tarjetas de selección (categoría/souvenir/tipo de formulario) se
// generan como <div> por JS — sin esto solo eran clickeables con mouse
// (auditoría 10/08/2026 §1). Mismo patrón que ya usaba el stepper
// (role="button" + tabindex + Enter/Espacio), acá centralizado para no
// repetirlo en cada `renderX()`.
function enableCardKeyboard(el, onActivate){
  el.setAttribute('role', 'button');
  el.setAttribute('tabindex', '0');
  el.addEventListener('click', onActivate);
  el.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      onActivate();
    }
  });
}

// ════════════════════════════════════════════════════════════
//  HELPERS
// ════════════════════════════════════════════════════════════
function escHtml(str){
  if (!str) return '';
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showHelp(){
  alert('Need Help?\nEmail: support@lacharitymarathon.org\nPhone: +1 (800) 555-0100');
}
</script>
</body>
</html>
