<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PersonaController;

use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\PromoCodeController;
use App\Http\Controllers\NotificationController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



Route::group(['prefix' => 'v1','namespace' => 'App\Http\Controllers'], function () {
    Route::apiResource('/event',EventoController::class);
    Route::apiResource('/coordinate',CoordinateController::class);
    Route::apiResource('/route',RouteController::class);
    Route::apiResource('/category',CategoryController::class);
    Route::apiResource('/form-type',FormTypeController::class);
    Route::apiResource('/persona',PersonaController::class);
    Route::post('persona/register', [PersonaController::class, 'register']);
    Route::post('persona/login', [PersonaController::class, 'login']);
    Route::post('persona/logout', [PersonaController::class, 'logout']);
    // Route::get('/events/{event}', 'App\Http\Controllers\EventoController@show');
    Route::get('/registrations',[RegistrationController::class, 'index']);
    Route::post('/registrations',[RegistrationController::class, 'store']);
    Route::post('/registrations/lookup',[RegistrationController::class, 'lookup']);
    Route::get('/registrations/{reference}',[RegistrationController::class, 'show']);
    Route::patch('/registrations/{reference}/payment',[RegistrationController::class, 'updatePayment']);
    Route::delete('/registrations/{reference}',[RegistrationController::class, 'destroy']);
    Route::put('/registrations/{reference}',[RegistrationController::class, 'update']);
    Route::patch('/registrations/{reference}/update-paid',[RegistrationController::class, 'updatePaid']);

    Route::get('/registrations/{reference}/generarToken',[RegistrationController::class, 'generarToken']);
    Route::get('/registrations/{reference}/estadoTransaccion',[RegistrationController::class, 'estadoTransaccion']);
    Route::get('/registrations/{reference}/generaQr',[RegistrationController::class, 'generaQr']);


    Route::get('/promo/{id}/code/{promocode}',[PromoCodeController::class, 'promoCode']);

    Route::get('/notifications',[NotificationController::class, 'index'])->middleware('auth:sanctum');
    Route::put('/notifications/{id}/read',[NotificationController::class, 'markAsRead'])->middleware('auth:sanctum');



   // Route::post('/promo/{id}/code/{promocode}',[EventoController::class, 'store']);

});