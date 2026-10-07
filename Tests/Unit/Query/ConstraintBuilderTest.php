<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Tests\Unit\Query;

use DigitalZombies\BeFilter\Filter\Filter;
use DigitalZombies\BeFilter\Filter\FilterTypeRegistry;
use DigitalZombies\BeFilter\Filter\Type\AbstractFilterType;
use DigitalZombies\BeFilter\Query\ConstraintBuilder;
use DigitalZombies\BeFilter\Tests\Unit\QueryBuilderTrait;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * Collecting the constraints of the filters. What a constraint looks like belongs to the
 * type, so a type that writes down what it was asked for is enough here.
 */
final class ConstraintBuilderTest extends TestCase
{
    use QueryBuilderTrait;

    private ConstraintBuilder $subject;

    protected function setUp(): void
    {
        $type = new class extends AbstractFilterType {
            public function getIdentifier(): string
            {
                return 'stub';
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
                return $value === 'nothing' ? null : $filter->table . '.' . $filter->name . ' = ' . $value;
            }
        };

        $this->subject = new ConstraintBuilder(new FilterTypeRegistry([$type]));
    }

    private function filter(string $name): Filter
    {
        return new Filter(table: 'tx_test', name: $name, type: 'stub', label: ucfirst($name));
    }

    public function testTheConstraintIsAskedOfTheTypeOfTheFilter(): void
    {
        self::assertSame(
            'tx_test.fuel = diesel',
            $this->subject->build($this->queryBuilder(), $this->filter('fuel'), 'diesel'),
        );
    }

    public function testAFilterWhoseTypeNobodyRegisteredConstrainsNothing(): void
    {
        $unknown = new Filter(table: 'tx_test', name: 'fuel', type: 'gone', label: 'Fuel');

        self::assertNull($this->subject->build($this->queryBuilder(), $unknown, 'diesel'));
    }

    public function testEveryValueThatConstrainsSomethingIsAddedToTheQuery(): void
    {
        $applied = [];
        $queryBuilder = $this->queryBuilder();
        $queryBuilder->method('andWhere')->willReturnCallback(
            static function (...$predicates) use (&$applied, $queryBuilder): QueryBuilder {
                foreach ($predicates as $predicate) {
                    $applied[] = (string)$predicate;
                }

                return $queryBuilder;
            }
        );

        $filters = ['fuel' => $this->filter('fuel'), 'body' => $this->filter('body')];
        $this->subject->apply($queryBuilder, $filters, ['fuel' => 'diesel', 'body' => 'sedan']);

        self::assertSame(['tx_test.fuel = diesel', 'tx_test.body = sedan'], $applied);
    }

    public function testValuesWithoutAFilterAndConstraintsThatSayNothingAreSkipped(): void
    {
        $applied = [];
        $queryBuilder = $this->queryBuilder();
        $queryBuilder->method('andWhere')->willReturnCallback(
            static function (...$predicates) use (&$applied, $queryBuilder): QueryBuilder {
                foreach ($predicates as $predicate) {
                    $applied[] = (string)$predicate;
                }

                return $queryBuilder;
            }
        );

        $this->subject->apply(
            $queryBuilder,
            ['fuel' => $this->filter('fuel')],
            ['fuel' => 'nothing', 'gone' => 'whatever'],
        );

        self::assertSame([], $applied, 'Neither an empty constraint nor a filter that is not displayed');
    }
}
