<?php

declare(strict_types=1);

return [
    'search' => [
        /*
         * The longest a contact search query may run, in milliseconds, before MySQL stops it and
         * the search reports search_too_broad. 0 removes the limit.
         */
        'max_execution_ms' => (int) env('CONTACTS_SEARCH_MAX_EXECUTION_MS', 2000),
    ],

    'seed' => [
        'bulk_contacts' => (int) env('SEED_BULK_CONTACTS', 9960),

        /*
         * BulkContactSeeder fills the database with fake contacts, so it refuses to run in production
         * unless this is set. DatabaseSeeder never runs it in production.
         */
        'bulk_in_production' => (bool) env('SEED_BULK_CONTACTS_IN_PRODUCTION', false),
    ],
];
