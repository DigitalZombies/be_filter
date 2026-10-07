<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Event;

/**
 * Asks the application how saved filters are scoped: they are written without the
 * DataHandler, so a scope of its own has to be named here.
 */
final class ModifyPresetQueryEvent
{
    private ?string $scopeField = null;

    private int $scopeValue = 0;

    public function __construct(private readonly string $table) {}

    public function getTable(): string
    {
        return $this->table;
    }

    public function setScope(string $field, int $value): void
    {
        $this->scopeField = $field;
        $this->scopeValue = $value;
    }

    public function getScopeField(): ?string
    {
        return $this->scopeField;
    }

    public function getScopeValue(): int
    {
        return $this->scopeValue;
    }
}
