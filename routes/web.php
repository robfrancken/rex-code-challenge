<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MeController;
use Illuminate\Support\Facades\Route;

Route::post('/login', LoginController::class)->middleware('throttle:login')->name('login');
Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');
Route::get('/api/v1/me', MeController::class)->middleware(['auth', 'throttle:api'])->name('me');

Route::get('/{any?}', fn () => view('app'))
    ->where('any', '^(?!api/|api$|login$|logout$|up$)(?!.*\.[A-Za-z0-9]+$).*')
    ->name('spa');
