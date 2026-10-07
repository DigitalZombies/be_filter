<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\State;

use DigitalZombies\BeFilter\Filter\Filter;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

/**
 * What the user has set up per table, kept in their module data. Saved filters are
 * shared and live in {@see \DigitalZombies\BeFilter\Preset\PresetRepository}.
 */
final class FilterStateRepository
{
    private const MODULE_DATA_KEY = 'be_filter';

    /**
     * @return array<string, mixed>
     */
    public function getValues(string $table): array
    {
        return (array)($this->getTableState($table)['values'] ?? []);
    }

    /**
     * @param array<string, mixed> $values
     */
    public function setValues(string $table, array $values): void
    {
        $this->updateTableState($table, ['values' => $values]);
    }

    /**
     * The displayed filters, in the order the user arranged them.
     *
     * @param array<string, Filter> $filters
     * @return string[]
     */
    public function getVisibleFilters(string $table, array $filters): array
    {
        $visible = $this->getTableState($table)['visible'] ?? null;
        if (!is_array($visible)) {
            return array_keys(array_filter($filters, static fn(Filter $filter): bool => $filter->default));
        }

        return array_values(array_filter(
            array_map(strval(...), $visible),
            static fn(string $name): bool => isset($filters[$name])
        ));
    }

    /**
     * @param string[] $names
     */
    public function setVisibleFilters(string $table, array $names): void
    {
        $this->updateTableState($table, ['visible' => array_values($names)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function getTableState(string $table): array
    {
        $state = $this->getBackendUser()->getModuleData(self::MODULE_DATA_KEY);

        return is_array($state) ? (array)($state[$table] ?? []) : [];
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function updateTableState(string $table, array $changes): void
    {
        $backendUser = $this->getBackendUser();
        $state = $backendUser->getModuleData(self::MODULE_DATA_KEY);
        $state = is_array($state) ? $state : [];
        $state[$table] = array_merge((array)($state[$table] ?? []), $changes);
        $backendUser->pushModuleData(self::MODULE_DATA_KEY, $state);
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
