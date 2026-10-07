<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Column;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Schema\Capability\TcaSchemaCapability;
use TYPO3\CMS\Core\Schema\TcaSchema;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;

/**
 * The columns a table can display, stored where the record list reads them:
 * "list/displayFields" in the module data of the user.
 */
final readonly class ColumnProvider
{
    private const PSEUDO_FIELDS = ['_REF_', '_PATH_'];
    private const MODULE_DATA_KEY = 'list/displayFields';

    public function __construct(private TcaSchemaFactory $tcaSchemaFactory) {}

    /**
     * @return array<int, array{name: string, label: string, selected: bool, fixed: bool}>
     */
    public function getColumns(string $table, int $pageId): array
    {
        $schema = $this->tcaSchemaFactory->get($table);
        $labelField = $schema->getCapability(TcaSchemaCapability::Label)->getPrimaryFieldName() ?? '';
        $selected = $this->getSelectedColumns($table);
        $tsConfig = BackendUtility::getPagesTSconfig($pageId)['TCEFORM.'][$table . '.'] ?? [];

        $columns = [];
        foreach ($this->getAvailableFields($table) as $field) {
            if ($tsConfig[$field . '.']['disabled'] ?? false) {
                continue;
            }
            $columns[$field] = [
                'name' => $field,
                'label' => $this->getLabel($schema, $field),
                'selected' => $field === $labelField || in_array($field, $selected, true),
                'fixed' => $field === $labelField,
            ];
        }

        return $this->sort($columns, $selected, $labelField);
    }

    /**
     * @param string[] $names
     */
    public function saveColumns(string $table, array $names): void
    {
        $available = $this->getAvailableFields($table);
        $columns = array_values(array_filter($names, static fn(string $name): bool => in_array($name, $available, true)));

        $backendUser = $this->getBackendUser();
        $displayFields = $backendUser->getModuleData(self::MODULE_DATA_KEY);
        $displayFields = is_array($displayFields) ? $displayFields : [];
        $displayFields[$table] = $columns;
        $backendUser->pushModuleData(self::MODULE_DATA_KEY, $displayFields);
    }

    /**
     * @return string[]
     */
    private function getSelectedColumns(string $table): array
    {
        $displayFields = $this->getBackendUser()->getModuleData(self::MODULE_DATA_KEY);

        return is_array($displayFields) ? array_values((array)($displayFields[$table] ?? [])) : [];
    }

    /**
     * @return string[]
     */
    private function getAvailableFields(string $table): array
    {
        return array_values(array_unique(array_merge(
            BackendUtility::getAllowedFieldsForTable($table),
            self::PSEUDO_FIELDS
        )));
    }

    /**
     * Fixed column first, then the selected ones in the order of the user, then the rest by label.
     *
     * @param array<string, array{name: string, label: string, selected: bool, fixed: bool}> $columns
     * @param string[] $selected
     * @return array<int, array{name: string, label: string, selected: bool, fixed: bool}>
     */
    private function sort(array $columns, array $selected, string $labelField): array
    {
        $sorted = [];
        if (isset($columns[$labelField])) {
            $sorted[$labelField] = $columns[$labelField];
        }
        foreach ($selected as $name) {
            if (isset($columns[$name])) {
                $sorted[$name] = $columns[$name];
            }
        }
        $remaining = array_diff_key($columns, $sorted);
        uasort($remaining, static fn(array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));

        return array_values($sorted + $remaining);
    }

    private function getLabel(TcaSchema $schema, string $field): string
    {
        $label = ($schema->hasField($field) ? $schema->getField($field)->getLabel() : '')
            ?: 'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.' . $field;

        return $this->getLanguageService()->sL($label) ?: $field;
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
