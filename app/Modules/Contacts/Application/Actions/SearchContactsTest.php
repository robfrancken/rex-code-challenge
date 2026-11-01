<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Actions\SearchContacts;
use App\Modules\Contacts\Application\DTOs\SearchContactsData;
use Database\Factories\Contacts\ContactEmailFactory;
use Database\Factories\Contacts\ContactFactory;
use Database\Factories\Contacts\ContactPhoneFactory;

/**
 * @return list<string>
 */
function searchNames(SearchContactsData $data): array
{
    return app(SearchContacts::class)->handle($data)->getCollection()->pluck('name')->all();
}

it('finds by name case-insensitively', function (): void {
    ContactFactory::new()->create(['name' => 'Jane Smith']);
    ContactFactory::new()->create(['name' => 'Bob']);

    expect(searchNames(new SearchContactsData('smi', null, null)))->toBe(['Jane Smith']);
});

it('finds by phone digits', function (): void {
    $match = ContactFactory::new()->create(['name' => 'Match']);
    ContactPhoneFactory::new()->for($match)->create(['phone' => '+61412341234']);
    $other = ContactFactory::new()->create(['name' => 'Other']);
    ContactPhoneFactory::new()->for($other)->create(['phone' => '+61498765432']);

    expect(searchNames(new SearchContactsData(null, '1234', null)))->toBe(['Match']);
});

it('finds by exact email domain', function (): void {
    $match = ContactFactory::new()->create(['name' => 'Match']);
    ContactEmailFactory::new()->for($match)->create([
        'email' => 'jo@rex.com.au',
        'domain' => 'rex.com.au',
    ]);
    $other = ContactFactory::new()->create(['name' => 'Other']);
    ContactEmailFactory::new()->for($other)->create([
        'email' => 'jo@notrex.com.au',
        'domain' => 'notrex.com.au',
    ]);

    expect(searchNames(new SearchContactsData(null, null, 'rex.com.au')))->toBe(['Match']);
});

it('combines filters with AND', function (): void {
    $both = ContactFactory::new()->create(['name' => 'Jane Smith']);
    ContactEmailFactory::new()->for($both)->create([
        'email' => 'j@rex.com.au',
        'domain' => 'rex.com.au',
    ]);
    $nameOnly = ContactFactory::new()->create(['name' => 'John Smith']);
    ContactEmailFactory::new()->for($nameOnly)->create([
        'email' => 'j@other.com',
        'domain' => 'other.com',
    ]);

    expect(searchNames(new SearchContactsData('smith', null, 'rex.com.au')))->toBe(['Jane Smith']);
});

it('returns everything ordered by name without filters', function (): void {
    ContactFactory::new()->create(['name' => 'Charlie']);
    ContactFactory::new()->create(['name' => 'Alice']);
    ContactFactory::new()->create(['name' => 'Bob']);

    expect(searchNames(new SearchContactsData(null, null, null)))->toBe(['Alice', 'Bob', 'Charlie']);
});

it('paginates', function (): void {
    ContactFactory::new()->count(3)->create();

    $page = app(SearchContacts::class)->handle(new SearchContactsData(null, null, null, perPage: 2));

    expect($page->items())->toHaveCount(2)
        ->and($page->total())->toBe(3);
});

it('treats % and _ literally', function (): void {
    ContactFactory::new()->create(['name' => '100% Agency']);
    ContactFactory::new()->create(['name' => 'Bob']);
    ContactFactory::new()->create(['name' => 'a_b']);
    ContactFactory::new()->create(['name' => 'axb']);

    expect(searchNames(new SearchContactsData('%', null, null)))->toBe(['100% Agency'])
        ->and(searchNames(new SearchContactsData('a_b', null, null)))->toBe(['a_b']);
});
