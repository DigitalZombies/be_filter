<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Event;

use DigitalZombies\BeFilter\Filter\Filter;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * Dispatched for every query the filters run on their own: the options of a filter, and the
 * narrowing of a dependent one. The listing itself is covered by the event of the core.
 */
final readonly class ModifyFilterQueryEvent
{
    public function __construct(
        private QueryBuilder $queryBuilder,
        private string $table,
        private Filter $filter,
    ) {}

    public function getQueryBuilder(): QueryBuilder
    {
        return $this->queryBuilder;
    }

    /**
     * The table being queried, which is either the listed table or the foreign table of the filter.
     */
    public function getTable(): string
    {
        return $this->table;
    }

    public function getFilter(): Filter
    {
        return $this->filter;
    }
}
