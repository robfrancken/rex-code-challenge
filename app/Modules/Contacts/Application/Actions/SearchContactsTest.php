<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Actions\SearchContacts;
use App\Modules\Contacts\Application\DTOs\SearchContactsData;
use App\Modules\Contacts\Application\Exceptions\SearchTooBroad;
use App\Modules\Contacts\Application\Validation\SearchContactsValidator;
use Database\Factories\Contacts\ContactEmailFactory;
use Database\Factories\Contacts\ContactFactory;
use Database\Factories\Contacts\ContactPhoneFactory;
use Database\Seeders\BulkContactSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function searchData(
    ?string $q = null,
    ?string $name = null,
    ?string $phone = null,
    ?string $emailDomain = null,
    int $perPage = 25,
    ?string $cursor = null,
): SearchContactsData {
    return new SearchContactsData($q, $name, $phone, $emailDomain, $perPage, $cursor);
}

it('finds by name case-insensitively', function (): void {
    ContactFactory::new()->create(['name' => 'Jane Smith']);
    ContactFactory::new()->create(['name' => 'Bob']);

    expect(searchNames(searchData(name: 'SMI')))->toBe(['Jane Smith']);
})->group('fulltext');

it('finds by phone digits', function (): void {
    $match = ContactFactory::new()->create(['name' => 'Match']);
    ContactPhoneFactory::new()->for($match)->create(['phone' => '+61412341234']);
    $other = ContactFactory::new()->create(['name' => 'Other']);
    ContactPhoneFactory::new()->for($other)->create(['phone' => '+61498765432']);

    expect(searchNames(searchData(phone: '1234')))->toBe(['Match']);
})->group('fulltext');

it('finds an E.164 number when searching by its local format', function (): void {
    $match = ContactFactory::new()->create(['name' => 'Match']);
    ContactPhoneFactory::new()->for($match)->create(['phone' => '+61412345678']);
    $other = ContactFactory::new()->create(['name' => 'Other']);
    ContactPhoneFactory::new()->for($other)->create(['phone' => '+61498765432']);

    $validated = app(SearchContactsValidator::class)->validate(['phone' => '0412 345 678']);

    expect(searchNames(SearchContactsData::fromValidated($validated)))->toBe(['Match']);
})->group('fulltext');

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

    expect(searchNames(searchData(emailDomain: 'rex.com.au')))->toBe(['Match']);
})->group('fulltext');

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

    expect(searchNames(searchData(name: 'smith', emailDomain: 'rex.com.au')))->toBe(['Jane Smith']);
})->group('fulltext');

it('searches names and domain prefixes with q', function (): void {
    ContactFactory::new()->create(['name' => 'Rex Carter']);
    $domain = ContactFactory::new()->create(['name' => 'Pat Jones']);
    ContactEmailFactory::new()->for($domain)->create([
        'email' => 'pat@rex.com.au',
        'domain' => 'rex.com.au',
    ]);
    ContactFactory::new()->create(['name' => 'Bob Brown']);

    expect(searchNames(searchData(q: 'rex')))->toBe(['Pat Jones', 'Rex Carter']);
})->group('fulltext');

it('returns everything ordered by name without filters', function (): void {
    ContactFactory::new()->create(['name' => 'Charlie']);
    ContactFactory::new()->create(['name' => 'Alice']);
    ContactFactory::new()->create(['name' => 'Bob']);

    expect(searchNames(searchData()))->toBe(['Alice', 'Bob', 'Charlie']);
});

it('pages by cursor without duplicating or skipping contacts', function (): void {
    ContactFactory::new()->count(40)->sequence(fn ($sequence): array => ['name' => 'Same Name '.intdiv($sequence->index, 8)])->create();
    $action = app(SearchContacts::class);

    $first = $action->handle(searchData(perPage: 25));
    $second = $action->handle(searchData(perPage: 25, cursor: $first->nextCursor()?->encode()));

    $ids = collect($first->items())->merge($second->items())->pluck('id');

    expect($first->items())->toHaveCount(25)
        ->and($second->items())->toHaveCount(15)
        ->and($second->nextCursor())->toBeNull()
        ->and($ids->unique()->count())->toBe(40)
        ->and($ids->count())->toBe(40);
});

it('limits the search query to the configured execution time', function (): void {
    config(['contacts.search.max_execution_ms' => 1500]);
    DB::enableQueryLog();

    app(SearchContacts::class)->handle(searchData(emailDomain: 'rex.com.au'));

    expect(DB::getQueryLog()[0]['query'])->toStartWith('select /*+ MAX_EXECUTION_TIME(1500) */ contacts.* from');
});

it('reports a search that runs out of time as too broad', function (): void {
    failTimeLimitedQueries();

    expect(fn () => app(SearchContacts::class)->handle(searchData()))->toThrow(
        SearchTooBroad::class,
        'The search matched too many contacts to sort in time. Add more characters or another filter.',
    );
});

it('lets other query errors through', function (): void {
    failTimeLimitedQueries(1064);

    expect(fn () => app(SearchContacts::class)->handle(searchData()))->toThrow(QueryException::class);
});

it('loads emails and phones for the page', function (): void {
    $contact = ContactFactory::new()->create();
    ContactEmailFactory::new()->for($contact)->create();

    $page = app(SearchContacts::class)->handle(searchData());

    expect($page->items()[0]->relationLoaded('emails'))->toBeTrue()
        ->and($page->items()[0]->relationLoaded('phones'))->toBeTrue();
});

/**
 * Every contact id the search returns, following the cursor through every page, sorted.
 *
 * @param  array{q?: string, name?: string, phone?: string, email_domain?: string}  $filters
 * @return list<string>
 */
function everySearchedId(array $filters): array
{
    $ids = [];
    $cursor = null;

    do {
        $page = app(SearchContacts::class)->handle(SearchContactsData::fromValidated([
            ...$filters,
            'cursor' => $cursor,
        ]));
        array_push($ids, ...$page->getCollection()->pluck('id')->all());
        $cursor = $page->nextCursor()?->encode();
    } while ($cursor !== null);

    sort($ids);

    return $ids;
}

it('returns exactly the contacts a plain LIKE and EXISTS query finds', function (): void {
    new BulkContactSeeder()->run(100);
    DB::statement('analyze table contacts, contact_emails, contact_phones');

    $hasPhone61 = "exists (select 1 from contact_phones p where p.contact_id = contacts.id and p.phone like '%61%')";
    $combinations = [
        'name and phone' => [
            [
                'name' => 'an',
                'phone' => '61',
            ],
            "name like '%an%' and {$hasPhone61}",
        ],
        'q and phone' => [
            [
                'q' => 'gmail',
                'phone' => '61',
            ],
            "(name like '%gmail%' or exists (select 1 from contact_emails e where e.contact_id = contacts.id and e.domain like 'gmail%')) and {$hasPhone61}",
        ],
        'name and email domain' => [
            [
                'name' => 'an',
                'email_domain' => 'gmail.com',
            ],
            "name like '%an%' and exists (select 1 from contact_emails e where e.contact_id = contacts.id and e.domain = 'gmail.com')",
        ],
        'q and name' => [
            [
                'q' => 'rex',
                'name' => 'an',
            ],
            "(name like '%rex%' or exists (select 1 from contact_emails e where e.contact_id = contacts.id and e.domain like 'rex%')) and name like '%an%'",
        ],
    ];

    foreach ($combinations as $label => [$filters, $condition]) {
        $expected = DB::table('contacts')->whereRaw($condition)->pluck('id')->sort()->values()->all();

        expect($expected)->not->toBeEmpty($label)
            ->and(everySearchedId($filters))->toBe($expected, $label);
    }
})->group('fulltext');
