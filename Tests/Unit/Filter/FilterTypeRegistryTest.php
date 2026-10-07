<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Tests\Unit\Filter;

use DigitalZombies\BeFilter\Filter\Filter;
use DigitalZombies\BeFilter\Filter\FilterTypeRegistry;
use DigitalZombies\BeFilter\Filter\Type\AbstractFilterType;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

final class FilterTypeRegistryTest extends TestCase
{
    private function type(string $identifier, bool $matches, int $priority = 0, ?string $module = null): AbstractFilterType
    {
        return new class ($identifier, $matches, $priority, $module) extends AbstractFilterType {
            public function __construct(
                private readonly string $identifier,
                private readonly bool $matches,
                private readonly int $priority,
                private readonly ?string $module,
            ) {}

            public function getIdentifier(): string
            {
                return $this->identifier;
            }

            public function matches(array $config, array $definition): bool
            {
                return $this->matches;
            }

            public function getPriority(): int
            {
                return $this->priority;
            }

            public function getJavaScriptModule(): ?string
            {
                return $this->module;
            }

            public function normalizeValue(mixed $value, Filter $filter): mixed
            {
                return $value;
            }

            public function buildConstraint(
                QueryBuilder $queryBuilder,
                Filter $filter,
                mixed $value
            ): string|CompositeExpression|null {
                return null;
            }
        };
    }

    public function testATypeIsFoundByItsName(): void
    {
        $registry = new FilterTypeRegistry([$this->type('rating', false)]);

        self::assertSame('rating', $registry->get('rating')?->getIdentifier());
        self::assertNull($registry->get('unknown'));
    }

    public function testADefinitionNamingItsTypeIsAnsweredDirectly(): void
    {
        $registry = new FilterTypeRegistry([$this->type('date', false)]);

        self::assertSame('date', $registry->resolve(['type' => 'date'], [])?->getIdentifier(), 'A field without a column names its type');
    }

    public function testTheFirstTypeThatTakesTheColumnWins(): void
    {
        $registry = new FilterTypeRegistry([
            $this->type('builtin', true),
            $this->type('custom', true, priority: 10),
        ]);

        self::assertSame('custom', $registry->resolve([], ['type' => ''] + [])?->getIdentifier() ?? null, 'A higher priority takes the column over');
    }

    public function testAColumnNoTypeTakesIsNotFiltered(): void
    {
        $registry = new FilterTypeRegistry([$this->type('builtin', false)]);

        self::assertNull($registry->resolve([], ['type' => 'text']), 'Text is left to the search box');
    }

    public function testNothingIsResolvedWithoutAColumnAndWithoutAName(): void
    {
        $registry = new FilterTypeRegistry([$this->type('builtin', true)]);

        self::assertNull($registry->resolve([], []));
    }

    public function testAnExtensionTakesATypeOverByAnsweringAHigherPriority(): void
    {
        $registry = new FilterTypeRegistry([
            $this->type('select', false, module: '@digitalzombies/be_filter/select-filter.js'),
            $this->type('select', false, priority: 10, module: '@my-vendor/my-ext/select-filter.js'),
        ]);

        self::assertSame(
            '@my-vendor/my-ext/select-filter.js',
            $registry->get('select')?->getJavaScriptModule(),
            'The type answering the higher priority owns the name'
        );
        self::assertSame(
            ['@my-vendor/my-ext/select-filter.js'],
            $registry->getJavaScriptModules(),
            'The control it replaces is not loaded any more'
        );
    }

    public function testTheModulesOfTheTypesAreCollectedOnce(): void
    {
        $registry = new FilterTypeRegistry([
            $this->type('a', false, module: '@vendor/ext/controls.js'),
            $this->type('b', false, module: '@vendor/ext/controls.js'),
            $this->type('c', false),
        ]);

        self::assertSame(['@vendor/ext/controls.js'], $registry->getJavaScriptModules());
    }
}
