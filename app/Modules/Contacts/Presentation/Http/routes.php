<?php

declare(strict_types=1);

use App\Modules\Contacts\Presentation\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

Route::put('contacts/{id}', [ContactController::class, 'upsert'])->name('upsert');
Route::get('contacts/{id}', [ContactController::class, 'show'])->whereUuid('id')->name('show');
Route::get('contacts', [ContactController::class, 'index'])->name('index');
Route::delete('contacts/{id}', [ContactController::class, 'destroy'])->whereUuid('id')->name('destroy');

// Registered last, so a non-UUID id on GET/DELETE (which matches only the unconstrained PUT route) answers 404, not 405.
Route::match(['GET', 'DELETE'], 'contacts/{notUuid}', fn () => throw new NotFoundHttpException);
