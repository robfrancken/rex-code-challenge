<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $previous = DB::selectOne('select @@session.innodb_ft_enable_stopword as value')->value;

        DB::statement('set session innodb_ft_enable_stopword = off');

        try {
            DB::statement('alter table contacts add fulltext index contacts_name_fulltext (name) with parser ngram');
            DB::statement('alter table contact_phones add fulltext index contact_phones_phone_fulltext (phone) with parser ngram');
        } finally {
            DB::statement('set session innodb_ft_enable_stopword = ?', [(int) $previous]);
        }
    }

    public function down(): void
    {
        DB::statement('alter table contacts drop index contacts_name_fulltext');
        DB::statement('alter table contact_phones drop index contact_phones_phone_fulltext');
    }
};
