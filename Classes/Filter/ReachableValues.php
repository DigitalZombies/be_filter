<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Filter;

use DigitalZombies\BeFilter\Query\ReachableValueLookup;

/**
 * Drops the values a user cannot reach.
 *
 * A saved filter carries the records it was saved with. Opened by somebody else, or after
 * one of those records is gone, it would constrain the listing to something invisible: an
 * empty list with no explanation. The values that no longer resolve are left out, and the
 * filters they belonged to are named so the user can be told.
 */
final readonly class ReachableValues
{
    public function __construct(
        private FilterTypeRegistry $typeRegistry,
        private ReachableValueLookup $optionProvider,
    ) {}

    /**
     * @param array<string, Filter> $filters
     * @param array<string, mixed> $values Normalized filter values
     * @return array{values: array<string, mixed>, adjusted: string[]} the values that stand, and the labels of the filters that lost one
     */
    public function apply(array $filters, array $values, int $pageId = 0): array
    {
        $adjusted = [];

        foreach ($values as $name => $value) {
            $filter = $filters[(string)$name] ?? null;
            if ($filter === null || $this->typeRegistry->get($filter->type)?->providesOptions() !== true) {
                // Only a filter offering records can name one that is out of reach.
                continue;
            }

            $wanted = array_values(array_map(strval(...), (array)$value));
            $reachable = $this->optionProvider->getReachableValues($filter, $wanted, $pageId);
            if (count($reachable) === count($wanted)) {
                continue;
            }

            $adjusted[] = $filter->label;
            if ($reachable === []) {
                unset($values[$name]);
            } else {
                $values[$name] = $reachable;
            }
        }

        return ['values' => $values, 'adjusted' => $adjusted];
    }

    /**
     * The names among them that this table has no filter for. A saved filter may name one
     * the user is not allowed to see, or one that has been taken out of the TCA since.
     *
     * @param array<string, Filter> $filters
     * @param string[] $names
     * @return string[]
     */
    public function missing(array $filters, array $names): array
    {
        return array_values(array_unique(array_filter(
            $names,
            static fn(string $name): bool => $name !== '' && !isset($filters[$name])
        )));
    }
}
