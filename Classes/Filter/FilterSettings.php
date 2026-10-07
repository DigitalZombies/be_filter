<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Filter;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

/**
 * Settings of the bar: the installation sets the defaults, a table overrides them in its
 * "ctrl" section.
 */
final class FilterSettings
{
    private const DEFAULTS = [
        // Applying a value reloads the listing right away, without the button.
        'autoRefresh' => false,
        // How many options a dropdown offers before the search has to narrow them.
        'optionLimit' => 50,
    ];

    /**
     * Whether the current user may filter this table. User TSconfig, so a backend group
     * carries it for everybody in it:
     *
     *     mod.be_filter.disabled = 1
     *     mod.be_filter.tables.tx_my_table.disabled = 1
     */
    public function isEnabled(string $table): bool
    {
        $config = $this->getUserTsConfig();
        if ((string)($config['disabled'] ?? '') === '1') {
            return false;
        }

        $perTable = $config['tables.'][$table . '.'] ?? [];

        return (string)((is_array($perTable) ? $perTable : [])['disabled'] ?? '') !== '1';
    }

    /**
     * @return array<string, mixed>
     */
    private function getUserTsConfig(): array
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$backendUser instanceof BackendUserAuthentication) {
            return [];
        }

        $config = $backendUser->getTSConfig()['mod.']['be_filter.'] ?? null;

        return is_array($config) ? $config : [];
    }

    public function isAutoRefresh(string $table): bool
    {
        return (bool)$this->get($table, 'autoRefresh');
    }

    /**
     * Captions of a from / to range. A table adds to what the installation sets, both
     * may be a LLL reference.
     *
     * @return array<string, string>
     */
    public function getRangeLabels(string $table): array
    {
        $global = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['be_filter']['labels'] ?? [];
        $perTable = $GLOBALS['TCA'][$table]['ctrl']['be_filter']['labels'] ?? [];

        $spelled = static fn(mixed $labels): array => array_filter(
            is_array($labels) ? $labels : [],
            static fn(mixed $label): bool => is_string($label) && $label !== ''
        );

        return $spelled($perTable) + $spelled($global);
    }

    public function getOptionLimit(string $table): int
    {
        $limit = (int)$this->get($table, 'optionLimit');

        return $limit > 0 ? $limit : self::DEFAULTS['optionLimit'];
    }

    private function get(string $table, string $setting): mixed
    {
        $perTable = $GLOBALS['TCA'][$table]['ctrl']['be_filter'] ?? null;
        if (is_array($perTable) && array_key_exists($setting, $perTable)) {
            return $perTable[$setting];
        }

        $global = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['be_filter'] ?? null;
        if (is_array($global) && array_key_exists($setting, $global)) {
            return $global[$setting];
        }

        return self::DEFAULTS[$setting] ?? null;
    }
}
