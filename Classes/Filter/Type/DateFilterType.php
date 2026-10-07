<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Filter\Type;

use DigitalZombies\BeFilter\Filter\DatePreset;
use DigitalZombies\BeFilter\Filter\DatePresetRegistry;
use DigitalZombies\BeFilter\Filter\Filter;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * A from / to range with quick ranges, see {@see DatePresetRegistry}.
 */
final class DateFilterType extends AbstractFilterType
{
    public function __construct(private DatePresetRegistry $datePresetRegistry) {}

    public function getIdentifier(): string
    {
        return 'date';
    }

    public function matches(array $config, array $definition): bool
    {
        return (string)($config['type'] ?? '') === 'datetime'
            && in_array((string)($config['format'] ?? 'datetime'), ['date', 'datetime'], true);
    }

    public function buildTypeOptions(array $definition, array $config): array
    {
        return [
            'presets' => $this->datePresetRegistry->getPresets($definition),
            'labels' => $this->rangeLabels($definition),
        ];
    }

    public function describe(Filter $filter): array
    {
        return [
            'presets' => array_values(array_map(
                static fn(DatePreset $preset): array => ['key' => $preset->key, 'label' => $preset->label],
                $this->presets($filter)
            )),
            'labels' => (array)($filter->typeOptions['labels'] ?? []),
        ];
    }

    public function normalizeValue(mixed $value, Filter $filter): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $preset = (string)($value['preset'] ?? '');
        if ($preset !== '' && isset($this->presets($filter)[$preset])) {
            return ['preset' => $preset];
        }

        $range = [];
        foreach (['from', 'to'] as $boundary) {
            $date = (string)($value[$boundary] ?? '');
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if ($parsed instanceof \DateTimeImmutable && $parsed->format('Y-m-d') === $date) {
                $range[$boundary] = $date;
            }
        }

        return $range === [] ? null : $range;
    }

    public function buildConstraint(
        QueryBuilder $queryBuilder,
        Filter $filter,
        mixed $value
    ): string|CompositeExpression|null {
        [$from, $to] = $this->resolveRange($filter, (array)$value);
        if ($from === null && $to === null) {
            return null;
        }

        $field = $filter->table . '.' . $filter->name;
        $format = $filter->getDateStorageFormat();
        $constraints = [];

        if ($format === null) {
            // Timestamps: records without a date must not match an open ended range.
            // Dates before 1970 are negative, so "greater than" would lose them.
            $constraints[] = $queryBuilder->expr()->neq($field, $queryBuilder->createNamedParameter(0, Connection::PARAM_INT));
        }
        if ($from !== null) {
            $constraints[] = $queryBuilder->expr()->gte($field, $this->dateParameter($queryBuilder, $from, $format));
        }
        if ($to !== null) {
            $constraints[] = $queryBuilder->expr()->lte($field, $this->dateParameter($queryBuilder, $to, $format));
        }

        return $queryBuilder->expr()->and(...$constraints);
    }

    /**
     * @return array<string, DatePreset>
     */
    private function presets(Filter $filter): array
    {
        $presets = $filter->typeOptions['presets'] ?? [];

        return is_array($presets) ? $presets : [];
    }

    /**
     * @param array{preset?: string, from?: string, to?: string} $value
     * @return array{0: ?\DateTimeImmutable, 1: ?\DateTimeImmutable}
     */
    private function resolveRange(Filter $filter, array $value): array
    {
        $preset = $this->presets($filter)[(string)($value['preset'] ?? '')] ?? null;
        if ($preset instanceof DatePreset) {
            return $preset->range(new \DateTimeImmutable('now'));
        }

        return [
            $this->parseDate((string)($value['from'] ?? ''), false),
            $this->parseDate((string)($value['to'] ?? ''), true),
        ];
    }

    private function parseDate(string $date, bool $endOfDay): ?\DateTimeImmutable
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed instanceof \DateTimeImmutable || $parsed->format('Y-m-d') !== $date) {
            return null;
        }

        return $endOfDay ? $parsed->setTime(23, 59, 59) : $parsed;
    }

    private function dateParameter(QueryBuilder $queryBuilder, \DateTimeImmutable $date, ?string $format): string
    {
        return $format === null
            ? $queryBuilder->createNamedParameter($date->getTimestamp(), Connection::PARAM_INT)
            : $queryBuilder->createNamedParameter($date->format($format));
    }

    public function getJavaScriptModule(): ?string
    {
        return '@digitalzombies/be_filter/date-filter.js';
    }
}
