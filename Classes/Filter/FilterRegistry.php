<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Filter;

use DigitalZombies\BeFilter\Filter\Type\FilterTypeInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Builds the filters of a table from the "filters" section of its TCA, see the README.
 * Fields the current user may not see are dropped, as are columns no type takes.
 */
final class FilterRegistry implements SingletonInterface
{
    /**
     * @var array<string, array<string, Filter>>
     */
    private array $filters = [];

    public function __construct(
        private readonly TcaSchemaFactory $tcaSchemaFactory,
        private readonly FilterTypeRegistry $typeRegistry,
        private readonly FilterSettings $settings,
    ) {}

    /**
     * @return array<string, Filter>
     */
    public function getFilters(string $table): array
    {
        return $this->filters[$table] ??= $this->buildFilters($table);
    }

    public function getFilter(string $table, string $name): ?Filter
    {
        return $this->getFilters($table)[$name] ?? null;
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function normalizeValues(string $table, array $values): array
    {
        $normalized = [];
        foreach ($this->getFilters($table) as $name => $filter) {
            $value = $this->typeRegistry->get($filter->type)?->normalizeValue($values[$name] ?? null, $filter);
            if ($value !== null) {
                $normalized[$name] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @return array<string, Filter>
     */
    private function buildFilters(string $table): array
    {
        $definitions = $GLOBALS['TCA'][$table]['filters'] ?? null;
        if (!is_array($definitions) || !$this->tcaSchemaFactory->has($table)) {
            return [];
        }

        $allowedFields = BackendUtility::getAllowedFieldsForTable($table);
        $candidates = [];
        foreach ($definitions as $name => $definition) {
            $name = (string)$name;
            $definition = is_array($definition) ? $definition : [];
            $config = (array)($GLOBALS['TCA'][$table]['columns'][$name]['config'] ?? []);
            $type = $this->typeRegistry->resolve($definition, $config);
            if ($type === null || !in_array($name, $allowedFields, true)) {
                continue;
            }
            $candidates[$name] = [$definition, $type, $config];
        }

        $filters = [];
        foreach ($candidates as $name => [$definition, $type, $config]) {
            /** @var FilterTypeInterface $type */
            $filters[$name] = new Filter(
                table: $table,
                name: $name,
                type: $type->getIdentifier(),
                label: $this->resolveLabel($table, $name, $definition),
                default: (bool)($definition['default'] ?? false),
                multiple: $type->providesOptions() && (bool)($definition['multiple'] ?? false),
                dependsOn: array_values(array_filter(
                    $this->resolveDependencies($definition),
                    static fn(string $parent): bool => $parent !== $name && isset($candidates[$parent])
                )),
                config: $config,
                typeOptions: $type->buildTypeOptions($this->withRangeLabels($table, $definition), $config),
                optionLimit: (int)($definition['optionLimit'] ?? $this->settings->getOptionLimit($table)),
            );
        }

        return $filters;
    }

    /**
     * @param array<string, mixed> $definition
     * @return string[]
     */
    private function resolveDependencies(array $definition): array
    {
        $dependsOn = $definition['dependsOn'] ?? [];
        $parents = is_array($dependsOn) ? $dependsOn : [$dependsOn];

        return array_values(array_filter(
            array_map(static fn($parent): string => is_scalar($parent) ? trim((string)$parent) : '', $parents),
            static fn(string $parent): bool => $parent !== ''
        ));
    }

    /**
     * The captions of a from / to range: the filter overrides the table, which overrides
     * the installation. Resolved here, so a type only ever reads "labels".
     *
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private function withRangeLabels(string $table, array $definition): array
    {
        $own = array_filter(
            is_array($definition['labels'] ?? null) ? $definition['labels'] : [],
            static fn(mixed $label): bool => is_scalar($label) && (string)$label !== ''
        );
        $labels = $own + $this->settings->getRangeLabels($table);

        $definition['labels'] = array_map(
            fn(mixed $label): string => $this->getLanguageService()->sL((string)$label) ?: (string)$label,
            $labels
        );

        return $definition;
    }

    private function resolveLabel(string $table, string $name, array $definition): string
    {
        $label = (string)($definition['label'] ?? $GLOBALS['TCA'][$table]['columns'][$name]['label'] ?? '');
        if ($label === '') {
            $label = 'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.' . $name;
        }

        return $this->getLanguageService()->sL($label) ?: $name;
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
