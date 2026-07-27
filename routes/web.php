<?php

use App\Http\Controllers\MarketingOptOutController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/marketing/opt-out/{persona}', [MarketingOptOutController::class, 'optOut'])
    ->name('marketing.opt-out')
    ->middleware('signed');
