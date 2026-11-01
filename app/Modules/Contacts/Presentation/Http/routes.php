<?php

declare(strict_types=1);

use App\Modules\Contacts\Presentation\Http\Controllers\CallController;
use App\Modules\Contacts\Presentation\Http\Controllers\ContactController;
use App\Modules\Contacts\Presentation\Http\Middleware\EnsureIdempotency;
use Illuminate\Support\Facades\Route;

Route::put('contacts/{id}', [ContactController::class, 'upsert'])->name('upsert');
Route::get('contacts/{id}', [ContactController::class, 'show'])->name('show');
Route::get('contacts', [ContactController::class, 'index'])->name('index');
Route::delete('contacts/{id}', [ContactController::class, 'destroy'])->name('destroy');

Route::post('contacts/{id}/calls', [CallController::class, 'store'])
    ->middleware(EnsureIdempotency::class)
    ->name('calls.store');
