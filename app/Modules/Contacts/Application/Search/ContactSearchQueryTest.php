<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Search\ContactSearchQuery;
use App\Modules\Contacts\Domain\Models\Contact;
use Database\Factories\Contacts\ContactEmailFactory;
use Database\Factories\Contacts\ContactFactory;
use Database\Factories\Contacts\ContactPhoneFactory;
use PHPUnit\Framework\AssertionFailedError;

/**
 * @return list<string>
 */
function searchedNames(
    ?string $q = null,
    ?string $name = null,
    ?string $phone = null,
    ?string $emailDomain = null,
): array {
    return new ContactSearchQuery()
        ->apply(Contact::query(), $q, $name, $phone, $emailDomain)
        ->orderBy('name')
        ->pluck('name')
        ->all();
}

function contactNamed(
    string $name,
    ?string $email = null,
    ?string $phone = null,
): void {
    $contact = ContactFactory::new()->create(['name' => $name]);

    if ($email !== null) {
        ContactEmailFactory::new()->for($contact)->create([
            'email' => $email,
            'domain' => substr($email, (int) strrpos($email, '@') + 1),
        ]);
    }

    if ($phone !== null) {
        ContactPhoneFactory::new()->for($contact)->create(['phone' => $phone]);
    }
}

it('finds a name by substring', function (): void {
    contactNamed('Jane Smith');
    contactNamed('Bob');

    expect(searchedNames(name: 'mit'))->toBe(['Jane Smith']);
})->group('fulltext');

it('matches names regardless of case', function (): void {
    contactNamed('Jane Smith');

    expect(searchedNames(name: 'JANE'))->toBe(['Jane Smith']);
})->group('fulltext');

it('matches accented names with unaccented text under the collation', function (): void {
    contactNamed('José Ortiz');

    expect(searchedNames(name: 'jose'))->toBe(['José Ortiz']);
})->group('fulltext');

it('finds a one character name by prefix', function (): void {
    contactNamed('Jane Smith');
    contactNamed('Bob');

    expect(searchedNames(name: 'j'))->toBe(['Jane Smith']);
})->group('fulltext');

it('finds a name by a substring that contains a one character word', function (
    string $name,
    string $term,
): void {
    contactNamed($name);
    contactNamed('Chloe Jones');
    contactNamed('Jane Smith');

    expect(searchedNames(name: $term))->toBe([$name])
        ->and(searchedNames(q: $term))->toBe([$name]);
})->with([
    'trailing initial' => ['Chloe Smith', 'chloe s'],
    'leading initial' => ['J Smith', 'j smith'],
    'end of a first name' => ['Zoe Smithers', 'e smithe'],
    'apostrophe' => ["Allene O'Reilly", "o'reilly"],
    'apostrophe after an initial' => ["Gina D'Angelo", "d'angelo"],
    'initial after a surname' => ['Ivy Lehner Ingram', 'lehner i'],
])->group('fulltext');

it('matches a typed separator against a hyphen or a space', function (): void {
    contactNamed('Mary-Jane Watson');
    contactNamed('Mary Jane Kelly');
    contactNamed('Maryjane Ortiz');

    expect(searchedNames(name: 'mary-jane'))->toBe(['Mary Jane Kelly', 'Mary-Jane Watson'])
        ->and(searchedNames(name: 'Mary-Jane Watson'))->toBe(['Mary-Jane Watson']);
})->group('fulltext');

it('falls back to a name prefix when no word has two characters', function (
    string $term,
    string $name,
): void {
    contactNamed($name);
    contactNamed('Bob Brown');

    expect(searchedNames(name: $term))->toBe([$name]);
})->with([
    'apostrophe' => ["o'", "O'Reilly"],
    'two initials' => ['a b', 'A B Smith'],
])->group('fulltext');

it('finds a phone by a fragment of its digits', function (string $term): void {
    contactNamed('Match', phone: '+61412345678');
    contactNamed('Other', phone: '+61498765432');

    expect(searchedNames(phone: $term))->toBe(['Match']);
})->with([
    'tail' => '5678',
    'local format' => '0412 345',
])->group('fulltext');

it('searches names, phones and domain prefixes with q', function (): void {
    contactNamed('Rex Carter');
    contactNamed('Pat Jones', email: 'pat@rex.com.au');
    contactNamed('Sam Lee', email: 'sam@notrex.com.au');

    expect(searchedNames(q: 'rex'))->toBe(['Pat Jones', 'Rex Carter']);
})->group('fulltext');

it('searches phones with q', function (): void {
    contactNamed('Match', phone: '+61412345678');
    contactNamed('Other', phone: '+61498765432');

    expect(searchedNames(q: '0412 345'))->toBe(['Match']);
})->group('fulltext');

it('leaves phones out of q when q does not look like a phone number', function (): void {
    contactNamed('Acme 2024');
    contactNamed('Other', phone: '+61420241234');

    expect(searchedNames(q: 'acme 2024'))->toBe(['Acme 2024'])
        ->and(searchedNames(q: '2024'))->toBe(['Acme 2024', 'Other']);
})->group('fulltext');

it('matches the explicit email domain exactly', function (): void {
    contactNamed('Pat Jones', email: 'pat@rex.com.au');

    expect(searchedNames(emailDomain: 'rex.com'))->toBe([])
        ->and(searchedNames(emailDomain: 'rex.com.au'))->toBe(['Pat Jones']);
})->group('fulltext');

it('ands the explicit filters', function (): void {
    contactNamed('Jane Smith', email: 'jane@rex.com.au');
    contactNamed('Sam Smith', email: 'sam@other.com');
    contactNamed('Jane Doe', email: 'doe@rex.com.au');

    expect(searchedNames(name: 'smi', emailDomain: 'rex.com.au'))->toBe(['Jane Smith']);
})->group('fulltext');

it('treats operator-like q as plain text', function (string $q): void {
    contactNamed('Jane Smith');
    contactNamed('Bob Brown');

    expect(searchedNames(q: $q))->toBeArray();
})->with(['"jane', '+jane -smith', '*', '()', '@', '~jane', 'jane*', '<jane>'])->group('fulltext');

it('does not let boolean operators change the meaning of q', function (): void {
    contactNamed('Jane Smith');
    contactNamed('Jane Doe');

    expect(searchedNames(q: '+jane -smith'))->toBe(['Jane Smith'])
        ->and(searchedNames(q: '"jane'))->toBe(['Jane Doe', 'Jane Smith']);
})->group('fulltext');

it('leaves the query unfiltered when q has nothing searchable', function (string $q): void {
    contactNamed('Jane Smith');
    contactNamed('Bob Brown');

    expect(searchedNames(q: $q))->toBe(['Bob Brown', 'Jane Smith']);
})->with(['-', '  ', '+', '@'])->group('fulltext');

it('fails a test that searches FULLTEXT outside the fulltext group', function (): void {
    expect(fn () => searchedNames(name: 'jane'))->toThrow(AssertionFailedError::class, "->group('fulltext')");
});
