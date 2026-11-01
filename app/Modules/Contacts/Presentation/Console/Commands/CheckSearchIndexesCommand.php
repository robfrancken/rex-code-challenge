<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Presentation\Console\Commands;

use App\Modules\Contacts\Application\Actions\CheckSearchIndexes;
use App\Modules\Contacts\Application\Search\SearchIndexHealth;

final class CheckSearchIndexesCommand extends ContactsCommand
{
    protected $signature = 'contacts:check-search-indexes';

    protected $description = 'Check the MySQL settings and FULLTEXT indexes contact search depends on (for deploy pipelines)';

    public function handle(CheckSearchIndexes $action): int
    {
        $health = $action->handle();

        if (! $health->isHealthy()) {
            foreach ($health->problems() as $problem) {
                $this->error($problem);
            }

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Search indexes are ready: stopwords off, ngram_token_size 2, and "%s" finds %d names through both the index and LIKE.',
            SearchIndexHealth::SMOKE_TEXT,
            $health->nameMatchesByLike,
        ));

        return self::SUCCESS;
    }
}
