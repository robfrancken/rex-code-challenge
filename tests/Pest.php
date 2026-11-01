<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Actions\SearchContacts;
use App\Modules\Contacts\Application\DTOs\SearchContactsData;
use App\Modules\Contacts\Application\DTOs\UpsertContactData;
use App\Modules\Contacts\Domain\Telephony\CallOutcome;
use App\Modules\Contacts\Domain\Telephony\TelephonyGateway;
use App\Modules\Contacts\Domain\ValueObjects\PhoneNumber;
use Database\Factories\Contacts\ContactFactory;
use Database\Factories\Contacts\ContactPhoneFactory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Pest\Expectation;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('../app', '../routes', '../database/seeders', 'Arch');

/**
 * Asserts that the closure under test throws a ValidationException with an error on $key,
 * and, when $message is given, that one of that key's messages contains it.
 */
expect()->extend('toFailValidationOn', function (
    string $key,
    ?string $message = null,
): Expectation {
    /** @var Expectation<callable(): mixed> $this */
    return $this->toThrow(function (ValidationException $exception) use ($key, $message): void {
        expect($exception->errors())->toHaveKey($key);

        if ($message !== null) {
            expect(implode("\n", $exception->errors()[$key]))->toContain($message);
        }
    });
});

/**
 * Binds a TelephonyGateway that always returns $outcome and records every number dialled.
 *
 * @return TelephonyGateway&object{dialled: list<string>}
 */
function bindGateway(CallOutcome $outcome): TelephonyGateway
{
    $gateway = new class($outcome) implements TelephonyGateway
    {
        /** @var list<string> */
        public array $dialled = [];

        public function __construct(private readonly CallOutcome $outcome) {}

        public function place(PhoneNumber $to): CallOutcome
        {
            $this->dialled[] = $to->value;

            return $this->outcome;
        }
    };
    app()->instance(TelephonyGateway::class, $gateway);

    return $gateway;
}

/**
 * Fails every query carrying a MAX_EXECUTION_TIME hint with MySQL error $code, before it runs.
 * The default, 3024, is what MySQL raises when a statement runs past that limit.
 */
function failTimeLimitedQueries(int $code = 3024): void
{
    DB::beforeExecuting(function (
        string $query,
        array $bindings,
    ) use ($code): void {
        if (! str_contains($query, 'MAX_EXECUTION_TIME')) {
            return;
        }

        $error = new PDOException('Query execution was interrupted, maximum statement execution time exceeded');
        $error->errorInfo = ['HY000', $code, $error->getMessage()];

        throw new QueryException('mysql', $query, $bindings, $error);
    });
}

/**
 * Runs $assertions while the name FULLTEXT index is rebuilt the way a table rebuild on a server
 * with stopwords on would leave it, then rebuilds it with stopwords off so later tests see a sound index.
 */
function withNameIndexRebuiltWithStopwords(callable $assertions): void
{
    $previous = (int) DB::selectOne('select @@session.innodb_ft_enable_stopword as value')->value;

    try {
        DB::statement('set session innodb_ft_enable_stopword = on');
        DB::statement('alter table contacts force');
        $assertions();
    } finally {
        DB::statement('set session innodb_ft_enable_stopword = off');
        DB::statement('alter table contacts force');
        DB::statement('set session innodb_ft_enable_stopword = ?', [$previous]);
    }
}

/**
 * The migration that adds the FULLTEXT search indexes.
 */
function searchIndexesMigration(): Migration
{
    return require database_path('migrations/2026_11_01_000010_add_search_indexes.php');
}

/**
 * Creates a contact whose phones are inserted in the given order (so the first has the lowest id) and returns its id.
 */
function contactWithPhones(string ...$phones): string
{
    $contact = ContactFactory::new()->create();
    foreach ($phones as $phone) {
        ContactPhoneFactory::new()->for($contact)->create(['phone' => $phone]);
    }

    return $contact->id;
}

/**
 * A valid contact payload (the upsert request body, without the id), with any keys replaced by $overrides.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function contactPayload(array $overrides = []): array
{
    return [
        'name' => 'Jane Citizen',
        'emails' => ['jane@rex.com.au'],
        'phones' => ['+61412345678'],
        ...$overrides,
    ];
}

/**
 * Upsert input as the validator would hand it to the DTO.
 *
 * @param  list<string>  $emails
 * @param  list<string>  $phones
 */
function upsertData(
    string $id,
    string $name = 'Jane',
    array $emails = ['jane@example.com'],
    array $phones = ['+61412345678'],
): UpsertContactData {
    return UpsertContactData::fromValidated([
        'id' => $id,
        'name' => $name,
        'emails' => $emails,
        'phones' => $phones,
    ]);
}

/**
 * The names on the first page of a contact search, in result order.
 *
 * @return list<string>
 */
function searchNames(SearchContactsData $data): array
{
    return app(SearchContacts::class)->handle($data)->getCollection()->pluck('name')->all();
}
