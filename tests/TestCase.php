<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase {
        beginDatabaseTransaction as private beginRolledBackTransaction;
    }

    /**
     * Tables emptied around a test in the fulltext group, children before parents.
     *
     * @var list<string>
     */
    private const array FULLTEXT_TABLES = ['contact_phones', 'contact_emails', 'contacts', 'users'];

    /**
     * InnoDB only indexes FULLTEXT rows on commit, so tests in the fulltext group
     * run without the per-test transaction and empty their tables instead.
     */
    public function beginDatabaseTransaction()
    {
        if (! in_array('fulltext', $this->groups(), true)) {
            $this->failFullTextQueries();
            $this->beginRolledBackTransaction();

            return;
        }

        $this->emptyFulltextTables();

        $this->beforeApplicationDestroyed(function (): void {
            $this->emptyFulltextTables();
            DB::disconnect();
        });
    }

    /**
     * Inside the rolled-back transaction MATCH never sees the test's own rows, so a FULLTEXT
     * search there returns nothing and an assertion on an empty result passes for the wrong
     * reason. Such a query fails the test before it runs.
     */
    private function failFullTextQueries(): void
    {
        DB::beforeExecuting(function (string $query): void {
            if (stripos($query, 'match (') !== false) {
                $this->fail("A FULLTEXT query (MATCH) ran in a test outside the fulltext group: add ->group('fulltext') to the test."
                    .' InnoDB only indexes FULLTEXT rows on commit, so inside the test transaction MATCH finds nothing.'
                    ."\nQuery: {$query}");
            }
        });
    }

    /**
     * DELETE rather than TRUNCATE: TRUNCATE recreates each table and rebuilds its FULLTEXT
     * indexes, which is slow and rebuilds them under the session's stopword setting.
     * MATCH already skips deleted rows, so the indexes the migration built stay valid.
     */
    private function emptyFulltextTables(): void
    {
        Schema::withoutForeignKeyConstraints(function (): void {
            foreach (self::FULLTEXT_TABLES as $table) {
                DB::table($table)->delete();
            }
        });
    }
}
