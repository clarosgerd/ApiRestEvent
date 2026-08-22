<?php

use App\Http\Controllers\ClubController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\Inscripcion\HomeController;
use App\Http\Controllers\Inscripcion\ListaEsperaProxyController;
use App\Http\Controllers\Inscripcion\PagoProxyController;
use App\Http\Controllers\Inscripcion\RegistroProxyController;
use App\Http\Controllers\Inscripcion\TipoCambioController;
use App\Http\Controllers\Inscripcion\Webhooks\MultipagoCallbackController;
use App\Http\Controllers\Inscripcion\Webhooks\SipCallbackController;
use App\Http\Controllers\PersonaController;
use App\Http\Controllers\PromoCodeController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\ResultadoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Consolidación monolito (22/08/2026), Fase 2 — ex `elascenso-blade/routes/
 * web.php`. Ver ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 *
 * A diferencia de `admin.php` (Fase 1), la mayoría de estas rutas apuntan
 * DIRECTO al controller de la API que ya expone `/api/v1/*` — sin wrapper
 * ni traducción — porque son JSON puro (SPA vía fetch()), no formularios
 * Blade con redirect. Solo 3 casos necesitan algo propio en
 * `App\Http\Controllers\Inscripcion`: el shell de la vista (`HomeController`),
 * tipo de cambio (no proxea ApiRestEvent, es utilidad propia) y lista de
 * espera (adapta evento_id-en-el-body a event-en-la-URL).
 *
 * Fase 2b — registro/actualización de inscripciones, estado de pago
 * (SIP/Multipago/QR nuevo) y los webhooks de los gateways. A diferencia del
 * resto de Fase 2, `RegistroProxyController`/`PagoProxyController` SÍ tienen
 * lógica propia (`RegistroValidacionService`, mantenida a propósito como
 * capa de prevalidación/UX — confirmado con el usuario) que se solapa con
 * `CrearInscripcionAction`/`RegistrationService` del lado API; el `forward()`
 * final de cada acción de escritura se reemplazó por la Action/Service
 * real in-process. Mismo nivel de cuidado que ya se aplicó con Caja en la
 * Fase 1d (dinero real de por medio).
 */
Route::get('/', [HomeController::class, 'index']);

// ── Eventos ──
Route::get('/eventos', [EventoController::class, 'index']);
Route::get('/eventos/{event}', [EventoController::class, 'show']);
Route::get('/eventos/{event}/agenda.pdf', [EventoController::class, 'agendaPdf']);
Route::get('/eventos/{event}/agenda.ics', [EventoController::class, 'agendaIcs']);
Route::get('/eventos/{event}/resultados', [ResultadoController::class, 'porEvento'])->middleware('auth:sanctum');
Route::post('/eventos/lista-espera', [ListaEsperaProxyController::class, 'store']);

// ── Persona ──
Route::post('/persona/login', [PersonaController::class, 'login']);
Route::post('/persona/registro', [PersonaController::class, 'register']);
Route::post('/persona/logout', [PersonaController::class, 'logout']);

// ── Club ──
Route::post('/club/login', [ClubController::class, 'login']);
Route::post('/club/logout', [ClubController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/club/landing', [ClubController::class, 'landing'])->middleware('auth:sanctum');

Route::get('/promo/{id}/{promocode}', [PromoCodeController::class, 'promoCode']);
Route::get('/tipo-cambio', [TipoCambioController::class, 'show']);

// ── Inscripciones ──
Route::post('/registro', [RegistroProxyController::class, 'store']);
// {reference} en inglés a propósito — UpdateRegistrationRequest/
// UpdatePaidRegistrationRequest leen $this->route('reference') (mismo
// motivo documentado en Fase 1: el nombre del route param tiene que
// calzar literal con lo que el FormRequest de la API espera).
Route::put('/registro/{reference}', [RegistroProxyController::class, 'update']);
Route::patch('/registro/{reference}/pagada', [RegistroProxyController::class, 'marcarPagada']);
Route::post('/registro/lookup', [RegistrationController::class, 'lookup']);
Route::get('/registros/mias', [RegistrationController::class, 'mine'])->middleware('auth:sanctum');
Route::get('/resultados/mios', [ResultadoController::class, 'mios'])->middleware('auth:sanctum');

Route::get('/pago/{referencia}/estado', [PagoProxyController::class, 'estado']);

// ── Webhooks — el gateway de pago los llama directo desde afuera, no son
// proxies del JS del frontend. Cada uno valida su propia autenticación (ver
// controller); quedan afuera de CSRF (`webhooks/*` en bootstrap/app.php).
Route::post('/webhooks/sip/callback', SipCallbackController::class);
Route::post('/webhooks/multipago/callback', MultipagoCallbackController::class);

// ── Fase 1 (de elascenso-blade) — alias temporales con los nombres viejos
// de api/*.php. `resources/views/inscripcion/home.blade.php` es un copy
// exacto del body de `elascenso/event/index.php`, su fetch() sigue llamando
// a `${API_BASE}/eventos.php`, etc. ($apiBase = 'api' en HomeController).
// Mismo criterio que el archivo original: no se borran todos de una, se van
// sacando a medida que la pantalla ya no los necesite.
Route::get('/api/eventos.php', function (Request $request) {
    $id = $request->query('id');

    return $id ? app(EventoController::class)->show(\App\Models\Evento::findOrFail($id)) : app(EventoController::class)->index($request);
});
Route::get('/api/agenda_pdf.php', fn (Request $request) => app(EventoController::class)->agendaPdf(\App\Models\Evento::findOrFail($request->query('id', ''))));
Route::get('/api/agenda_ics.php', fn (Request $request) => app(EventoController::class)->agendaIcs(\App\Models\Evento::findOrFail($request->query('id', ''))));
Route::post('/api/lista_espera.php', [ListaEsperaProxyController::class, 'store']);
Route::post('/api/persona_login.php', [PersonaController::class, 'login']);
Route::post('/api/persona_register.php', [PersonaController::class, 'register']);
Route::post('/api/persona_logout.php', [PersonaController::class, 'logout']);
Route::post('/api/club_login.php', [ClubController::class, 'login']);
Route::post('/api/club_logout.php', [ClubController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/api/club_landing.php', [ClubController::class, 'landing'])->middleware('auth:sanctum');
Route::get('/api/promo.php', fn (Request $request) => app(PromoCodeController::class)->promoCode($request, (string) $request->query('event_id', ''), (string) $request->query('code', '')));
Route::get('/api/tipo_cambio.php', [TipoCambioController::class, 'show']);
Route::post('/api/registro.php', [RegistroProxyController::class, 'store']);
Route::post('/api/registro_actualizar.php', fn (Request $request, RegistroProxyController $controller) => $controller->update($request, (string) $request->input('referencia', '')));
Route::post('/api/registro_actualizar_pagada.php', fn (Request $request, RegistroProxyController $controller) => $controller->marcarPagada($request, (string) $request->input('referencia', '')));
Route::post('/api/registro_lookup.php', [RegistrationController::class, 'lookup']);
Route::get('/api/registros_mine.php', [RegistrationController::class, 'mine'])->middleware('auth:sanctum');
Route::get('/api/resultados_mios.php', [ResultadoController::class, 'mios'])->middleware('auth:sanctum');
Route::get('/api/resultados_evento.php', fn (Request $request) => app(ResultadoController::class)->porEvento($request, \App\Models\Evento::findOrFail($request->query('evento_id', ''))))->middleware('auth:sanctum');
Route::get('/api/pago_status.php', fn (Request $request, PagoProxyController $controller) => $controller->estado((string) $request->query('referencia', '')));
