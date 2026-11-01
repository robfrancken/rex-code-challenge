<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Search;

/**
 * What a deploy needs to know about the FULLTEXT search indexes, and what is wrong with them.
 */
final readonly class SearchIndexHealth
{
    /**
     * The text the smoke check counts in names through both the index and LIKE. "an" is a stopword,
     * so an index built with stopwords on drops it and finds nothing.
     */
    public const string SMOKE_TEXT = 'an';

    /**
     * Rebuilds both tables, and with them their FULLTEXT indexes under the session's stopword setting.
     */
    private const string REBUILD = 'ALTER TABLE contacts FORCE; ALTER TABLE contact_phones FORCE;';

    /**
     * @param  list<string>  $missingIndexes  table.index for each FULLTEXT index that does not exist
     * @param  int|null  $nameMatchesByFullText  null when the name index is missing and could not be searched
     */
    public function __construct(
        public bool $stopwordsEnabled,
        public int $ngramTokenSize,
        public array $missingIndexes,
        public int $nameMatchesByLike,
        public ?int $nameMatchesByFullText,
    ) {}

    public function isHealthy(): bool
    {
        return $this->problems() === [];
    }

    /**
     * One message per failed check, each saying how to fix it.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $problems = [];

        if ($this->stopwordsEnabled) {
            $problems[] = 'innodb_ft_enable_stopword is ON at server level, so any table rebuild (ALTER TABLE, OPTIMIZE TABLE, TRUNCATE)'
                .' recreates the FULLTEXT indexes without words like "an". Set innodb_ft_enable_stopword=OFF in the MySQL server'
                .' configuration and restart, then rebuild the FULLTEXT indexes: '.self::REBUILD;
        }

        if ($this->ngramTokenSize !== 2) {
            $problems[] = "ngram_token_size is {$this->ngramTokenSize}, but contact search needs 2. Set ngram_token_size=2 in the MySQL"
                .' server configuration and restart, then rebuild the FULLTEXT indexes: '.self::REBUILD;
        }

        foreach ($this->missingIndexes as $index) {
            $problems[] = "The FULLTEXT index {$index} is missing. Run the migrations.";
        }

        if ($this->nameMatchesByFullText !== null && $this->nameMatchesByFullText !== $this->nameMatchesByLike) {
            $problems[] = sprintf(
                '%d contact names contain "%s" but the FULLTEXT index finds %d, so name search misses contacts.'
                .' Rebuild the FULLTEXT indexes from a session with stopwords off: SET SESSION innodb_ft_enable_stopword=OFF; %s',
                $this->nameMatchesByLike,
                self::SMOKE_TEXT,
                $this->nameMatchesByFullText,
                self::REBUILD,
            );
        }

        return $problems;
    }
}
