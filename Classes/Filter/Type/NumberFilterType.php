<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Filter\Type;

use DigitalZombies\BeFilter\Filter\Filter;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * A from / to range of numbers, as two fields or as a slider. See the README for the modes
 * a slider with one handle offers.
 */
final class NumberFilterType extends AbstractFilterType
{
    private const MODES = ['range', 'min', 'max', 'exact'];

    public function getIdentifier(): string
    {
        return 'number';
    }

    public function matches(array $config, array $definition): bool
    {
        $type = (string)($config['type'] ?? '');
        if ($type === 'number') {
            return true;
        }

        // Numbers were "input" with an eval before TYPO3 v12.
        $eval = (string)($config['eval'] ?? '');

        return $type === 'input'
            && (str_contains($eval, 'int') || str_contains($eval, 'double') || str_contains($eval, 'num'));
    }

    public function buildTypeOptions(array $definition, array $config): array
    {
        $decimal = (string)($config['format'] ?? '') === 'decimal' || str_contains((string)($config['eval'] ?? ''), 'double');
        $decimals = (int)($definition['decimals'] ?? ($decimal ? (int)($config['precision'] ?? 2) : 0));
        $step = $definition['step'] ?? $definition['steps'] ?? $config['slider']['step'] ?? null;

        if ($step === null) {
            $step = $decimals > 0 ? 1 / (10 ** $decimals) : 1;
        }

        $min = $definition['min'] ?? $config['range']['lower'] ?? null;
        $max = $definition['max'] ?? $config['range']['upper'] ?? null;
        $mode = (string)($definition['mode'] ?? 'range');

        return [
            'min' => $min !== null ? $this->toNumber($min, $decimals) : null,
            'max' => $max !== null ? $this->toNumber($max, $decimals) : null,
            'step' => $this->toNumber($step, $decimals),
            'decimals' => $decimals,
            'render' => (string)($definition['render'] ?? '') === 'slider' ? 'slider' : 'input',
            'mode' => in_array($mode, self::MODES, true) ? $mode : 'range',
            'labels' => $this->rangeLabels($definition),
        ];
    }

    /**
     * The control reads its bounds under "number", as it always has.
     */
    public function describe(Filter $filter): array
    {
        return ['number' => $filter->typeOptions];
    }

    public function normalizeValue(mixed $value, Filter $filter): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $range = [];
        foreach (['from', 'to'] as $boundary) {
            $entry = $value[$boundary] ?? null;
            if (!is_scalar($entry)) {
                continue;
            }
            $entry = str_replace(',', '.', trim((string)$entry));
            if ($entry !== '' && is_numeric($entry)) {
                $range[$boundary] = $entry;
            }
        }

        return $range === [] ? null : $range;
    }

    public function buildConstraint(
        QueryBuilder $queryBuilder,
        Filter $filter,
        mixed $value
    ): string|CompositeExpression|null {
        $value = (array)$value;
        $field = $filter->table . '.' . $filter->name;
        $decimals = (int)($filter->typeOptions['decimals'] ?? 0);
        $constraints = [];

        if (isset($value['from'])) {
            $constraints[] = $queryBuilder->expr()->gte($field, $this->parameter($queryBuilder, (string)$value['from'], $decimals));
        }
        if (isset($value['to'])) {
            $constraints[] = $queryBuilder->expr()->lte($field, $this->parameter($queryBuilder, (string)$value['to'], $decimals));
        }

        return $constraints === [] ? null : $queryBuilder->expr()->and(...$constraints);
    }

    private function parameter(QueryBuilder $queryBuilder, string $value, int $decimals): string
    {
        return $decimals > 0
            ? $queryBuilder->createNamedParameter((string)(float)$value)
            : $queryBuilder->createNamedParameter((int)$value, Connection::PARAM_INT);
    }

    private function toNumber(mixed $value, int $decimals): float|int
    {
        return $decimals > 0 ? (float)$value : (int)$value;
    }

    public function getJavaScriptModule(): ?string
    {
        return '@digitalzombies/be_filter/number-filter.js';
    }
}
