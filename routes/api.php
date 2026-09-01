<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PersonaController;

use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\PagoAdicionalController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\PromoCodeController;
use App\Http\Controllers\ParticipanteController;
use App\Http\Controllers\ResultadoController;
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminAuditLogController;
use App\Http\Controllers\TipoEventoController;
use App\Http\Controllers\SocioController;
use App\Http\Controllers\LiquidacionController;
use App\Http\Controllers\PresupuestoEventoController;
use App\Http\Controllers\PresupuestoCategoriaController;
use App\Http\Controllers\SesionCongresoController;
use App\Http\Controllers\SesionCongresoStaffController;
use App\Http\Controllers\AsistenciaSesionController;
use App\Http\Controllers\TallerCongresoController;
use App\Http\Controllers\ItemBodegaController;
use App\Http\Controllers\ItemStockController;
use App\Http\Controllers\CategoryPricePeriodController;
use App\Http\Controllers\ListaEsperaController;
use App\Http\Controllers\OrganizadorController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\CajaTurnoController;
use App\Http\Controllers\PaisController;
use App\Http\Controllers\CiudadController;
use App\Http\Controllers\SexoController;
use App\Http\Controllers\SubtipoEventoController;
use App\Http\Controllers\RelacionContactoController;
use App\Http\Controllers\FormasPagoController;
use App\Http\Controllers\FormularioCamposController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



Route::group(['prefix' => 'v1','namespace' => 'App\Http\Controllers'], function () {
    // Lectura pública — la SPA de inscripción (elascenso/event) depende de
    // que estas rutas sigan sin auth. No mover al grupo protegido de abajo.
    // /event/consumo debe ir ANTES del apiResource de abajo — si no,
    // Laravel matchea "consumo" como si fuera el {event} de la ruta show
    // (mismo criterio que /registrations/by-pay-order más abajo). Ver
    // brain/PLAN-ENDPOINT-CONSUMO-05082026.md.
    Route::get('/event/consumo', [EventoController::class, 'consumo']);
    Route::get('/tipos-evento', [TipoEventoController::class, 'index']);
    Route::apiResource('/event',EventoController::class)->only(['index', 'show']);
    Route::get('/event/{event}/agenda-pdf', [EventoController::class, 'agendaPdf']);
    Route::get('/event/{event}/agenda-ics', [EventoController::class, 'agendaIcs']);
    Route::get('/event/{event}/gafetes-pdf', [EventoController::class, 'gafetesPdf']);
    Route::get('/event/{event}/certificados-pdf', [EventoController::class, 'certificadosPdf']);
    Route::apiResource('/coordinate',CoordinateController::class)->only(['index', 'show']);
    Route::apiResource('/route',RouteController::class)->only(['index', 'show']);
    Route::apiResource('/category',CategoryController::class)->only(['index', 'show']);
    Route::apiResource('/form-type',FormTypeController::class)->only(['index', 'show']);
    Route::apiResource('/promo-code',PromoCodeController::class)->only(['index', 'show']);
    Route::apiResource('/souvenir',SouvenirController::class)->only(['index', 'show']);
    Route::apiResource('/auspiciador',AuspiciadorController::class)->only(['index', 'show']);
    Route::apiResource('/agenda-item',AgendaItemController::class)->only(['index', 'show']);

    // Kit/tallas/stock (11/08/2026) — ver
    // PRD-kit-tallas-stock-lista-espera.md. Anotarse a la lista de
    // espera es público (sin auth), mismo criterio que
    // /registrations/lookup — quien se anota no necesariamente tiene
    // cuenta todavía.
    Route::post('/event/{event}/lista-espera', [ListaEsperaController::class, 'store']);

    // persona/me tiene que registrarse ANTES que el CRUD admin de
    // /persona (más abajo, dentro del grupo auth:admins) — Laravel matchea
    // rutas en orden de registro, y GET /persona/{persona} (el show del
    // admin) es ambiguo con GET /persona/me (aceptaría "me" como si fuera
    // un id). Si el admin CRUD se registrara primero, /persona/me dejaría
    // de funcionar para cualquier Persona pública consultando su propia
    // cuenta.
    Route::get('persona/me', [PersonaController::class, 'me'])->middleware('auth:sanctum');

    // SIP multi-banco (28/08/2026) — server-to-server ÚNICAMENTE, nunca
    // auth:admins/sanctum (esto no es para humanos). Solo el backend PHP
    // de elascenso/event lo llama, con el secreto compartido
    // INTERNAL_API_SECRET (ver RequiresInternalSecret). Ver
    // brain/api_rest_event/PLAN-SIP-MULTIBANCO-28082026.md.
    Route::middleware('internal.secret')->prefix('internal')->group(function () {
        Route::get('/event/{event}/sip-banco', [\App\Http\Controllers\Internal\SipBancoInternalController::class, 'paraEvento']);
        Route::get('/sip-bancos/callback-credenciales', [\App\Http\Controllers\Internal\SipBancoInternalController::class, 'callbackCredenciales']);
    });

    // Escritura — panel de administración de eventos (ver
    // brain/PLAN-PANEL-ADMIN-EVENTOS-02082026.md), protegido con guard
    // `admins` (super_admin ve todo, admin scoped a un evento — el scoping
    // real se valida dentro de cada controlador vía AuthorizesEventoScope,
    // el middleware acá solo exige "es un AdminUser válido").
    Route::middleware('auth:admins')->group(function () {
        Route::apiResource('/event', EventoController::class)->only(['store', 'update', 'destroy']);
        Route::patch('/event/{event}/publicar', [EventoController::class, 'publicar']);
        Route::patch('/event/{event}/despublicar', [EventoController::class, 'despublicar']);
        Route::apiResource('/coordinate', CoordinateController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('/route', RouteController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('/category', CategoryController::class)->only(['store', 'update', 'destroy']);

        // Precios por período (12/08/2026) — ver
        // PRD-precios-periodos-fechas.md. Mismo scoping que category
        // (admin de su propio evento, o super_admin). Sin index propio:
        // la lista ya viaja embebida en CategoryResource.periodos.
        Route::post('/category/{category}/periodos', [CategoryPricePeriodController::class, 'store']);
        Route::put('/category-price-period/{categoryPricePeriod}', [CategoryPricePeriodController::class, 'update']);
        Route::delete('/category-price-period/{categoryPricePeriod}', [CategoryPricePeriodController::class, 'destroy']);
        Route::apiResource('/form-type', FormTypeController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('/promo-code', PromoCodeController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('/souvenir', SouvenirController::class)->only(['store', 'update', 'destroy']);

        // Preguntas adicionales del formulario de inscripción (20/08/2026) —
        // ver FormularioCamposController. Mismo scoping que souvenir/
        // category-price-period (admin de su propio evento, o
        // super_admin). Sin index() propio: la lista ya viaja embebida en
        // FormTypeResource.preguntas (GET /event/{id}).
        Route::post('/form-type/{formType}/preguntas', [FormularioCamposController::class, 'store']);
        Route::put('/pregunta/{pregunta}', [FormularioCamposController::class, 'update']);
        Route::delete('/pregunta/{pregunta}', [FormularioCamposController::class, 'destroy']);

        // Kit/tallas/stock (11/08/2026) — ver
        // PRD-kit-tallas-stock-lista-espera.md. Mismo scoping que
        // souvenir (admin de su propio evento, o super_admin).
        Route::get('/souvenir/{souvenir}/stock', [ItemStockController::class, 'index']);
        Route::post('/souvenir/{souvenir}/stock', [ItemStockController::class, 'store']);
        Route::put('/item-stock/{itemStock}', [ItemStockController::class, 'update']);
        Route::delete('/item-stock/{itemStock}', [ItemStockController::class, 'destroy']);
        Route::get('/event/{event}/lista-espera', [ListaEsperaController::class, 'index']);

        // Bodega de stock por evento (14/08/2026) — ver
        // PLAN-BODEGA-STOCK-EVENTO-14082026.md. Mismo scoping que
        // souvenir/item-stock.
        Route::get('/event/{event}/item-bodega', [ItemBodegaController::class, 'index']);
        Route::post('/event/{event}/item-bodega', [ItemBodegaController::class, 'store']);
        Route::put('/item-bodega/{itemBodega}', [ItemBodegaController::class, 'update']);
        Route::delete('/item-bodega/{itemBodega}', [ItemBodegaController::class, 'destroy']);
        Route::post('/item-bodega/{itemBodega}/asignar', [ItemBodegaController::class, 'asignar']);

        // Mapa de ubicación de delivery (12/08/2026) — vista de solo
        // lectura para el organizador, mismo scoping que lista-espera.
        Route::get('/event/{event}/delivery', [DeliveryController::class, 'indexForAdmin']);

        Route::apiResource('/auspiciador', AuspiciadorController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('/agenda-item', AgendaItemController::class)->only(['store', 'update', 'destroy']);

        // Numeración de corredor/chip (panel de administración) — ver
        // brain/PLAN-RESULTADOS-EQUIPOS-31072026.md §1 y
        // PLAN-NUMERACION-PANEL-05082026.md. Antes sin auth (deuda
        // documentada en project_features_resultados_equipos_delivery);
        // ahora scoped igual que el resto del panel.
        Route::get('/event/{event}/participantes', [ParticipanteController::class, 'porEvento']);
        Route::patch('/registrations/{reference}/participantes/{participante}/numeracion', [RegistrationController::class, 'updateNumeracion']);
        // Conciliación manual de "Pago pendiente (USD)" (24/08/2026) — ver
        // RegistrationController::confirmarPagoManual().
        Route::patch('/registrations/{reference}/confirmar-pago-manual', [RegistrationController::class, 'confirmarPagoManual']);
        Route::post('/event/{event}/participantes/numeracion/bulk', [ParticipanteController::class, 'numeracionBulk']);

        // Edición restringida de datos de contacto/identidad de un
        // participante (panel de administración) — ver
        // ParticipanteController::update() / UpdateParticipanteRequest
        // para el whitelist real de campos permitidos.
        Route::patch('/participantes/{participante}', [ParticipanteController::class, 'update']);

        // Acreditación (check-in) escaneando el QR de referencia — panel
        // de administración, mismo scoping que Numeración. El QR ya
        // existía (ReferenceQrService, solo codifica la referencia) pero
        // no tenía ningún consumidor; este es el primero. A propósito NO
        // se reusa GET /registrations/{reference} (más abajo, público,
        // sin scoping por evento) — el lookup de acreditación necesita
        // confirmar que la referencia es de ESE evento antes de mostrar
        // nada.
        Route::get('/event/{event}/checkin/{reference}', [RegistrationController::class, 'checkinLookup']);
        Route::patch('/participantes/{participante}/checkin', [ParticipanteController::class, 'checkin']);

        // Dashboard de inscripciones (mismo conteo que ya se manda por
        // correo vía link firmado, ver OrganizadorDashboardController),
        // ahora también disponible autenticado dentro del panel. Incluye
        // `balance` (ver BalanceEventoData) desde la sesión 11/08/2026.
        Route::get('/event/{event}/dashboard-inscripciones', [EventoController::class, 'dashboardInscripciones']);

        // Presupuesto de un evento (control financiero del organizador) —
        // ver PRD-presupuesto_de_un_evento.md y elascenso/event/brain/
        // (sesión 11/08/2026). A diferencia de Socios/Liquidación (solo
        // super_admin), esto sí lo puede operar el admin scoped a su
        // propio evento — mismo criterio que Numeración/Participantes.
        Route::get('/event/{event}/presupuesto', [PresupuestoEventoController::class, 'index']);
        Route::post('/event/{event}/presupuesto', [PresupuestoEventoController::class, 'store']);
        Route::put('/event/{event}/presupuesto/{presupuesto}', [PresupuestoEventoController::class, 'update']);
        Route::delete('/event/{event}/presupuesto/{presupuesto}', [PresupuestoEventoController::class, 'destroy']);

        // Agenda y sesiones de congreso — ver
        // PRD-Agenda-sessiones-onlycongresos.md y elascenso/event/brain/
        // (sesión 11/08/2026). Mismo criterio de permisos que Presupuesto
        // (admin scoped a su evento, o super_admin) — la API no bloquea
        // por tipo de evento, el gating "solo congresos" es del panel.
        Route::get('/event/{event}/sesiones', [SesionCongresoController::class, 'index']);
        Route::post('/event/{event}/sesiones', [SesionCongresoController::class, 'store']);
        Route::put('/event/{event}/sesiones/{sesion}', [SesionCongresoController::class, 'update']);
        Route::delete('/event/{event}/sesiones/{sesion}', [SesionCongresoController::class, 'destroy']);

        // Congresos con talleres (18/08/2026) — ver
        // brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md. CRUD
        // de talleres (agrupación de sesiones con modalidad REQUIRED/
        // OPTIONAL y precio). Mismo scoping que sesiones.
        Route::get('/event/{event}/talleres', [TallerCongresoController::class, 'index']);
        Route::post('/event/{event}/talleres', [TallerCongresoController::class, 'store']);
        Route::get('/event/{event}/talleres/{taller}', [TallerCongresoController::class, 'show']);
        Route::put('/event/{event}/talleres/{taller}', [TallerCongresoController::class, 'update']);
        Route::delete('/event/{event}/talleres/{taller}', [TallerCongresoController::class, 'destroy']);
        Route::get('/event/{event}/sesiones/{sesion}/lookup/{reference}', [AsistenciaSesionController::class, 'lookup']);
        Route::patch('/event/{event}/sesiones/{sesion}/participantes/{participante}/checkin', [AsistenciaSesionController::class, 'checkin']);
        Route::post('/event/{event}/sesiones/{sesion}/checkin-bulk', [AsistenciaSesionController::class, 'checkinBulk']);
        Route::get('/event/{event}/sesiones/{sesion}/asistencia', [AsistenciaSesionController::class, 'asistencia']);
        Route::get('/event/{event}/sesiones-reporte', [AsistenciaSesionController::class, 'reporte']);

        // Asignación de staff/ayudantes a sesiones — ver
        // brain/PLAN-ASIGNACION-STAFF-SESIONES-CONGRESO-13082026.md.
        Route::get('/event/{event}/staff-disponible', [SesionCongresoStaffController::class, 'disponibles']);
        Route::get('/event/{event}/sesiones/{sesion}/staff', [SesionCongresoStaffController::class, 'index']);
        Route::post('/event/{event}/sesiones/{sesion}/staff', [SesionCongresoStaffController::class, 'store']);
        Route::delete('/event/{event}/sesiones/{sesion}/staff/{participante}', [SesionCongresoStaffController::class, 'destroy']);

        // Botón "Sincronizar ahora" del panel — ver
        // brain/groovy-chasing-ladybug.md Parte B.
        Route::post('/event/{event}/chronotrack/sincronizar', [ResultadoController::class, 'sincronizarChronoTrack']);

        // Carga masiva de inscripciones por CSV (panel de administración,
        // solo super_admin) — ver brain/PLAN-REGISTRO-MANUAL-CSV-05082026.md.
        Route::post('/event/{event}/registro-manual/bulk', [RegistrationController::class, 'importarBulk']);

        // Caja de cobro presencial (14/08/2026) — ver
        // PLAN-CAJA-COBRO-PRESENCIAL-14082026.md. Accesible por rol
        // `admin`/`cajero` (scoped a su evento) o `super_admin`, vía
        // assertCanOperarCaja() dentro de cada controller — el listado de
        // turnos (control de cierre) es la excepción, solo admin/super_admin.
        Route::get('/event/{event}/caja/turno-actual', [CajaTurnoController::class, 'actual']);
        Route::post('/event/{event}/caja/turno/abrir', [CajaTurnoController::class, 'abrir']);
        Route::post('/caja/turno/{turno}/cerrar', [CajaTurnoController::class, 'cerrar']);
        Route::get('/event/{event}/caja/turnos', [CajaTurnoController::class, 'index']);
        Route::get('/event/{event}/caja/turnos/{turno}', [CajaTurnoController::class, 'show']);

        Route::get('/event/{event}/caja/buscar', [CajaController::class, 'buscar']);
        // Prellenado desde `personas` (20/08/2026) — `Persona` es global
        // (no por evento), pero la ruta igual queda anidada bajo
        // /event/{event}/caja para reusar el mismo scoping que el resto
        // de Caja (assertCanOperarCaja) en vez de abrir un endpoint sin
        // scope de evento.
        Route::get('/event/{event}/caja/persona', [CajaController::class, 'buscarPersona']);
        Route::post('/event/{event}/caja/inscripcion', [CajaController::class, 'inscripcion']);
        Route::post('/registrations/{reference}/caja/cobrar-pendiente', [CajaController::class, 'cobrarPendiente']);
        Route::patch('/registrations/{reference}/caja/editar-pendiente', [CajaController::class, 'editarPendiente']);
        Route::patch('/registrations/{reference}/caja/editar-pagada', [CajaController::class, 'editarPagada']);

        Route::post('/admin/logout', [AdminAuthController::class, 'logout']);
        Route::get('/admin/me', [AdminAuthController::class, 'me']);
        Route::apiResource('/admin/users', AdminUserController::class)->except(['create', 'edit']);
        Route::get('/admin/audit-logs', [AdminAuditLogController::class, 'index']);

        // SIP multi-banco (28/08/2026) — CRUD de bancos, solo super_admin
        // (asertado dentro del controller, mismo criterio que
        // AdminUserController). Ver PLAN-SIP-MULTIBANCO-28082026.md.
        Route::apiResource('/sip-bancos', \App\Http\Controllers\SipBancoController::class)->except(['create', 'edit']);

        // Consolidación financiera (liquidación de utilidades) — solo
        // super_admin, ver LiquidarEventoAction y elascenso/event/brain/
        // (sesión 11/08/2026). Socios es config global (no scoped a un
        // evento); la liquidación sí es por evento.
        Route::apiResource('/socios', SocioController::class)->only(['index', 'store', 'update', 'destroy']);

        // CRUD de organizadores (catálogo global, no scoped por evento) —
        // solo super_admin, mismo criterio que Socios. Ver
        // OrganizadorController.
        //
        // ->parameters() es obligatorio acá: Str::singular('organizadores')
        // da "organizadore" (el inflector de Laravel es en inglés, no
        // reconoce el plural en "-es" del español), así que sin esto la
        // ruta generada sería {organizadore} — no coincide con el
        // `Organizador $organizador` de los métodos del controlador, y el
        // binding implícito de modelo se rompe en silencio: en vez de un
        // 404/error visible, inyecta un Organizador vacío (id null) y cada
        // acción sigue "de largo" con datos incorrectos. Encontrado en los
        // tests de este mismo PRD (destroy() no bloqueaba con eventos
        // asociados porque estaba chequeando el modelo vacío).
        Route::apiResource('/organizadores', OrganizadorController::class)
            ->parameters(['organizadores' => 'organizador'])
            ->only(['index', 'show', 'store', 'update', 'destroy']);

        // Formas de pago activas por organizador (19/08/2026) — ver
        // brain/PLAN-INTEGRACION-PAGO-MERU-19082026.md y
        // Organizador::formasPagoSeleccionadas()/formasPagoEfectivas().
        Route::get('/organizadores/{organizador}/formas-pago', [OrganizadorController::class, 'formasPago']);
        Route::put('/organizadores/{organizador}/formas-pago', [OrganizadorController::class, 'updateFormasPago']);

        // Catálogos globales (15/08/2026) — País/Ciudad/Sexo/Tipo de
        // evento/Subtipo de evento/Relación de contacto, todos config
        // global (no scoped por evento), solo super_admin, mismo criterio
        // que Socios/Organizadores. Prefijo `catalogos/` a propósito para
        // no chocar con el `GET /tipos-evento` público de más abajo (ver
        // TipoEventoController::index(), que sigue intacto). Rutas
        // explícitas en vez de apiResource: TipoEvento ya tiene su propio
        // `index()` público, así que su listado de administración usa
        // `adminIndex()` con nombre de método distinto — ver
        // elascenso/event/brain/PLAN-CATALOGOS-GLOBALES-15082026.md.
        Route::prefix('catalogos')->group(function () {
            Route::get('/paises', [PaisController::class, 'index']);
            Route::post('/paises', [PaisController::class, 'store']);
            Route::put('/paises/{pais}', [PaisController::class, 'update']);
            Route::delete('/paises/{pais}', [PaisController::class, 'destroy']);

            Route::get('/ciudades', [CiudadController::class, 'index']);
            Route::post('/ciudades', [CiudadController::class, 'store']);
            Route::put('/ciudades/{ciudad}', [CiudadController::class, 'update']);
            Route::delete('/ciudades/{ciudad}', [CiudadController::class, 'destroy']);

            Route::get('/sexos', [SexoController::class, 'index']);
            Route::post('/sexos', [SexoController::class, 'store']);
            Route::put('/sexos/{sexo}', [SexoController::class, 'update']);
            Route::delete('/sexos/{sexo}', [SexoController::class, 'destroy']);

            Route::get('/tipos-evento', [TipoEventoController::class, 'adminIndex']);
            Route::post('/tipos-evento', [TipoEventoController::class, 'store']);
            Route::put('/tipos-evento/{tipoEvento}', [TipoEventoController::class, 'update']);
            Route::delete('/tipos-evento/{tipoEvento}', [TipoEventoController::class, 'destroy']);

            Route::get('/subtipos-evento', [SubtipoEventoController::class, 'index']);
            Route::post('/subtipos-evento', [SubtipoEventoController::class, 'store']);
            Route::put('/subtipos-evento/{subtipoEvento}', [SubtipoEventoController::class, 'update']);
            Route::delete('/subtipos-evento/{subtipoEvento}', [SubtipoEventoController::class, 'destroy']);

            Route::get('/relaciones-contacto', [RelacionContactoController::class, 'index']);
            Route::post('/relaciones-contacto', [RelacionContactoController::class, 'store']);
            Route::put('/relaciones-contacto/{relacionContacto}', [RelacionContactoController::class, 'update']);
            Route::delete('/relaciones-contacto/{relacionContacto}', [RelacionContactoController::class, 'destroy']);

            // Formas de pago (19/08/2026) — ver
            // brain/PLAN-INTEGRACION-PAGO-MERU-19082026.md. Mismo criterio
            // que los catálogos de arriba, pero administra formas_pagos, no
            // uno de los catálogos "config" clásicos.
            Route::get('/formas-pago', [FormasPagoController::class, 'index']);
            Route::post('/formas-pago', [FormasPagoController::class, 'store']);
            Route::put('/formas-pago/{formasPago}', [FormasPagoController::class, 'update']);
            Route::delete('/formas-pago/{formasPago}', [FormasPagoController::class, 'destroy']);
        });

        Route::get('/event/{event}/liquidacion/preview', [LiquidacionController::class, 'preview']);
        Route::get('/event/{event}/liquidacion', [LiquidacionController::class, 'show']);
        Route::post('/event/{event}/liquidacion', [LiquidacionController::class, 'store']);

        // Catálogo de rubros del presupuesto — sí es solo super_admin
        // (config global, igual que Socios), a diferencia de los
        // movimientos de presupuesto en sí (arriba, fuera de este bloque).
        Route::get('/presupuesto-categorias', [PresupuestoCategoriaController::class, 'index']);
        Route::post('/presupuesto-categorias', [PresupuestoCategoriaController::class, 'store']);
        Route::put('/presupuesto-categorias/{categoria}', [PresupuestoCategoriaController::class, 'update']);
        Route::delete('/presupuesto-categorias/{categoria}', [PresupuestoCategoriaController::class, 'destroy']);

        // CRUD de personas (21/08/2026) — solo super_admin, ver
        // PersonaController::assertIsSuperAdmin(). Movido acá DENTRO del
        // grupo `auth:admins` a propósito: antes vivía suelto más abajo
        // con `->middleware('auth:sanctum')` (el guard de Persona, no de
        // AdminUser), así que ni siquiera un admin real podía autenticarse
        // correctamente contra este endpoint con ese guard — y cualquier
        // Persona común (participante autenticado con su propio token) sí
        // podía. `persona/me`, `persona/register`, `persona/login` y
        // `persona/logout` (más abajo) siguen siendo del guard de Persona
        // a propósito, son la cuenta pública, no tocarlos.
        Route::apiResource('/persona', PersonaController::class);
    });

    Route::post('/admin/login', [AdminAuthController::class, 'login']);

    Route::post('persona/register', [PersonaController::class, 'register']);
    Route::post('persona/login', [PersonaController::class, 'login']);
    Route::post('persona/logout', [PersonaController::class, 'logout']);
    // Route::get('/events/{event}', 'App\Http\Controllers\EventoController@show');
    Route::get('/registrations',[RegistrationController::class, 'index'])->middleware('auth:sanctum');
    Route::post('/registrations',[RegistrationController::class, 'store'])->middleware('auth:sanctum');
    Route::post('/registrations/lookup',[RegistrationController::class, 'lookup']);
    Route::get('/registrations/mine',[RegistrationController::class, 'mine'])->middleware('auth:sanctum');
    // Debe ir antes de la ruta comodín /registrations/{reference} de abajo,
    // si no Laravel matchea "by-pay-order" como si fuera un {reference}.
    Route::get('/registrations/by-pay-order/{payOrderNumber}',[RegistrationController::class, 'findByPayOrder']);
    Route::get('/registrations/{reference}',[RegistrationController::class, 'show']);
    Route::patch('/registrations/{reference}/payment',[RegistrationController::class, 'updatePayment']);
    Route::delete('/registrations/{reference}',[RegistrationController::class, 'destroy'])->middleware('auth:sanctum');
    Route::put('/registrations/{reference}',[RegistrationController::class, 'update']);
    Route::patch('/registrations/{reference}/update-paid',[RegistrationController::class, 'updatePaid']);

    // Cobro real por SIP del monto adicional (26/08/2026) — ver
    // PLAN-COBRO-SIP-ADICIONAL-26082026.md. {reference} = referencia de la
    // inscripción pagada; {referenciaAdicional} = alias propio del pago
    // adicional ('AD-XXXXXXXX'), nunca la misma referencia de la
    // inscripción.
    Route::post('/registrations/{reference}/pagos-adicionales', [PagoAdicionalController::class, 'store']);
    Route::patch('/pagos-adicionales/{referenciaAdicional}/qr', [PagoAdicionalController::class, 'guardarQrId']);
    Route::get('/pagos-adicionales/{referenciaAdicional}', [PagoAdicionalController::class, 'show']);
    Route::patch('/pagos-adicionales/{referenciaAdicional}/confirmar', [PagoAdicionalController::class, 'confirmar']);

    Route::get('/registrations/{reference}/generarToken',[RegistrationController::class, 'generarToken']);
    Route::get('/registrations/{reference}/estadoTransaccion',[RegistrationController::class, 'estadoTransaccion']);
    Route::get('/registrations/{reference}/generaQr',[RegistrationController::class, 'generaQr']);

    // Resultados de carrera en bulk — ver
    // brain/PLAN-RESULTADOS-EQUIPOS-31072026.md §2 (la numeración de
    // corredor/chip se movió al grupo auth:admins de arriba, es del
    // panel de administración; esto lo sigue subiendo el software de
    // cronometraje directo, sin panel, así que queda sin auth por ahora).
    Route::post('/event/{event}/resultados/bulk', [ResultadoController::class, 'bulk']);

    // Catálogo de equipos por evento (inscripción individual con hasTeam)
    Route::get('/event/{event}/equipos', [EquipoController::class, 'index']);
    Route::post('/event/{event}/equipos', [EquipoController::class, 'store']);

    // Resultados del participante logueado (individual + equipo)
    Route::get('/personas/me/resultados', [ResultadoController::class, 'mios'])->middleware('auth:sanctum');

    // Leaderboard completo de un evento (todas las categorías, no solo la
    // del participante) — ver brain/groovy-chasing-ladybug.md Parte C.
    Route::get('/event/{event}/resultados', [ResultadoController::class, 'porEvento'])->middleware('auth:sanctum');

    // Login propio del club + landing con historial/ranking privado — ver
    // elascenso/event/brain/PLAN-CLUBES-31072026.md
    Route::post('/club/login', [ClubController::class, 'login']);
    Route::post('/club/logout', [ClubController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/club/me', [ClubController::class, 'me'])->middleware('auth:sanctum');
    Route::get('/club/me/landing', [ClubController::class, 'landing'])->middleware('auth:sanctum');


    Route::get('/promo/{id}/code/{promocode}',[PromoCodeController::class, 'promoCode']);



   // Route::post('/promo/{id}/code/{promocode}',[EventoController::class, 'store']);

});