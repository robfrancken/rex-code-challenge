<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\LazyLoadingViolationException;

it('prevents lazy loading outside production', function (): void {
    User::factory()->count(2)->create();

    $user = User::query()->get()->firstOrFail();

    expect(fn () => $user->notifications)->toThrow(LazyLoadingViolationException::class);
});
