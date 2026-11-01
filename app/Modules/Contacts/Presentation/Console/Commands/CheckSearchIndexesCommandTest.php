<?php

declare(strict_types=1);

use Database\Factories\Contacts\ContactFactory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

it('fails with the fix when the index disagrees with LIKE', function (): void {
    ContactFactory::new()->create(['name' => 'Jane Andrews']);

    withNameIndexRebuiltWithStopwords(function (): void {
        $this->artisan('contacts:check-search-indexes')
            ->expectsOutputToContain('1 contact names contain "an" but the FULLTEXT index finds 0')
            ->assertExitCode(1)
            ->run();
    });
})->group('fulltext');

it('fails while the server has stopwords on and passes once every check does', function (): void {
    ContactFactory::new()->create(['name' => 'Jane Andrews']);
    $stopwordsOn = (bool) DB::selectOne('select @@global.innodb_ft_enable_stopword as value')->value;

    $exitCode = Artisan::call('contacts:check-search-indexes');

    expect($exitCode)->toBe($stopwordsOn ? 1 : 0)
        ->and(Artisan::output())->toContain($stopwordsOn ? 'innodb_ft_enable_stopword is ON' : 'Search indexes are ready');
})->group('fulltext');
