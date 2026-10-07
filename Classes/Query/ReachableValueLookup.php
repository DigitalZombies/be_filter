<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Query;

use DigitalZombies\BeFilter\Filter\Filter;

/**
 * Answers which of a filter's values the current user may actually see.
 */
interface ReachableValueLookup
{
    /**
     * @param string[] $values
     * @return string[] those of them that still name something the user may see
     */
    public function getReachableValues(Filter $filter, array $values, int $pageId = 0): array;
}
