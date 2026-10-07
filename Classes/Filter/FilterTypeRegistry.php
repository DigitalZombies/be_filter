<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Filter;

use DigitalZombies\BeFilter\Filter\Type\FilterTypeInterface;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Every service implementing {@see FilterTypeInterface}, collected by the container.
 */
final class FilterTypeRegistry implements SingletonInterface
{
    /**
     * @var array<string, FilterTypeInterface>
     */
    private array $types = [];

    /**
     * @param iterable<FilterTypeInterface> $types
     */
    public function __construct(iterable $types)
    {
        $sorted = [];
        foreach ($types as $type) {
            $sorted[] = $type;
        }
        usort($sorted, static fn(FilterTypeInterface $a, FilterTypeInterface $b): int => $b->getPriority() <=> $a->getPriority());

        foreach ($sorted as $type) {
            $this->types[$type->getIdentifier()] ??= $type;
        }
    }

    public function get(string $identifier): ?FilterTypeInterface
    {
        return $this->types[$identifier] ?? null;
    }

    /**
     * A definition naming its type is answered directly, which is what fields without a
     * TCA column need.
     *
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $config
     */
    public function resolve(array $definition, array $config): ?FilterTypeInterface
    {
        $named = (string)($definition['type'] ?? '');
        if ($named !== '') {
            return $this->get($named);
        }
        if ($config === []) {
            return null;
        }

        foreach ($this->types as $type) {
            if ($type->matches($config, $definition)) {
                return $type;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function getJavaScriptModules(): array
    {
        $modules = [];
        foreach ($this->types as $type) {
            $module = $type->getJavaScriptModule();
            if ($module !== null) {
                $modules[$module] = true;
            }
        }

        return array_keys($modules);
    }
}
