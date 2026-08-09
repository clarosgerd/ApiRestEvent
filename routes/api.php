<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PersonaController;

use App\Http\Controllers\RegistrationController;
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
        Route::apiResource('/form-type', FormTypeController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('/promo-code', PromoCodeController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('/souvenir', SouvenirController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('/auspiciador', AuspiciadorController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('/agenda-item', AgendaItemController::class)->only(['store', 'update', 'destroy']);

        // Numeración de corredor/chip (panel de administración) — ver
        // brain/PLAN-RESULTADOS-EQUIPOS-31072026.md §1 y
        // PLAN-NUMERACION-PANEL-05082026.md. Antes sin auth (deuda
        // documentada en project_features_resultados_equipos_delivery);
        // ahora scoped igual que el resto del panel.
        Route::get('/event/{event}/participantes', [ParticipanteController::class, 'porEvento']);
        Route::patch('/registrations/{reference}/participantes/{participante}/numeracion', [RegistrationController::class, 'updateNumeracion']);
        Route::post('/event/{event}/participantes/numeracion/bulk', [ParticipanteController::class, 'numeracionBulk']);

        // Edición restringida de datos de contacto/identidad de un
        // participante (panel de administración) — ver
        // ParticipanteController::update() / UpdateParticipanteRequest
        // para el whitelist real de campos permitidos.
        Route::patch('/participantes/{participante}', [ParticipanteController::class, 'update']);

        // Dashboard de inscripciones (mismo conteo que ya se manda por
        // correo vía link firmado, ver OrganizadorDashboardController),
        // ahora también disponible autenticado dentro del panel.
        Route::get('/event/{event}/dashboard-inscripciones', [EventoController::class, 'dashboardInscripciones']);

        // Botón "Sincronizar ahora" del panel — ver
        // brain/groovy-chasing-ladybug.md Parte B.
        Route::post('/event/{event}/chronotrack/sincronizar', [ResultadoController::class, 'sincronizarChronoTrack']);

        // Carga masiva de inscripciones por CSV (panel de administración,
        // solo super_admin) — ver brain/PLAN-REGISTRO-MANUAL-CSV-05082026.md.
        Route::post('/event/{event}/registro-manual/bulk', [RegistrationController::class, 'importarBulk']);

        Route::post('/admin/logout', [AdminAuthController::class, 'logout']);
        Route::get('/admin/me', [AdminAuthController::class, 'me']);
        Route::apiResource('/admin/users', AdminUserController::class)->except(['create', 'edit']);
        Route::get('/admin/audit-logs', [AdminAuditLogController::class, 'index']);
    });

    Route::post('/admin/login', [AdminAuthController::class, 'login']);

    Route::get('persona/me', [PersonaController::class, 'me'])->middleware('auth:sanctum');
    Route::apiResource('/persona',PersonaController::class)->middleware('auth:sanctum');
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