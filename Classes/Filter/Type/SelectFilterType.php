<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Filter\Type;

use DigitalZombies\BeFilter\Filter\Filter;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * Values of a column: its static items, the records of its foreign table, or both.
 */
final class SelectFilterType extends AbstractFilterType
{
    public function __construct(private ConnectionPool $connectionPool) {}

    public function getIdentifier(): string
    {
        return 'select';
    }

    public function matches(array $config, array $definition): bool
    {
        if (!in_array((string)($config['type'] ?? ''), ['select', 'category', 'radio', 'group'], true)) {
            return false;
        }

        return $this->hasSelectableValues($config);
    }

    public function providesOptions(): bool
    {
        return true;
    }

    public function normalizeValue(mixed $value, Filter $filter): ?array
    {
        $values = array_values(array_unique(array_filter(
            array_map(
                static fn($item): string => is_scalar($item) ? trim((string)$item) : '',
                is_array($value) ? $value : [$value]
            ),
            // Commas would be ambiguous in comma separated relation fields.
            static fn(string $item): bool => $item !== '' && !str_contains($item, ',')
        )));

        if ($values === []) {
            return null;
        }

        return $filter->multiple ? $values : [$values[0]];
    }

    public function buildConstraint(
        QueryBuilder $queryBuilder,
        Filter $filter,
        mixed $value
    ): string|CompositeExpression|null {
        $values = (array)$value;
        if ($values === []) {
            return null;
        }

        $field = $filter->table . '.' . $filter->name;
        $mmTable = $filter->getMmTable();

        if ($mmTable !== null) {
            return $this->mmConstraint($queryBuilder, $filter, $mmTable, $values);
        }

        if ($filter->isMultiValue()) {
            return $queryBuilder->expr()->or(
                ...array_map(
                    static fn(string $entry): string => $queryBuilder->expr()->inSet($field, $queryBuilder->quote($entry)),
                    $values
                )
            );
        }

        return $queryBuilder->expr()->in(
            $field,
            $queryBuilder->createNamedParameter($values, Connection::PARAM_STR_ARRAY)
        );
    }

    /**
     * @param string[] $values
     */
    private function mmConstraint(QueryBuilder $queryBuilder, Filter $filter, string $mmTable, array $values): string
    {
        [$recordColumn, $valueColumn] = $filter->hasOppositeMmSide()
            ? ['uid_foreign', 'uid_local']
            : ['uid_local', 'uid_foreign'];

        $subQuery = $this->connectionPool->getQueryBuilderForTable($mmTable);
        $subQuery->getRestrictions()->removeAll();
        $subQuery
            ->select($recordColumn)
            ->from($mmTable)
            ->where(
                $subQuery->expr()->in(
                    $valueColumn,
                    $queryBuilder->createNamedParameter(array_map(intval(...), $values), Connection::PARAM_INT_ARRAY)
                )
            );
        foreach ($filter->getMmMatchFields() as $matchField => $matchValue) {
            $subQuery->andWhere(
                $subQuery->expr()->eq($matchField, $queryBuilder->createNamedParameter($matchValue))
            );
        }

        return $queryBuilder->expr()->in($filter->table . '.uid', $subQuery->getSQL());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function hasSelectableValues(array $config): bool
    {
        $probe = new Filter(table: '', name: '', type: 'select', label: '', config: $config);

        return $probe->getForeignTable() !== null || $probe->getItems() !== [];
    }

    public function getJavaScriptModule(): ?string
    {
        return '@digitalzombies/be_filter/select-filter.js';
    }
}
