<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Filter\Type;

use DigitalZombies\BeFilter\Filter\Filter;

/**
 * Answers everything a type may leave alone: identifier, matches, normalizeValue and
 * buildConstraint are what is left. See the README for an example.
 */
abstract class AbstractFilterType implements FilterTypeInterface
{
    public function getPriority(): int
    {
        return 0;
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function buildTypeOptions(array $definition, array $config): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function describe(Filter $filter): array
    {
        return $filter->typeOptions;
    }

    /**
     * Captions of a from / to range, already resolved by the registry.
     *
     * @param array<string, mixed> $definition
     * @return array<string, string>
     */
    protected function rangeLabels(array $definition): array
    {
        $labels = is_array($definition['labels'] ?? null) ? $definition['labels'] : [];

        return array_filter(
            ['from' => (string)($labels['from'] ?? ''), 'to' => (string)($labels['to'] ?? '')],
            static fn(string $label): bool => $label !== ''
        );
    }

    public function providesOptions(): bool
    {
        return false;
    }

    public function getJavaScriptModule(): ?string
    {
        return null;
    }
}
