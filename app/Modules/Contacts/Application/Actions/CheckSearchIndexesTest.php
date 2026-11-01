<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Actions\CheckSearchIndexes;
use Database\Factories\Contacts\ContactFactory;
use Illuminate\Support\Facades\DB;

it('reads the server settings and finds the indexes', function (): void {
    $server = DB::selectOne('select @@global.innodb_ft_enable_stopword as stopwords, @@global.ngram_token_size as token_size');

    $health = app(CheckSearchIndexes::class)->handle();

    expect($health->stopwordsEnabled)->toBe((bool) $server->stopwords)
        ->and($health->ngramTokenSize)->toBe((int) $server->token_size)
        ->and($health->missingIndexes)->toBe([]);
})->group('fulltext');

it('finds the same names through the index and LIKE', function (): void {
    ContactFactory::new()->count(3)->sequence(['name' => 'Jane Andrews'], ['name' => 'Dan Brown'], ['name' => 'Bob Smith'])->create();

    $health = app(CheckSearchIndexes::class)->handle();

    expect($health->nameMatchesByLike)->toBe(2)
        ->and($health->nameMatchesByFullText)->toBe(2);
})->group('fulltext');

it('notices an index rebuilt with stopwords', function (): void {
    ContactFactory::new()->create(['name' => 'Jane Andrews']);

    withNameIndexRebuiltWithStopwords(function (): void {
        $health = app(CheckSearchIndexes::class)->handle();

        expect($health->nameMatchesByLike)->toBe(1)
            ->and($health->nameMatchesByFullText)->toBe(0)
            ->and($health->isHealthy())->toBeFalse();
    });
})->group('fulltext');

it('notices a missing index', function (): void {
    DB::statement('alter table contact_phones drop index contact_phones_phone_fulltext');

    try {
        expect(app(CheckSearchIndexes::class)->handle()->missingIndexes)->toBe(['contact_phones.contact_phones_phone_fulltext']);
    } finally {
        searchIndexesMigration()->up();
    }
})->group('fulltext');
