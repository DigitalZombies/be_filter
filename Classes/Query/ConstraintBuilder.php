<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Query;

use DigitalZombies\BeFilter\Filter\Filter;
use DigitalZombies\BeFilter\Filter\FilterTypeRegistry;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * Turns filter values into query constraints. What a constraint looks like belongs to
 * the filter type, this only collects them.
 */
final readonly class ConstraintBuilder
{
    public function __construct(private FilterTypeRegistry $typeRegistry) {}

    /**
     * @param array<string, Filter> $filters
     * @param array<string, mixed> $values Normalized filter values
     */
    public function apply(QueryBuilder $queryBuilder, array $filters, array $values): void
    {
        foreach ($values as $name => $value) {
            $filter = $filters[$name] ?? null;
            $constraint = $filter !== null ? $this->build($queryBuilder, $filter, $value) : null;
            if ($constraint !== null) {
                $queryBuilder->andWhere($constraint);
            }
        }
    }

    public function build(QueryBuilder $queryBuilder, Filter $filter, mixed $value): string|CompositeExpression|null
    {
        return $this->typeRegistry->get($filter->type)?->buildConstraint($queryBuilder, $filter, $value);
    }
}
