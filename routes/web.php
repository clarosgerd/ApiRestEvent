<?php

use App\Http\Controllers\MarketingOptOutController;
use App\Http\Controllers\OrganizadorDashboardController;
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
