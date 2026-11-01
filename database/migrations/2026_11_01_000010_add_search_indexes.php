<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MySQL commits each ALTER TABLE on its own, so a failure part-way leaves the first index in place.
 * Each step checks information_schema first, so running the migration again finishes the job.
 *
 * Locking: the first FULLTEXT index on a table rebuilds it to add the hidden FTS_DOC_ID column and
 * blocks writes (reads continue) until it finishes. On a large production table, run it in a quiet
 * window, then check the result with `php artisan contacts:check-search-indexes`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $previous = DB::selectOne('select @@session.innodb_ft_enable_stopword as value')->value;

        DB::statement('set session innodb_ft_enable_stopword = off');

        try {
            if (! Schema::hasIndex('contacts', 'contacts_name_fulltext')) {
                DB::statement('alter table contacts add fulltext index contacts_name_fulltext (name) with parser ngram');
            }

            if (! Schema::hasIndex('contact_phones', 'contact_phones_phone_fulltext')) {
                DB::statement('alter table contact_phones add fulltext index contact_phones_phone_fulltext (phone) with parser ngram');
            }
        } finally {
            DB::statement('set session innodb_ft_enable_stopword = ?', [(int) $previous]);
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('contacts', 'contacts_name_fulltext')) {
            DB::statement('alter table contacts drop index contacts_name_fulltext');
        }

        if (Schema::hasIndex('contact_phones', 'contact_phones_phone_fulltext')) {
            DB::statement('alter table contact_phones drop index contact_phones_phone_fulltext');
        }
    }
};
