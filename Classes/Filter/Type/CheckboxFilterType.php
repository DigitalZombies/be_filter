<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Filter\Type;

use DigitalZombies\BeFilter\Filter\Filter;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * A single checkbox, offered as a yes / no dropdown or as a switch.
 */
final class CheckboxFilterType extends AbstractFilterType
{
    public function getIdentifier(): string
    {
        return 'checkbox';
    }

    public function matches(array $config, array $definition): bool
    {
        // Several items are a bitmask, which is not a single yes or no.
        return (string)($config['type'] ?? '') === 'check' && count((array)($config['items'] ?? [[]])) <= 1;
    }

    public function buildTypeOptions(array $definition, array $config): array
    {
        return ['checkbox' => (string)($definition['render'] ?? '') === 'checkbox'];
    }

    public function normalizeValue(mixed $value, Filter $filter): ?string
    {
        $value = is_scalar($value) ? (string)$value : '';

        if ($filter->asCheckbox()) {
            return $value === '1' ? '1' : null;
        }

        return in_array($value, ['0', '1'], true) ? $value : null;
    }

    public function buildConstraint(QueryBuilder $queryBuilder, Filter $filter, mixed $value): ?string
    {
        $value = (string)$value;
        if (!in_array($value, ['0', '1'], true)) {
            return null;
        }

        $checked = $value === '1';
        if ($filter->isInverted()) {
            $checked = !$checked;
        }

        return $queryBuilder->expr()->eq(
            $filter->table . '.' . $filter->name,
            $queryBuilder->createNamedParameter($checked ? 1 : 0, Connection::PARAM_INT)
        );
    }

    public function getJavaScriptModule(): ?string
    {
        return '@digitalzombies/be_filter/checkbox-filter.js';
    }
}
