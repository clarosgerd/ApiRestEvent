<?php

use App\Http\Controllers\MarketingOptOutController;
use App\Http\Controllers\OrganizadorDashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\OpsAuthController;
use App\Http\Controllers\OpsBackupController;
use App\Http\Controllers\OpsJobController;
use App\Http\Controllers\OpsLinkController;
use App\Http\Controllers\OpsLogController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/marketing/opt-out/{persona}', [MarketingOptOutController::class, 'optOut'])
    ->name('marketing.opt-out')
    ->middleware('signed');

// Dashboard privado del organizador — sin login, protegido por firma de URL.
// El link se genera con `php artisan organizador:generar-link {evento}`.
Route::get('/organizador/evento/{evento}/dashboard', [OrganizadorDashboardController::class, 'show'])
    ->name('organizador.dashboard')
    ->middleware('signed');

// La descarga valida la firma a mano (ignorando los filtros de query string)
// para que un mismo link firmado sirva con cualquier combinación de filtros.
Route::get('/organizador/evento/{evento}/participantes.csv', [OrganizadorDashboardController::class, 'exportCsv'])
    ->name('organizador.dashboard.export');

// Dashboard de delivery de kits — mismo patrón sin login que el dashboard
// del organizador. El link se genera con `php artisan delivery:generar-link
// {evento}` y se puede pasar tal cual a la empresa de delivery.
Route::get('/organizador/evento/{evento}/delivery', [DeliveryController::class, 'show'])
    ->name('delivery.dashboard')
    ->middleware('signed');

Route::get('/organizador/evento/{evento}/delivery.csv', [DeliveryController::class, 'exportCsv'])
    ->name('delivery.dashboard.export');

// Link de consumo (JSON) para que la empresa de delivery integre esta
// info a su propio sistema en vez de leer la página HTML a mano.
Route::get('/organizador/evento/{evento}/delivery.json', [DeliveryController::class, 'json'])
    ->name('delivery.dashboard.json')
    ->middleware('signed');

// Avanza el estado de envío de un participante (pendiente/confirmado/
// entregado/cancelado) — GET simple (no form) para que funcione como link
// directo desde la tabla del dashboard, sin sesión/CSRF de por medio.
Route::get('/organizador/evento/{evento}/delivery/{participante}/estado', [DeliveryController::class, 'updateEstado'])
    ->name('delivery.dashboard.update-estado');

// Push-back de numeración de corredor/chip desde el POS de retiro en sitio
// de elascenso/delivery — mismo patrón sin sesión/CSRF que el de arriba,
// pero por numero_documento (no hay id de participante en ese flujo). La
// firma se valida a mano en el controller para poder ignorar
// numero_corredor/chip en la query string.
Route::get('/organizador/evento/{evento}/participantes/{documento}/numeracion', [OrganizadorDashboardController::class, 'actualizarNumeracionSitio'])
    ->name('organizador.dashboard.actualizar-numeracion');

// ── Panel de operaciones (/ops) — jobs, logs, backups a Google Drive,
// enlaces de organizador/delivery. Login propio (guard `web` nativo,
// tabla `users`), completamente aparte de admin-eventos (admin_users) y
// de la SPA de inscripción (persona/club).
Route::get('/ops/login', [OpsAuthController::class, 'showLogin'])->name('ops.login.show');
Route::post('/ops/login', [OpsAuthController::class, 'login'])->name('ops.login');

Route::middleware('auth')->prefix('ops')->name('ops.')->group(function () {
    Route::post('/logout', [OpsAuthController::class, 'logout'])->name('logout');

    Route::get('/jobs', [OpsJobController::class, 'index'])->name('jobs');
    Route::post('/jobs/{uuid}/retry', [OpsJobController::class, 'retry'])->name('jobs.retry');
    Route::delete('/jobs/failed/{uuid}', [OpsJobController::class, 'forget'])->name('jobs.forget');
    Route::delete('/jobs/{id}', [OpsJobController::class, 'destroy'])->whereNumber('id')->name('jobs.destroy');

    Route::get('/logs', [OpsLogController::class, 'index'])->name('logs');

    Route::get('/backups', [OpsBackupController::class, 'index'])->name('backups');
    Route::post('/backups/run', [OpsBackupController::class, 'run'])->name('backups.run');
    Route::get('/backups/{backupRun}/download', [OpsBackupController::class, 'download'])->name('backups.download');

    Route::get('/enlaces', [OpsLinkController::class, 'index'])->name('enlaces');
    Route::get('/enlaces/{evento}', [OpsLinkController::class, 'show'])->name('enlaces.show');
});
