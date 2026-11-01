<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Search\SearchIndexHealth;

/**
 * A health report that passes every check unless overridden.
 *
 * @param  list<string>  $missingIndexes
 */
function searchIndexHealth(
    bool $stopwordsEnabled = false,
    int $ngramTokenSize = 2,
    array $missingIndexes = [],
    int $nameMatchesByLike = 12,
    ?int $nameMatchesByFullText = 12,
): SearchIndexHealth {
    return new SearchIndexHealth($stopwordsEnabled, $ngramTokenSize, $missingIndexes, $nameMatchesByLike, $nameMatchesByFullText);
}

it('reports no problems when the server and the indexes are ready', function (): void {
    expect(searchIndexHealth()->problems())->toBe([])
        ->and(searchIndexHealth()->isHealthy())->toBeTrue();
});

it('reports server stopwords with the fix', function (): void {
    $problems = searchIndexHealth(stopwordsEnabled: true)->problems();

    expect($problems)->toHaveCount(1)
        ->and($problems[0])->toContain('innodb_ft_enable_stopword is ON')
        ->and($problems[0])->toContain('innodb_ft_enable_stopword=OFF')
        ->and($problems[0])->toContain('rebuild the FULLTEXT indexes');
});

it('reports an ngram token size other than 2', function (): void {
    $problems = searchIndexHealth(ngramTokenSize: 3)->problems();

    expect($problems)->toHaveCount(1)
        ->and($problems[0])->toContain('ngram_token_size is 3')
        ->and($problems[0])->toContain('ngram_token_size=2');
});

it('reports a missing index', function (): void {
    $problems = searchIndexHealth(missingIndexes: ['contact_phones.contact_phones_phone_fulltext'])->problems();

    expect($problems)->toBe(['The FULLTEXT index contact_phones.contact_phones_phone_fulltext is missing. Run the migrations.']);
});

it('reports the index and LIKE disagreeing on the smoke search', function (): void {
    $problems = searchIndexHealth(nameMatchesByLike: 12, nameMatchesByFullText: 0)->problems();

    expect($problems)->toHaveCount(1)
        ->and($problems[0])->toContain('12 contact names contain "an"')
        ->and($problems[0])->toContain('the FULLTEXT index finds 0');
});

it('skips the smoke search when the name index is missing', function (): void {
    $problems = searchIndexHealth(missingIndexes: ['contacts.contacts_name_fulltext'], nameMatchesByFullText: null)->problems();

    expect($problems)->toBe(['The FULLTEXT index contacts.contacts_name_fulltext is missing. Run the migrations.']);
});

it('reports every problem at once', function (): void {
    expect(searchIndexHealth(stopwordsEnabled: true, ngramTokenSize: 1, nameMatchesByFullText: 0)->problems())->toHaveCount(3);
});
