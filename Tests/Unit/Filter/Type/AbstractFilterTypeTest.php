<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Tests\Unit\Filter\Type;

use DigitalZombies\BeFilter\Filter\Filter;
use DigitalZombies\BeFilter\Filter\Type\AbstractFilterType;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * What a type gets for free, so that four methods are all it has to answer.
 */
final class AbstractFilterTypeTest extends TestCase
{
    private AbstractFilterType $subject;

    protected function setUp(): void
    {
        $this->subject = new class extends AbstractFilterType {
            public function getIdentifier(): string
            {
                return 'example';
            }

            public function matches(array $config, array $definition): bool
            {
                return false;
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

    public function testABuiltInTypeIsAskedAfterEveryTypeOfAnExtension(): void
    {
        self::assertSame(0, $this->subject->getPriority());
    }

    public function testATypeKeepsNothingOnTheFilterUnlessItSaysSo(): void
    {
        self::assertSame([], $this->subject->buildTypeOptions(['render' => 'slider'], ['type' => 'number']));
    }

    public function testTheControlIsToldWhatTheTypeKept(): void
    {
        $filter = new Filter(table: 'tx_test', name: 'field', type: 'example', label: 'F', typeOptions: ['min' => 1]);

        self::assertSame(['min' => 1], $this->subject->describe($filter));
    }

    public function testATypeOffersNeitherOptionsNorAControlByItself(): void
    {
        self::assertFalse($this->subject->providesOptions());
        self::assertNull($this->subject->getJavaScriptModule());
    }

    public function testTheCaptionsOfARangeAreTakenFromTheDefinition(): void
    {
        $type = new class extends AbstractFilterType {
            public function getIdentifier(): string
            {
                return 'example';
            }

            public function matches(array $config, array $definition): bool
            {
                return false;
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

            /**
             * @param array<string, mixed> $definition
             * @return array<string, string>
             */
            public function captions(array $definition): array
            {
                return $this->rangeLabels($definition);
            }
        };

        self::assertSame([], $type->captions([]));
        self::assertSame(['from' => 'Since'], $type->captions(['labels' => ['from' => 'Since', 'to' => '']]));
        self::assertSame(
            ['from' => 'Since', 'to' => 'Until'],
            $type->captions(['labels' => ['from' => 'Since', 'to' => 'Until']])
        );
    }
}
