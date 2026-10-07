<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Filter\Type;

use DigitalZombies\BeFilter\Filter\Filter;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * A kind of filter. Every implementation is collected by the container, see the README
 * for a worked example.
 */
interface FilterTypeInterface
{
    /**
     * Also the name a control is registered under on the client.
     */
    public function getIdentifier(): string;

    /**
     * @param array<string, mixed> $config TCA column, empty for fields without one
     * @param array<string, mixed> $definition Entry of the "filters" section
     */
    public function matches(array $config, array $definition): bool;

    /**
     * Types are asked in this order, built in types answer 0.
     */
    public function getPriority(): int;

    /**
     * What the type keeps on the filter, see {@see Filter::$typeOptions}.
     *
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function buildTypeOptions(array $definition, array $config): array;

    /**
     * NULL when the value constrains nothing.
     */
    public function normalizeValue(mixed $value, Filter $filter): mixed;

    public function buildConstraint(
        QueryBuilder $queryBuilder,
        Filter $filter,
        mixed $value
    ): string|CompositeExpression|null;

    /**
     * What the control needs on top of name, label and type.
     *
     * @return array<string, mixed>
     */
    public function describe(Filter $filter): array;

    /**
     * Whether the filter offers its values through the options endpoint.
     */
    public function providesOptions(): bool;

    public function getJavaScriptModule(): ?string;
}
