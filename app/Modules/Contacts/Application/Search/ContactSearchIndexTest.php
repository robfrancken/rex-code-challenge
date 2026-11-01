<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Search\ContactSearchQuery;
use App\Modules\Contacts\Domain\Models\Contact;
use Illuminate\Support\Facades\DB;

/**
 * @return array{key: ?string, type: string}
 */
function explainedPlan(string $sql): array
{
    $row = DB::selectOne('explain '.$sql);

    return [
        'key' => $row->key,
        'type' => $row->type,
    ];
}

it('searches names through the name fulltext index', function (): void {
    $sql = new ContactSearchQuery()->nameBranch('jane')?->toRawSql();

    expect(explainedPlan((string) $sql))->toBe([
        'key' => 'contacts_name_fulltext',
        'type' => 'fulltext',
    ]);
})->group('fulltext');

it('searches phones through the phone fulltext index', function (): void {
    $sql = new ContactSearchQuery()->phoneBranch('412345')?->toRawSql();

    expect(explainedPlan((string) $sql))->toBe([
        'key' => 'contact_phones_phone_fulltext',
        'type' => 'fulltext',
    ]);
})->group('fulltext');

it('searches domain prefixes through the domain index as a range', function (): void {
    $sql = new ContactSearchQuery()->domainPrefixBranch('rex.com')?->toRawSql();

    expect(explainedPlan((string) $sql))->toBe([
        'key' => 'contact_emails_domain_index',
        'type' => 'range',
    ]);
})->group('fulltext');

it('plans the q union without dependent subqueries', function (): void {
    $sql = new ContactSearchQuery()
        ->apply(Contact::query(), 'rex 0412', null, null, null)
        ->toRawSql();

    $types = collect(DB::select('explain '.$sql))->pluck('select_type')->all();

    expect($types)->not->toContain('DEPENDENT SUBQUERY')
        ->and($types)->not->toContain('DEPENDENT UNION');
})->group('fulltext');
