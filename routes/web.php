<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/login', LoginController::class)->middleware('throttle:login')->name('login');
Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');
Route::get('/api/v1/me', MeController::class)->middleware('auth')->name('me');
