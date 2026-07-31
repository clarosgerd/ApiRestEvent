<?php

use App\Http\Controllers\MarketingOptOutController;
use App\Http\Controllers\OrganizadorDashboardController;
use App\Http\Controllers\DeliveryController;
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
