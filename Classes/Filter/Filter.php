<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Filter;

use TYPO3\CMS\Core\Schema\RelationshipType;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * One filter of one table. What it accepts and how it constrains a query belongs to its
 * type, the helpers here describe how the values of the field are stored.
 */
final readonly class Filter
{
    private const MULTIPLE_RENDER_TYPES = ['selectSingleBox', 'selectCheckBox', 'selectMultipleSideBySide', 'selectTree'];

    /**
     * @param string $type Identifier of the filter type
     * @param string[] $dependsOn Filters narrowing the options of this one
     * @param array<string, mixed> $config TCA column configuration, empty for fields without a column
     * @param array<string, mixed> $typeOptions What the type keeps for itself
     * @param int $optionLimit How many options the dropdown offers at once, 0 for the default
     */
    public function __construct(
        public string $table,
        public string $name,
        public string $type,
        public string $label,
        public bool $default = false,
        public bool $multiple = false,
        public array $dependsOn = [],
        public array $config = [],
        public array $typeOptions = [],
        public int $optionLimit = 0,
    ) {}

    public function getForeignTable(): ?string
    {
        $foreignTable = (string)($this->config['foreign_table'] ?? '');
        if ($foreignTable === '' && ($this->config['type'] ?? '') === 'group') {
            $allowed = GeneralUtility::trimExplode(',', (string)($this->config['allowed'] ?? ''), true);
            $foreignTable = count($allowed) === 1 ? $allowed[0] : '';
        }

        return $foreignTable !== '' && $foreignTable !== '*' ? $foreignTable : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getItems(): array
    {
        return array_filter(
            (array)($this->config['items'] ?? []),
            static fn($item): bool => is_array($item) && (string)($item['value'] ?? '') !== '' && ($item['value'] ?? '') !== '--div--'
        );
    }

    public function getMmTable(): ?string
    {
        $mmTable = (string)($this->config['MM'] ?? '');

        return $mmTable !== '' ? $mmTable : null;
    }

    /**
     * Categories and other opposite side relations store the record in "uid_foreign".
     */
    public function hasOppositeMmSide(): bool
    {
        return (string)($this->config['MM_opposite_field'] ?? '') !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function getMmMatchFields(): array
    {
        return (array)($this->config['MM_match_fields'] ?? []);
    }

    /**
     * TRUE when the field holds a comma separated list.
     */
    public function isMultiValue(): bool
    {
        return match (RelationshipType::fromTcaConfiguration($this->config)) {
            RelationshipType::ManyToMany => false,
            RelationshipType::List, RelationshipType::OneToMany => true,
            RelationshipType::OneToOne, RelationshipType::ManyToOne => false,
            RelationshipType::Undefined => $this->allowsSeveralItems(),
        };
    }

    /**
     * Static items: the render types selecting several of them need no "maxitems" to do
     * so, the core does not default it for them.
     */
    private function allowsSeveralItems(): bool
    {
        if (isset($this->config['maxitems'])) {
            return (int)$this->config['maxitems'] > 1;
        }

        return ($this->config['type'] ?? '') === 'select'
            && in_array((string)($this->config['renderType'] ?? ''), self::MULTIPLE_RENDER_TYPES, true);
    }

    /**
     * Checkboxes such as "hidden" display the unchecked state as the enabled one.
     */
    public function isInverted(): bool
    {
        return (bool)($this->config['items'][0]['invertStateDisplay'] ?? false);
    }

    public function asCheckbox(): bool
    {
        return (bool)($this->typeOptions['checkbox'] ?? false);
    }

    public function getDateStorageFormat(): ?string
    {
        return match ((string)($this->config['dbType'] ?? '')) {
            'date' => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            default => null,
        };
    }
}
