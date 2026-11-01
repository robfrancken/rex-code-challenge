<?php

declare(strict_types=1);

use App\Modules\Contacts\Presentation\Http\Controllers\CallController;
use App\Modules\Contacts\Presentation\Http\Controllers\ContactController;
use App\Modules\Contacts\Presentation\Http\Middleware\EnsureIdempotency;
use Illuminate\Support\Facades\Route;

Route::apiResource('contacts', ContactController::class)->only(['index', 'show', 'destroy']);

Route::post('contacts', [ContactController::class, 'store'])
    ->middleware(EnsureIdempotency::class)
    ->name('contacts.store');

Route::put('contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');

Route::apiResource('contacts.calls', CallController::class)
    ->only('store')
    ->middleware(EnsureIdempotency::class);
