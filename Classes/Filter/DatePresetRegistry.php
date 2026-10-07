<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Filter;

use TYPO3\CMS\Core\Localization\LanguageService;

/**
 * The quick ranges a date filter offers, see the README for how to replace them.
 */
final class DatePresetRegistry
{
    private const LANGUAGE = 'LLL:EXT:be_filter/Resources/Private/Language/locallang.xlf:';

    /**
     * @var array<string, array{label: string, from?: string, to?: string}>
     */
    private const DEFAULTS = [
        'week' => ['label' => self::LANGUAGE . 'preset.week', 'from' => '-1 week', 'to' => 'now'],
        'month' => ['label' => self::LANGUAGE . 'preset.month', 'from' => '-1 month', 'to' => 'now'],
        'lastMonth' => ['label' => self::LANGUAGE . 'preset.lastMonth', 'from' => 'first day of last month', 'to' => 'last day of last month'],
        'thisMonth' => ['label' => self::LANGUAGE . 'preset.thisMonth', 'from' => 'first day of this month', 'to' => 'last day of this month'],
        'lastYear' => ['label' => self::LANGUAGE . 'preset.lastYear', 'from' => 'first day of January last year', 'to' => 'last day of December last year'],
        'thisYear' => ['label' => self::LANGUAGE . 'preset.thisYear', 'from' => 'first day of January this year', 'to' => 'last day of December this year'],
    ];

    /**
     * @param array<string, mixed> $definition The "filters" definition of the field
     * @return array<string, DatePreset>
     */
    public function getPresets(array $definition): array
    {
        $defaults = $this->defaults();
        $configured = $definition['presets'] ?? null;

        if (!is_array($configured)) {
            return $this->build($defaults);
        }

        $presets = [];
        foreach ($configured as $key => $entry) {
            $key = (string)$key;
            if ($entry === false || $entry === null) {
                continue;
            }
            $base = $defaults[$key] ?? [];
            $own = is_array($entry) ? $entry : [];
            $presets[$key] = array_replace($base, $this->normalize($own));
        }

        return $this->build($presets);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function defaults(): array
    {
        $overrides = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['be_filter']['datePresets'] ?? null;
        if (!is_array($overrides)) {
            return self::DEFAULTS;
        }

        $defaults = self::DEFAULTS;
        foreach ($overrides as $key => $entry) {
            $key = (string)$key;
            if ($entry === false || $entry === null) {
                unset($defaults[$key]);
                continue;
            }
            $defaults[$key] = array_replace($defaults[$key] ?? [], $this->normalize(is_array($entry) ? $entry : []));
        }

        return $defaults;
    }

    /**
     * "value" is the short form of a range reaching until now.
     *
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function normalize(array $entry): array
    {
        if (isset($entry['value']) && !isset($entry['from'])) {
            $entry['from'] = (string)$entry['value'];
            $entry['to'] ??= 'now';
        }
        unset($entry['value']);

        return $entry;
    }

    /**
     * @param array<string, array<string, mixed>> $entries
     * @return array<string, DatePreset>
     */
    private function build(array $entries): array
    {
        $presets = [];
        foreach ($entries as $key => $entry) {
            $key = (string)$key;
            $from = isset($entry['from']) ? (string)$entry['from'] : null;
            $to = isset($entry['to']) ? (string)$entry['to'] : null;
            if ($from === null && $to === null) {
                continue;
            }

            $presets[$key] = new DatePreset(
                key: $key,
                label: $this->translate((string)($entry['label'] ?? $key)),
                from: $from,
                to: $to,
            );
        }

        return $presets;
    }

    private function translate(string $label): string
    {
        $language = $GLOBALS['LANG'] ?? null;

        return $language instanceof LanguageService ? ($language->sL($label) ?: $label) : $label;
    }
}
