<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Search\ContactSearchQuery;
use App\Modules\Contacts\Domain\Models\Contact;
use Database\Seeders\BulkContactSeeder;
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

    $plan = explainedPlan((string) $sql);

    expect($plan['key'])->toBe('contact_emails_domain_index')
        ->and($plan['type'])->toBeIn(['range', 'ref']);
})->group('fulltext');

it('plans the q union without dependent subqueries', function (): void {
    $sql = new ContactSearchQuery()
        ->apply(Contact::query(), 'rex 0412', null, null, null)
        ->toRawSql();

    $types = collect(DB::select('explain '.$sql))->pluck('select_type')->all();

    expect($types)->not->toContain('DEPENDENT SUBQUERY')
        ->and($types)->not->toContain('DEPENDENT UNION');
})->group('fulltext');

it('drives the q search from the match set, not a scan of contacts', function (): void {
    new BulkContactSeeder()->run(25);
    DB::statement('analyze table contacts, contact_emails, contact_phones');

    $sql = new ContactSearchQuery()
        ->apply(Contact::query(), 'rex 0412', null, null, null)
        ->orderBy('name')
        ->orderBy('id')
        ->limit(26)
        ->toRawSql();

    $plan = collect(DB::select('explain '.$sql));
    $contacts = $plan->firstWhere('table', 'contacts');

    expect($contacts->type)->not->toBe('ALL')
        ->and($contacts->key)->toBe('PRIMARY')
        ->and($plan->pluck('select_type')->all())->not->toContain('DEPENDENT SUBQUERY', 'DEPENDENT UNION');
})->group('fulltext');

it('joins the explicit fulltext filters as derived tables, never a semijoin', function (): void {
    new BulkContactSeeder()->run(25);
    DB::statement('analyze table contacts, contact_emails, contact_phones');

    $sql = new ContactSearchQuery()
        ->apply(Contact::query(), null, 'an', '61', null)
        ->orderBy('name')
        ->orderBy('id')
        ->limit(26)
        ->toRawSql();

    $plan = collect(DB::select('explain '.$sql));

    expect($plan->where('select_type', 'DERIVED'))->toHaveCount(2)
        ->and($plan->pluck('select_type')->all())->not->toContain('DEPENDENT SUBQUERY')
        ->and($plan->pluck('Extra')->implode(' '))->not->toContain('FirstMatch');
})->group('fulltext');
