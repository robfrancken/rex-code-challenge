<?php

declare(strict_types=1);

use App\Modules\Contacts\Domain\Telephony\CallOutcome;
use App\Modules\Contacts\Domain\ValueObjects\PhoneNumber;
use App\Modules\Contacts\Infrastructure\FakeTelephonyGateway;
use Random\Engine\Mt19937;
use Random\Randomizer;

it('returns a CallOutcome', function (): void {
    $outcome = (new FakeTelephonyGateway)->place(PhoneNumber::fromString('+61412345678'));

    expect($outcome)->toBeInstanceOf(CallOutcome::class);
});

it('is deterministic with a seeded engine', function (): void {
    $to = PhoneNumber::fromString('+61412345678');
    $first = new FakeTelephonyGateway(new Randomizer(new Mt19937(42)));
    $second = new FakeTelephonyGateway(new Randomizer(new Mt19937(42)));

    $sequence = fn (FakeTelephonyGateway $gateway): array => array_map(
        fn (int $call): CallOutcome => $gateway->place($to),
        range(1, 5),
    );

    expect($sequence($first))->toBe($sequence($second));
});

it('can return every outcome', function (): void {
    $to = PhoneNumber::fromString('+61412345678');
    $gateway = new FakeTelephonyGateway(new Randomizer(new Mt19937(42)));

    $seen = [];
    foreach (range(1, 200) as $call) {
        $seen[$gateway->place($to)->value] = true;
    }

    $expected = array_map(fn (CallOutcome $case): string => $case->value, CallOutcome::cases());

    expect(array_keys($seen))->toEqualCanonicalizing($expected);
});
