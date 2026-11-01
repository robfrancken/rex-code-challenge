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
     * run without the per-test transaction and are cleaned up by truncation instead.
     */
    public function beginDatabaseTransaction()
    {
        if (! in_array('fulltext', $this->groups(), true)) {
            $this->beginRolledBackTransaction();

            return;
        }

        $this->truncateFulltextTables();

        $this->beforeApplicationDestroyed(function (): void {
            $this->truncateFulltextTables();
            DB::disconnect();
        });
    }

    /**
     * TRUNCATE recreates the table and its FULLTEXT indexes, and the stopword setting
     * of the session doing so decides whether the rebuilt index drops words like "it".
     */
    private function truncateFulltextTables(): void
    {
        DB::statement('set session innodb_ft_enable_stopword = off');

        try {
            Schema::withoutForeignKeyConstraints(function (): void {
                foreach (self::FULLTEXT_TABLES as $table) {
                    DB::table($table)->truncate();
                }
            });
        } finally {
            DB::statement('set session innodb_ft_enable_stopword = on');
        }
    }
}
