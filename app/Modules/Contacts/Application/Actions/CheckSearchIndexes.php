<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Actions;

use App\Modules\Contacts\Application\Search\SearchIndexHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final readonly class CheckSearchIndexes
{
    /**
     * Each FULLTEXT index contact search needs, as table => index.
     *
     * @var array<string, string>
     */
    private const array INDEXES = [
        'contacts' => 'contacts_name_fulltext',
        'contact_phones' => 'contact_phones_phone_fulltext',
    ];

    /**
     * Reads the server's FULLTEXT settings, checks the indexes exist, and counts the names containing
     * the smoke text through the name index and through LIKE. The counts scan contacts once each,
     * which suits a deploy check rather than a request.
     */
    public function handle(): SearchIndexHealth
    {
        $server = DB::selectOne('select @@global.innodb_ft_enable_stopword as stopwords, @@global.ngram_token_size as token_size');

        $missingIndexes = [];

        foreach (self::INDEXES as $table => $index) {
            if (! Schema::hasIndex($table, $index, 'fulltext')) {
                $missingIndexes[] = "{$table}.{$index}";
            }
        }

        $nameIndexExists = ! in_array('contacts.'.self::INDEXES['contacts'], $missingIndexes, true);

        return new SearchIndexHealth(
            (bool) $server->stopwords,
            (int) $server->token_size,
            $missingIndexes,
            DB::table('contacts')->where('name', 'like', '%'.SearchIndexHealth::SMOKE_TEXT.'%')->count(),
            $nameIndexExists
                ? DB::table('contacts')->whereFullText('name', '"'.SearchIndexHealth::SMOKE_TEXT.'"', ['mode' => 'boolean'])->count()
                : null,
        );
    }
}
